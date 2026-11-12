# Online payments: Paystack split settlement

Online payments (public application fees and the public store) go through the platform's Paystack account and settle straight to each academy's own **Paystack subaccount**.

## How the money splits

For a fee of `base`, the parent pays `base + surcharge`:

- The payment is opened with `subaccount = <academy code>`, `transaction_charge = surcharge` and `bearer = account`.
- The academy's subaccount receives exactly `base`.
- The main (platform) account receives the surcharge and pays Paystack's fee from it. What is left is the platform fee, settled to the platform's bank on Paystack's schedule.

`PaystackFeeCalculator::breakdown()` works out the surcharge:

1. `platform_fee = round(base × platform %) + platform fixed`
2. `paystack_fee` = Paystack's charge, grossed up so that `base + platform_fee` is left after Paystack's fee (configured %, fixed fee, waiver threshold and cap)
3. `surcharge = platform_fee + paystack_fee`

Each payment attempt stores `platform_fee_minor`, `paystack_fee_estimate_minor` and `subaccount_code`. Paystack's actual fee is stored on settlement as `provider_fee_minor`. The academy's `AcademyPayment` keeps `amount_minor = base`, `customer_fee_minor = surcharge` and `platform_fee_minor`.

**Risk:** cards Paystack charges more for (for example international cards at 3.9% + ₦100, uncapped) cost more than the estimate. The difference comes out of the platform fee. Check in test mode how Paystack handles a charge larger than the main account's share.

## Settings

- **Platform → Platform settings** (`platform_settings`, one row): Paystack %, fixed fee, cap and waiver threshold, plus the platform fee (% and/or fixed). The platform fee defaults to 0.
- **Platform → Academies → academy**: an optional platform fee override for that academy (leave both blank for the default), the subaccount status, and **Send to Paystack again**.
- The old per-academy `paystack_fee_*` columns on `academies` are no longer read. They are kept for now and can be dropped in a later migration.

## Academy bank account and subaccount

With Paystack configured, **Settings → Academy → Bank details** lists Paystack's banks. On save (`UpdateAcademyBankDetails`):

1. The account number is checked with `/bank/resolve`. The bank's account name is stored, and an unconfirmed account is rejected.
2. The subaccount is created (`POST /subaccount`) or updated (`PUT /subaccount/:code`) with `percentage_charge = 0`, since each payment sets the split.
3. Status is `active`, `failed` (with Paystack's message), `inactive` (bank details removed) or `none`.

Online payment is available only when Paystack keys are set **and** the academy's subaccount is `active` (`Academy::acceptsOnlinePayments()`). Otherwise parents see bank transfer / pay later, and the payment endpoints return 422.

## Confirming payments without a webhook

The live webhook URL belongs to another application, so payments are confirmed by asking Paystack (`RequeryPendingPayments`, scheduled every five minutes, also `php artisan payments:requery`):

- Pending attempts younger than an hour are checked every run, then hourly.
- After 48 hours, an attempt Paystack still reports as `abandoned` becomes `expired`.
- Paystack's `abandoned` status means "not finished yet", not failed. Only `failed` and `reversed` close an attempt.
- A settled payment whose `subaccount.subaccount_code` differs from the one it was opened with goes to `review_required`.

The server must run Laravel's scheduler: `* * * * * php artisan schedule:run`.
