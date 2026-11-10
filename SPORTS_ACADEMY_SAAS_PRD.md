# Bootcademy — Product Requirements & Technical Architecture

**Version:** 1.0  
**Date:** 2026-10-28  
**Status:** Proposed blueprint; decisions marked _TBD_ require founder approval  
**First pilot tenant:** King's Court Football Academy (KCFA), Lagos, Nigeria  
**Product name / domain:** Bootcademy / `bootcademy.com` — do not use KCFA branding as the platform identity

## 1. Executive summary

Build a commercial, multi-tenant SaaS for subscription-based academies to administer branches, programmes, staff, learners, guardians, sessions, attendance, fees, expenses, communications, referrals, development, inventory, scholarships, sponsorships, and performance reporting. Sports, educational, language and skills academies are first-class use cases. Domain labels may adapt to the academy type (for example player/student/learner and coach/teacher/instructor) without changing tenant-safe core logic. KCFA is the launch customer, not the product owner.

**North-star outcome:** An academy can enroll a player, obtain guardian consent, assign a programme and branch, bill the guardian, record attendance, evaluate development, and view financial/operational performance from a single secure system.

### Goals

- Reduce spreadsheet/WhatsApp fragmentation and manual reconciliation.
- Increase visibility into outstanding player fees and renewals.
- Enable coaches to manage attendance and assessments on mobile browsers.
- Provide parents with safe, scoped access to their children's records.
- Support predictable recurring SaaS subscriptions with configurable entitlements.
- Ensure strict academy-to-academy isolation and protection of children's data.

### Non-goals for MVP

- Native iOS/Android apps; web app must be responsive/PWA-ready.
- AI-generated player scouting scores or automated child profiling.
- Franchise management, full e-commerce, advanced payroll, or complex accounting.
- Microservices or separate frontend/backend deployments.
- Rebuilding KCFA's public website.

## 2. Personas and roles

| Persona                 | Scope                          | Core needs                                                      |
| ----------------------- | ------------------------------ | --------------------------------------------------------------- |
| Platform super admin    | Global, privileged             | Tenants, plans, entitlements, billing, support, platform health |
| Academy owner/admin     | One or more assigned academies | All academy operations and reports                              |
| Academy finance officer | Academy, permitted branches    | Invoices, payment reconciliation, expenses, exports             |
| Branch manager          | Assigned branches              | Enrollments, schedules, attendance, staff, local reports        |
| Coach                   | Assigned groups/sessions       | Rosters, attendance, evaluations, training notes                |
| Parent/guardian         | Explicitly linked children     | Fees, receipts, attendance, progress, announcements             |
| Read-only auditor       | Explicitly granted scope       | Reports and audit trails, no mutation                           |

A single user may belong to multiple academies, with separate role assignments per academy. One guardian may be linked to multiple players. All sensitive actions require server-side authorization.

## 3. Commercial plans — proposed, configurable

These are **draft launch prices**, not immutable business rules. Plans and limits must be stored in the database and editable by platform admins.

| Plan         | Monthly | Quarterly | Annual (10% discount) | Active player cap |
| ------------ | ------: | --------: | --------------------: | ----------------: |
| Essential    | ₦25,000 |   ₦75,000 |              ₦270,000 |               100 |
| Professional | ₦45,000 |  ₦135,000 |              ₦486,000 |               300 |
| Enterprise   | ₦85,000 |  ₦255,000 |              ₦918,000 |             1,000 |

- Existing KCFA operations cover three branches; plan-specific branch caps **TBD**. Never hardcode the three KCFA branches as a global limit.
- Define `active_player` precisely (recommended: active enrolment in a current academy membership period, counted once per academy).
- Implement plan entitlements and quotas centrally, including feature flags, player/branch/staff caps, grace periods, upgrades/downgrades and billing status.
- Downgrade behavior: warn before exceeding limits; preserve data; prevent prohibited _new_ usage rather than deleting records.
- External costs (Paystack fees, SMS/WhatsApp, premium storage, custom work) are excluded unless contractually included.
- Subscription billing from **academy to SaaS vendor** must be financially and logically separate from **guardian payments to academy**. Never route academy player-fee money into the SaaS vendor's account by default.
- Payment recipient accounts, settlement rules, chargebacks, tax treatment, and provider onboarding are TBD and require legal/payment review.

