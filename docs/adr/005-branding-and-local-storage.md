# ADR 005: Accessible tenant branding and local private storage

- Status: Accepted for implementation in M1
- Date: 2026-10-28

## Decision

Each academy Owner/Admin may upload its own logo and set the academy's primary and secondary colours. A Platform Super Admin may also manage colours across academies from the platform control plane. Branding is academy configuration, never hardcoded application logic.

Theme values are validated server-side against a strict colour format. The server chooses the higher-contrast dark or light foreground for each supplied colour. Tenant branding is applied to that academy's public website, including its primary, secondary and accessible foreground tokens. The authenticated administration workspace deliberately retains Bootcademy's standard deep-navy, orange, royal-blue and teal theme so controls, security states and support guidance remain consistent across tenants. The active academy logo may still identify the workspace and replace its favicon.

Initial files use Laravel's local private disk per the founder's current hosting constraint. Logo access is mediated by an authorized/cacheable application response; learner documents and media remain private and require short-lived authorized access. Storage paths include academy context and generated names. The domain uses Laravel's filesystem abstraction so an S3-compatible disk can be adopted later without changing business logic.

## Consequences

Academy admins can upload/replace their academy logo and change their public website colours. Platform Super Admins can change any academy's public website theme without receiving access to its operational records. Every branding update is audited. A missing or invalid asset falls back to the neutral platform mark and default accessible public-site theme. Tenant colours never override the standard Bootcademy administration theme.
