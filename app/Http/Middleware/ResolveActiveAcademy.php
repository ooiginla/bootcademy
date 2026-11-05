<?php

namespace App\Http\Middleware;

use App\Academies\AcademyContext;
use App\Models\AcademyMembership;
use App\Platform\SupportAccess;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveActiveAcademy
{
    public function __construct(
        private readonly AcademyContext $context,
        private readonly SupportAccess $supportAccess,
    ) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if ($supportAcademy = $this->supportAccess->resolve($request)) {
            $this->context->setSupport($supportAcademy);

            return $next($request);
        }

        $requestedAcademyId = $request->session()->get('active_academy_id');

        $memberships = $user->activeAcademyMemberships()
            ->with('academy')
            ->get();

        if ($memberships->isEmpty()) {
            $request->session()->forget('active_academy_id');

            return to_route('academy.onboarding.create');
        }

        /** @var AcademyMembership|null $membership */
        $membership = $memberships->firstWhere('academy_id', (int) $requestedAcademyId)
            ?? $memberships->first();

        $request->session()->put('active_academy_id', $membership->academy_id);
        $this->context->set($membership);

        return $next($request);
    }
}
