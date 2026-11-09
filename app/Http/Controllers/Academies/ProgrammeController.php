<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\SaveProgramme;
use App\Academies\Actions\BulkReenrollProgrammeMembers;
use App\Enums\CohortMembershipStatus;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\BulkCompleteProgrammeEnrollmentsRequest;
use App\Http\Requests\Academies\BulkReenrollProgrammeMembersRequest;
use App\Http\Requests\Academies\SaveProgrammeRequest;
use App\Http\Requests\Academies\UpdateProgrammeImageRequest;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Programme;
use App\Platform\AuditLogger;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProgrammeController extends Controller
{
    public function index(AcademyContext $context): Response
    {
        return Inertia::render('academies/programmes', [
            'programmes' => $context->academy()->programmes()
                ->with('branches:id,name,is_active')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (Programme $programme): array => [
                    ...$this->programmeData($programme),
                    'branches' => $programme->branches->map(fn (Branch $branch): array => [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'is_active' => $branch->is_active,
                    ])->values(),
                ]),
            'branches' => $context->academy()->branches()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'is_active']),
            'can_manage' => $context->canManageAcademy(),
        ]);
    }

    public function store(
        SaveProgrammeRequest $request,
        AcademyContext $context,
        SaveProgramme $saveProgramme,
    ): RedirectResponse {
        $saveProgramme->handle($context->academy(), $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Programme created.']);

        return back();
    }

    public function show(Request $request, Programme $programme, AcademyContext $context): Response
    {
        abort_unless($programme->academy_id === $context->id(), 404);
        $academy = $context->academy();
        $canManage = $context->canManageAcademy();
        $allowedBranchIds = $canManage
            ? $academy->branches()->pluck('id')->all()
            : $context->membership()->branches()->pluck('branches.id')->all();
        $branches = $academy->branches()
            ->whereIn('id', $allowedBranchIds)
            ->orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'is_active']);

        $selectedBranchId = null;
        $branchQuery = $request->query('branch');
        if (is_string($branchQuery) && $branchQuery !== '' && $branchQuery !== 'all') {
            abort_unless(ctype_digit($branchQuery), 404);
            $selectedBranchId = (int) $branchQuery;
            $selectedBranch = $academy->branches()->find($selectedBranchId);
            abort_if($selectedBranch === null, 404);
            abort_unless(in_array($selectedBranchId, $allowedBranchIds, true), 403);
        }

        $selectedStatus = $request->query('status', EnrollmentStatus::Active->value);
        abort_unless(is_string($selectedStatus) && in_array($selectedStatus, [
            'all', EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value,
        ], true), 404);

        $memberQuery = Enrollment::query()
            ->where('academy_id', $academy->id)
            ->where('programme_id', $programme->id)
            ->with(['player:id,first_name,middle_name,last_name,internal_identifier,status', 'branch:id,name']);

        if ($selectedBranchId !== null) {
            $memberQuery->where('branch_id', $selectedBranchId);
        } elseif (! $canManage) {
            $memberQuery->whereIn('branch_id', $allowedBranchIds);
        }
        if ($selectedStatus !== 'all') {
            $memberQuery->where('status', $selectedStatus);
        }

        $programme->load('branches:id,name,is_active');
        $activeTab = $request->query('tab') === 'members' ? 'members' : 'details';

        return Inertia::render('academies/programme-details', [
            'programme' => [
                ...$this->programmeData($programme),
                'branches' => $programme->branches->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'is_active' => $branch->is_active,
                ])->values(),
            ],
            'members' => $memberQuery
                ->orderByDesc('starts_on')->orderByDesc('id')
                ->paginate(20, ['*'], 'members_page')->withQueryString()
                ->through(fn (Enrollment $enrollment): array => [
                    'id' => $enrollment->id,
                    'player_id' => $enrollment->player_id,
                    'player_name' => trim("{$enrollment->player->first_name} {$enrollment->player->middle_name} {$enrollment->player->last_name}"),
                    'internal_identifier' => $enrollment->player->internal_identifier,
                    'player_status' => $enrollment->player->status->value,
                    'branch_name' => $enrollment->branch?->name,
                    'status' => $enrollment->status->value,
                    'starts_on' => $enrollment->starts_on->toDateString(),
                    'ends_on' => $enrollment->ends_on?->toDateString(),
                ]),
            'branch_options' => $branches,
            'selected_branch_id' => $selectedBranchId,
            'selected_status' => $selectedStatus,
            'reenrollment_options' => $canManage ? [
                'cycles' => $academy->cycles()->with(['terms' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
                    ->orderByRaw("status = 'active' DESC")->orderByDesc('starts_on')->get()->map(fn ($cycle): array => [
                        'id' => $cycle->id,
                        'name' => $cycle->name,
                        'is_active' => $cycle->status->value === 'active',
                        'terms' => $cycle->terms->map(fn ($term): array => [
                            'id' => $term->id, 'name' => $term->name,
                            'starts_on' => $term->starts_on?->toDateString(),
                            'ends_on' => $term->ends_on?->toDateString(),
                        ])->values(),
                    ])->values(),
                'offerings' => $academy->programmeCycles()->where('is_active', true)
                    ->with(['programme:id,name', 'branch:id,name', 'cohorts' => fn ($query) => $query->whereIn('status', ['open', 'ongoing'])->orderBy('name')])
                    ->get()->map(fn ($offering): array => [
                        'id' => $offering->id,
                        'academy_cycle_id' => $offering->academy_cycle_id,
                        'programme_id' => $offering->programme_id,
                        'programme_name' => $offering->programme->name,
                        'branch_id' => $offering->branch_id,
                        'branch_name' => $offering->branch?->name,
                        'cohorts' => $offering->cohorts->map(fn ($cohort): array => [
                            'id' => $cohort->id, 'name' => $cohort->name, 'branch_id' => $cohort->branch_id,
                        ])->values(),
                    ])->values(),
            ] : null,
            'active_tab' => $activeTab,
            'can_manage' => $canManage,
        ]);
    }

    public function completeEnrollments(
        BulkCompleteProgrammeEnrollmentsRequest $request,
        Programme $programme,
        AcademyContext $context,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless($programme->academy_id === $context->id(), 404);
        $ids = $request->validated('enrollment_ids');
        $completed = DB::transaction(function () use ($ids, $programme, $context, $request, $audit): int {
            $enrollments = Enrollment::query()->where('academy_id', $context->id())
                ->where('programme_id', $programme->id)->whereIn('id', $ids)->lockForUpdate()->get();
            if ($enrollments->count() !== count(array_unique($ids))) {
                abort(404);
            }
            $count = 0;
            foreach ($enrollments as $enrollment) {
                if ($enrollment->status !== EnrollmentStatus::Active) {
                    continue;
                }
                $completedOn = $enrollment->starts_on->isAfter(today()) ? $enrollment->starts_on : today();
                $enrollment->update(['status' => EnrollmentStatus::Completed, 'ends_on' => $completedOn]);
                $enrollment->cohortMemberships()->where('status', CohortMembershipStatus::Active->value)
                    ->update(['status' => CohortMembershipStatus::Completed->value, 'left_on' => $completedOn]);
                $audit->record('academy.enrollment.completed', $request->user(), $context->academy(), $enrollment, request: $request);
                $count++;
            }

            return $count;
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$completed} enrollment(s) marked as completed."]);

        return back();
    }

    public function reenrollMembers(
        BulkReenrollProgrammeMembersRequest $request,
        Programme $programme,
        AcademyContext $context,
        BulkReenrollProgrammeMembers $action,
    ): RedirectResponse {
        abort_unless($programme->academy_id === $context->id(), 404);
        $count = $action->handle($context->academy(), $programme, $request->user(), $request->validated(), $request);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$count} enrollment(s) activated for the selected term. Applicable invoices were issued without duplicates.",
        ]);

        return back();
    }

    public function update(
        SaveProgrammeRequest $request,
        Programme $programme,
        AcademyContext $context,
        SaveProgramme $saveProgramme,
    ): RedirectResponse {
        $saveProgramme->handle($context->academy(), $request->user(), $request->validated(), $request, $programme);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Programme updated.']);

        return back();
    }

    public function updateImage(
        UpdateProgrammeImageRequest $request,
        Programme $programme,
        AcademyContext $context,
        AuditLogger $audit,
    ): RedirectResponse {
        $file = $request->file('image');
        abort_unless($file !== null, 422);
        $path = $file->storeAs(
            'academies/'.$context->id().'/programmes/'.$programme->id,
            Str::uuid().'.'.$file->extension(),
            'local',
        );
        abort_unless(is_string($path), 500, 'The programme image could not be stored.');
        $oldPath = $programme->hero_image_path;

        try {
            DB::transaction(function () use ($programme, $path, $request, $context, $audit): void {
                $programme->update(['hero_image_path' => $path]);
                $audit->record('academy.programme.image.updated', $request->user(), $context->academy(), $programme, request: $request);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        if ($oldPath !== null) {
            Storage::disk('local')->delete($oldPath);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Programme image updated.']);

        return back();
    }

    public function destroyImage(Request $request, Programme $programme, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        abort_unless($programme->academy_id === $context->id(), 404);
        abort_unless($request->user()?->can('manage', $context->academy()) === true, 403);
        $oldPath = $programme->hero_image_path;

        DB::transaction(function () use ($programme, $request, $context, $audit): void {
            $programme->update(['hero_image_path' => null]);
            $audit->record('academy.programme.image.removed', $request->user(), $context->academy(), $programme, request: $request);
        });
        if ($oldPath !== null) {
            Storage::disk('local')->delete($oldPath);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Programme image removed.']);

        return back();
    }

    public function image(Programme $programme, AcademyContext $context): StreamedResponse
    {
        abort_unless($programme->academy_id === $context->id(), 404);
        abort_if($programme->hero_image_path === null, 404);
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        abort_unless($disk->exists($programme->hero_image_path), 404);

        return $disk->response($programme->hero_image_path, null, [
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, mixed> */
    private function programmeData(Programme $programme): array
    {
        $data = $programme->only([
            'id', 'name', 'code', 'description', 'duration', 'delivery_format',
            'programme_type', 'public_timezone', 'level', 'outcomes_html',
            'skills_gained_html', 'curriculum_html', 'eligibility_html',
            'audience_html', 'demand_html', 'application_process_html',
            'tools_platforms', 'faqs', 'testimonials', 'is_active', 'updated_at',
        ]);
        $data['image_url'] = $programme->hero_image_path
            ? route('academy.programmes.image', $programme).'?v='.$programme->updated_at->timestamp
            : null;

        return $data;
    }
}
