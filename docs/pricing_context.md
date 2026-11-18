# Pricing Context

## Status and purpose

This document records the recommended launch pricing and packaging context for Bootcademy. It is a commercial recommendation, not an immutable business rule. Prices, limits, discounts, taxes, onboarding commitments and support promises must remain configurable and should be reviewed against real customer conversion, usage and support costs.

The three-plan structure should be retained:

- **Essential** for small academies moving away from spreadsheets.
- **Professional** for growing academies that need online collections and operational automation.
- **Enterprise** for larger, multi-branch organizations that need advanced finance, commerce, integrations and higher-touch support.

Professional should be presented as the recommended or most popular plan.

## Recommended launch pricing

| Plan         |  Monthly | Quarterly |     Annual | Active players | Branches | Staff accounts |
| ------------ | -------: | --------: | ---------: | -------------: | -------: | -------------: |
| Essential    |  ₦25,000 |   ₦71,250 |   ₦270,000 |             75 |        1 |              5 |
| Professional |  ₦50,000 |  ₦142,500 |   ₦540,000 |            250 |        3 |             20 |
| Enterprise   | ₦100,000 |  ₦285,000 | ₦1,080,000 |            750 |       10 |             60 |

Quarterly billing provides a 5% discount. Annual billing provides a 10% discount. Academies exceeding 750 active players or 10 branches should receive a custom quote instead of being given an unlimited entitlement.

The pricing ladder deliberately follows an approximate 1:2:4 relationship. Capacity grows faster than price, giving larger academies a volume benefit without making Enterprise disproportionately inexpensive to operate.

## Recommended feature comparison

| Capability                             |  Essential   |  Professional  |           Enterprise            |
| -------------------------------------- | :----------: | :------------: | :-----------------------------: |
| Applications and player records        |     Yes      |      Yes       |               Yes               |
| Programmes, cycles and sessions        |     Yes      |      Yes       |               Yes               |
| Attendance                             |     Yes      |      Yes       |               Yes               |
| Manual invoices, payments and expenses |     Yes      |      Yes       |               Yes               |
| Basic dashboards and CSV exports       |     Yes      |      Yes       |               Yes               |
| Standard public academy website        |     Yes      |      Yes       |               Yes               |
| Team roles and audit history           |     Yes      |      Yes       |               Yes               |
| Paystack online payments               |      No      |      Yes       |               Yes               |
| Instalment and payment plans           |      No      |      Yes       |               Yes               |
| Automated payment reminders            |      No      |      Yes       |               Yes               |
| Reviews and form builder               |      No      |      Yes       |               Yes               |
| Coupons and referrals                  |      No      |      Yes       |               Yes               |
| Store and inventory                    |      No      |      Yes       |               Yes               |
| Advanced website branding              |      No      |      Yes       |               Yes               |
| Scholarships and sponsorships          |      No      |       No       |               Yes               |
| API access                             |      No      |       No       |               Yes               |
| Custom domain                          |      No      |       No       |               Yes               |
| Data migration assistance              | Self-service |     Guided     |            Included             |
| Support                                |    Email     | Priority email | Priority onboarding and support |

Security and customer-data protections are platform requirements, not premium features. Tenant isolation, authorization, consent records, auditability, backups and access to academy data exports must not be weakened or withheld based on plan.

Only advertise a feature when it is operational, supported and enforced. Parent portals, assessments, SMS/WhatsApp messaging, branded mobile applications and other future functionality must not appear in a plan comparison before delivery is approved and complete.

## Entitlement definitions

### Active player

An active player should mean a unique player with an active enrolment in a current academy membership period or cycle. A player enrolled in multiple programmes within the same academy should count once. Historical, graduated, withdrawn and archived players should not consume the active-player allowance, but their records must be preserved.

When an academy exceeds a limit after a downgrade, existing records must remain accessible. The application should prevent prohibited new usage and present an upgrade path; it must not delete or silently deactivate records.

### Branch and staff limits

- A branch counts when it is active and available for current operations.
- A staff account counts when the user has an active academy membership in a staff role.
- Guardians and public applicants should not consume staff-account allowances.
- Archived branches and revoked memberships should not consume allowances.

### External and variable costs

Paystack processing fees, SMS/WhatsApp usage, premium storage, custom integrations and bespoke implementation work should not be represented as unlimited inclusions unless a specific contract covers their cost. Academy subscription billing must remain separate from payments collected by an academy from parents or players.

## Market context

Current sports and academy platforms use several pricing approaches:

- [TrainTeam](https://trainteam.app/en) primarily scales prices by organization size, publishing plans for up to 100, 300 and 800 people.
- [Classcard](https://www.classcardapp.com/pricing) combines a flat platform price with feature differentiation, placing automation and multi-location capabilities on higher plans.
- [TeamSnap](https://www.teamsnap.com/pricing) and [PlayMetrics](https://app.playmetrics.com/pricing) move larger clubs and leagues toward customized organization pricing.
- [MySport Academy](https://mysportacademy.net/platform/pricing) advertises very low Nigerian pricing, but its published comparison states that integrated online payments are still on its roadmap. Bootcademy should not anchor its price to a materially narrower or less mature offer.

A hybrid of capacity and feature-based packaging is the most appropriate model for Bootcademy. Player, branch and staff volume affect operating costs, while payment automation, commerce, sponsorship administration and integrations create materially different customer value and support requirements.

## Current implementation gap

The platform plan editor currently supports only:

- Plan name
- Monthly, quarterly and annual NGN prices
- Active-player limit
- Active or inactive assignment status

The database contains `plan_features`, and `AcademyEntitlements` can read a feature flag or feature limit, but plan features are not currently seeded, editable through the platform plan screen or consistently enforced by module routes and actions. The active-player allowance is also not yet enforced in player or enrolment workflows.

Until enforcement is implemented, the detailed feature matrix must be treated as packaging guidance rather than a reliable system restriction.

## Recommended entitlement implementation

Add platform-admin controls for these initial feature keys:

- `online_payments`
- `payment_plans`
- `automated_reminders`
- `reviews`
- `coupons`
- `referrals`
- `commerce`
- `scholarships`
- `api_access`
- `advanced_branding`
- `custom_domain`

Add enforceable limits for:

- `active_players`
- `branches`
- `staff_accounts`

Entitlements must be checked server-side in policies, requests or domain actions. Hiding a navigation item is useful presentation but is not authorization or enforcement. Cross-tenant authorization remains mandatory regardless of plan.

The public pricing comparison should read from the same configured plan and entitlement records used by server-side enforcement. Marketing copy must not maintain a separate hardcoded feature matrix.

## Launch and review guidance

- Use a time-limited introductory discount for early customers instead of permanently lowering the public list price.
- Keep the Professional plan visually emphasized because it represents the best balance of value and sustainable support cost.
- Do not promise unlimited usage for resources that create infrastructure or support costs.
- Review pricing after approximately 90 days or the first 10 paying academies, whichever comes first.
- Track trial-to-paid conversion, plan selection, active-player utilization, payment volume, support hours, churn and reasons for lost sales.
- Validate the final positioning through direct interviews with small, growing and multi-branch academies before treating these prices as settled.
