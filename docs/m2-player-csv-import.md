# M2 player CSV import

Academy Owner/Admin users can import existing student/player records from `/app/players/import`. The terminology shown in the interface follows the academy configuration, while the persisted domain remains `players`.

## Workflow

1. Download the academy-specific template. It includes standard profile, guardian, programme/enrollment and active custom-field columns.
2. Upload a CSV of at most 2 MB and 500 data rows. The file is stored on the private local disk and scoped to the active academy and uploader.
3. Review every row before any profile is created. The preview validates formats, required custom fields, active programme/branch names and duplicate identifiers or biodata.
4. Correct the source CSV and upload it again when errors are present. A CSV error report is available for bulk correction.
5. Confirm a clean preview. The server revalidates the private file and imports all profiles, guardians, custom values and initial enrollments in one database transaction.

There is no partial-success mode: any validation or concurrent database conflict prevents or rolls back the entire import. Successful imports delete the staged source file. Cancelled imports are deleted immediately, preview tokens expire after 24 hours, and the hourly cleanup removes expired staged files.

Existing guardians are matched only inside the active academy by phone or email. Cross-tenant programme, branch, guardian and player data is never accepted. CSV error-report cells are escaped against spreadsheet-formula injection.

The audit log contains the normal per-profile, guardian and enrollment actions plus an aggregate `academy.players.imported` event.
