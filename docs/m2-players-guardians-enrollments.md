# M2 player, guardian and enrollment foundation

Academy Owner/Admin users can manage learner profiles, guardian contacts and programme enrolments from `/app/players`. Platform Super Admins in Support Mode receive the same access.

## Rules implemented

- Players, guardians, links and enrolments are academy-owned at both application and database levels.
- A player may link to multiple guardians and may hold multiple simultaneous programme enrolments.
- A guardian can be created inline while creating a player, or an existing guardian can be linked with a relationship such as Mother, Father, Parent or Aunt.
- The first selected guardian is the primary and emergency contact. Authorized pickup is not implied.
- Programme enrolment has an independent lifecycle: active, paused, withdrawn or completed.
- Programme member lists support bulk completion and bulk re-enrollment. Completion keeps the original enrollment and financial history; it never deletes the record.
- A term registration belongs to an enrollment. Registering the same enrollment for another term does not create a duplicate programme enrollment, while moving to a different programme or cycle creates or reactivates the target enrollment and preserves the selected source enrollment as completed history.
- Player lifecycle is active, paused, withdrawn or graduated.
- Only Owner/Admin can access this privacy-sensitive directory until branch-scoped roster policies are implemented.
- Audit records contain changed field names and related record IDs, not copied child/contact values.

Guardian consent versions, applications, CSV import, private documents, fees and guardian portal authentication are intentionally separate follow-up features.

## Extended profiles and academy fields

Player profiles include optional middle name, gender, phone, WhatsApp, home address, city, state and country fields. Guardian records include WhatsApp and a private signature image. Signature responses require academy Owner/Admin access and use `private, no-store` caching.

Academy custom fields are typed as short text, long text, number, date, yes/no or select. Each field has a stable lowercase API key, active/required flags, ordering and a sensitive-data flag. Values are tenant constrained in the database and validated against the active academy definition before storage.

Sensitive fields should only be configured where the academy has a documented purpose and appropriate consent. Marking a field sensitive prepares it for stricter export and role handling; it does not replace consent or medical-data governance.

## Authenticated API payload

External websites no longer create player records directly. They use academy-scoped bearer credentials and `POST /api/v1/applications`; see `docs/m2-applicant-api.md`. Only reviewed applications or validated imports can create main profiles.

```json
{
    "first_name": "Amara",
    "middle_name": "Grace",
    "last_name": "Okafor",
    "status": "active",
    "guardian_ids": [12],
    "guardian_relationships": { "12": "Mother" },
    "custom_fields": {
        "playing_position": "Forward",
        "boot_size": 42,
        "has_asthma": false
    }
}
```

Unknown, inactive and incorrectly typed custom keys receive a validation response and are never silently stored.
