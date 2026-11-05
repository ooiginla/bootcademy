<?php

namespace App\Http\Controllers\Academies;

use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademyMembership;
use App\Platform\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AcademyInvitationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('academies/invitations', [
            'invitations' => $request->user()->academyMemberships()
                ->where('status', MembershipStatus::Invited->value)
                ->with('academy:id,name,academy_type,staff_label_singular')
                ->get()
                ->map(fn (AcademyMembership $membership): array => [
                    'id' => $membership->id,
                    'academy_name' => $membership->academy->name,
                    'academy_type' => $membership->academy->academy_type,
                    'role_label' => $membership->academy->roleLabel($membership->role),
                ]),
        ]);
    }

    public function accept(
        Request $request,
        AcademyMembership $membership,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless(
            $membership->user_id === $request->user()->id
            && $membership->status === MembershipStatus::Invited,
            404,
        );

        DB::transaction(function () use ($membership, $request, $audit): void {
            $membership->update([
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ]);
            $audit->record('academy.invitation.accepted', $request->user(), $membership->academy, $membership, request: $request);
        });

        $request->session()->put('active_academy_id', $membership->academy_id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Academy invitation accepted.']);

        return to_route('dashboard');
    }
}