## 4. Functional modules and release scope

**Priority legend:** P0 = MVP, P1 = next release, P2 = advanced/enterprise.

### 4.1 SaaS control plane (P0)

- Tenant signup/invitation, verification, academy profile, timezone, currency and branding.
- Plan catalog, subscriptions, trial/grace periods, renewal status, entitlement enforcement.
- Tenant suspension/reactivation and offboarding with data export.
- Global operational metrics without exposing individual tenant records unnecessarily.
- Support impersonation only if explicitly approved, time-limited, consented, logged and prominently indicated; otherwise do not implement.

### 4.2 Academy and branch management (P0)

- Academy details, logo, contact information, academic/training cycles, optional fixed-cycle terms and policies. Rolling cycles are managed directly without terms.
- Branches, venues, age bands, programmes, sessions, schedules, capacities, coach assignment.
- Branch-specific dashboards and access restrictions.
- Configurable programmes such as football, athletics, fitness, camps; no assumption that every programme is offered at every branch.

### 4.3 Player, guardian and enrollment management (P0)

- Player profile, date of birth, preferred programme, academy/branch, enrollment status, optional photo, internal identifier.
- Guardian contacts, relationships, emergency contact, authorized pickup contacts (optional), multiple linked children.
- Consent records with version, timestamp, purpose, withdrawal and provenance.
- Application -> review -> accepted/rejected -> enrolled -> paused/withdrawn/graduated lifecycle.
- Import existing player data via validated CSV with preview, duplicate detection and rollback/error report.
- Avoid collecting sensitive health information unless genuinely needed and governed by additional safeguards.

### 4.4 Staff and permissions (P0)

- Invite and deactivate staff; academy/branch scoped role assignment.
- Coaches assigned to sessions and groups; view only authorized rosters.
- Least-privilege policies, audited permission changes, session management.

### 4.5 Attendance (P0)

- Session roster, present/absent/late/excused status, optional notes, bulk check-in.
- Session scheduling must provide both list and calendar tabs. The calendar previews when sessions occur in the academy timezone, supports branch/programme/coach filters, and opens the selected session without exposing another tenant's schedule.
- Prevent duplicate attendance for a player/session; preserve change history.
- Coach-friendly mobile interface, attendance by player/branch/date/programme.
- QR-based check-in optional P1, with anti-replay and privacy controls.

### 4.6 Academy fees and finance (P0)

- Configurable fee schedules by programme, cycle, optional fixed-cycle term, age band, academy and effective date. Training-cycle structure must remain separate from SaaS subscription billing periods.
- Invoices, configurable payment-plan totals and repayment schedules, discounts, scholarships/waivers, payments, receipts, adjustments, outstanding balances and renewals. Each plan must expose its fee breakdown and its repayments must equal its contracted total.
- Manual payment recording with verification status and audit trail.
- Expense categories, branch attribution, supporting attachments and basic cash summaries.
- Do not treat a payment webhook as trusted without signature verification and idempotency checks.
- Refunds and reversals must be explicit transactions, not deletion of original financial records.
- Basic reports: collected vs billed, aging, fees due, expense summaries, branch comparisons.
- No claim of full accounting compliance or audited profit without corresponding accounting controls.

### 4.7 Dashboard and reporting (P0)

- Active players, new enrollments, attendance, outstanding fees, collected fees, expenses, branch-level metrics.
- Date range and branch filters enforced on server.
- CSV exports respecting roles and tenant boundaries.
- Consistent definitions for revenue, enrollment and active players.

### 4.8 Parent portal and notifications (P1)

- Guardian sign-in and linked-child switcher.
- Fees, receipts, upcoming sessions, attendance, published coach feedback.
- Announcements, opt-in preferences, email notifications, renewal reminders.
- SMS/WhatsApp provider integrations behind adapters; usage metered and billed separately as appropriate.
- Parent cannot see another family's information or unpublished coach notes.

### 4.9 Coaching and player development (P1)

