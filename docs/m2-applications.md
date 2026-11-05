# M2 applications and prospect conversion

Academy Owner/Admin users manage prospects at `/app/applications`. Applications are tenant-owned, searchable, status-filterable and paginated.

The implemented lifecycle is `submitted -> under review -> accepted -> enrolled`, with rejection available before enrolment. State changes use row locking, validation and audit records. Enrolment atomically creates the academy-owned profile, optional guardian relationship and programme enrolment, then links the application to the converted profile.

Applications retain the complete standard profile data used by the main student/player record: internal ID, names, date of birth, gender, phone, WhatsApp, address, city, state and country. Guardian names, email, phone, WhatsApp, relationship and private signature are also retained. Every academy-defined player custom field is stored with the application and transferred during conversion. The private signature is copied to the guardian profile while the source application file remains intact for the admissions record.

Programme, branch and custom-field inputs are validated against the active academy. Conversion checks for a matching name plus date of birth, or name plus guardian phone where no birth date exists. A possible duplicate blocks conversion for manual review rather than silently creating another profile.

Rejected and enrolled applications are terminal. Application access is limited to Owner/Admin and Platform Support Mode until a separately authorized public application endpoint and guardian-consent workflow are delivered.
