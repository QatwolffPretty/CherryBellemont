# Cherry Bellemont deployment guide

## Server requirements

- PHP 8.2 or newer with BCMath, Ctype, cURL, DOM, Fileinfo, GD, JSON, Mbstring, OpenSSL, PDO MySQL, Tokenizer, XML, and ZIP enabled.
- Composer 2, Node.js 20 or newer, npm, and MySQL 8 or compatible MariaDB.
- A web server configured with its document root pointing at the application's public directory.
- HTTPS enabled before enabling production traffic.

## Environment

Copy .env.example to .env on the server and set unique, production-only values. Never copy a local .env or commit it.

Required production values include:

- APP_NAME, APP_ENV=production, APP_KEY, APP_DEBUG=false, APP_URL=https://your-domain.example
- MySQL DB credentials
- CACHE_STORE, SESSION_DRIVER, and QUEUE_CONNECTION appropriate for the host
- mail credentials and a verified MAIL_FROM_ADDRESS
- Stripe test keys until the separate live-mode checklist is completed
- real DuitNow account details and a real QR asset path
- ADMIN_NOTIFICATION_EMAIL and LOW_STOCK_THRESHOLD

Set SESSION_SECURE_COOKIE=true when the application is served over HTTPS. If the app runs behind a reverse proxy, set TRUSTED_PROXIES only to that provider's documented proxy addresses or CIDR ranges.

## Safe deployment sequence

Run these from the application directory. Review output after each command; do not run this sequence automatically from a browser request.

~~~
composer install --no-dev --optimize-autoloader
php artisan down
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm ci
npm run build
php artisan queue:restart
php artisan up
~~~

Route caching is compatible with the current route definitions. Preview routes are conditionally registered only in the local environment and are not cached in production.

## Receipt storage upgrade

New DuitNow receipts are stored privately after migration 2026_07_21_000026. Existing receipts retain their current disk until explicitly secured. First review them without changes:

~~~
php artisan receipts:secure-storage --dry-run
~~~

After confirming the report and backing up uploads, move legacy receipt files:

~~~
php artisan receipts:secure-storage
~~~

The command copies each file to private storage, updates its record, and removes only the verified public copy. Keep a backup until the command completes without skipped files.

## File permissions and storage

The web server needs write access to storage and bootstrap/cache. Run php artisan storage:link once for public product, review, and campaign images. Payment receipts must not be linked or served directly; admins retrieve them through protected application routes.

## Post-deployment checks

- Run php artisan migrate:status.
- Confirm php artisan about reports the intended cache, queue, mail, session, and database drivers.
- Start or restart the queue worker and scheduler.
- Rotate or archive local development logs before copying a release. Never deploy a local laravel.log containing test or diagnostic values.
- Complete the smoke-test checklist in docs/PRODUCTION-CHECKLIST.md.
- Keep the previous release available until the smoke test succeeds, then remove maintenance mode.
