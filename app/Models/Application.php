<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $academy_id
 * @property string $reference
 * @property string $source
 * @property string|null $external_reference
 * @property string|null $submission_hash
 * @property string|null $internal_identifier
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $email
 * @property Carbon|null $date_of_birth
 * @property string|null $gender
 * @property string|null $phone
 * @property string|null $whatsapp_number
 * @property string|null $home_address
 * @property string|null $city
 * @property string|null $state_region
 * @property string|null $country
 * @property string|null $guardian_first_name
 * @property string|null $guardian_last_name
 * @property string|null $guardian_email
 * @property string|null $guardian_phone
 * @property string|null $guardian_whatsapp_number
 * @property string|null $guardian_relationship
 * @property string|null $guardian_signature_path
 * @property Carbon|null $guardian_consent_at
 * @property string|null $privacy_policy_version
 * @property int|null $programme_id
 * @property int|null $programme_cycle_id
 * @property int|null $branch_id
 * @property array<string, mixed>|null $custom_fields
 * @property ApplicationStatus $status
 * @property string|null $review_notes
 * @property int|null $reviewed_by_user_id
 * @property int|null $converted_player_id
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $enrolled_at
 */
#[Fillable([
    'academy_id', 'reference', 'source', 'external_reference', 'submission_hash', 'public_access_token_hash',
    'internal_identifier', 'first_name', 'middle_name', 'last_name', 'email', 'date_of_birth',
    'gender', 'phone', 'whatsapp_number', 'home_address', 'city', 'state_region', 'country',
    'guardian_first_name', 'guardian_last_name', 'guardian_email', 'guardian_phone',
    'guardian_whatsapp_number', 'guardian_relationship', 'guardian_signature_path',
    'guardian_consent_at', 'privacy_policy_version',
    'programme_id', 'branch_id', 'custom_fields', 'status',
    'programme_cycle_id', 'cohort_id', 'application_intake_id',
    'review_notes', 'reviewed_by_user_id', 'reviewed_at', 'converted_player_id', 'enrolled_at',
])]
class Application extends Model
{
    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /** @return BelongsTo<ProgrammeCycle, $this> */
    public function programmeCycle(): BelongsTo
    {
        return $this->belongsTo(ProgrammeCycle::class);
    }

    /** @return BelongsTo<Cohort, $this> */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    /** @return BelongsTo<ApplicationIntake, $this> */
    public function applicationIntake(): BelongsTo
    {
        return $this->belongsTo(ApplicationIntake::class);
    }

    /** @return HasMany<AcademyInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(AcademyInvoice::class);
    }

    /** @return HasMany<AcademyPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(AcademyPayment::class);
    }

    /** @return HasMany<OnlinePaymentIntent, $this> */
    public function onlinePaymentIntents(): HasMany
    {
        return $this->hasMany(OnlinePaymentIntent::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function convertedPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'converted_player_id');
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date', 'custom_fields' => 'array', 'status' => ApplicationStatus::class,
            'guardian_consent_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime', 'enrolled_at' => 'immutable_datetime',
        ];
    }
}
