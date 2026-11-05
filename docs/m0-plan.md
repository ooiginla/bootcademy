# M0 foundation plan

## Scope

1. Scaffold Laravel 13 with React 19, strict TypeScript, Inertia 3, Tailwind 4 and accessible UI primitives.
2. Keep Laravel's session authentication, email verification, password reset, two-factor and account settings flows.
3. Provide a responsive, academy-neutral landing page and authenticated dashboard shell with honest empty states.
4. Configure MySQL application defaults, Redis queues/cache and private local file storage without committing credentials.
5. Lock dependencies and gate changes with formatting, static analysis, frontend unit tests, PHP feature tests, builds and dependency audits in CI.
6. Record tenancy, identity, money, billing, branding and storage decisions plus the M1 tenant-isolation red/green plan.

## M0 exit gate

- Dependency lockfiles are present.
- Authentication and dashboard route tests pass.
- Dashboard component tests pass.
- PHP formatting/static analysis/tests and frontend lint/typecheck/tests/build pass in the pinned runtime.
- CI uses PHP 8.4 and Node 22.18.
- No tenant tables or production side effects are introduced.
- Founder approval is requested before M1 begins.

## Known risks and controls

- **Tenant leakage:** no academy data is introduced in M0; M1 starts from the documented cross-tenant red tests.
- **Generic product language:** reusable UI uses learner, programme and staff language; tenant-specific labels will be configuration.
- **Brand accessibility:** colours are not accepted directly from the browser; M1 will validate and derive accessible tokens server-side.
- **Local storage durability:** backups are an operational requirement and the filesystem abstraction preserves a future S3-compatible migration path.
- **Undecided business policy:** plan limits, payment settlement, tax, trial and quota behavior remain unimplemented until approved.
