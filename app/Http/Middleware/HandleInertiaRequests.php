<?php

namespace App\Http\Middleware;

use App\Academies\AcademyContext;
use App\Platform\SupportAccess;
use App\Support\AccessibleColor;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'academy' => function (): ?array {
                $context = app(AcademyContext::class);

                if (! $context->hasAcademy()) {
                    return null;
                }

                $academy = $context->academy();
                $membership = $context->membershipOrNull();

                return [
                    'id' => $academy->id,
                    'name' => $academy->name,
                    'academy_type' => $academy->academy_type,
                    'terminology' => [
                        'singular' => $academy->member_label_singular,
                        'plural' => $academy->member_label_plural,
                        'staff_singular' => $academy->staff_label_singular,
                        'staff_plural' => $academy->staff_label_plural,
                    ],
                    'currency' => $academy->currency,
                    'timezone' => $academy->timezone,
                    'logo_url' => $academy->logo_path
                        ? route('academy.logo', ['v' => $academy->updated_at?->timestamp])
                        : null,
                    'role' => $context->isSupportMode() ? 'owner_admin' : $membership?->role->value,
                    'role_label' => $context->isSupportMode()
                        ? 'Support Mode · Owner/Admin'
                        : ($membership ? $academy->roleLabel($membership->role) : null),
                    'can_manage' => $context->canManageAcademy(),
                    'can_manage_finance' => $context->canManageFinance(),
                    'can_view_audit_log' => $context->canViewAuditLog(),
                    'active_member_count' => $academy->memberships()
                        ->where('status', 'active')
                        ->count(),
                    'theme' => AccessibleColor::theme($academy->primary_color, $academy->secondary_color),
                ];
            },
            'academies' => fn (): array => $request->user()
                ? $request->user()->activeAcademyMemberships()
                    ->with('academy:id,name,logo_path,staff_label_singular')
                    ->get()
                    ->map(fn ($item): array => [
                        'id' => $item->academy_id,
                        'name' => $item->academy->name,
                        'role_label' => $item->academy->roleLabel($item->role),
                    ])->all()
                : [],
            'platform' => [
                'is_super_admin' => $request->user()?->is_platform_super_admin === true,
            ],
            'support_access' => function () use ($request): ?array {
                $context = app(AcademyContext::class);

                if (! $context->isSupportMode()) {
                    return null;
                }

                $details = app(SupportAccess::class)->details($request);

                return $details ? [
                    'academy_name' => $context->academy()->name,
                ] : null;
            },
            'pending_invitations_count' => $request->user()
                ? $request->user()->academyMemberships()->where('status', 'invited')->count()
                : 0,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
