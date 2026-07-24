@php($pendingReceiptCount = \App\Models\PaymentReceipt::query()->where('status', 'pending')->count())
@php($pendingReturnCount = \App\Models\ReturnRequest::query()->whereIn('status', ['requested', 'under_review'])->count())
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Atelier | Cherry Bellemont' }}</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-wine text-cream antialiased">
    <div class="min-h-screen md:flex">
        <aside class="border-b border-cream/15 bg-wine-deep p-6 md:min-h-screen md:w-64 md:border-b-0 md:border-r">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <a class="font-display tracking-[.18em]" href="{{ route('admin.dashboard') }}">CHERRY BELLEMONT</a>
                    <p class="mt-2 text-xs uppercase tracking-widest text-gold">Atelier</p>
                </div>
                <a class="nav-icon md:hidden" href="{{ route('home') }}" aria-label="View Store"><i class="bi bi-shop" aria-hidden="true"></i></a>
            </div>
            <nav class="mt-10 grid gap-2 text-sm" aria-label="Admin navigation">
                <a class="admin-nav {{ request()->routeIs('admin.dashboard') ? 'admin-nav-active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a class="admin-nav {{ request()->routeIs('admin.products.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.products.index') }}"><i class="bi bi-box-seam"></i> Products</a>
                <a class="admin-nav {{ request()->routeIs('admin.product-stock-notifications.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.product-stock-notifications.index') }}"><i class="bi bi-bell"></i> Back-in-Stock</a>
                <a class="admin-nav {{ request()->routeIs('admin.reviews.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.reviews.index') }}"><i class="bi bi-chat-square-quote"></i> Reviews</a>
                <a class="admin-nav {{ request()->routeIs('admin.orders.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.orders.index') }}"><i class="bi bi-handbag-fill"></i> Orders</a>
                <a class="admin-nav {{ request()->routeIs('admin.shipments.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.shipments.index') }}"><i class="bi bi-truck"></i> Shipments</a>
                <a class="admin-nav {{ request()->routeIs('admin.returns.*', 'admin.refunds.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.returns.index') }}"><i class="bi bi-arrow-repeat"></i> Returns &amp; Refunds @if($pendingReturnCount)<span class="ml-auto bg-gold px-2 py-0.5 text-xs text-wine">{{ $pendingReturnCount }}</span>@endif</a>
                <a class="admin-nav {{ request()->routeIs('admin.payment-receipts.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.payment-receipts.index') }}"><i class="bi bi-credit-card"></i> Payment Verification @if($pendingReceiptCount)<span class="ml-auto rounded-full bg-gold px-2 py-0.5 text-xs text-wine">{{ $pendingReceiptCount }}</span>@endif</a>
                <a class="admin-nav {{ request()->routeIs('admin.shipping-zones.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.shipping-zones.index') }}"><i class="bi bi-geo-alt"></i> Shipping Zones</a>
                <a class="admin-nav {{ request()->routeIs('admin.delivery-methods.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.delivery-methods.index') }}"><i class="bi bi-truck"></i> Delivery Methods</a>
                <a class="admin-nav {{ request()->routeIs('admin.couriers.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.couriers.index') }}"><i class="bi bi-signpost-split"></i> Couriers</a>
                <a class="admin-nav {{ request()->routeIs('admin.coupons.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.coupons.index') }}"><i class="bi bi-ticket-perforated"></i> Coupons</a>
                <a class="admin-nav {{ request()->routeIs('admin.newsletter.index', 'admin.newsletter.unsubscribe', 'admin.newsletter.export', 'admin.newsletter.destroy') ? 'admin-nav-active' : '' }}" href="{{ route('admin.newsletter.index') }}"><i class="bi bi-envelope-paper"></i> Newsletter Subscribers</a>
                <a class="admin-nav {{ request()->routeIs('admin.newsletter.campaigns.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.newsletter.campaigns.index') }}"><i class="bi bi-send"></i> Newsletter Campaigns</a>
                <a class="admin-nav {{ request()->routeIs('admin.faqs.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.faqs.index') }}"><i class="bi bi-patch-question"></i> FAQ</a>
                <a class="admin-nav {{ request()->routeIs('admin.customers.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.customers.index') }}"><i class="bi bi-people"></i> Customers</a>
                <a class="admin-nav {{ request()->routeIs('admin.reports.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.reports.index') }}"><i class="bi bi-graph-up"></i> Reports</a>
                <a class="admin-nav {{ request()->routeIs('admin.settings.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.settings.index') }}"><i class="bi bi-gear"></i> Settings</a>
                <a class="admin-nav mt-6" href="{{ route('home') }}"><i class="bi bi-shop"></i> View Store</a>
                <form method="POST" action="{{ route('logout') }}">@csrf <button class="admin-nav w-full" type="submit"><i class="bi bi-box-arrow-right"></i> Sign Out</button></form>
            </nav>
        </aside>
        <main class="min-w-0 flex-1">{{ $slot }}</main>
    </div>
</body>
</html>
