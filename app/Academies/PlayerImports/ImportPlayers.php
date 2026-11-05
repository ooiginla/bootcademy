<?php

namespace App\Academies\PlayerImports;

use App\Academies\Actions\SaveEnrollment;
use App\Academies\Actions\SavePlayer;
use App\Models\Academy;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportPlayers
{
    public function __construct(
        private readonly AnalyzePlayerImport $analyze,
        private readonly SavePlayer $savePlayer,
        private readonly SaveEnrollment $saveEnrollment,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Academy $academy, User $actor, string $path, Request $request): int
    {
        $analysis = $this->analyze->handle($academy, $path);
        if (! $analysis->isValid()) {
            throw ValidationException::withMessages([
                'csv_file' => 'The CSV changed or contains errors. Review the preview before importing.',
            ]);
        }

        try {
            return DB::transaction(function () use ($academy, $actor, $request, $analysis): int {
                foreach ($analysis->rows as $row) {
                    $data = $row['data'];
                    $guardianId = is_int($data['guardian_id']) ? $data['guardian_id'] : null;
                    $relationship = (string) $data['guardian_relationship'];
                    $newGuardian = [];
                    if ($guardianId === null && $data['guardian_first_name'] !== null) {
                        $newGuardian = [
                            'first_name' => $data['guardian_first_name'], 'last_name' => $data['guardian_last_name'],
                            'email' => $data['guardian_email'], 'phone' => $data['guardian_phone'],
                            'whatsapp_number' => $data['guardian_whatsapp_number'], 'relationship' => $relationship,
                        ];
                    }
                    $player = $this->savePlayer->handle($academy, $actor, [
                        'internal_identifier' => $data['internal_identifier'], 'first_name' => $data['first_name'],
                        'middle_name' => $data['middle_name'], 'last_name' => $data['last_name'],
                        'date_of_birth' => $data['date_of_birth'], 'gender' => $data['gender'],
                        'phone' => $data['phone'], 'whatsapp_number' => $data['whatsapp_number'],
                        'home_address' => $data['home_address'], 'city' => $data['city'],
                        'state_region' => $data['state_region'], 'country' => $data['country'],
                        'status' => $data['status'], 'custom_fields' => $data['custom_fields'],
                        'guardian_ids' => $guardianId === null ? [] : [$guardianId],
                        'guardian_relationships' => $guardianId === null ? [] : [$guardianId => $relationship],
                        'new_guardian' => $newGuardian,
                    ], $request);

                    if (is_int($data['programme_id'])) {
                        $this->saveEnrollment->handle($academy, $player, $actor, [
                            'programme_id' => $data['programme_id'], 'branch_id' => $data['branch_id'],
                            'status' => $data['enrollment_status'], 'starts_on' => $data['enrollment_starts_on'],
                            'ends_on' => $data['enrollment_ends_on'],
                        ], $request);
                    }
                }

                $this->audit->record('academy.players.imported', $actor, $academy, $academy, [
                    'row_count' => $analysis->totalRows(),
                ], $request);

                return $analysis->totalRows();
            });
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'csv_file' => 'No profiles were imported because the academy data changed after preview. Review the file and try again.',
            ]);
        }
    }
}
