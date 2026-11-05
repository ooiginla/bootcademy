# ADR 002: Global identity and academy-scoped authorization

- Status: Accepted for implementation in M1
- Date: 2026-10-28

## Decision

Users are global identities and may belong to multiple academies through memberships. Roles and permissions are assigned in academy context, and branch restrictions are additional scope rather than separate identities.

The required roles are Platform Super Admin, Academy Owner/Admin, Finance Officer, Branch Manager, Coach, Guardian and optional Read-Only Auditor. An Academy Owner/Admin receives every academy-level capability, but those capabilities never cross into another academy or the platform control plane. Guardians are authorized through explicit learner links.

Laravel session authentication, verified email, policies, middleware and form requests provide the server-side boundary. UI visibility is convenience only and never substitutes for authorization.

## Consequences

Switching academies changes active context, not identity. Permission caches must include academy context. Membership deactivation immediately removes tenant access while preserving audit history.
