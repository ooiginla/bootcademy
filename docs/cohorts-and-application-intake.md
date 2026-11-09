# Cohorts and application intake

## Domain language

Bootcademy keeps programmes, cycles, programme offerings and cohorts separate because they answer different operational questions.

Terms are also separate from cohorts. A term divides a cycle by time; a cohort groups members inside a programme offering. The same cohort can participate in Term 1, Term 2 and Term 3 without recreating its memberships.

| Concept            | Question answered                                                      | Example                                  |
| ------------------ | ---------------------------------------------------------------------- | ---------------------------------------- |
| Programme          | What does the academy teach or provide?                                | Data Analytics Bootcamp                  |
| Cycle              | Which financial and reporting period owns the enrolment and fees?      | 2027 Academic Year                       |
| Term               | Which subdivision of the cycle owns a fee or activity?                 | Term 1                                   |
| Programme offering | What is the priced version of this programme in this cycle and branch? | Data Analytics, 2027, Lekki, NGN 350,000 |
| Cohort             | Which delivery group learns together?                                  | January 2027 Weekend Cohort              |

`programme_cycles` remains the database name for compatibility, but the product language should call it a **programme offering**.

## Relationship

```text
Programme
  └── Programme offering (programme + cycle + optional branch pricing scope)
        ├── Cohort A
        ├── Cohort B
        └── Cohort C
```

A programme may be reused across many cycles. A cycle may contain many programme offerings. A programme offering may contain zero or many cohorts. Academies that do not operate batches may leave the cohort unset.

## Responsibilities

The cycle owns financial and historical context:

- fixed or rolling period rules;
- application and programme fees;
- invoices, payments, balances and financial reporting;
- historical membership for the period.

The cohort owns delivery context:

- name, start and end dates, capacity and status;
- branch and delivery format;
- the group assigned to sessions and attendance;
- membership history when a learner moves between delivery groups.

An enrolment remains the learner's programme/cycle financial relationship. A separate cohort membership assigns that enrolment to a delivery group. Moving cohorts closes the previous cohort membership and creates another; it must not cancel, recreate or rewrite the learner's invoices.

## Application intake

Applications are closed by default. An academy Owner/Admin enables applications directly on a cohort. Because every cohort belongs to one programme offering, that single choice resolves the programme, cycle, branch and fee configuration automatically.

The cohort application controls contain:

- enabled/disabled state;
- the cohort, whose programme offering determines programme and cycle;
- the cohort or offering branch;
- optional opening and closing dates.

No dates means the enabled window is open continuously. A dated window is open inclusively from its opening date through its closing date in the academy timezone. Closed, disabled, future and expired windows are not presented to applicants and cannot be submitted through the public form or versioned API.

The public application form and API configuration expose only cohorts currently receiving applications. Completed, cancelled and full cohorts are excluded. If none are open, the public page states that the academy is not currently receiving applications. Server-side validation rechecks the selected cohort-backed intake during submission to prevent stale pages or forged requests.

An intake scoped to all locations does not mean the applicant can omit a location. The public form lists the academy's active branches and records the selected branch as the applicant's preference while keeping the general cohort intake and fee scope. A branch-specific intake fixes the selection to that branch. Both identifiers are tenant-validated on submission.

Internally, Bootcademy retains an `application_intakes` record for each enabled or previously enabled cohort. This preserves application-window and audit history without presenting a second configuration concept to academy administrators. Pausing applications disables that record rather than deleting it.

Historical applications are never hidden or deleted when an intake closes. The admin Applications directory continues to show all submissions for audit and follow-up.

## Tenant and history rules

- Every cohort, intake, cohort membership and related foreign key is academy-scoped.
- A cohort must belong to the selected programme offering.
- A branch-specific cohort or intake must use a branch owned by the academy and compatible with the offering.
- Applications snapshot their selected cohort and intake.
- Accepted applications create the normal enrolment and, when selected, an active cohort membership.
- The rollout creates one `Default cohort` for every existing programme offering, links its existing applications and enrolments, and links sessions where the active offering can be resolved safely. This normalizes historical data without opening applications or changing invoices.
- Intake and cohort changes are audited; records referenced by applications or memberships are retained rather than destructively removed.

## Delivery sequence

1. Add tenant-scoped cohorts, cohort memberships and application intake windows.
2. Add cohort and intake references to applications and an optional cohort reference to sessions.
3. Manage application availability and dates directly from the cohort create/edit dialog.
4. Filter public/API application choices through currently open intake windows and enforce the same rule on submission.
5. Create cohort membership during applicant conversion and use cohort membership for cohort-specific attendance rosters.
6. Preserve the existing programme/branch roster when a session has no cohort.

## Existing-data normalization

The schema migration performs a one-time, tenant-scoped backfill. Each existing programme offering receives a default cohort whose dates and status come from its cycle. Existing enrolments and applications already linked to that offering inherit its default cohort. Legacy enrolments without a cycle/offering reference are left unchanged because assigning them to a financial period would fabricate history. A session is linked only when its academy, programme, active cycle and branch identify an unambiguous offering; otherwise it remains cohort-neutral and retains the legacy programme/branch attendance behavior.

The backfill deliberately creates no application-intake window. An academy must explicitly enable applications for a cohort in **Applications → Cohorts**, so deploying the migration can never publish an admissions option accidentally.
