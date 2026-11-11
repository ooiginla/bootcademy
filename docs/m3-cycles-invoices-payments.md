# M3 cycles, programme fees and payment ledger

## Purpose

Academies can define how programme membership and fees are tracked without rewriting a player's history when they move to another programme or cycle. All monetary values are stored as integer minor units with a currency code. SaaS subscription billing remains separate from academy fees.

## Cycle types

- **Fixed cycle:** has shared start and end dates, such as `2026 Academic Year`. A late enrollee still finishes on the cycle's configured end date.
- **Rolling cycle:** has a duration in days, weeks or months. A player's start date is the application acceptance date, and their end date is calculated from that date. This supports periods such as 14 days, 2 weeks or 6 months without forcing whole-month durations.

An academy can have one active cycle. Activating another cycle closes the previously active cycle; historical enrollments, invoices and payments remain attached to their original cycle.

## Optional terms and fee scope

A fixed cycle may contain ordered terms. Rolling cycles do not use terms: their programme fees, additional fees, enrollments and reporting remain directly scoped to the cycle. Terms are time periods and are siblings of programme offerings; they do not replace cohorts. A cohort remains a group beneath a programme offering and normally continues across every term in a fixed cycle.

```text
Academy cycle
├── Terms
│   ├── Term 1
│   ├── Term 2
│   └── Term 3
└── Programme offerings
    └── Cohorts/class arms
```

Every new fixed cycle receives a default term. Rolling cycles do not create a default term. Legacy rolling-cycle term records are retained for auditability but are not exposed for new term-scoped activity; their fees are presented at cycle scope. The original terms migration created one default term for every cycle and assigned existing programme fee items to it. Existing general cycle fees remain outside the term structure.

Programme fee items may remain at the whole-cycle/session level or target a term and, optionally, one cohort belonging to that programme offering. Other cycle fees may likewise remain general or target a term and optional cohort. The cycle workspace displays unscoped programme fees as **Programme cycle fees**, unscoped custom fees as **Additional cycle fees**, and term-scoped fees inside the selected term. A cohort-scoped compulsory fee only invoices an active enrollment with active membership in that cohort. A missing term means the whole cycle; a missing cohort means all otherwise eligible cohorts. Application invoices exclude programme fee items targeting another cohort, while already-issued invoices remain immutable snapshots.

For fixed cycles, bulk re-enrollment records one idempotent term registration beneath each player's enrollment. On the first term registration for a new enrollment, unscoped whole-cycle programme fees and compulsory cycle fees are included alongside the selected term's fees. On later term registrations, only newly applicable term-scoped fees are issued. Repeating the same bulk operation returns the existing registration and cannot issue a second registration invoice. Existing application programme invoices are treated as the full historical offering snapshot and are not duplicated by term registration. Rolling-cycle enrollments use the cycle duration and do not enter the term re-enrollment workflow.

## Programme offerings and fees

A programme is offered within a cycle through a programme-cycle record. The offering contains:

- an optional application fee;
- a standard one-off payment plan with an itemized fee breakdown;
- a scope-specific payment-plan catalogue with independently itemized contracted totals;
- one or more repayments per plan, using dates for fixed cycles and activation offsets for rolling cycles.

Every visible programme-fee scope (whole cycle, term and optional cohort) has a details workspace. Plans in one scope cannot appear in or move to another scope. A plan's fee items define its contracted total, while its repayment rows must add up exactly to that total. This permits transparent alternatives such as a ₦100,000 one-off plan, a ₦110,000 two-payment plan or a ₦120,000 monthly plan without treating them as the same invoice amount. Each scope has one canonical **Standard payment** plan with one full repayment. Legacy offering fee rows remain synchronized internally for backward-compatible invoice fallback, but are no longer presented as a second pricing source. Existing scopes missing a standard plan are backfilled without replacing an academy's chosen default plan.

Payment plans have an explicit display order. Their fee items may include a short public description, such as `₦10,000/month`; the initial public application summary shows this description instead of exposing the plan total. Repayment amounts remain available through an expandable repayment schedule.

