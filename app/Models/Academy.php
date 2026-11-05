<?php

namespace App\Models;

use App\Enums\AcademyRole;
use Database\Factories\AcademyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $academy_type
 * @property string $member_label_singular
 * @property string $member_label_plural
 * @property string $staff_label_singular
 * @property string $staff_label_plural
 * @property string $timezone
 * @property string $currency
 * @property string|null $bank_name
 * @property string|null $bank_account_name
 * @property string|null $bank_account_number
 * @property string|null $logo_path
 * @property string|null $primary_color
 * @property string|null $secondary_color
 * @property int $created_by_user_id
 * @property int $memberships_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'slug',
    'academy_type',
    'member_label_singular',
    'member_label_plural',
    'staff_label_singular',
    'staff_label_plural',
    'timezone',
    'currency',
    'bank_name',
    'bank_account_name',
    'bank_account_number',
    'logo_path',
    'primary_color',
    'secondary_color',
    'created_by_user_id',
])]
class Academy extends Model
{
    /** @use HasFactory<AcademyFactory> */
    use HasFactory;

    public function roleLabel(AcademyRole $role): string
    {
        return $role === AcademyRole::Coach ? $this->staff_label_singular : $role->label();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<AcademyMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(AcademyMembership::class);
    }

    /** @return HasMany<Branch, $this> */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /** @return HasMany<Programme, $this> */
    public function programmes(): HasMany
    {
        return $this->hasMany(Programme::class);
    }

    /** @return HasMany<TrainingSession, $this> */
    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    /** @return HasMany<Player, $this> */
    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @return HasMany<Guardian, $this> */
    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    /** @return HasMany<AcademyCustomField, $this> */
    public function customFields(): HasMany
    {
        return $this->hasMany(AcademyCustomField::class);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /** @return HasMany<AcademyCycle, $this> */
    public function cycles(): HasMany
    {
        return $this->hasMany(AcademyCycle::class);
    }

    /** @return HasMany<ProgrammeCycle, $this> */
    public function programmeCycles(): HasMany
    {
        return $this->hasMany(ProgrammeCycle::class);
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

    public function cycleFees(): HasMany
    {
        return $this->hasMany(AcademyCycleFee::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(AcademyExpense::class);
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    public function expenseVendors(): HasMany
    {
        return $this->hasMany(ExpenseVendor::class);
    }

    /** @return HasMany<AcademyApiKey, $this> */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(AcademyApiKey::class);
    }

    /** @return HasMany<AcademyInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(AcademyInvitation::class);
    }

    /** @return HasOne<AcademySubscription, $this> */
    public function subscription(): HasOne
    {
        return $this->hasOne(AcademySubscription::class);
    }

    /** @return HasOne<AcademyWebsite, $this> */
    public function website(): HasOne
    {
        return $this->hasOne(AcademyWebsite::class);
    }
}