- Training plans, drills, skill rubrics configurable by sport and age group.
- Assessments, coach remarks, development goals, termly progress reports.
- Versioned criteria and published/unpublished assessment states.
- Avoid automated rankings or unsupported predictions about children.

### 4.10 Referrals, camps and events (P1)

- Configurable referral rules, attribution, qualifying events, pending/approved/paid reward ledger.
- For KCFA, default referral reward is **10%**, subject to client confirmation of eligible base and payout rules.
- Camp/event registration, capacity, participant rosters and fees.

### 4.11 Inventory, commerce and equipment (P2)

- Products, variants/sizes, stock locations, purchase and adjustment records, order lifecycle, fulfillment.
- Optional integration to academy's public store; prevent overselling via transactional stock updates.
- Equipment assignment and returns.

### 4.12 Sponsors, scholarships and community impact (P2)

- Sponsor records, commitments, sponsorship agreements, benefits and renewals.
- Scholarship allocations, approved beneficiaries, disbursement and reporting.
- Community initiatives, volunteer activity, expenses and evidence/media with consent.
- Do not fabricate impact metrics; distinguish reported from verified data.

### 4.13 Website CMS, advanced analytics, franchise tools (P2)

- Opt-in public website content integration, news/events/gallery and controlled publishing.
- Retention, fee collection trends, attendance patterns, budgets, forecast assumptions.
- Multi-location expansion/franchise structures only after real customer requirements are validated.

## 5. KCFA pilot configuration (seed/configuration, not platform assumptions)

- Academy: King's Court Football Academy; founded 2024.
- Three Lagos branches: Oniwaya (Saturday 8–10 AM, 10 AM–12 PM), Gbagada (Saturday 1–3 PM), Alimosho (Sunday 1–3 PM).
- Age bands: 2–5, 6–10, 11–17.
- Reported annual academy fee: ₦150,000; configure as effective-dated fee, not a universal platform constant.
- Reported referral incentive: 10%; define qualification/payout contract before automation.
- Public website: https://kingscourtfootballacademy.com; integrate registration submissions through a secured API or signed webhook with validation, deduplication and consent.
- Reported monthly reach of 10,000+ is marketing context, not an operational system metric.
- Do not import historical data, logos, photos, children’s images, or private details without authorization.

## 6. Technical architecture

### Stack

- **Backend:** Laravel on supported PHP 8.4, using supported stable versions at implementation time.
- **Web frontend:** React + TypeScript + Inertia.js in the Laravel application.
- **UI:** Tailwind CSS + accessible component primitives (e.g., shadcn/ui), responsive design.
- **Database:** MySQL 8.x with migrations and indexes.
- **Queue/cache:** Redis; Laravel queued jobs, retries and failed-job monitoring.
- **Object storage:** Laravel filesystem abstraction using private local storage initially; preserve an upgrade path to S3-compatible storage and use authorized, short-lived downloads.
- **Payments:** Paystack through a provider abstraction; verified callbacks and reconciliation.
- **Deployment:** Ubuntu LTS, Nginx, PHP-FPM, HTTPS, automated backups and CI/CD.
- **Testing:** PHPUnit/Pest, feature/integration tests, React component tests, end-to-end smoke tests.

### Architectural approach

- Modular monolith with explicit domain boundaries: `Platform`, `Identity`, `Academies`, `Players`, `Training`, `Attendance`, `Finance`, `Engagement`, `Commerce`, `Impact`, `Reporting`.
- Thin controllers; application services/actions for business operations; policies for authorization; jobs for asynchronous tasks.
- Laravel session auth for Inertia web; dedicated versioned API and token-based auth only for approved external/mobile clients.
- No business logic in React components. No direct browser access to payment secrets.
- Database transactions for critical operations; unique constraints and idempotency keys for external events.

### Multi-tenancy — mandatory design

