# Academy SaaS implementation status

Last updated: 2026-11-12

This is the live execution checklist. It complements `SPORTS_ACADEMY_SAAS_PRD.md`, which contains the full product requirements, and `AGENTS.md`, which contains engineering rules. Update this file whenever a feature starts, completes, is reordered by the product owner, or becomes blocked by a business decision.

## Status legend

- `[x]` Complete and covered by automated tests.
- `[~]` In progress or the immediate next item.
- `[ ]` Planned but not started.
- `[!]` Waiting for a product/business decision.

## Current position

- `[x]` **M0 — Foundation:** Laravel/Inertia/React shell, authentication, CI checks and architecture decisions.
- `[x]` **M1 — Identity and tenancy:** multi-academy membership, roles, tenant policies, academy switching, plans, subscriptions and unrestricted Super Admin support mode.
- `[x]` **M2 — Academy operations:** branches, programmes, teams/roles, invitations, students/players, guardians, custom fields, multiple programme enrollments, applications and CSV import.
- `[x]` **External applicant API:** tenant API keys, configuration endpoint, idempotent applicant submission, status lookup, consent capture and duplicate detection.
- `[x]` **Cohorts and application intake:** tenant-scoped delivery cohorts, default-cohort normalization, intake date/branch controls, public/API availability gating and cohort-based enrolment/attendance.
- `[x]` **Bootcademy public sites:** unique academy subdomains, editable Launch theme, tenant-branded public pages, programme details and a non-submitting application-form preview.
- `[x]` **M3 finance foundation:** fixed/rolling cycles, cycle-specific programme fees, immutable invoices, installments, verified offline payments, cycle enrollment and player ledgers.
- `[x]` **M3 attendance foundation:** tenant-scoped session rosters, mobile marking and immutable correction history.
- `[x]` **M3 complete:** sessions, attendance, offline finance, receipts, reversals, expenses, operational dashboard and tenant-safe CSV reporting.
- `[x]` **Soft deletes and spam cleanup:** dependency-guarded soft deletes for applications, branches, programmes, sessions, cycles, cycle fees, programme fees, payment plans, expenses, players, coaches and store products; Platform Super Admin permanent academy purge with typed-name confirmation; Google reCAPTCHA v3 on signup, public applications and store checkout. See `docs/deletion-and-spam-protection.md`.
- `[!]` **Current decision:** how online academy fee payments settle through Paystack.

## Immediate execution queue

M3 is complete. The next milestone is M4 pilot readiness. Online settlement remains deferred until the academy payment merchant model is approved and is tracked with the M5 online-payment work.

1. `[!]` Choose the academy payment merchant model:
    - each academy connects its own Paystack account and receives payment directly; or
    - the platform uses its Paystack account with academy subaccounts/split settlement.
2. `[x]` Add tenant-defined fixed and rolling cycles with one active cycle.
3. `[x]` Add cycle-specific programme offerings, optional application fees and itemized fee schedules using integer minor units and currency.
4. `[x]` Create immutable applicant/student invoices that retain the amount applicable when issued.
5. `[x]` Support default and student-specific installments, partial payments and payment allocations without rewriting history.
    - `[x]` Add cycle/term/cohort-scoped programme payment-plan details with independent fee breakdowns, contracted totals and balanced repayment schedules.
    - `[x]` Let public applicants compare and select an active plan during registration, then snapshot its fee items and repayments onto the issued programme invoice.
6. `[x]` Add audited manual/offline verified payment recording with duplicate-reference and overpayment protection.
7. `[x]` Convert and enroll immediately on authorized acceptance after any required application fee is fully paid; retain programme fees as player balances.
8. `[x]` Show each player's current and historical cycle memberships, invoices, payments and balances.
9. `[!]` Add payment initialization behind the provider abstraction after the merchant-model decision (deferred to the online-payment milestone).
10. `[!]` Add signature-verified, idempotent Paystack webhook processing and reference re-query after that decision.
11. `[x]` Invoice cancellation, audited partial/full write-offs, printable receipts, partial/full payment reversals and finance reporting.

The system will never enroll from a website-supplied `paid: true` or `accepted: true` value. Enrollment follows an authorized admin acceptance after any required application fee is settled.

## Remaining roadmap

