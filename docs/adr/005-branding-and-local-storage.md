# ADR 005: Accessible tenant branding and local private storage

- Status: Accepted for implementation in M1
- Date: 2026-10-28

## Decision

Each academy Owner/Admin may upload its own logo and set the academy's primary and secondary colours. A Platform Super Admin may also manage colours across academies from the platform control plane. Branding is academy configuration, never hardcoded application logic.

Theme values are validated server-side against a strict colour format. The server chooses the higher-contrast dark or light foreground for each supplied colour. Tenant branding may change primary, secondary, focus and navigation tokens but cannot override destructive, warning or security-state semantics. The active academy's validated tokens are applied as CSS custom properties so responsive components continue to work in both light and dark appearance modes.

Initial files use Laravel's local private disk per the founder's current hosting constraint. Logo access is mediated by an authorized/cacheable application response; learner documents and media remain private and require short-lived authorized access. Storage paths include academy context and generated names. The domain uses Laravel's filesystem abstraction so an S3-compatible disk can be adopted later without changing business logic.

## Consequences

Academy admins can upload/replace their academy logo and change their own theme colours. Platform Super Admins can change any academy's theme without receiving access to its operational records. Every branding update is audited. A missing or invalid asset falls back to the neutral platform mark and default accessible theme.
