# M5 referrals and reward administration

Referral attribution links one existing academy player to either an applicant or another player. A referred person can have only one attribution within an academy, self-referral is rejected, and every referenced record is tenant-scoped.

Reward automation is disabled by default. The academy Owner/Admin may configure one prospective rule:

- qualification after enrolment, first verified payment, or a fully paid programme invoice;
- a fixed reward, or a percentage of the verified qualifying amount;
- programme payments only, or every verified academy fee.

Enrolment qualification requires a fixed reward because no payment base exists at that point. Percentage values cannot exceed 100%. The system does not seed KCFA's reported 10% until its eligible base and payout policy are approved.

Reward reconciliation runs nightly and can also be triggered from the admin page. Stable event keys make it idempotent. Created rewards begin as `pending`; Finance Officer or Owner/Admin explicitly approves them, then records an external payout reference to move them to `paid`. Recording a payout does not initiate a bank transfer. Historical reward amounts are ledger entries and are not recalculated when the current rule changes.

Marking an approved reward as paid atomically creates one linked academy expense for the same amount and currency under the academy's `Referral rewards` category. The payout reference is retained on both records, duplicate payout references and duplicate expenses are blocked by database constraints, and the expense register reports referral rewards separately from manual costs and invoice write-offs.
