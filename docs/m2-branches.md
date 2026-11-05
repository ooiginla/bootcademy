# M2 branch management

Academy branches are tenant-owned operational records. Every write resolves the active academy on the server and rejects a branch identifier belonging to another academy with a 404 response. Owner/Admin users and Platform Super Admins acting as owners can create and update branches; other active academy staff can view the branch directory.

Branches contain a required name and timezone plus optional code, address, country, and contact details. Deactivation preserves the record for future schedules, enrollments, finance, and reporting history. There is intentionally no destructive branch-delete workflow.

Operational management screens follow a list-first interaction pattern: create and edit forms open in dialogs so the user keeps the list context. Records with substantially larger or multi-section forms should use a dedicated page instead.

Branch quotas are not enforced yet because plan-specific branch caps remain a product decision. The schema and academy relationship support any number of branches without KCFA-specific assumptions.
