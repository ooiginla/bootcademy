<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\SaveCoachProfile;
use App\Enums\AcademyRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\SaveCoachProfileRequest;
use App\Models\AcademyCustomField;
use App\Models\AcademyMembership;
use App\Models\CoachCustomFieldValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CoachController extends Controller
{
    public function index(AcademyContext $context): Response
    {
        abort_unless($context->canManageAcademy(), 403);
        $search = mb_substr(trim((string) request('search')), 0, 160);
        $academy = $context->academy();

        $coaches = $academy->memberships()->where('role', AcademyRole::Coach->value)
            ->with(['user:id,name,email', 'branches:id,name', 'coachProfile.customFieldValues.field'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('user', fn (Builder $user) => $user->where('name', 'like', "%$search%")->orWhere('email', 'like', "%$search%"))
                        ->orWhereHas('coachProfile', fn (Builder $profile) => $profile
                            ->where('first_name', 'like', "%$search%")
                            ->orWhere('last_name', 'like', "%$search%")
                            ->orWhere('phone', 'like', "%$search%"));
                });
            })
            ->orderByDesc('status')->orderBy('id')->paginate(20)->withQueryString()
            ->through(fn (AcademyMembership $membership): array => $this->coachData($membership));

        return Inertia::render('academies/coaches', [
            'coaches' => $coaches,
            'custom_fields' => $academy->customFields()->where('entity_type', 'coach')
                ->orderBy('sort_order')->orderBy('label')->get()->map(fn (AcademyCustomField $field): array => [
                    'id' => $field->id, 'key' => $field->key, 'label' => $field->label,
                    'type' => $field->type->value, 'options' => $field->options ?? [],
                    'is_required' => $field->is_required, 'is_sensitive' => $field->is_sensitive,
                    'is_active' => $field->is_active, 'sort_order' => $field->sort_order,
                ]),
            'active_tab' => request('tab') === 'fields' ? 'fields' : 'coaches',
            'filters' => ['search' => $search],
            'countries' => config('countries'),
        ]);
    }

    public function update(SaveCoachProfileRequest $request, AcademyMembership $membership, AcademyContext $context, SaveCoachProfile $action): RedirectResponse
    {
        $action->handle($context->academy(), $membership, $request->user(), $request->validated(), $request);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $context->academy()->staff_label_singular.' profile updated.',
        ]);

        return back();
    }

    /** @return array<string, mixed> */
    private function coachData(AcademyMembership $membership): array
    {
        $profile = $membership->coachProfile;

        return [
            'membership_id' => $membership->id,
            'name' => $profile ? collect([$profile->first_name, $profile->middle_name, $profile->last_name])->filter()->implode(' ') : $membership->user->name,
            'email' => $membership->user->email,
            'status' => $membership->status->value,
            'branches' => $membership->branches->pluck('name')->values(),
            'profile' => $profile ? [
                'first_name' => $profile->first_name, 'middle_name' => $profile->middle_name, 'last_name' => $profile->last_name,
                'date_of_birth' => $profile->date_of_birth?->toDateString(), 'gender' => $profile->gender,
                'phone' => $profile->phone, 'whatsapp_number' => $profile->whatsapp_number,
                'home_address' => $profile->home_address, 'city' => $profile->city,
                'state_region' => $profile->state_region, 'country' => $profile->country,
                'years_of_experience' => $profile->years_of_experience, 'biography' => $profile->biography,
                'custom_fields' => $profile->customFieldValues->mapWithKeys(fn (CoachCustomFieldValue $value): array => [$value->field->key => $value->value]),
            ] : null,
        ];
    }
}
