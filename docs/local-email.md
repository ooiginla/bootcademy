# Local email delivery

Laravel is configured with the in-memory `array` mailer by default so local development cannot accidentally send real messages or write private invitation links into logs. That transport does not deliver to an inbox.

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
