# Rollback Procedure

## Before every deployment

1. Record the current release revision and back up the MySQL database and storage/app/.
2. Confirm queue workers can be restarted by the process manager.
3. Do not use destructive migration commands as a rollback shortcut.

## Application-only rollback

1. Put the site into maintenance mode: php artisan down.
2. Restore the previous application release and its compiled frontend assets.
3. Run composer install --no-dev --optimize-autoloader.
4. Run php artisan optimize:clear, then php artisan config:cache, php artisan route:cache, and php artisan view:cache.
5. Run php artisan queue:restart.
6. Bring the site back: php artisan up.

## Database rollback

Prefer a tested database restore when a release included data-affecting migrations. Do not automatically run migrate:rollback against production orders, payments, receipts, or campaign history.

1. Keep the site in maintenance mode.
2. Restore the verified database backup and, when needed, the matching uploaded-file backup.
3. Verify order totals, payment statuses, receipt access, and queue tables before reopening the site.

## After rollback

- Review storage/logs/laravel.log.
- Check php artisan queue:failed; retain historical failed jobs for audit and do not retry jobs caused by deleted subscriber records.
- Run the critical customer and admin checks in FINAL-UAT-CHECKLIST.md.