- Shared database, shared schema initially; every tenant-owned record has `academy_id` (or a justified ownership path) with appropriate composite indexes.
- Resolve active academy from authenticated membership, never from an untrusted request field alone.
- Scope reads/writes at service/query and policy layers; validate cross-entity ownership (e.g., invoice's player belongs to same academy).
- Prevent tenant leakage in queued jobs, caches, file paths, exports, events, logs, search and notifications.
- Platform-global tables must be explicitly documented; cross-tenant admin privileges require audited authorization.
- Test cross-tenant access systematically (direct IDs, nested relations, bulk actions, exports, uploads, webhooks, background jobs).
- Consider a vetted tenancy library if it materially improves safety, but do not assume a library alone guarantees isolation.

### Security and child-data protection

- Data minimization, explicit guardian consent, configurable retention/deletion, privacy notices and access logs.
- Encrypt data in transit; encrypt sensitive data at rest where appropriate; never log credentials, tokens, medical notes or children's sensitive data.
- CSRF, validation, rate limiting, secure cookies, password reset, optional MFA for admins, least-privilege roles.
- Private media with signed access, strict MIME/size validation and malware scanning where practical.
- Backup restore drills, incident response procedures, audit trails and security update cadence.
- Review applicability of Nigeria Data Protection Act 2023 and relevant child-data obligations with qualified counsel before launch.

## 7. Initial logical data model

**Platform:** `academies`, `academy_settings`, `plans`, `plan_features`, `subscriptions`, `subscription_events`, `platform_invoices`, `platform_payments`, `feature_entitlements`.

**Identity:** `users`, `academy_memberships`, `roles`, `permissions`, `role_assignments`, `audit_logs`, `consents`.

**Operations:** `branches`, `programmes`, `age_groups`, `training_groups`, `training_sessions`, `coach_assignments`, `staff_profiles`.

**Players:** `players`, `guardians`, `player_guardians`, `applications`, `enrollments`, `player_documents`.

**Finance:** `fee_schedules`, `academy_invoices`, `academy_invoice_items`, `academy_payments`, `payment_allocations`, `payment_adjustments`, `expenses`, `expense_categories`.

**Attendance:** `attendance_records`, `attendance_change_logs`.

**Growth:** `assessments`, `assessment_criteria`, `training_plans`, `announcements`, `notification_deliveries`, `referrals`, `referral_rewards`, `events`, `event_registrations`.

**Enterprise:** `products`, `stock_movements`, `orders`, `order_items`, `sponsors`, `scholarships`, `community_projects`, `media_assets`.

### Modeling conventions

- Use UUID/ULID or consistent bigint identifiers; document choice before migrations.
- Foreign keys, unique constraints, soft deletion only where justified, timestamps and explicit status enums/state transitions.
- Monetary values in integer minor units with ISO currency codes; never use floating point for money.
- Preserve financial history with adjustments and reversals; avoid hard deletion.
- Distinguish academy SaaS invoices/payments from guardian-to-academy invoices/payments.
- Record effective dates for fee schedules and plan prices; historical invoices remain immutable.
- Use `academy_id` in unique indexes where tenant-specific uniqueness is intended.

## 8. Key workflows and acceptance criteria

### A. Academy onboarding

1. Platform admin or self-service flow creates academy.
2. Owner verifies email, accepts terms and sets academy profile.
3. Selects plan; subscription state established.
4. Creates branches/programmes and invites staff.
   **Accept:** No access to another academy; quota checks apply; onboarding can resume safely.

### B. Player enrollment

1. Guardian or staff submits application and required consent.
2. Academy admin reviews, deduplicates and approves.
3. Player linked to guardian, programme and branch; invoice generated if applicable.
   **Accept:** Duplicate submissions handled; no player visible to unauthorized guardian or academy.

### C. Attendance

1. Authorized staff reviews upcoming sessions in a list or calendar view and filters by branch, programme or coach.
2. Coach opens an assigned session roster from either view.
3. Marks attendance and saves.
4. System records actor/time and updates reporting.
   **Accept:** Calendar and list represent the same tenant-scoped schedule in the academy timezone; unauthorized coach denied; duplicate attendance prevented; correction audited.

### D. Fees and payment

1. Academy issues invoice based on applicable fee schedule.
2. Guardian pays online or finance records a verified offline payment.
3. Payment reconciled and receipt generated; balances updated.
   **Accept:** Duplicate webhooks do not duplicate payments; partial payments handled; refunds/adjustments traceable.

### E. SaaS billing

1. Academy subscribes to plan.
2. Vendor payment verified, subscription activated/renewed.
3. Grace period and entitlement policy applied for failed/expired payments.
   **Accept:** Vendor billing never mixed with academy fee collection; limits enforced server-side.

## 9. User experience and routes

- `/platform/*`: SaaS super-admin console (separate authorization boundary).
- `/app/*`: academy admin dashboard with academy switcher for multi-membership users.
- `/coach/*`: coach-focused responsive screens.
- `/parent/*`: guardian portal (P1).
- `/api/v1/*`: authenticated, rate-limited integrations; only implement endpoints actually needed.

Design mobile-first for coaches and parents, desktop-friendly for finance/admin. Use accessible forms, clear empty states, confirmations for destructive actions, loading/error states, keyboard navigation and meaningful audit messages. Tenant branding must never override platform security or accessibility.

## 10. Delivery milestones

| Milestone                  | Deliverables                                                                                   | Exit gate                                    |
| -------------------------- | ---------------------------------------------------------------------------------------------- | -------------------------------------------- |
| M0: Discovery & foundation | Finalize domain model, plan policy, tenancy strategy, repo, CI, coding standards, UI shell     | Approved ADRs and green CI                   |
| M1: Identity & tenancy     | Auth, memberships, roles/policies, academy switching, subscription entitlement service         | Cross-tenant attack tests pass               |
| M2: Academy operations     | Branches, programmes, staff, players, guardians, applications, enrollment/import               | End-to-end enrollment passes                 |
| M3: Attendance & finance   | Sessions, attendance, invoices, offline payments, expenses, dashboard                          | Reconciliation and audit tests pass          |
| M4: Pilot launch           | KCFA configuration, website registration integration, migration, training, monitoring, backups | UAT sign-off, restore drill, security review |
| M5: Professional           | Parent portal, assessments, announcements, reminders, referrals, online payments               | Portal/privacy/payment tests pass            |
| M6: Enterprise             | Inventory, commerce, sponsorships, scholarships, impact, advanced analytics                    | Feature-specific acceptance tests pass       |

**Timing:** Estimate after codebase inspection and detailed requirements; do not promise dates without scope and capacity estimates.

## 11. Quality and delivery standards

- Every feature: migration + model + authorization + validation + tests + UI + docs as applicable.
- All endpoints and jobs enforce tenant scope; no raw user-supplied academy IDs trusted.
- Unit tests for fee calculations, quotas, eligibility, and state transitions.
- Integration tests for payments, webhooks, imports and idempotency.
- End-to-end tests for signup, enrollment, attendance, payment and tenant isolation.
- CI gates: lint, typecheck, test suite, dependency audit and build.
- Observability: structured logs without sensitive data, error tracking, queue monitoring and uptime alerts.
- Operational runbooks: deploy, rollback, backup/restore, incident handling, tenant export/offboarding.

## 12. Key decisions to resolve before coding

1. Product brand, domain and target market (Nigeria first vs regional).
2. Whether football-only at launch with generic sport taxonomy (recommended) or fully multi-sport UI immediately.
3. Player cap counting rules and branch/staff limits for each plan.
4. Trial duration, suspension/grace period, refund policy and taxes.
5. Academy payment merchant model: direct academy Paystack account vs platform-mediated payments (requires compliance review).
6. Exact guardian consent/privacy and retention requirements.
7. Whether KCFA has legacy player/finance data to migrate, and its format/quality.
8. Notification provider and chargeback model.
9. Whether existing KCFA site supports secure server-to-server registration submission.
10. Hosting budget, backups, recovery targets and support SLAs.

## 13. Codex execution protocol

1. Read `AGENTS.md` and this PRD before changing code.
2. Inspect repository and produce an implementation plan with dependencies and risks.
3. Work on **one milestone or bounded feature at a time**; never attempt the entire product in one change.
4. Before coding, identify affected domain, tenant boundary, authorization, migrations, validation and tests.
5. Implement and run relevant tests/builds; report exact commands and results.
6. If a business rule is undecided, document assumption and request approval rather than inventing it.
7. Never deploy, modify production data, or initiate real payments without explicit approval.
8. Keep this PRD and architectural decision records updated as decisions are made.