Each programme/cycle can have one academy-wide default fee schedule and one override per branch. Leaving the branch blank creates the default. When an applicant selects a branch, the branch override is used if one exists; otherwise the academy-wide default is retained. The branch belongs to the same tenant, and duplicate defaults or duplicate overrides for the same scope are blocked by validation and database uniqueness constraints.

Application fees are separate invoices and must be settled before acceptance when charged. On public registration, the right-hand application summary presents active whole-cycle plans as selectable cards with contracted totals, fee items and repayment schedules. Cohort-specific plans replace general plans when configured for the selected cohort. The selected plan is stored on the application and its amounts are copied to immutable invoice items and installments when the programme invoice is issued, so later plan changes cannot alter the applicant's historical obligation. Offerings without an active plan retain the legacy offering-fee invoice path for backward compatibility.

Before any payment is recorded, an administrator may replace a programme invoice's default plan with a student-specific schedule. One row represents a one-off full payment; multiple rows represent custom installments. Its total must exactly match the invoice total. A schedule cannot be rewritten after payment has started. A student may also settle the full outstanding invoice at once even when the programme has a default installment schedule.

## Applicant-to-student flow

1. An applicant selects an active programme offering through the admin form or versioned applicant API.
2. The system issues the optional application-fee invoice.
3. A non-zero application fee must be fully paid before the application can be accepted.
4. Acceptance issues the programme-fee invoice from the current offering snapshot and immediately converts the applicant into an active player enrolled in that programme and cycle.
5. Any unpaid programme fee remains on the player's ledger as an outstanding balance.
6. Later verified payments are allocated to the invoice's installments in sequence without changing the enrollment history.

The client cannot enroll an applicant by submitting a `paid` or `accepted` flag. An authorized academy administrator must accept the application server-side, after any required application fee is fully paid.

## Manual payment capture

Academy administrators can record verified bank-transfer, cash, POS, cheque or other offline payments. The operation is transactional and rejects:

- cross-tenant invoices;
- amounts above the outstanding balance; and
- duplicate external references within an academy.

Payments and allocations are retained as financial history. Corrections must be implemented as explicit reversals or adjustments rather than deleting paid records.

## Player account history

The player detail page groups programme membership, invoices, installments and payments by cycle. It shows total billed, paid and owing across cycles while preserving prior-cycle programme membership. Promoting a player from one programme in an old cycle to another programme in a new cycle does not invalidate the old membership or its balance.

Academy Settings stores the tenant's bank name, account name and account number as a complete validated set for bank-transfer instructions. Changes are restricted to academy administrators and written to the audit log.

Administrators can correct application and guardian biodata before enrollment. Once an invoice exists, the programme-cycle offering and branch cannot be changed from the edit form because doing so would detach the application from its immutable fee snapshot. After enrollment, biodata is maintained from the player profile.

Each application has a full details page for biodata, guardian information, cycle/programme context, invoices and verified payment history. New admin-created applications explicitly select an active cycle before selecting a programme offering, so an application-fee invoice always retains the correct cycle, programme and amount.

The Payments directory lists verified collections by payer, fee type, programme, cycle, method and reference. Payment records keep their original `application_id`; after conversion they also receive the resulting `player_id`. This provides a continuous audit trail from applicant to enrolled student. Bank-transfer and cheque capture show the tenant's configured bank details.

## Deferred online settlement decision

The ledger remains provider-independent. Manual verified payments and public Paystack payments both post into the same immutable academy ledger. Paystack initialization uses server-calculated invoice allocations, Popup V2 receives only the provider access code, and the backend independently verifies the reference, amount, currency and status. Signed webhooks and browser polling share an idempotent settlement service; a browser-provided success value can never mark a payment verified. Verified applicants receive a queued email receipt.

The current implementation uses the platform Paystack merchant keys and does not invent subaccount splits or academy payouts. Per-academy customer surcharge settings are controlled by Platform Super Admin and audited. Direct-academy accounts or split settlement remain a separate product/compliance decision.
