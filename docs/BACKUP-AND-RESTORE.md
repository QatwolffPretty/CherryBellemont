# Backup and restore guidance

## What to back up

- MySQL database, including orders, payments, coupons, campaigns, reviews, and subscribers.
- storage/app/public for storefront assets.
- storage/app/private for protected DuitNow receipt files.
- Production .env stored in a secure secrets manager or encrypted off-server location.

Do not rely on a source-code backup for customer uploads or database records.

## Recommended schedule

- Database: daily minimum, with more frequent backups during active sales.
- Uploads: daily minimum and before any storage migration.
- Retention: keep several restore points both on-server and off-server.
- Restore testing: test a database and file restore on a non-production environment at least quarterly.

## Example backup approach

Use host-supported backup tooling where possible. A MySQL backup is commonly created with:

~~~
mysqldump --single-transaction --routines --triggers -u DATABASE_USER -p DATABASE_NAME > cherry-bellemont.sql
~~~

Archive public and private storage separately, preserve access permissions, encrypt archives, and transfer them to a location outside the application server.

## Restore procedure

1. Enable maintenance mode.
2. Snapshot the current database and uploads before changing anything.
3. Restore the database and the matching public and private storage backup.
4. Verify .env, storage permissions, storage:link, queues, and scheduler.
5. Run the smoke-test checklist before returning the store to service.

Never test a restoration by overwriting the live database without a verified recent backup and a documented rollback plan.
