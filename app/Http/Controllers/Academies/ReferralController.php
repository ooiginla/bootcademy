<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\PayReferralReward;
use App\Academies\Actions\ReconcileReferralRewards;
use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\ReferralSetting;
use App\Platform\AuditLogger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function index(AcademyContext $context): Response
    {
        abort_unless($context->canManageAcademy() || $context->canManageFinance(), 403);
        $academy = $context->academy();
        $settings = ReferralSetting::query()->firstOrCreate(['academy_id' => $academy->id], ['is_enabled' => false, 'qualifying_event' => 'first_verified_payment', 'reward_type' => 'percentage', 'eligible_payment_scope' => 'programme']);

        return Inertia::render('academies/referrals', [
            'referrals' => $academy->referrals()->with(['referrer:id,first_name,last_name', 'referredPlayer:id,first_name,last_name', 'application:id,reference,first_name,last_name,converted_player_id', 'rewards:id,referral_id,status,reward_amount_minor'])->latest('attributed_at')->limit(250)->get()->map(fn (Referral $referral): array => ['id' => $referral->id, 'referrer_name' => trim($referral->referrer->first_name.' '.$referral->referrer->last_name), 'referred_name' => $referral->referredPlayer ? trim($referral->referredPlayer->first_name.' '.$referral->referredPlayer->last_name) : trim(($referral->application?->first_name ?? '').' '.($referral->application?->last_name ?? '')), 'application_reference' => $referral->application?->reference, 'status' => $referral->status, 'source' => $referral->source, 'notes' => $referral->notes, 'attributed_at' => $referral->attributed_at->toIso8601String(), 'reward_count' => $referral->rewards->count()]),
            'rewards' => $academy->referralRewards()->with(['referral.referrer:id,first_name,last_name', 'referral.referredPlayer:id,first_name,last_name', 'referral.application:id,first_name,last_name', 'payment:id,reference', 'invoice:id,number'])->latest('earned_at')->limit(250)->get()->map(fn (ReferralReward $reward): array => ['id' => $reward->id, 'referrer_name' => trim($reward->referral->referrer->first_name.' '.$reward->referral->referrer->last_name), 'referred_name' => $reward->referral->referredPlayer ? trim($reward->referral->referredPlayer->first_name.' '.$reward->referral->referredPlayer->last_name) : trim(($reward->referral->application?->first_name ?? '').' '.($reward->referral->application?->last_name ?? '')), 'status' => $reward->status, 'currency' => $reward->currency, 'qualifying_amount' => Money::fromMinor($reward->qualifying_amount_minor), 'reward_amount' => Money::fromMinor($reward->reward_amount_minor), 'payment_reference' => $reward->payment?->reference, 'invoice_number' => $reward->invoice?->number, 'earned_at' => $reward->earned_at->toIso8601String(), 'payout_reference' => $reward->payout_reference]),
            'settings' => ['is_enabled' => $settings->is_enabled, 'qualifying_event' => $settings->qualifying_event, 'reward_type' => $settings->reward_type, 'reward_value' => $settings->reward_type === 'percentage' ? number_format(((int) $settings->reward_basis_points) / 100, 2, '.', '') : Money::fromMinor((int) $settings->fixed_reward_minor), 'eligible_payment_scope' => $settings->eligible_payment_scope, 'currency' => $academy->currency],
            'players' => $academy->players()->where('status', 'active')->orderBy('first_name')->orderBy('last_name')->limit(500)->get(['id', 'first_name', 'last_name'])->map(fn ($player): array => ['id' => $player->id, 'name' => trim($player->first_name.' '.$player->last_name)]),
            'applications' => $academy->applications()->whereIn('status', ['submitted', 'under_review', 'accepted', 'enrolled'])->latest()->limit(500)->get(['id', 'reference', 'first_name', 'last_name'])->map(fn ($application): array => ['id' => $application->id, 'name' => trim($application->first_name.' '.$application->last_name).' · '.$application->reference]),
            'can_configure' => $context->canManageAcademy(), 'can_manage_rewards' => $context->canManageFinance(),
        ]);
    }

    public function store(Request $request, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        abort_unless($context->canManageAcademy(), 403);
        $academy = $context->academy();
        $data = $request->validate(['referrer_player_id' => ['required', Rule::exists('players', 'id')->withoutTrashed()->where('academy_id', $academy->id)], 'referred_type' => ['required', Rule::in(['application', 'player'])], 'referred_id' => ['required', 'integer'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $applicationId = $data['referred_type'] === 'application' ? (int) $data['referred_id'] : null;
        $playerId = $data['referred_type'] === 'player' ? (int) $data['referred_id'] : null;
        if ($applicationId !== null && ! $academy->applications()->whereKey($applicationId)->exists()) {
            abort(404);
        }
        if ($playerId !== null && ! $academy->players()->whereKey($playerId)->exists()) {
            abort(404);
        }
        if ($playerId !== null && $playerId === (int) $data['referrer_player_id']) {
            throw ValidationException::withMessages(['referred_id' => 'A player cannot refer themselves.']);
        }
        if ($academy->referrals()->when($applicationId, fn ($query) => $query->where('referred_application_id', $applicationId))->when($playerId, fn ($query) => $query->where('referred_player_id', $playerId))->exists()) {
            throw ValidationException::withMessages(['referred_id' => 'This person already has a referral attribution.']);
        }
        $referral = Referral::query()->create(['academy_id' => $academy->id, 'referrer_player_id' => $data['referrer_player_id'], 'referred_application_id' => $applicationId, 'referred_player_id' => $playerId, 'status' => 'attributed', 'source' => 'admin', 'notes' => $data['notes'] ?? null, 'attributed_at' => now(), 'created_by_user_id' => $request->user()->id]);
        $audit->record('academy.referral.attributed', $request->user(), $academy, $referral, [], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Referral attribution created.']);

        return back();
    }

    public function updateSettings(Request $request, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        abort_unless($context->canManageAcademy(), 403);
        $data = $request->validate(['is_enabled' => ['required', 'boolean'], 'qualifying_event' => ['required', Rule::in(['enrollment', 'first_verified_payment', 'programme_invoice_paid'])], 'reward_type' => ['required', Rule::in(['percentage', 'fixed'])], 'reward_value' => ['required', 'numeric', 'gt:0'], 'eligible_payment_scope' => ['required', Rule::in(['programme', 'all'])]]);
        if ($data['qualifying_event'] === 'enrollment' && $data['reward_type'] !== 'fixed') {
            throw ValidationException::withMessages(['reward_type' => 'Enrollment rewards must use a fixed amount because no payment has occurred yet.']);
        }
        if ($data['reward_type'] === 'percentage' && (float) $data['reward_value'] > 100) {
            throw ValidationException::withMessages(['reward_value' => 'Percentage rewards cannot exceed 100%.']);
        }
        $values = ['is_enabled' => $data['is_enabled'], 'qualifying_event' => $data['qualifying_event'], 'reward_type' => $data['reward_type'], 'eligible_payment_scope' => $data['eligible_payment_scope'], 'reward_basis_points' => $data['reward_type'] === 'percentage' ? (int) round((float) $data['reward_value'] * 100) : null, 'fixed_reward_minor' => $data['reward_type'] === 'fixed' ? Money::toMinor((string) $data['reward_value']) : null];
        $settings = ReferralSetting::query()->updateOrCreate(['academy_id' => $context->id()], $values);
        $audit->record('academy.referral_settings.updated', $request->user(), $context->academy(), $settings, $values, $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Referral reward settings updated.']);

        return back();
    }

    public function reconcile(Request $request, AcademyContext $context, ReconcileReferralRewards $reconcile): RedirectResponse
    {
        abort_unless($context->canManageAcademy() || $context->canManageFinance(), 403);
        $count = $reconcile->handle($context->academy());
        Inertia::flash('toast', ['type' => 'success', 'message' => "Referral evaluation complete; {$count} new reward(s) created."]);

        return back();
    }

    public function approve(Request $request, ReferralReward $reward, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        abort_unless($context->canManageFinance() && $reward->academy_id === $context->id(), 404);
        if ($reward->status !== 'pending') {
            throw ValidationException::withMessages(['reward' => 'Only pending rewards can be approved.']);
        }
        $reward->update(['status' => 'approved', 'approved_at' => now(), 'approved_by_user_id' => $request->user()->id]);
        $audit->record('academy.referral_reward.approved', $request->user(), $context->academy(), $reward, [], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Referral reward approved.']);

        return back();
    }

    public function markPaid(Request $request, ReferralReward $reward, AcademyContext $context, PayReferralReward $pay): RedirectResponse
    {
        abort_unless($context->canManageFinance() && $reward->academy_id === $context->id(), 404);
        $data = $request->validate(['payout_reference' => ['required', 'string', 'max:120', Rule::unique('referral_rewards')->where('academy_id', $context->id())], 'payout_notes' => ['nullable', 'string', 'max:2000']]);
        $pay->handle($context->academy(), $reward, $request->user(), $data, $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Referral payout recorded.']);

        return back();
    }
}