### M3 — Attendance and finance

- `[x]` Training/session schedules and coach assignments, presented as list and calendar tabs with academy-timezone previews and branch/programme/coach filters.
- `[x]` Mobile attendance register with present, absent, late and excused statuses.
- `[x]` Attendance correction history and duplicate-prevention constraints.
- `[x]` Fee schedules, invoices, payment allocations, printable receipts and explicit immutable reversal/adjustment flows.
- `[x]` Expenses, categories, private attachments, cycle attribution and branch attribution.
- `[x]` Operational dashboard with server-enforced branch/date filters, attendance, cash, aging and branch comparisons.
- `[x]` Finance and attendance CSV exports with tenant-safe authorization and audit logging.

### M4 — Pilot readiness

- `[ ]` Configure pilot academy data without hardcoding it into reusable logic.
- `[ ]` Connect the public website to the applicant API.
- `[ ]` Authorized legacy-data migration and reconciliation.
- `[ ]` User acceptance testing and staff training.
- `[ ]` Backup/restore drill, monitoring and security review.

### M5 — Professional features

- `[ ]` Guardian portal with explicitly linked children only.
- `[ ]` Assessments and development records.
- `[~]` Announcements, reminders and notifications: communication centre, email delivery history, upcoming payment/renewal lists and idempotent automation are implemented; guardian in-app preferences await the guardian portal.
- `[x]` Referrals and reward administration with tenant-configurable qualification rules, manual attribution, idempotent evaluation, and pending/approved/paid reward ledger.
- `[~]` Online payment refinements, renewals and automated reminders: renewal/payment automation is implemented; secure guardian invoice payment links remain dependent on the guardian portal and merchant settlement decision.

### M6 — Enterprise features

- `[x]` Tenant-safe inventory, merchandise and orders with product variants, branch/central stock, immutable movements, transactional reservations, public cart/checkout, offline sales, payment/delivery tracking and versioned storefront APIs.
- `[x]` Scholarships and sponsorships with sponsor commitments, scoped scholarship schemes, beneficiary awards, invoice responsibility allocation, verified sponsor contributions and separate concession/sponsor receivable reporting.
- `[ ]` Community-impact records.
- `[ ]` Advanced reporting and analytics.

## Recently completed

- Tenant-safe scholarship and sponsorship administration with full/percentage/fixed coverage, cycle/term/programme/branch scopes, beneficiary approvals, duplicate-safe invoice application, academy concessions, sponsor receivables, verified contribution payments and reversal-aware ledgers.
- Optional fixed-cycle terms with automatic default-term normalization, term/cohort fee scopes, cohort-aware compulsory invoicing and a term-tabbed fee workspace; rolling cycles remain directly cycle-scoped without terms.
- Academy commerce workspace with product categories, tags, variants, server-priced orders, central/branch stock, immutable adjustment/reservation/sale movements, partial/full order payments, independent fulfilment status, offline counter sales, public academy cart/checkout and authenticated external storefront APIs.

