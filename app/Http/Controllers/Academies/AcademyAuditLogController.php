<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AcademyAuditLogController extends Controller
{
    public function __invoke(Request $request, AcademyContext $context, AuditLogger $audit): Response
    {
        abort_unless($context->canViewAuditLog(), 403);

        $academy = $context->academy();
        $academyLogs = AuditLog::query()->where('academy_id', $academy->id);
        $actions = (clone $academyLogs)->distinct()->orderBy('action')->pluck('action')->all();
        $subjectTypes = (clone $academyLogs)->whereNotNull('auditable_type')
            ->distinct()->orderBy('auditable_type')->pluck('auditable_type')->all();

        $actorId = $request->integer('actor_user_id') ?: null;
        $action = mb_substr(trim((string) $request->query('action', '')), 0, 120);
        $subjectType = mb_substr(trim((string) $request->query('subject_type', '')), 0, 160);
        $subjectId = $request->integer('subject_id') ?: null;
        $dateFrom = $this->validDate((string) $request->query('date_from', ''));
        $dateTo = $this->validDate((string) $request->query('date_to', ''));

        if (! in_array($action, $actions, true)) {
            $action = '';
        }

        if (! in_array($subjectType, $subjectTypes, true)) {
            $subjectType = '';
        }

        $query = (clone $academyLogs)
            ->with('actor:id,name,email')
            ->when($actorId, fn (Builder $query) => $query->where('actor_user_id', $actorId))
            ->when($action !== '', fn (Builder $query) => $query->where('action', $action))
            ->when($subjectType !== '', fn (Builder $query) => $query->where('auditable_type', $subjectType))
            ->when($subjectId, fn (Builder $query) => $query->where('auditable_id', $subjectId))
            ->when($dateFrom, fn (Builder $query) => $query->where(
                'created_at',
                '>=',
                Carbon::parse($dateFrom, $academy->timezone)->startOfDay()->utc(),
            ))
            ->when($dateTo, fn (Builder $query) => $query->where(
                'created_at',
                '<=',
                Carbon::parse($dateTo, $academy->timezone)->endOfDay()->utc(),
            ));

        $logs = $query->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(25)->withQueryString()
            ->through(fn (AuditLog $log): array => [
                'id' => $log->id,
                'actor' => $log->actor ? [
                    'id' => $log->actor->id,
                    'name' => $log->actor->name,
                    'email' => $log->actor->email,
                ] : null,
                'action' => $log->action,
                'action_label' => $this->actionLabel($log->action),
                'subject_type' => $log->auditable_type,
                'subject_label' => $this->subjectLabel($log->auditable_type),
                'subject_id' => $log->auditable_id,
                'metadata' => $log->metadata ?? [],
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->setTimezone($academy->timezone)->format('M j, Y g:i A'),
            ]);

        $filters = [
            'actor_user_id' => $actorId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        $audit->record('academy.audit_log.viewed', $request->user(), $academy, metadata: [
            'filters' => array_filter($filters, fn (mixed $value): bool => $value !== null && $value !== ''),
        ], request: $request);

        return Inertia::render('academies/audit-log', [
            'logs' => $logs,
            'actors' => User::query()
                ->whereIn('id', (clone $academyLogs)->whereNotNull('actor_user_id')->select('actor_user_id'))
                ->orderBy('name')->get(['id', 'name', 'email']),
            'actions' => collect($actions)->map(fn (string $value): array => [
                'value' => $value,
                'label' => $this->actionLabel($value),
            ])->values(),
            'subject_types' => collect($subjectTypes)->map(fn (string $value): array => [
                'value' => $value,
                'label' => $this->subjectLabel($value),
            ])->values(),
            'filters' => $filters,
        ]);
    }

    private function actionLabel(string $action): string
    {
        return Str::of($action)->replace('.', ' ')->headline()->toString();
    }

    private function subjectLabel(?string $type): ?string
    {
        return $type ? Str::of(class_basename($type))->headline()->toString() : null;
    }

    private function validDate(string $value): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
