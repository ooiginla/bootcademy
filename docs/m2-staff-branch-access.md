# M2 staff branch access

Academy staff memberships and email invitations can be assigned to one or more branches. Owner/Admin membership is always academy-wide; other staff roles receive only explicitly selected branches. An empty selection therefore means no branch-scoped operational access, while still allowing the account to exist for later assignment.

Branch selections are validated against the active academy and protected by composite database foreign keys, preventing a membership or invitation from referencing another tenant's branch. Invitation acceptance transfers the exact branch assignments to the resulting membership in the same transaction.

The Team and Roles list displays branch access as rounded tags. Invite, immediate account creation, and edit dialogs all support branch selection. Role, status, and branch changes are included in audit metadata. Future player, schedule, attendance, finance, and reporting policies should call the membership branch-access boundary before exposing branch-owned data.