- Academy branding with an audited editable academy name, theme colours, tenant logo and favicon.
- Academy-specific Student/Player/Customer terminology.
- Team invitation links and immediate administrator-created accounts.
- Super Admin academy impersonation/support mode without approval or expiry.
- Table/dialog UI conventions across branches, programmes, team and player directories.
- Complete applicant biodata preservation during conversion.
- Private guardian signatures and academy-defined custom fields.
- Searchable, paginated student and guardian directories.
- Programme detail/member tabs and branch filtering.
- Validated all-or-nothing CSV imports with downloadable error reports.
- Secure external applicant API; the former direct-player-creation API was removed.
- Fixed and rolling academy cycles with cycle-specific programme offerings.
- Academy-wide default programme fees with optional per-branch overrides and automatic fallback.
- Flexible rolling durations in days, weeks or months, including short periods such as two weeks.
- Optional application fees, itemized programme fees and default or student-specific installments.
- Full payment and installment choices can coexist for the same programme cycle, with student-specific payment plans before payment begins.
- Currency-symbol amounts, thousands separators and live application/programme totals across finance screens.
- Tenant bank-account settings with complete-set validation and audit history.
- Editable pre-enrollment applications while preserving issued invoices and enrolled-player history.
- Full application detail pages with explicit active-cycle selection and contextual application-fee capture.
- Tenant payment directory filtered by cycle, programme and method, preserving applicant-to-student traceability.
- Immutable invoices, verified offline payments and acceptance-triggered enrollment with outstanding player balances.
- Per-player historical cycle membership and outstanding-balance ledger.
- Tabbed player profiles for biodata, guardians, invoice actions and verified payments.
- Player-profile editing for biodata, programme and branch; consolidated invoice action menus; unpaid invoice cancellation; partial/full write-offs recorded as academy expenses.
- Cycle-wide compulsory or optional medical, insurance and other fee definitions for all or selected programmes, with automatic compulsory invoicing and duplicate-safe manual player booking.
- Explicit programme fee names displayed on cycle schedules and snapshotted onto invoices, with invoice purpose shown as supporting fee-type text.
- Cancelled invoices remain visible as non-collectible audit records, and guardian contacts can be edited directly from player profile cards.
- Duplicate-safe programme memberships: the same player/programme/branch/cycle combination is blocked while different programmes, branches and cycles remain supported.
- Tenant-scoped invoice register with current-cycle defaults, full-history access, cycle/programme/branch/fee/date/status filters, pagination and filter-aware billed, paid, owed and written-off summaries.
- Searchable Coaches/Instructors directory synchronized from team roles, with tenant-specific biodata, contact/address details, experience, biography and configurable coach custom fields.
- Academy-configurable coaching-staff terminology, consistently applied to navigation, directories, profile dialogs, team roles, invitations and notifications.
- Tenant-safe session scheduling with all/selected branch scope, multiple coach assignments, physical/virtual delivery and optional meeting links, plus list/calendar views, academy-timezone conversion, filters and coach-specific visibility.
- Mobile session attendance for eligible programme/branch rosters, with one-tap marking, notes, cancelled-session locking, assigned-coach access, unique attendance records and immutable correction logs.
- Tenant-scoped tabbed expense register with editable academy-defined categories, reusable vendor CRUD plus one-off payees, branch/cycle attribution, filter-aware summaries, audited manual entries, invoice write-off visibility and authorization-protected receipt attachments.
- Printable payment receipts and explicit partial/full reversals that preserve the original transaction, reverse allocations safely and reopen invoice/installment balances.
- Operational dashboard with active-player, enrollment, session, attendance, billed, outstanding, collected, reversal, expense, net-cash, aging and branch-comparison metrics.
- Authorization-protected, audit-logged invoice, payment, expense and attendance CSV exports.
- Finance Officer access to finance dashboards, registers, exports, receipts and reversals, while academy branding and unrelated administration remain restricted.
- Read-only, tenant-scoped academy audit-log directory for Owner/Admin and Super Admin support mode, with actor, action, affected-record and date filters plus immutable metadata details.
- Bootcademy product identity and tenant public-site foundation with collision-safe subdomains, wildcard-domain routing, academy colour/logo inheritance and an accordion-based section editor.
- Launch public-site theme with announcement marquee, responsive header/footer, hero slides or video, about/founder/programme/location/experience/event/news/stat/team/testimonial/alumni/gallery/affiliation/sponsor/FAQ sections and WhatsApp contact.
- Public programme directory/detail pages and a deliberately non-submitting application UI populated from active programmes, cycles, locations, fees, installment plans, applicant biodata and academy custom fields.
- Admin-managed public programme profiles with formatted outcomes, curriculum, audience, eligibility, demand and application guidance; structured tools, FAQs and testimonials; server-side HTML sanitization; and a fee-aware public detail layout.
- Private, tenant-scoped programme hero-image upload, replacement and removal with a responsive right-side public hero composition.
- Viewport-triggered statistics counters with reduced-motion support, plus enlarged academy branding and a compact public navigation that keeps four priority sections visible and places only explicitly enabled sections under an accessible Explore dropdown.

## Quality baseline

At the last completed feature, the full suite passed:

- 238 backend tests and 2,043 assertions.
- 82 frontend tests.
- TypeScript, formatting, linting and production build.

The repository-wide PHPStan check currently reports pre-existing finance/player typing errors outside the invoice-register implementation; the new invoice controller reports no static-analysis errors.

These totals will change as features and regression tests are added.
