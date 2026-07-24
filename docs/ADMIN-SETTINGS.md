# Admin Settings

The Settings module at `/admin/settings` lets authorised administrators update ordinary Cherry Bellemont business and storefront values without editing templates. It is protected by both `auth` and `admin` middleware.

## Managed settings

- **General:** store name, tagline, description, public support details, country and the MYR display currency.
- **Contact & Social:** enquiry/support email, phone, WhatsApp, address, and Threads, Instagram and Facebook URLs.
- **Branding:** light/dark logos, favicon and default Open Graph image. Uploads use the public `settings/` directory with generated filenames.
- **Payments Display:** public Stripe and DuitNow labels, enabled state and public DuitNow account/QR/instructions. At least one payment method must remain enabled.
- **Shipping & Pickup:** self-pickup presentation, pickup details and future-order processing/free-shipping display settings. Shipping Zones and Delivery Methods remain the source of delivery-rate rules.
- **Gift Experience:** whether the feature is offered, its title, description, price and message length. The configured price is trusted server-side for future checkouts only; every existing order retains its stored gift fee snapshot.
- **Returns:** eligibility window, damaged-item window, evidence limits and returns contact. New submissions use the current configured values; existing return requests retain their own submitted record and are never rewritten.
- **Newsletter:** public section visibility and copy, plus campaign sender name/address.
- **SEO:** default title, description, Open Graph image and organisation name. Page-specific metadata can still override these defaults.
- **Footer:** copyright suffix and public social/contact visibility.
- **Inventory:** low-stock threshold and back-in-stock feature switch. Existing stock levels and notification history are not changed.

## Values that must never be entered here

Do **not** put Stripe secret keys, Stripe webhook secrets, database passwords, mail passwords, `APP_KEY`, API tokens, private banking credentials, or other private production credentials in this module. Keep them in `.env` or the hosting provider's secret manager.

## Caching and fallback behaviour

`SettingsService` caches database settings briefly using the application's configured cache driver. The cache is cleared immediately whenever a value changes and can be cleared manually from the Settings page.

During an early deployment before the settings migration is available, the service falls back to the existing `config/store.php`, `config/duitnow.php` and mail configuration values so shared storefront components do not fail. Persistent database errors are logged safely. The Settings audit log migration should always be applied before granting staff access to the module.

## Seeding and deployment

Seed the editable defaults once after migrations:

```powershell
php artisan migrate
php artisan db:seed --class=SettingsSeeder
php artisan storage:link
php artisan optimize:clear
```

The seeder uses `firstOrCreate`, so it only creates missing keys and never overwrites administrator changes. Uploaded images require the public storage link in production. Back up the `storage/app/public/settings` directory with other customer-facing uploads.

## Audit history and rollback

Every actual setting change creates a read-only audit record containing the setting key, prior and new stored value, administrator, timestamp and a hashed request origin. Audit entries use nullable foreign keys, so staff deletion does not erase history.

To roll back a configuration change, restore the previous non-secret value through the Settings page; do not edit the audit record. For a code rollback, keep the database migrations and the settings table in place—the service falls back safely when a newly introduced key is absent.
