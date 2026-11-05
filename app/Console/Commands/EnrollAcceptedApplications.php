<?php

namespace App\Console\Commands;

use App\Academies\Actions\ReviewApplication;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Throwable;

class EnrollAcceptedApplications extends Command
{
    protected $signature = 'applications:enroll-accepted
        {--academy= : Limit the backfill to one academy ID}
        {--dry-run : Report eligible applications without changing them}';

    protected $description = 'Convert legacy accepted applications into players while preserving outstanding programme balances';

    public function handle(ReviewApplication $review): int
    {
        $query = Application::query()
            ->where('status', ApplicationStatus::Accepted)
            ->whereNull('converted_player_id')
            ->with(['academy.creator']);
        if ($this->option('academy') !== null) {
            $query->where('academy_id', (int) $this->option('academy'));
        }
        $applications = $query->orderBy('id')->get();
        if ($this->option('dry-run')) {
            $this->info("{$applications->count()} accepted application(s) are eligible for conversion.");

            return self::SUCCESS;
        }

        $converted = 0;
        $failed = 0;
        foreach ($applications as $application) {
            $actor = User::query()->find($application->reviewed_by_user_id) ?? $application->academy->creator;
            try {
                $review->handle($application->academy, $application, $actor, [
                    'decision' => 'enrol',
                    'review_notes' => $application->review_notes,
                    'programme_id' => $application->programme_id,
                    'branch_id' => $application->branch_id,
                    'activation_on' => ($application->reviewed_at ?? now())->toDateString(),
                ], Request::create('/console/applications/enroll-accepted', 'POST'));
                $converted++;
            } catch (Throwable $exception) {
                $failed++;
                $this->error("{$application->reference}: {$exception->getMessage()}");
            }
        }

        $this->info("Converted {$converted} accepted application(s); {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
