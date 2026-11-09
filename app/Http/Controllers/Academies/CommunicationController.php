<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\SendAcademyCommunication;
use App\Http\Controllers\Controller;
use App\Models\AcademyCommunicationSetting;
use App\Models\AcademyInvoice;
use App\Models\Announcement;
use App\Models\Enrollment;
use App\Platform\AuditLogger;
use App\Support\CalendarDays;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommunicationController extends Controller
{
    public function index(AcademyContext $context): Response
    {
        abort_unless($context->canManageAcademy() || $context->canManageFinance(), 403);
        $academy = $context->academy();
        $today = now($academy->timezone)->startOfDay();
        $invoices = $academy->invoices()->whereIn('status', ['issued', 'partially_paid'])
            ->whereRaw('total_minor > (paid_minor - sponsor_paid_minor) + written_off_minor + scholarship_concession_minor + sponsor_responsibility_minor')
            ->with(['player.guardians', 'programme:id,name', 'branch:id,name', 'installments'])->latest('issued_at')->limit(500)->get();
        $paymentDue = $invoices->map(function (AcademyInvoice $invoice) use ($today): ?array {
            $installment = $invoice->installments->filter(fn ($item) => $item->amount_minor > ($item->paid_minor - $item->sponsor_paid_minor) + $item->written_off_minor + $item->scholarship_concession_minor + $item->sponsor_responsibility_minor)->sortBy('due_on')->first();
            $dueOn = $installment?->due_on ?? $invoice->due_on;
            if ($dueOn === null || $dueOn->gt($today->copy()->addDays(45))) {
                return null;
            }

            return ['id' => $invoice->id, 'number' => $invoice->number, 'player_id' => $invoice->player_id, 'player_name' => trim(($invoice->player?->first_name ?? '').' '.($invoice->player?->last_name ?? '')), 'fee_name' => $invoice->fee_name ?? 'Academy fee', 'programme' => $invoice->programme?->name, 'branch' => $invoice->branch?->name, 'due_on' => $dueOn->toDateString(), 'days_until_due' => CalendarDays::roundedUntil($today, $dueOn), 'balance' => Money::fromMinor($invoice->personalBalanceMinor()), 'currency' => $invoice->currency, 'recipient_count' => $invoice->player?->guardians->whereNotNull('email')->unique('email')->count() ?? 0];
        })->filter()->sortBy('due_on')->values();
        $renewals = $academy->enrollments()->where('status', 'active')->whereNotNull('ends_on')->whereDate('ends_on', '<=', $today->copy()->addDays(60))
            ->with(['player.guardians', 'programme:id,name', 'branch:id,name'])->orderBy('ends_on')->limit(250)->get()->map(fn (Enrollment $enrollment): array => ['id' => $enrollment->id, 'player_id' => $enrollment->player_id, 'player_name' => trim($enrollment->player->first_name.' '.$enrollment->player->last_name), 'programme' => $enrollment->programme->name, 'branch' => $enrollment->branch?->name, 'ends_on' => $enrollment->ends_on->toDateString(), 'days_until_end' => CalendarDays::roundedUntil($today, $enrollment->ends_on), 'recipient_count' => $enrollment->player->guardians->whereNotNull('email')->unique('email')->count()])->values();
        $settings = AcademyCommunicationSetting::query()->firstOrCreate(['academy_id' => $academy->id], ['automatic_payment_reminders' => false, 'payment_reminder_days' => [14, 7, 1, 0], 'automatic_renewal_reminders' => false, 'renewal_reminder_days' => [30, 14, 7]]);

        return Inertia::render('academies/communications', [
            'announcements' => $academy->announcements()->latest()->limit(100)->get(),
            'payment_due' => $paymentDue,
            'renewals' => $renewals,
            'deliveries' => $academy->notificationDeliveries()->latest('queued_at')->limit(100)->get(),
            'settings' => $settings,
            'branches' => $academy->branches()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'programmes' => $academy->programmes()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'can_manage_announcements' => $context->canManageAcademy(),
        ]);
    }

    public function storeAnnouncement(Request $request, AcademyContext $context, SendAcademyCommunication $send, AuditLogger $audit): RedirectResponse
    {
        abort_unless($context->canManageAcademy(), 403);
        $academy = $context->academy();
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:10000'], 'audience_type' => ['required', Rule::in(['all', 'branch', 'programme'])], 'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('academy_id', $academy->id)], 'programme_id' => ['nullable', Rule::exists('programmes', 'id')->where('academy_id', $academy->id)], 'publish_now' => ['required', 'boolean'], 'expires_at' => ['nullable', 'date', 'after:now']]);
        if ($data['audience_type'] === 'branch' && empty($data['branch_id'])) {
            return back()->withErrors(['branch_id' => 'Select a branch.']);
        }
        if ($data['audience_type'] === 'programme' && empty($data['programme_id'])) {
            return back()->withErrors(['programme_id' => 'Select a programme.']);
        }
        $announcement = Announcement::query()->create([...$data, 'academy_id' => $academy->id, 'status' => $data['publish_now'] ? 'published' : 'draft', 'published_at' => $data['publish_now'] ? now() : null, 'created_by_user_id' => $request->user()->id]);
        $queued = 0;
        if ($data['publish_now']) {
            $guardians = $academy->guardians()->whereNotNull('email')->whereHas('players.enrollments', fn ($query) => $query->where('status', 'active'))->when($data['audience_type'] === 'branch', fn ($query) => $query->whereHas('players.enrollments', fn ($query) => $query->where('branch_id', $data['branch_id'])->where('status', 'active')))->when($data['audience_type'] === 'programme', fn ($query) => $query->whereHas('players.enrollments', fn ($query) => $query->where('programme_id', $data['programme_id'])->where('status', 'active')))->get()->unique(fn ($guardian) => mb_strtolower($guardian->email))->map(fn ($guardian): array => ['email' => $guardian->email, 'name' => trim($guardian->first_name.' '.$guardian->last_name)]);
            $queued = $send->announcement($academy, $announcement, $guardians);
        }
        $audit->record('academy.announcement.created', $request->user(), $academy, $announcement, ['queued' => $queued], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => $data['publish_now'] ? "Announcement published; {$queued} email(s) queued." : 'Announcement saved as draft.']);

        return back();
    }

    public function updateSettings(Request $request, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        abort_unless($context->canManageAcademy(), 403);
        $data = $request->validate(['automatic_payment_reminders' => ['required', 'boolean'], 'payment_reminder_days' => ['required', 'array', 'min:1', 'max:8'], 'payment_reminder_days.*' => ['integer', 'min:0', 'max:365', 'distinct'], 'automatic_renewal_reminders' => ['required', 'boolean'], 'renewal_reminder_days' => ['required', 'array', 'min:1', 'max:8'], 'renewal_reminder_days.*' => ['integer', 'min:0', 'max:365', 'distinct']]);
        $settings = AcademyCommunicationSetting::query()->updateOrCreate(['academy_id' => $context->id()], $data);
        $audit->record('academy.communication_settings.updated', $request->user(), $context->academy(), $settings, $data, $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reminder settings updated.']);

        return back();
    }

    public function remindInvoice(Request $request, AcademyInvoice $invoice, AcademyContext $context, SendAcademyCommunication $send): RedirectResponse
    {
        abort_unless($context->canManageFinance() && $invoice->academy_id === $context->id(), 404);
        $count = $send->invoice($context->academy(), $invoice, -1);
        Inertia::flash('toast', ['type' => $count ? 'success' : 'info', 'message' => $count ? "{$count} payment reminder(s) queued." : 'No new reminder was queued. Check guardian email details or today’s delivery history.']);

        return back();
    }

    public function remindRenewal(Request $request, Enrollment $enrollment, AcademyContext $context, SendAcademyCommunication $send): RedirectResponse
    {
        abort_unless(($context->canManageAcademy() || $context->canManageFinance()) && $enrollment->academy_id === $context->id(), 404);
        $count = $send->renewal($context->academy(), $enrollment, -1);
        Inertia::flash('toast', ['type' => $count ? 'success' : 'info', 'message' => $count ? "{$count} renewal reminder(s) queued." : 'No new reminder was queued. Check guardian email details or today’s delivery history.']);

        return back();
    }
}
