# M5 communications, renewals and reminders

The academy Communications area consolidates five operational views: announcements, payments due within 45 days, enrolments ending within 60 days, delivery history and automated reminder settings. Payment dates come from the earliest outstanding invoice installment, falling back to the invoice due date. Renewal dates come from each active enrolment's `ends_on`, which supports fixed cycles and rolling six-month/year memberships that expire on different dates.

Announcements can target all active families, one branch or one programme. Recipients are deduplicated guardian/contact email addresses linked to active enrolments. Draft announcements do not send email. Publishing creates one immutable, tenant-scoped delivery record per recipient before queueing mail.

Payment and renewal reminders can be sent manually from their tables. Owners can also configure distinct day offsets, such as payment reminders 14, 7, 1 and 0 days before due and renewal reminders 30, 14 and 7 days before expiry. The scheduler evaluates each academy at 08:00 in its own timezone. Tenant-scoped idempotency keys prevent a repeated scheduler run or repeated button click from duplicating the same day's message.

Delivery records retain recipient, type, channel, queued/sent/failed state and related invoice, enrolment or announcement. Notification events update delivery state after the mail transport reports success or failure. SMS and WhatsApp are not assumed; they require separate provider, consent, metering and pricing decisions.

Online applicant payments continue through the Paystack provider abstraction with signature verification, server-side amount calculation and idempotent ledger settlement. A public player/guardian invoice payment link is intentionally not fabricated in this slice: it requires an authenticated guardian portal or secure payment-link token plus confirmation of the academy merchant settlement model.
