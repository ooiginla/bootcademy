# Multicurrency and regional pricing — plan

Status: **proposal, not started.** Needs the decisions in [Decisions needed](#decisions-needed) before Phase 1.

## Goal

Let an academy charge different prices to different groups of payers, for example a reduced local price and a higher international/diaspora price, and later collect some of those prices in a foreign currency, without ever converting a price automatically or changing an invoice after it is issued.

## Principles

1. **Explicit prices, never automatic conversion.** The academy types every price it charges. Exchange rates may _suggest_ a figure while configuring, but the academy confirms the final amount.
2. **Market decides the price.** A market is an academy-defined group of payers (for example "Local" and "International") with one currency and a list of countries. The applicant's declared country picks the market, not their IP address. Staff can change an application's market.
3. **Invoices are snapshots.** The currency and amounts are copied onto the invoice when it is issued and never recalculated.
4. **One currency per invoice.** A payment is recorded in the invoice's currency. A payment is never allocated across currencies.
5. **No mixed-currency totals.** Totals are always grouped by currency. A combined (for example all-NGN) figure is only shown when it uses a stored exchange rate.
6. **No silent fallback.** If a market has no price for something, the default market's price is shown as-is, in its own currency, and is never converted.

## Where the code is today

**Data — already a good foundation.** Every money table stores amounts in minor units plus its own `currency` (`char(3)`):
`programme_cycles`, `programme_cycle_payment_plans`, `academy_cycle_fees`, `academy_invoices`, `academy_payments`, `online_payment_intents`, `products`, `store_orders`, `store_order_payments`, `academy_expenses`, plus the scholarship, sponsorship and referral tables.

**But every value comes from one currency.** Records are created with `$academy->currency` (programme fees, cycle fees, plans copy their programme fee's currency, invoices copy the plan or fee). The academy picks its currency at signup (`CreateNewUser`, `AcademyLocaleOptions::currencyCodes()`).

**Totals assume one currency.** `DashboardController` (billed, collected, expenses, per-branch), the invoice register summary and the CSV exports sum `total_minor` / `amount_minor` without grouping by currency.

**Forms assume one currency.**

| Form                                         | Money fields today                                                                                                                                                          |
| -------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Add/Edit programme fee (`cycle-details.tsx`) | Application fee, labelled with the academy currency symbol                                                                                                                  |
| Payment plan (`programme-fee-details.tsx`)   | Fee items (name, description, amount) and repayments (amount, due date), in the programme fee's currency; scope = whole cycle, term and/or cohort; default and public flags |
| Cycle fee (`cycle-details.tsx`)              | One amount, academy currency                                                                                                                                                |
| Store product (`store.tsx`)                  | Base price and variant prices, academy currency                                                                                                                             |
| Expenses                                     | One amount, academy currency                                                                                                                                                |

**Online payments.** Paystack, platform account with one settlement subaccount per academy (see `docs/online-payments-paystack-split.md`). Fee settings (Paystack rate and platform fee) are platform-wide and assume NGN.

## Review of the earlier recommendation

The earlier recommendation (explicit regional prices, locked invoice amounts, country-based market selection, exchange rates only for suggestions and reporting) is sound and is kept. Three changes:

1. **A market-specific price is a payment plan, not an entry inside a plan.** A USD price needs its own repayment schedule (different amounts, possibly different instalments). That is exactly what a payment plan already holds, and plans already have a `currency` column. Giving plans a market avoids a second pricing structure and keeps the existing scope, default-plan and public-visibility rules working.
2. **GBP cannot be collected online.** For a Nigerian business, Paystack charges in NGN and, once approved, USD. GBP can be shown for reference but not charged. The first version should support NGN and, if approved, USD.
3. **Collecting a foreign currency is a separate, later step.** Charging in USD needs Paystack to enable USD on the platform account, a USD settlement account (domiciliary) for each academy with its own subaccount, and Paystack's USD rate in platform settings. Diaspora parents can already pay **NGN** prices with foreign cards; Paystack converts for them. So an "International" market can start as a higher NGN price, which delivers the pricing benefit with no currency risk.

## Plan

### Phase 1 — Markets (NGN only)

Regional pricing without a second currency.

**Data**

- `academy_markets`: `academy_id`, `name`, `currency` (Phase 1: academy currency only), `countries` (JSON list of ISO codes), `is_default`, `is_active`, `sort_order`. Exactly one default market per academy, enforced by a unique index. A migration creates a default "Local" market for every academy.
- `programme_cycle_payment_plans.academy_market_id` (nullable, null = default market).
- `applications.academy_market_id` (set at submission, editable by staff before an invoice is paid).
- `programme_cycle_market_prices`: optional application fee per market for a programme fee.
- `academy_cycle_fee_market_prices`: optional amount per market for a cycle fee.

**Behaviour**

- A `ResolveMarket` service maps the applicant's declared country to a market, falling back to the default market.
- `PublicProgrammePaymentPlans::forOffering()` also filters by market. The existing cohort-overrides-general rule is unchanged.
- Issuing invoices uses the market's application fee or cycle fee amount when one exists, otherwise the default amount.
- Academies with one market see no difference anywhere.

**Forms**

- **Academy settings → Markets** (new section): name, currency (fixed to the academy currency in Phase 1), countries (searchable multi-select), default.
- **Payment plan form:** a Market selector at the top. Plan cards show a market badge, and the plan list can be filtered by market.
- **Programme fee form:** the application fee becomes one row per market, shown only when the academy has more than one market.
- **Cycle fee form:** the same per-market amount rows.
- **Application details (staff):** a Market field next to the payment plan.
- **Public application:** shows only the applicant's market plans once they pick their country.

**Tests:** market resolution and fallback; plans filtered by market on all public surfaces; staff override; cross-tenant market IDs rejected; invoice snapshots unchanged after a market price changes.

### Phase 2 — Currency correctness everywhere

Required before any academy has a second currency.

- Dashboard, invoice register, payments, expenses and exports group totals by currency (one row or card per currency).
- Recording a payment against an invoice in another currency is rejected. Allocation, write-off, scholarship and sponsor actions check currency.
- Every money label and `formatCurrency` call uses the record's own currency, not the academy's.
- Store products stay in the academy currency.

**Tests:** mixed-currency fixtures for every total; cross-currency payment and allocation rejected.

### Phase 3 — Collect USD through Paystack

Only after Paystack confirms USD for the platform account and that split payments work in USD.

- `academy_settlement_accounts`: one row per academy per currency (bank, account, subaccount code, status). This replaces the single `paystack_subaccount_*` columns on `academies`, with a data migration.
- Bank details screen: one settlement account per currency the academy's markets use.
- `platform_settings` fee rules become per currency (for example NGN 1.5% + ₦100 capped at ₦2,000; USD at Paystack's USD rate).
- Paying a USD invoice uses the academy's USD subaccount. Online payment in a currency is off until that currency's subaccount is active, while bank transfer stays available.
- Market currency becomes selectable (NGN or USD).

**Tests:** subaccount chosen by invoice currency; currency without an active subaccount disables online payment; fee rule per currency.

### Phase 4 — Combined reporting

- When a payment is recorded, store `reporting_currency`, `exchange_rate` and `rate_source` (and when it was fetched).
- Reports can then show a combined NGN figure built from stored rates, clearly labelled, alongside the per-currency totals.
- Exchange rates may be offered as suggestions in the price forms, but are never applied automatically.

## Decisions needed

1. **Currencies:** NGN only for now (Phase 1 and 2), or NGN plus USD (Phase 3)? GBP display-only or not at all?
2. **International pricing before USD collection:** should international parents pay a higher NGN price in Phase 1?
3. **Country source:** is the country on the application form the right signal, or should markets use the guardian's country of residence?
4. **Who can change an application's market:** Owner/Admin only, or Finance too?
5. **Paystack:** confirm with Paystack, before Phase 3, that the platform account can charge USD and split USD payments to subaccounts, and which settlement account each academy needs.

## Out of scope

SaaS subscription billing (stays NGN, separate from academy fees), changing an existing invoice's currency, and automatic price conversion.
