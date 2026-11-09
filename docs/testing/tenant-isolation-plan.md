# Tenant isolation red/green test plan

This plan must be executed before M1 is declared complete. Each scenario is first added as a failing test (red), followed by the smallest policy/query implementation that makes it pass (green). Tests use at least two academies, users with overlapping roles and direct identifiers from the other tenant.

## Membership and active academy

- Guest cannot enter `/app/*` or `/platform/*`.
- User without an active membership cannot select or access an academy.
- Multi-academy user can switch only to an active membership they own.
- Deactivated membership loses access immediately.
- A request-provided `academy_id` never creates authorization.

## Academy records and branding

- Academy admin can read and update allowed settings for their own academy.
- Academy admin cannot read, update, upload a logo for or download a private asset from another academy, including by direct ID.
- Academy Owner/Admin can change public-site theme colours for their own academy but cannot change another academy's colours.
- Platform Super Admin can update a selected academy theme through `/platform/*`; a normal user cannot.
- Invalid colours, inaccessible contrast and invalid logo type/size are rejected without changing existing branding.
- Theme and logo shared with Inertia always belong to the active academy; cache keys cannot leak another academy's theme.
- Tenant colours affect only public academy sites; the authenticated workspace retains the standard Bootcademy theme.

## Roles and scopes

- Academy Owner/Admin can perform every academy-level action in their academy.
- Finance, Branch Manager and Coach permissions are restricted to their capabilities and assigned branches.
- Guardian sees only explicitly linked learners.
- Read-Only Auditor cannot mutate data.
- No academy role grants platform control-plane access.

## Cross-boundary infrastructure

- Queued jobs restore and verify academy context before querying.
- Exports contain only authorized academy/branch rows.
- Notifications resolve recipients inside the same academy.
- Cache entries include academy and relevant branch/user scope.
- Audit entries capture academy, actor, action and target without sensitive payloads.

## Dashboard

- Date and branch filters are validated and applied server-side.
- Every aggregate is computed from the active academy only.
- Branch-restricted users cannot infer other branch counts or money totals.
- Empty, loading and unavailable states do not fabricate zero-value financial data.
