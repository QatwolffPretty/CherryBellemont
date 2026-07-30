# Guest Order Lookup and Mailpit

## Guest order lookup

Customers can open `/track-order`, enter the order number and checkout email, and are redirected only when both values match. Email comparison is trimmed and case-insensitive. The lookup is throttled to five attempts per minute per IP and always uses the existing secure guest URL:

`/orders/{order_number}/access/{guest_access_token}`

No order is exposed by numeric database ID, and a failed lookup uses the same generic message regardless of which value did not match.

## Local Mailpit configuration

Use the following values in your local `.env` (the repository's `.env.example` contains the same non-secret defaults):

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=hello@cherrybellemont.com
MAIL_FROM_NAME="${APP_NAME}"
```

Mailpit accepts SMTP mail at `127.0.0.1:1025`; its local web inbox is normally available at `http://127.0.0.1:8025`. Mailpit captures local messages only. It does not deliver real external email.

Start Mailpit using the method appropriate to your local environment, then use **Admin → Settings → Email Test**. Test and transactional delivery records appear in **Email Logs**. When using an asynchronous queue, run a local worker as well:

```powershell
php artisan queue:work
```

## Transactional email safety

Order, payment, fulfilment, shipment, and refund notifications remain queued after their business transaction commits. `order_notification_logs` records a stable event key for automatic delivery, preventing duplicate status or webhook emails; manual resends intentionally create a separately marked record. Failed jobs remain retryable through Laravel's normal failed-job workflow.

The log stores delivery metadata and a safe error summary, not full email content, Stripe credentials, receipt paths, or guest access tokens.
