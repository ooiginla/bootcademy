# Local email delivery

Laravel is configured with the in-memory `array` mailer by default so local development cannot accidentally send real messages or write private invitation links into logs. That transport does not deliver to an inbox.

## Resend

To deliver transactional mail with Resend, install `resend/resend-php` and configure these local-only values:

```text
MAIL_MAILER=resend
RESEND_API_KEY=re_your_key
MAIL_FROM_ADDRESS=onboarding@resend.dev
MAIL_FROM_NAME="Bootcademy"
```

`onboarding@resend.dev` is suitable only for Resend's restricted test delivery. To send to arbitrary applicants, verify a sending domain in Resend and replace `MAIL_FROM_ADDRESS` with an address on that domain. Run `php artisan config:clear` after changing mail configuration.

Application and payment messages are queued. `composer run dev` starts the queue listener together with the web server and frontend. When running processes separately, also run `php artisan queue:work`.

To deliver invitation email through an SMTP provider, set the following local-only values in `.env`:

```text
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_SCHEME=smtp
MAIL_FROM_ADDRESS=no-reply@your-domain.example
MAIL_FROM_NAME="Bootcademy"
```

Then run `php artisan config:clear`. Use the exact host, port and encryption settings supplied by the provider. Never commit credentials or copy production credentials into logs, screenshots or support messages.

For development-only inspection, a local mail catcher can provide SMTP on `127.0.0.1` and a browser inbox. It proves rendering and links but does not send to the recipient's real mailbox.
