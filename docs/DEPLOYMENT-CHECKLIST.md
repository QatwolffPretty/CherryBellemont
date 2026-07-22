# Deployment Checklist

## Server requirements

- PHP 8.3 or later with BCMath, Ctype, cURL, DOM, Fileinfo, Mbstring, OpenSSL, PDO MySQL, Tokenizer, XML, and ZIP.
- MySQL 8 or compatible MariaDB, Composer 2, Node.js 20+, and a web server whose document root is public/.
- HTTPS and write access for storage/ and bootstrap/cache/.

## Safe release sequence

Set production values in the server environment or .env first. Never commit secrets.

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

Run php artisan route:cache only with APP_ENV=production; local-only email preview routes are intentionally excluded in production.

## Required production services

~~~
php artisan queue:work --sleep=3 --tries=3 --timeout=120
~~~

Install a cron entry using the server's absolute project path:

~~~cron
* * * * * php /path/to/CherryBellemont/artisan schedule:run >> /dev/null 2>&1
~~~

The scheduler starts due newsletter campaigns. On shared hosting, use the host's scheduled-task panel with the same command.

## After deployment

- Verify the secure receipt storage command reports no public receipts: php artisan receipts:secure-storage --dry-run.
- Check php artisan queue:failed and application logs.
- Complete the smoke tests in FINAL-UAT-CHECKLIST.md.
