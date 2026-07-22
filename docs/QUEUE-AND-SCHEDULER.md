# Queue and scheduler operations

Cherry Bellemont queues customer mail, admin operational mail, newsletter campaign deliveries, and back-in-stock notifications. These jobs are retry-safe and use three attempts with increasing backoff.

## Database queue prerequisites

Use the database queue only after the application's migrations have run. The normal Laravel jobs, job_batches, and failed_jobs tables are already included.

For a foreground worker:

~~~
php artisan queue:work --sleep=3 --tries=3 --timeout=120
~~~

After each deployment:

~~~
php artisan queue:restart
~~~

Inspect and retry failures deliberately:

~~~
php artisan queue:failed
php artisan queue:retry all
~~~

Read storage/logs/laravel.log before retrying repeated failures. Logs intentionally omit payment secrets, guest access tokens, and raw webhook payloads.

## Linux Supervisor example

Use Supervisor only where the host supports persistent processes:

~~~ini
[program:cherry-bellemont-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/cherry-bellemont/artisan queue:work --sleep=3 --tries=3 --timeout=120
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/cherry-bellemont/storage/logs/queue-worker.log
stopwaitsecs=3600
~~~

Reload Supervisor according to the host's instructions after changing the configuration.

## Shared-hosting alternative

If persistent workers are unavailable, configure a frequent scheduled task supplied by the host:

~~~
php /path/to/artisan queue:work --stop-when-empty --sleep=3 --tries=3 --timeout=120
~~~

Use a provider-supported cron frequency and monitor queue latency. This is less responsive than Supervisor, so it is not preferred for campaigns or payment notifications.

## Scheduler

The scheduler runs newsletter:send-scheduled every minute. Configure one cron entry:

~~~cron
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
~~~

For local verification:

~~~
php artisan schedule:list
php artisan schedule:work
~~~

Do not hardcode /path/to; replace it with the server's release path.
