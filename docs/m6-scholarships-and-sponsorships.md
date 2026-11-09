# M6 scholarships and sponsorships

## Financial treatment

Scholarships never overwrite an invoice, impersonate a cash payment or use the bad-debt write-off ledger. The original billed amount remains immutable.

- An **academy-funded scholarship** is an explicit concession. It reduces the academy's collectible invoice balance and the beneficiary's personal balance, while remaining separately reportable.
- An **externally sponsored scholarship** assigns part of the invoice to a sponsor. It reduces the beneficiary's personal balance but remains in the academy receivable until a verified sponsor contribution is captured.
- A sponsor contribution creates a verified academy payment and normal invoice/installment allocations. It also updates the linked award allocation and sponsor commitment. Reversals unwind all four ledgers transactionally.
- A regular beneficiary payment cannot consume an amount already assigned to a sponsor.

Money remains integer minor units plus an ISO currency. Invoice, installment, award-allocation, commitment and payment rows are locked while financial state changes are made. Unique award/invoice allocation constraints make applying the same award idempotent.

## Structure

```text
Sponsor
└── Commitment (promised and received)

Scholarship scheme
├── funding: academy or sponsor
├── coverage: full, percentage or fixed amount
└── optional cycle, term, programme, branch and validity scope
    └── Beneficiary award
        └── Invoice allocation
            └── Sponsor contribution/payment (when externally funded)
```

An award is approval for a beneficiary. Applying it to an invoice is a separate controlled action so an administrator can review the exact fee and calculated amount before changing responsibility.

## Authorization and audit

Academy Owner/Admin, Finance Officer and Platform Super Admin support mode can manage the module. All lookups are tenant scoped in validation and again inside transactional actions. Sponsor creation, commitment creation, scheme changes, beneficiary awards, invoice allocations and contributions are audit logged.

Scholarship agreements and document uploads, public donation collection, non-cash equipment valuation and accounting classification of concessions remain separate decisions. No tax or statutory accounting treatment is inferred.
