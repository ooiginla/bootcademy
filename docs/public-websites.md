# Bootcademy public academy websites

Each academy receives a unique public-site record when its workspace is created. The first theme is `launch`; the theme key is stored independently so additional themes can be added without rewriting academy content.

## Local use

Academy owners edit the website at `/app/settings/website`. Local previews use:

```text
http://127.0.0.1:8000/sites/{academy-subdomain}
```

Programme pages and the application flow are beneath that URL. The first step validates and saves the applicant, guardian consent, selected programme/cycle and custom-field values. The saved application then opens a private, token-protected fee page; payment-plan choices are deliberately not collected on the biodata form.

The fee page is generated from server-side invoices. It can offer the compulsory application fee by itself, the application fee plus the complete programme balance, or the application fee plus a configured installment. Browser-supplied amounts are ignored. Paystack Popup V2 receives an access code created by the Laravel backend, while verification, allocation and receipt delivery happen server-side.

Payment is optional at this stage. Skip actions above and below the fee content lead to an application-submitted page without changing or deleting the outstanding invoices. Paystack attempts lead to the same completion page, where the server-derived intent status reports the charged amount as verified, unsuccessful or awaiting confirmation. In every case, the page makes clear that the application was already successfully submitted.

A queued application-confirmation email is sent to the applicant after the public application is saved. A separate verified-payment receipt is sent after Paystack settlement and itemizes the receipt and provider references, allocated invoice/installment amounts, processing charge, total charged and remaining application balance. Local delivery uses the configured Laravel mail transport; Resend setup is documented in `docs/local-email.md`.

The current merchant model uses Bootcademy's platform Paystack keys. It does not create academy subaccounts or infer settlement splits. Each academy has a Super-Admin-controlled customer surcharge configuration (percentage, fixed amount, cap and fixed-fee waiver threshold), defaulting to the Nigerian local-card pricing assumptions. A future direct-account or split-settlement model requires a separate compliance and product decision.

## Application availability

The public application page is driven by cohorts marked as receiving applications, not by every programme configured in the back office. Each cohort resolves to its programme offering, cycle, branch and fees. Future, expired, paused, full, completed, cancelled, inactive-cycle and inactive-offering options are omitted. When no cohort is open, the page displays that the academy is not currently receiving applications and does not render a submission form. The public POST endpoint performs the same check again to protect against stale pages and forged identifiers.

Programme, cycle, cohort and location are displayed as separate dependent controls. For an all-locations cohort, Location expands to the academy's active branches and the applicant must choose a preferred specific branch. The browser submits both that preference and the hidden cohort-backed intake ID; the server independently verifies tenant ownership and branch compatibility while retaining the intake's fee scope. A branch-specific intake permits only its configured branch.

Academy settings control whether the public form collects guardian/contact details. The same settings provide configurable singular and plural labels, so the UI and API configuration can use terms such as `Guardian`, `Parent` or `Next of Kin` without changing the stored domain model. Disabling collection hides those fields and causes submitted guardian fields to be rejected server-side.

Programme creation remains intentionally short. After creation, Owner/Admin users can use Edit to add an optional public profile: a replaceable hero image, duration, delivery format, programme type, timezone, level, formatted outcomes, skills, curriculum, eligibility, audience, demand and application process, plus repeatable tools/platforms, FAQs and testimonials. Rich content is allow-list sanitized on the server before storage. Hero images remain in private storage and are served through academy-scoped routes. Public programme pages combine this profile with academy-scoped active cycles, branch availability, fee breakdowns and payment arrangements.

## Production subdomains

Set the root tenant domain in the environment:

```text
TENANT_DOMAIN=bootcademy.com
```

Production infrastructure must also:

- point the wildcard DNS record `*.bootcademy.com` to the Laravel application;
- provision TLS for `*.bootcademy.com` and `bootcademy.com`;
- preserve the incoming `Host` header through the reverse proxy;
- reserve platform labels such as `www`, `app`, `api`, `admin` and `platform`.

Laravel then serves academy sites at `https://{academy-subdomain}.bootcademy.com` while retaining strict academy-scoped queries.

## Content and privacy

- Public sections are disabled independently and only enabled content is rendered.
- Programmes, branches, active cycles, fee schedules and applicant custom fields are academy-scoped.
- Programme rich text permits headings, emphasis, lists, quotes and safe links; scripts, event attributes, embeds and unsafe URL schemes are removed server-side.
- Team members, testimonials, learner stories, gallery images, memberships and sponsors should only be published with appropriate permission.
- Website media currently accepts public HTTPS URLs. A managed public media library can be added as a separate feature.
