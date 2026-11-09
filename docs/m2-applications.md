# M2 applications and prospect conversion

Academy Owner/Admin users manage prospects at `/app/applications`. Applications are tenant-owned, searchable, status-filterable and paginated.

The implemented lifecycle is `submitted -> under review -> accepted -> enrolled`, with rejection available before enrolment. State changes use row locking, validation and audit records. Enrolment atomically creates the academy-owned profile, optional guardian relationship and programme enrolment, then links the application to the converted profile.

Applications retain the complete standard profile data used by the main student/player record: internal ID, names, date of birth, gender, phone, WhatsApp, address, city, state and country. Every academy-defined player custom field is stored with the application and transferred during conversion. Academies may enable or disable guardian/contact collection and configure its singular and plural display labels (for example, `Guardian` or `Next of Kin`). When enabled, names, email, phone, WhatsApp, relationship and private signature are retained; the private signature is copied to the guardian profile while the source application file remains intact for the admissions record.

Programme, branch and custom-field inputs are validated against the active academy. Conversion checks for a matching name plus date of birth, or name plus guardian phone where no birth date exists. A possible duplicate blocks conversion for manual review rather than silently creating another profile.

Rejected and enrolled applications are terminal. Staff access remains limited to authorized academy roles and Platform Support Mode.

The **Cohorts** tab controls intake alongside each delivery group. An Owner/Admin can mark a cohort as receiving applications and provide an optional inclusive opening and closing date. Applications are closed by default. Only enabled, currently open cohorts whose offering and cycle are active and whose capacity is not full appear on the public form and in the versioned API configuration response. Submissions send the cohort-backed `application_intake_id`. A branch-specific intake fixes the branch; an all-locations intake additionally requires the public applicant to select an active academy branch, stored as the preferred branch without changing the intake or its fee scope.

Published academy websites expose a throttled public submission endpoint with server-side validation, guardian consent capture, tenant-scoped intake checks and an opaque token for the applicant's private fee page. Closing an intake never removes its historical applications from the admin directory.

Admin-created applications also require a cohort. The server derives the programme offering, cycle, branch and fee scope from that cohort; it does not trust separately submitted relationship IDs. This ensures accepting an admin-entered application creates both its financial enrolment and cohort membership.
