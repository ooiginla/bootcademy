# ADR 001: Shared-schema academy tenancy

- Status: Accepted for implementation in M1
- Date: 2026-10-28

## Decision

Use one application and a shared database/schema. Every academy-owned root record carries an immutable `academy_id` foreign key and appropriate composite indexes. The active academy is resolved only from an authenticated, active membership. Request-supplied academy identifiers may select among authorized memberships but never grant access.

Academy-owned queries must be scoped in application services and authorized by policies. Nested objects must verify the same academy ownership. Queue payloads, cache keys, files, exports, notifications and audit logs include explicit academy context. Platform-global tables are documented as exceptions.

Platform super-admin access uses a separate `/platform/*` boundary and explicit platform authorization. Tenant impersonation is not part of this decision and must not be added without a separately approved, time-limited and audited design.

## Consequences

This is operationally simpler than database-per-tenant while still supporting strict isolation. It requires systematic cross-tenant tests and careful review of every new query, job and cache key.
