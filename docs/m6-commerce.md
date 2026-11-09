# M6 commerce, inventory and orders

Bootcademy commerce uses one tenant-scoped catalogue and order service for the built-in academy website, authenticated external storefront APIs and administrator-recorded counter sales.

## Catalogue

- Products belong to one academy and may have a category, reusable tags and one or more options/variants such as size or colour.
- A product image can be uploaded while the product is created or replaced later. Files are stored under the academy's private storage scope and served through authorized/public product endpoints.
- Money is stored as integer minor units. A variant may override the product price.
- Only active products and active variants are returned by storefronts. Draft and archived records remain available internally for audit/history.
- Public-store visibility is controlled by the `Store` section in **Public website** settings.

## Stock ledger

Every physical stock balance belongs to an actual academy branch. `All branches` is a read-only sum and is never stored or treated as a location. Each branch balance separates:

- `on_hand`: physically available units;
- `reserved`: units committed to open orders;
- `available`: `on_hand - reserved`.

The Products tab displays the combined on-hand count for each product and opens a branch-by-branch inventory detail dialog. The Inventory tab contains only actual, non-empty branch balances; it does not generate zero rows for every product/location combination or repeat the combined total as a location.

Every adjustment, reservation, release and sale creates an immutable stock movement. Checkout locks the relevant balance inside a database transaction. Overselling is rejected unless the product explicitly permits backorders.

`Allow backorders` means an order may be accepted even when available stock is insufficient. It should only be enabled where the academy intentionally sells before restocking and can communicate the delayed fulfilment to the customer.

Manual adjustments explicitly add or remove a positive quantity and use a structured reason such as purchase, opening stock, customer return, stock count, damage, expiry, loss or supplier return. `Other` requires a written explanation.

Stock transfers move available units between actual branches. Both sides are updated atomically, academy-wide on-hand stock remains unchanged, and linked `transfer_out`/`transfer_in` movements share a transfer reference. Reserved units cannot be transferred.

The public store works from combined available inventory across all branches. The customer's selected branch is only their preferred pickup/service location and does not control which branch supplies the stock. Reservations are allocated from combined inventory in a deterministic branch order. Every order-item allocation records its actual supplying branch and quantity so cancellation releases, and delivery consumes, the exact branch balances originally reserved.

An order reserves stock. Cancelling an unpaid, unfulfilled order releases it. Marking an order delivered reduces both on-hand and reserved stock exactly once. Delivered orders cannot be moved backwards or cancelled. Paid orders require a future explicit refund flow before cancellation; payment history is never deleted.

## Built-in storefront checkout

Adding an item shows a success notification and immediately opens a right-side cart drawer. Checkout progresses through Cart, Details, Notes and Payment, then clears the browser cart after the order is successfully created. It captures the customer's name, email, phone, fulfilment preference, optional order instructions and an optional free-text member name. The public form never exposes or searches the academy's player/student directory.

Customers may choose Paystack, the academy's configured bank account, or place the order without payment. Every choice creates the order and reserves stock first. Paystack charges the outstanding order amount plus the academy's configured processing surcharge, verifies the amount/currency/reference server-side, updates the order idempotently and emails a receipt. Bank-transfer and pay-later orders remain unpaid until academy staff record or verify their payment.

## Orders and offline sales

Payment and fulfilment are independent:

- payment: unpaid, partially paid or paid;
- fulfilment: unfulfilled, processing, ready, shipped, delivered or cancelled.

Owners/Admins and Finance Officers can record offline sales, partial or full verified payments and delivery progress. Browser totals are ignored; the server reloads variants and calculates every line using current stored prices.

Every order number in the administration register opens a tenant-scoped details page. It preserves the customer and optional student/player name, fulfilment preference, line items, actual supplying-branch allocations, status dates, notes and every payment attempt. The financial summary counts only verified payments toward the paid total and calculates the remaining balance from the stored order total. Pending and failed payment attempts remain visible for audit but do not reduce the balance. The page includes a print action that produces a clean order/receipt document without the application navigation.

## Storefront API

External storefront backends use the existing academy API bearer key:

```text
GET  /api/v1/store/catalog
POST /api/v1/store/orders
```

The catalogue exposes only active tenant products. Order creation accepts customer/delivery data and `items[{variant_id, quantity}]`; it deliberately accepts no client price or total. The response contains the order number, server-calculated total and a private status token. API secrets must remain on the external site's server and must never be shipped in browser JavaScript.

## Deferred decisions

Online store payment initialization is intentionally not hardwired to Paystack until the platform's merchant/payout ownership and refund policy are approved. Current public orders are submitted as unpaid and can display the academy bank details; authorized staff verify payment in the Store workspace.
