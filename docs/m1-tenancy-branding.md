# M1 tenancy and branding slice

## Delivered scope

- Generic academy creation during signup and onboarding for existing users.
- Shared-schema academy and membership records with one active academy resolved from server-verified membership.
- Required role values for owner/admin, finance, branch management, coaching/instruction, guardians and read-only audit.
- Two onboarding paths: expiring email invitation links, or immediate admin-created accounts with forced temporary-password replacement.
- Personal account creation, role assignment, acceptance, access suspension and self-lockout protection.
- Owner/admin academy management policy and cross-academy denial tests.
- Private per-academy logo upload and authorized logo delivery.
- Audited Owner/Admin colour controls for their own academy, plus a separate Platform Super Admin boundary for platform-wide theme administration.
- Academy theme tokens, logo, name and role in the responsive dashboard shell.
- Active academy logos replace the browser favicon, with the platform icon retained as fallback.
- Explicit CLI grant/revoke command for Platform Super Admin access.
- Configurable plan catalog, one current academy subscription, append-only subscription state events and centralized feature/limit entitlement checks.
- Unlimited Platform Support Mode with full owner authorization, prominent UI indication and audit records.
- Audited per-academy singular and plural member terminology, with Student/Students defaults and tenant-defined alternatives such as Player/Players, Customer/Customers or Child/Children.

## Security notes

The active academy ID stored in the session is only a preference. Every request resolves it through the authenticated user's active memberships and falls back to an authorized academy when the session is stale or tampered with. Route model IDs are never accepted as proof of membership.

Logo files are stored on the private `local` disk under generated academy-scoped paths. Only the active academy logo route serves a file, with private caching and MIME-sniffing protection. Theme and logo changes create audit records.

Terminology is presentation-only. The database schema, model names, route identifiers and versioned API payload keys continue to use `player` consistently. Labels are tenant-scoped, length-limited and reject markup; normal React output escaping remains in place.

Platform Super Admin status does not confer academy membership. Platform routes expose academy identity and membership counts needed for theme administration, not academy operational data.

Plan prices are stored as integer minor units. Essential, Professional and Enterprise are idempotent seed data rather than constants. Administrative assignment does not invoke a payment provider. Suspended or missing subscriptions grant no plan-governed entitlements. Plan changes retain subscription and audit history; future downgrade enforcement must block prohibited new usage without deleting existing tenant records.

Support Mode never impersonates an academy user. The Platform Super Admin's own user ID remains the actor on every audit record. Support authorization is resolved per request, grants the same academy management rights as an Owner/Admin, has no time limit, and remains active until explicitly exited or the authenticated session ends.

Team invitations store only a SHA-256 hash of a random token. The raw token exists only in the queued email link, expires after the configured interval and becomes unusable after acceptance. A new recipient supplies personal details and becomes a verified user plus active academy member in one transaction. An existing user must authenticate as the exact invited address. Acceptance switches the active academy and writes an audit record. An academy admin cannot change their own access, and cross-academy membership identifiers return 404.

For immediate creation, the admin supplies the person's identity, role and a temporary password. User creation plus active membership is transactional and audited. The account is blocked from academy application routes until the person replaces the temporary password; the normal email-verification policy still applies independently.

## Deferred by milestone order

Staff invitations and fine-grained permissions beyond academy-wide owner/admin control, branches, programmes, learners, sessions and financial metrics remain in M2/M3. Dashboard cards deliberately show empty states until those domains exist.
