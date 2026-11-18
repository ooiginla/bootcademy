# Academy subscriptions

How academies pay the platform. This is separate from fees parents pay academies (see `online-payments-paystack-split.md`).

## Plans

Three plans, seeded from `docs/pricing_context.md` by migration `2026_11_18_000730` and `PlanSeeder`. The platform admin edits them under **Platform plans**.

| Plan                        | Monthly  | Quarterly | Annual     | Active players | Branches | Staff accounts |
| --------------------------- | -------- | --------- | ---------- | -------------- | -------- | -------------- |
| Essential                   | ₦25,000  | ₦71,250   | ₦270,000   | 75             | 1        | 5              |
| Professional (most popular) | ₦50,000  | ₦142,500  | ₦540,000   | 250            | 3        | 20             |
| Enterprise                  | ₦100,000 | ₦285,000  | ₦1,080,000 | 750            | 10       | 60             |

- Every feature in `config/plans.php` → `included` comes with every plan.
- Per-plan flags (`plan_features`): `api_access` and `custom_domain`, on for Enterprise only. Data migration and support are text columns on the plan.
- A null branch or staff limit means no limit.
- The migration **overwrites** the three plans' prices and limits with the values above.

## Access

Access comes from **periods** (`academy_subscription_periods`): `trial`, `paid` (from a Paystack payment) or `granted` (by the platform admin). `App\Platform\AcademyEntitlements` decides what applies now.

- **Trial:** a new academy gets the trial plan (Enterprise) for `trial_days` (30). Set under Platform settings → Free trial.
- **Existing academies:** every academy that existed at deploy got a 30-day Enterprise trial from the migration date.
- **Lapsed:** when no period covers now, the academy is on the post-trial plan (Essential) with no access. `EnsureAcademySubscription` sends every staff route to the subscription page, and the sidebar shows only Subscription.
- **Suspended:** the platform admin can suspend an academy. Suspension blocks the app even during a paid period.
- **Not gated:** the public website, public applications, parent payments, the store, the API, and platform support access.

## Paying

1. On **Settings → Subscription**, the Owner/Admin picks a plan and Monthly, Quarterly or Annual, then pays with Paystack. Other staff see a notice to ask the owner.
2. The price is the plan's list price, or the academy's own price if the platform admin set one.
3. The academy pays the price plus the **online payment fee**: the platform fee plus Paystack's fee grossed up, worked out by `PaystackFeeCalculator::subscriptionBreakdown`. This uses the platform's default fee; academy fee overrides only apply to parent payments.
4. With an active platform settlement subaccount, the price settles to that subaccount and the fee stays in the main Paystack balance (`transaction_charge` = fee, `bearer` = account). Without one, the whole payment stays in the main balance.
5. The browser verifies the payment after the popup. `payments:requery` (every 5 minutes) also confirms pending subscription payments.
6. A verified payment adds one paid period (`SettleSubscriptionPayment`, idempotent). The period starts when the academy's paid or granted time runs out, or now. Paying during a trial ends the trial immediately. A plan change applies from the new period.
7. Amount, currency, reference or subaccount mismatches go to `review_required` and add no period.

## Platform screens

- **Platform plans:** prices, limits, flags, migration and support text, most-popular badge and availability.
- **Platform academies → Subscription:**
    - suspend or reactivate
    - grant access until a date (for bank transfers or complimentary time)
    - set this academy's own monthly, quarterly and annual prices per plan
    - period and payment history
- **Tenant subscriptions:**
    - cards for total revenue (plan prices), this month, platform fees, and paying, trial and expired academies
    - payment list with plan, period, amount, fee and status, filterable and searchable
- **Platform settings:** subscription settlement account (Paystack subaccount) and free-trial settings.

## Limits

`PlanLimits` stops new usage only and never removes records:

- **Branches:** creating or reactivating an active branch.
- **Staff accounts:** inviting or creating staff, or reactivating a staff membership. Active staff memberships plus pending invitations count; guardians do not.
- **Active players:** an enrolment becoming active for a player with no other active enrolment. A player counts once across programmes.
- **API keys:** creating a key needs `api_access`. Existing keys keep working.

## Not done / open

- Custom domains are not built yet. The flag is shown in the comparison but enforces nothing.
- There are no renewal reminder emails before a period ends.
- No proration: an upgrade takes effect at the next period.
