# M2 programme management

Programmes are generic tenant-owned academy offerings. They are not tied to a particular sport or teaching model and can be assigned to any subset of the academy's branches.

Owner/Admin users and Platform Super Admins acting as owners can create, edit, assign branches, and deactivate programmes. Other active academy staff can view the programme directory. Every mutation validates the active academy, rejects cross-tenant programme and branch identifiers, and writes an audit record.

The interface follows the list-first convention: Add and Edit open in responsive dialogs, validation stays in the active dialog, and the programme directory uses a compact table. Selecting a programme opens a dedicated page with Basic details and Members tabs.

The Members tab reads programme enrolments, paginates them in groups of 20, and supports an All branches or individual branch filter. Owner/Admin and Platform Support Mode can see all academy branches. Other staff only receive member rows and filter options for branches assigned to their active academy membership; attempts to request another branch are rejected.

Deactivation preserves enrollment, scheduling, and finance history.

Programme fees are deliberately deferred to configurable fee schedules in the finance milestone. No pricing or branch-cap business rules are hardcoded here.
