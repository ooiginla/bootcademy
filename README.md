# Bootcademy

Multi-tenant operations and public websites for sports, education, language, music, technology and other subscription-based academies.

See [local setup](#local-setup) and [public academy websites](docs/public-websites.md).

A commercial, independently branded management platform for subscription-based academies. The product is designed for sports, educational, language and skills academies; no tenant or activity type is built into reusable application logic.

## Current milestone

M0–M3 are complete: foundation, tenant identity and authorization, academy operations, applications, players/guardians, cycles, offline finance, sessions, attendance, expenses, reporting and audit history. The Bootcademy public-site foundation now adds unique academy subdomains, an editable tenant-branded theme, programme pages and a non-submitting application preview. M4 pilot readiness is next; see `IMPLEMENTATION_STATUS.md` for the live queue.

Team invitations accept any valid email address. The recipient receives a private, expiring, one-time link to create a personal account and join the academy in the assigned role. Existing users must sign in as the invited address before accepting, preventing a link from taking over or attaching the wrong account.

Alternatively, an academy Owner/Admin can create an active team account immediately with the person's name, email, role and a temporary password. The new user must replace that admin-known password after first login before accessing academy data. Admin-created email addresses remain subject to the platform's configured email-verification policy.

Successful application forms and workspace actions return consistent, closable toast notifications at the top of the interface. Redirect-based actions and Inertia settings flashes share the same global feedback component.

The seeded plan catalog uses the approved configurable defaults and integer minor-unit amounts. Platform Super Admins can edit catalog values and administratively assign or suspend an academy subscription. These controls do not charge a payment method, create a fabricated trial or mix SaaS billing with learner-fee finance. Plan changes preserve an append-only event history and never delete tenant data.

Platform support uses Support Mode rather than identity impersonation. A Platform Super Admin can enter any academy directly, retains their own identity, receives full academy Owner/Admin authorization without a time limit, sees a persistent banner and can exit at any time. Entry, exit and support actions are auditable, and Support Mode never creates a hidden academy membership.

M2 branch management provides tenant-scoped academy locations with optional address/contact details, per-branch timezone, active/inactive lifecycle, audit records, and cross-tenant authorization checks. Branch records are deactivated rather than deleted so future operational and financial history remains intact.

## Stack

- Laravel 13, PHP 8.4
- Inertia 3, React 19, strict TypeScript on Node 22.18+
- Tailwind CSS 4 and accessible UI primitives
- MySQL 8 for application data
- Redis for queues and cache
- Local private storage initially; the storage interface remains portable

Exact dependency versions are recorded in `composer.lock` and `package-lock.json`.

## Local setup

1. Copy `.env.example` to `.env` and provide local-only database credentials.
2. Run `composer install` and `npm install`.
3. Run `php artisan key:generate`.
4. Create the configured MySQL database, then run `php artisan migrate`.
5. Run `composer run dev`.

An existing local user without an academy is sent to academy onboarding after login. New registrations create the academy and its owner/admin membership in one transaction.

To grant the separate Platform Super Admin role to an existing local user:

```text
php artisan platform:super-admin your-email@example.com
```

Revoke it with `php artisan platform:super-admin your-email@example.com --revoke`. This role can manage academy colour themes from **Platform academies**; it does not silently grant membership in an academy or access to its operational records.

Local development can bypass email verification with `AUTH_REQUIRE_EMAIL_VERIFICATION=false`. Keep verification enabled in production.

The safe local `MAIL_MAILER=array` does not deliver messages or persist private links. Configure an SMTP provider in `.env` to deliver invitation email, or use a local mail catcher during development. Never commit SMTP credentials. See `docs/local-email.md`.

Never commit `.env`, provider secrets, production credentials or real learner data. Automated tests use an in-memory SQLite database and do not touch the configured local MySQL database.

## Quality checks

```text
composer ci:check
```

This runs frontend formatting/lint checks, TypeScript checks, PHP formatting, static analysis and the automated PHP test suite. Frontend unit tests are also run by `npm run test`.

## Product and architecture

- Product requirements: `SPORTS_ACADEMY_SAAS_PRD.md`
- Repository rules: `AGENTS.md`
- Architecture decisions: `docs/adr/`
- Tenant isolation red/green plan: `docs/testing/tenant-isolation-plan.md`
- Branding and local storage decision: `docs/adr/005-branding-and-local-storage.md`
