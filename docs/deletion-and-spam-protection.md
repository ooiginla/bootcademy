# Deletion and spam protection

## Soft deletes (academy records)

Academy users delete records with a soft delete: the row gets a `deleted_at` timestamp, disappears from lists, forms and validation, and stays in the database for history. Every delete writes an `academy.<type>.deleted` audit entry.

A record can only be deleted once nothing still belongs to it. The server checks this (`app/Academies/Deletion/DeletionBlockers.php`) inside a locked transaction (`SoftDeleteAcademyRecord`), and the delete dialog shows the reason when it refuses.

There are two kinds of blocker:

- **Removable:** records the user can delete or move first, such as a programme's fees or a branch's sessions.
- **Permanent:** history that is never deleted: invoices, payments, enrolments, attendance, store orders, stock movements, referrals and scholarship awards. A record with permanent history cannot be deleted. Deactivate it instead.

| Record         | Who can delete       | Blocked while it still has                                                                                                                                                                                                                 |
| -------------- | -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Application    | Owner/Admin          | invoices, payments, online payment attempts, referrals, a converted student                                                                                                                                                                |
| Branch         | Owner/Admin          | programmes offered there, sessions, applications, expenses, programme fees, cohorts, intakes, scholarship schemes, staff assigned, pending invitations, stock on hand · enrolments, invoices, store orders, stock movements, announcements |
| Programme      | Owner/Admin          | programme fees, cycle fees, sessions, applications, scholarship schemes · enrolments, invoices, announcements                                                                                                                              |
| Session        | Owner/Admin          | attendance records                                                                                                                                                                                                                         |
| Cycle          | Owner/Admin, Finance | programme fees, cycle fees, expenses, scholarship schemes, sponsor commitments · enrolments, invoices                                                                                                                                      |
| Cycle fee      | Owner/Admin, Finance | invoices                                                                                                                                                                                                                                   |
| Programme fee  | Owner/Admin, Finance | payment plans, applications, cohorts, intakes · enrolments, invoices                                                                                                                                                                       |
| Payment plan   | Owner/Admin, Finance | applications, invoices. Deleting the default plan makes the next active plan the default.                                                                                                                                                  |
| Expense        | Owner/Admin, Finance | a link to an invoice write-off or a referral payout                                                                                                                                                                                        |
| Student/player | Owner/Admin          | enrolments, cohort memberships, invoices, payments, attendance, scholarship awards, referrals                                                                                                                                              |
| Coach          | Owner/Admin          | assigned sessions. Removing a coach suspends and soft-deletes their membership and profile. Re-inviting them restores both.                                                                                                                |
| Store product  | Owner/Admin          | stock on hand · order lines                                                                                                                                                                                                                |

In the table, items after "·" are permanent.

Unique names, codes, SKUs and references include a generated `undeleted` column (1 for live rows, NULL for deleted rows), so a deleted record does not reserve its name. `Rule::exists` / `Rule::unique` on these tables use `withoutTrashed()`.

**Rollback:** migration `2026_11_17_000650` rolls back cleanly unless a live record now shares a name or code with a deleted one. Resolve those first.

## Permanent academy purge (Platform Super Admin)

Platform → Academies has a **Delete** button for each academy. It is meant for spam or test signups. The admin must type the academy's exact name to confirm. `PurgeAcademy` then:

1. Deletes every row in every table with an `academy_id` column, in foreign-key order worked out from the live schema, then the academy itself, all in one transaction.
2. Deletes user accounts that belonged only to that academy. It keeps platform admins, the person purging, and anyone who still belongs to, created, or invited people to another academy.
3. Removes the academy's private files (`academies/{id}` and `imports/{id}`).
4. Writes a `platform.academy.purged` audit entry (with no academy link) recording the name, per-table row counts and the number of users deleted.

This cannot be undone. Take a database backup before purging an academy that might hold real data.

## Google reCAPTCHA v3

The signup form (`/register`), public application forms and public store checkout send a reCAPTCHA v3 token. The server checks it with Google before creating anything (`App\Support\Recaptcha`, rule `PassesRecaptcha`).

- Set `RECAPTCHA_SITE_KEY` and `RECAPTCHA_SECRET_KEY` in `.env`. Optionally set `RECAPTCHA_MIN_SCORE` (default `0.5`; Google scores run from 0, likely a bot, to 1, likely human).
- Leaving both keys empty turns verification off (local development and tests).
- A submission fails if the token is missing or invalid, the score is too low, the action does not match (`register`, `apply`, `store_order`), or Google cannot be reached. Rejections are logged with the score.
- Register the production domains, including academy subdomains, for the key in the reCAPTCHA admin console.
- The external applicant API (`/api/v1`) uses API keys, not reCAPTCHA.
