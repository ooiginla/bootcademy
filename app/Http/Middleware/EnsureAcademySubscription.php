<?php

namespace App\Http\Middleware;

use App\Academies\AcademyContext;
use App\Platform\AcademyEntitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limits the staff app to the subscription page while an academy has no trial or paid period.
 *
 * The public website, public applications and payments, and the API are not behind this.
 * Platform support access is not limited, so support can still help a lapsed academy.
 */
class EnsureAcademySubscription
{
    /** Routes that stay open without a subscription. */
    private const array ALLOWED = ['academy.subscription.*', 'academy.logo'];

    public function __construct(private readonly AcademyContext $context) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->context->hasAcademy()
            || $this->context->isSupportMode()
            || $request->routeIs(...self::ALLOWED)
            || AcademyEntitlements::for($this->context->academy())->hasAccess()) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'This academy needs an active subscription.'], 402);
        }

        return to_route('academy.subscription.show');
    }
}
