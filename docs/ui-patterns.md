# Application UI patterns

## List, create and edit

Resource management screens are list-first. Keep the collection visible as the primary page and place the main **Add** action in the page header.

- Use a dialog for create and edit forms that are short enough to complete comfortably without navigation.
- Keep validation errors in the dialog and close it only after a successful submission.
- Display useful record details and status directly in the list or card view.
- Use a dedicated page when a record has a large form, multiple sections, dependent workflows, or information that benefits from its own URL.
- On mobile, dialogs must remain scrollable and fit within the viewport.

Applied examples: Branches and Team and Roles both keep their lists visible while create, invite, and edit actions use focused dialogs with icon-labelled action buttons.

List pages provide a name filter near the page header. Small tenant collections filter immediately in the browser; paginated platform collections filter on the server and preserve the search query across pages. User roles appear as rounded, role-coloured badges so responsibilities can be scanned quickly.
