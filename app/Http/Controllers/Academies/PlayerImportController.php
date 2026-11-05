<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\PlayerImports\AnalyzePlayerImport;
use App\Academies\PlayerImports\ImportPlayers;
use App\Academies\PlayerImports\PlayerImportAnalysis;
use App\Academies\PlayerImports\PlayerImportToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\UploadPlayerImportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlayerImportController extends Controller
{
    public function create(
        Request $request,
        AcademyContext $context,
        PlayerImportToken $tokens,
        AnalyzePlayerImport $analyze,
    ): Response {
        abort_unless($context->canManageAcademy(), 403);
        $token = trim((string) $request->query('token'));
        $preview = null;
        if ($token !== '') {
            $payload = $tokens->resolve($token, $context->academy(), $request->user());
            $analysis = $analyze->handle($context->academy(), $payload['path']);
            $preview = $this->present($analysis, $payload['original_name']);
        }

        return Inertia::render('academies/player-import', [
            'token' => $token === '' ? null : $token,
            'preview' => $preview,
            'template_headers' => $analyze->templateHeaders($context->academy()),
        ]);
    }

    public function preview(
        UploadPlayerImportRequest $request,
        AcademyContext $context,
        PlayerImportToken $tokens,
    ): RedirectResponse {
        $file = $request->file('csv_file');
        abort_if($file === null, 422);
        $path = $file->storeAs(
            "imports/{$context->id()}/{$request->user()->id}",
            Str::uuid().'.csv',
            'local',
        );
        abort_if($path === false, 500, 'The CSV file could not be stored.');
        $token = $tokens->issue($context->academy(), $request->user(), $path, $file->getClientOriginalName());

        return to_route('academy.players.import.create', ['token' => $token]);
    }

    public function confirm(
        Request $request,
        AcademyContext $context,
        PlayerImportToken $tokens,
        ImportPlayers $import,
    ): RedirectResponse {
        abort_unless($context->canManageAcademy(), 403);
        $validated = $request->validate(['token' => ['required', 'string', 'max:4096']]);
        $payload = $tokens->resolve($validated['token'], $context->academy(), $request->user());
        $count = $import->handle($context->academy(), $request->user(), $payload['path'], $request);
        Storage::disk('local')->delete($payload['path']);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$count} profiles imported successfully."]);

        return to_route('academy.players.index');
    }

    public function cancel(Request $request, AcademyContext $context, PlayerImportToken $tokens): RedirectResponse
    {
        abort_unless($context->canManageAcademy(), 403);
        $validated = $request->validate(['token' => ['required', 'string', 'max:4096']]);
        $payload = $tokens->resolve($validated['token'], $context->academy(), $request->user());
        Storage::disk('local')->delete($payload['path']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Import cancelled and uploaded file removed.']);

        return to_route('academy.players.import.create');
    }

    public function template(Request $request, AcademyContext $context, AnalyzePlayerImport $analyze): StreamedResponse
    {
        abort_unless($context->canManageAcademy(), 403);
        $headers = $analyze->templateHeaders($context->academy());

        return response()->streamDownload(function () use ($headers): void {
            $stream = fopen('php://output', 'wb');
            if ($stream !== false) {
                fputcsv($stream, $headers, ',', '"', '');
                fclose($stream);
            }
        }, 'student-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function errors(
        Request $request,
        AcademyContext $context,
        PlayerImportToken $tokens,
        AnalyzePlayerImport $analyze,
    ): StreamedResponse {
        abort_unless($context->canManageAcademy(), 403);
        $token = trim((string) $request->query('token'));
        abort_if($token === '', 404);
        $payload = $tokens->resolve($token, $context->academy(), $request->user());
        $analysis = $analyze->handle($context->academy(), $payload['path']);

        return response()->streamDownload(function () use ($analysis): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }
            fputcsv($stream, ['row', 'record', 'errors'], ',', '"', '');
            foreach ($analysis->fileErrors as $error) {
                fputcsv($stream, [1, 'File', $this->csvSafe($error)], ',', '"', '');
            }
            foreach ($analysis->rows as $row) {
                if ($row['errors'] !== []) {
                    fputcsv($stream, [
                        $row['line'], $this->csvSafe($row['label']), $this->csvSafe(implode(' | ', $row['errors'])),
                    ], ',', '"', '');
                }
            }
            fclose($stream);
        }, 'student-import-errors.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function present(PlayerImportAnalysis $analysis, string $fileName): array
    {
        return [
            'file_name' => $fileName, 'total_rows' => $analysis->totalRows(),
            'valid_rows' => $analysis->totalRows() - $analysis->errorRows(), 'error_rows' => $analysis->errorRows(),
            'file_errors' => $analysis->fileErrors, 'can_import' => $analysis->isValid(),
            'rows' => array_map(fn (array $row): array => [
                'line' => $row['line'], 'label' => $row['label'], 'errors' => $row['errors'],
                'internal_identifier' => $row['data']['internal_identifier'],
                'date_of_birth' => $row['data']['date_of_birth'], 'programme' => $row['data']['programme'],
                'branch' => $row['data']['branch'], 'guardian' => trim("{$row['data']['guardian_first_name']} {$row['data']['guardian_last_name']}"),
            ], array_slice($analysis->rows, 0, 100)),
            'is_truncated' => $analysis->totalRows() > 100,
        ];
    }

    private function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@]/', ltrim($value)) === 1 ? "'{$value}" : $value;
    }
}
