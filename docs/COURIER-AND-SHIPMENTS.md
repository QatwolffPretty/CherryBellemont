# Courier and Shipment Management

Cherry Bellemont uses a manual shipment foundation. It stores courier choices, tracking snapshots, private labels, and an auditable shipment timeline without contacting any external courier service.

## Admin workflow

1. Create or update editable courier examples in **Admin → Couriers**.
2. Move a paid order through **Processing** and **Packed** using the existing fulfilment workflow.
3. Select **Create Shipment** on the packed order.
4. Add an optional private label, courier, service, tracking number, estimated delivery date, and internal note.
5. Select **Mark as Shipped**. A courier and tracking number are required. This safely moves the order to **Shipped**, records the timeline event, copies public tracking details to the order snapshot, and queues the existing shipped customer email once.
6. Add manual events such as In Transit, Out for Delivery, Delivered, Delivery Failed, or Returned. A Delivered event safely moves an already shipped order to Delivered.

Stock is never deducted by shipment operations. Shipment changes do not enter the DuitNow receipt queue or alter payment status.

## Labels and customer tracking

- Labels accept PDF, PNG, JPG, and JPEG files up to 10 MB.
- Labels are stored on Laravel's private `local` disk. Only administrators can download them.
- Guests can view tracking only through the existing secure order number and guest access token route. The public tracking page excludes addresses, payment details, internal notes, and label paths.
- A courier tracking URL is generated only by substituting a URL-encoded tracking number into the courier's editable `{tracking_number}` template.

## Queue requirements

Shipment emails reuse the existing queued order notification infrastructure. Keep a worker running:

```powershell
php artisan queue:work --sleep=3 --tries=3 --timeout=120
```

Email failures are logged and never roll back shipment, fulfilment, or stock updates.

## Future courier APIs

`App\Services\Couriers\CourierProviderInterface` provides the extension point for future providers. `ManualCourierProvider` deliberately makes no HTTP requests and stores no credentials. Provider keys, tokens, and API secrets must stay in the server environment or secret manager—never in Admin Settings or the database.

## Setup

After the normal non-destructive migration, seed the editable courier examples once:

```powershell
php artisan migrate
php artisan db:seed --class=Database\Seeders\CourierSeeder
```

The Settings module also exposes non-secret shipment controls: default courier ID, default processing days, customer tracking visibility, manual event availability, and shipment-email availability.
