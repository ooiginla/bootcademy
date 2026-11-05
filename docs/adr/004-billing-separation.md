# ADR 004: Separate platform billing from academy finance

- Status: Accepted
- Date: 2026-10-28

## Decision

SaaS subscription billing (academy to platform) and learner-fee billing (guardian/learner to academy) use separate tables, services, permissions, reports and payment-provider configuration. A shared provider adapter may implement transport concerns, but domain records and settlement ownership never overlap.

Provider webhooks require signature verification, idempotency keys and auditable state transitions. The platform will not assume that academy fee collections settle to the platform's merchant account; merchant and settlement policy remains pending approval.

## Consequences

The separation prevents misleading balances and accidental fund routing. Reconciliation and reporting are implemented independently for each ledger.
