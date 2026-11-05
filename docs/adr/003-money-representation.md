# ADR 003: Integer minor-unit money

- Status: Accepted
- Date: 2026-10-28

## Decision

Store every monetary amount as an integer in the currency's minor unit together with an ISO 4217 currency code. Never use floating point for financial calculations. Currency precision and formatting belong to a dedicated money value object/service.

Fee schedules are effective-dated. When an invoice or individual payment obligation is created, its amount and currency are copied into an immutable financial record so later price changes cannot alter history. Corrections use explicit adjustments or reversals rather than deletion.

## Consequences

Multi-currency records remain unambiguous, but totals across currencies require an explicitly approved exchange-rate policy. No such conversion policy is assumed.
