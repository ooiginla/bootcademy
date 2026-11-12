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
 * @property bool $collect_guardian_information
 * @property string $guardian_label_singular
 * @property string $guardian_label_plural
 * @property string $timezone
 * @property string $currency
 * @property string|null $bank_name
 * @property string|null $bank_account_name
 * @property string|null $bank_account_number
 * @property int $paystack_fee_basis_points
 * @property int $paystack_fee_fixed_minor
 * @property int|null $paystack_fee_cap_minor
 * @property int|null $paystack_fee_waiver_threshold_minor
 * @property int|null $platform_fee_basis_points
 * @property int|null $platform_fee_fixed_minor
 * @property string|null $bank_code
 * @property string|null $paystack_subaccount_code
 * @property string $paystack_subaccount_status
 * @property string|null $paystack_subaccount_error
 * @property Carbon|null $paystack_subaccount_synced_at
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
    'collect_guardian_information',
    'guardian_label_singular',
    'guardian_label_plural',
    'timezone',
    'currency',
    'bank_name',
    'bank_account_name',
    'bank_account_number',
    'bank_code',
    'paystack_subaccount_code',
    'paystack_subaccount_status',
    'paystack_subaccount_error',
    'paystack_subaccount_synced_at',
    'platform_fee_basis_points',
    'platform_fee_fixed_minor',
    'paystack_fee_basis_points',
    'paystack_fee_fixed_minor',
    'paystack_fee_cap_minor',
    'paystack_fee_waiver_threshold_minor',
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

    /** @return HasMany<AcademyCycleTerm, $this> */
    public function cycleTerms(): HasMany
    {
        return $this->hasMany(AcademyCycleTerm::class);
    }

    /** @return HasMany<ProgrammeCycle, $this> */
    public function programmeCycles(): HasMany
    {
        return $this->hasMany(ProgrammeCycle::class);
    }

    /** @return HasMany<Cohort, $this> */
    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class);
    }

    /** @return HasMany<ApplicationIntake, $this> */
    public function applicationIntakes(): HasMany
    {
        return $this->hasMany(ApplicationIntake::class);
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

    /** @return HasMany<Sponsor, $this> */
    public function sponsors(): HasMany
    {
        return $this->hasMany(Sponsor::class);
    }

    /** @return HasMany<SponsorCommitment, $this> */
    public function sponsorCommitments(): HasMany
    {
        return $this->hasMany(SponsorCommitment::class);
    }

    /** @return HasMany<ScholarshipScheme, $this> */
    public function scholarshipSchemes(): HasMany
    {
        return $this->hasMany(ScholarshipScheme::class);
    }

    /** @return HasMany<ScholarshipAward, $this> */
    public function scholarshipAwards(): HasMany
    {
        return $this->hasMany(ScholarshipAward::class);
    }

    /** @return HasMany<OnlinePaymentIntent, $this> */
    public function onlinePaymentIntents(): HasMany
    {
        return $this->hasMany(OnlinePaymentIntent::class);
    }

    /** @return HasMany<Announcement, $this> */
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    /** @return HasMany<NotificationDelivery, $this> */
    public function notificationDeliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    /** @return HasMany<Referral, $this> */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    /** @return HasMany<ReferralReward, $this> */
    public function referralRewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class);
    }

    /** @return HasMany<AcademyCycleFee, $this> */
    public function cycleFees(): HasMany
    {
        return $this->hasMany(AcademyCycleFee::class);
    }

    /** @return HasMany<AcademyExpense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(AcademyExpense::class);
    }

    /** @return HasMany<ExpenseCategory, $this> */
    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    /** @return HasMany<ExpenseVendor, $this> */
    public function expenseVendors(): HasMany
    {
        return $this->hasMany(ExpenseVendor::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<ProductCategory, $this> */
    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    /** @return HasMany<ProductTag, $this> */
    public function productTags(): HasMany
    {
        return $this->hasMany(ProductTag::class);
    }

    /** @return HasMany<StoreOrder, $this> */
    public function storeOrders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
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

    /** True when parents can pay online: Paystack is configured and the academy has an active subaccount. */
    public function acceptsOnlinePayments(): bool
    {
        return filled(config('services.paystack.secret_key'))
            && $this->paystack_subaccount_status === 'active'
            && filled($this->paystack_subaccount_code);
    }

    /** @return HasOne<AcademyWebsite, $this> */
    public function website(): HasOne
    {
        return $this->hasOne(AcademyWebsite::class);
    }

    protected function casts(): array
    {
        return [
            'collect_guardian_information' => 'boolean',
            'paystack_fee_basis_points' => 'integer',
            'paystack_fee_fixed_minor' => 'integer',
            'paystack_fee_cap_minor' => 'integer',
            'paystack_fee_waiver_threshold_minor' => 'integer',
            'platform_fee_basis_points' => 'integer',
            'platform_fee_fixed_minor' => 'integer',
            'paystack_subaccount_synced_at' => 'datetime',
        ];
    }
}
