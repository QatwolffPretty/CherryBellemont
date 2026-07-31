@php($pendingReceiptCount = \App\Models\PaymentReceipt::query()->where('status', 'pending')->count())
@php($pendingReturnCount = \App\Models\ReturnRequest::query()->whereIn('status', ['requested', 'under_review'])->count())
@php($catalogActive = request()->routeIs('admin.products.*', 'admin.categories.*', 'admin.product-sizes.*', 'admin.product-colours.*', 'admin.product-tags.*', 'admin.product-stock-notifications.*'))
@php($inventoryActive = request()->routeIs('admin.products.index') && request()->boolean('low_stock'))
@php($productActive = request()->routeIs('admin.products.*') && ! $inventoryActive)
@php($salesActive = request()->routeIs('admin.orders.*', 'admin.payment-receipts.*', 'admin.returns.*', 'admin.refunds.*', 'admin.shipments.*', 'admin.shipping-zones.*', 'admin.delivery-methods.*', 'admin.couriers.*'))
@php($ordersActive = request()->routeIs('admin.orders.*'))
@php($paymentVerificationActive = request()->routeIs('admin.payment-receipts.*'))
@php($returnsActive = request()->routeIs('admin.returns.*', 'admin.refunds.*'))
@php($shipmentsActive = request()->routeIs('admin.shipments.*'))
@php($shippingZonesActive = request()->routeIs('admin.shipping-zones.*'))
@php($deliveryMethodsActive = request()->routeIs('admin.delivery-methods.*'))
@php($couriersActive = request()->routeIs('admin.couriers.*'))
@php($marketingActive = request()->routeIs('admin.coupons.*', 'admin.customers.*', 'admin.faqs.*', 'admin.newsletter.*', 'admin.reviews.*'))
@php($reportsActive = request()->routeIs('admin.reports.*'))
@php($reportSection = request()->query('section', 'sales'))
@php($accountingActive = request()->routeIs('admin.accounting.*'))
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
        <aside class="flex flex-col border-b border-cream/15 bg-wine-deep p-6 md:min-h-screen md:w-64 md:border-b-0 md:border-r">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <a class="font-display tracking-[.18em]" href="{{ route('admin.dashboard') }}">CHERRY BELLEMONT</a>
                    <p class="mt-2 text-xs uppercase tracking-widest text-gold">Atelier</p>
                </div>
                <a class="nav-icon md:hidden" href="{{ route('home') }}" aria-label="View Store"><i class="bi bi-shop" aria-hidden="true"></i></a>
            </div>

            <nav class="mt-6 flex flex-1 flex-col text-sm" aria-label="Admin navigation">
                <div class="flex flex-col gap-1">
                    <a class="admin-nav {{ request()->routeIs('admin.dashboard') ? 'admin-nav-active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>

                    <details class="admin-nav-group" data-sidebar-group @if($accountingActive) open @endif>
                        <summary class="admin-nav admin-nav-summary {{ $accountingActive ? 'admin-nav-group-active' : '' }}" aria-controls="sidebar-accounting" aria-expanded="{{ $accountingActive ? 'true' : 'false' }}"><span><i class="bi bi-calculator"></i> Accounting</span><i class="bi bi-chevron-down admin-nav-chevron" aria-hidden="true"></i></summary>
                        <div id="sidebar-accounting" class="admin-nav-children">
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.accounting.overview') ? 'admin-nav-active' : '' }}" href="{{ route('admin.accounting.overview') }}"><i class="bi bi-speedometer2"></i> Accounting Dashboard</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.accounting.cash-flow.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.accounting.cash-flow.index') }}"><i class="bi bi-cash-stack"></i> Cash Flow</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.accounting.accounts.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.accounting.accounts.index') }}"><i class="bi bi-diagram-3"></i> Chart of Accounts</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.accounting.expenses.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.accounting.expenses.index') }}"><i class="bi bi-arrow-up-circle"></i> Expenses</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.accounting.settings.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.accounting.settings.index') }}"><i class="bi bi-sliders"></i> Financial Settings</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.accounting.ledger.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.accounting.ledger.index') }}"><i class="bi bi-journal-bookmark"></i> General Ledger</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.accounting.income.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.accounting.income.index') }}"><i class="bi bi-arrow-down-circle"></i> Income</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.accounting.owner-transactions.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.accounting.owner-transactions.index') }}"><i class="bi bi-person-badge"></i> Owner Compensation</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.accounting.profit-loss.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.accounting.profit-loss.index') }}"><i class="bi bi-graph-up-arrow"></i> Profit &amp; Loss</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.accounting.sales-summary') ? 'admin-nav-active' : '' }}" href="{{ route('admin.accounting.sales-summary') }}"><i class="bi bi-bar-chart-line"></i> Sales Summary</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.accounting.journals.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.accounting.journals.index') }}"><i class="bi bi-journal-text"></i> Transactions</a>
                        </div>
                    </details>

                    <details class="admin-nav-group" data-sidebar-group @if($catalogActive) open @endif>
                        <summary class="admin-nav admin-nav-summary {{ $catalogActive ? 'admin-nav-group-active' : '' }}" aria-controls="sidebar-catalog" aria-expanded="{{ $catalogActive ? 'true' : 'false' }}"><span><i class="bi bi-box-seam"></i> Catalog</span><i class="bi bi-chevron-down admin-nav-chevron" aria-hidden="true"></i></summary>
                        <div id="sidebar-catalog" class="admin-nav-children">
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.product-stock-notifications.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.product-stock-notifications.index') }}"><i class="bi bi-bell"></i> Back-in-Stock Requests</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.categories.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.categories.index') }}"><i class="bi bi-tags"></i> Categories</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.product-tags.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.product-tags.index') }}"><i class="bi bi-bookmark-star"></i> Collection Tags</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.product-colours.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.product-colours.index') }}"><i class="bi bi-palette"></i> Colours</a>
                            <a class="admin-nav admin-nav-sub {{ $inventoryActive ? 'admin-nav-active' : '' }}" href="{{ route('admin.products.index', ['low_stock' => 1]) }}"><i class="bi bi-boxes"></i> Inventory</a>
                            <a class="admin-nav admin-nav-sub {{ $productActive ? 'admin-nav-active' : '' }}" href="{{ route('admin.products.index') }}"><i class="bi bi-box-seam"></i> Products</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.product-sizes.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.product-sizes.index') }}"><i class="bi bi-rulers"></i> Sizes</a>
                        </div>
                    </details>

                    <details class="admin-nav-group" data-sidebar-group @if($marketingActive) open @endif>
                        <summary class="admin-nav admin-nav-summary {{ $marketingActive ? 'admin-nav-group-active' : '' }}" aria-controls="sidebar-marketing" aria-expanded="{{ $marketingActive ? 'true' : 'false' }}"><span><i class="bi bi-megaphone"></i> Marketing</span><i class="bi bi-chevron-down admin-nav-chevron" aria-hidden="true"></i></summary>
                        <div id="sidebar-marketing" class="admin-nav-children">
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.coupons.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.coupons.index') }}"><i class="bi bi-ticket-perforated"></i> Coupons</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.customers.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.customers.index') }}"><i class="bi bi-people"></i> Customers</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.faqs.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.faqs.index') }}"><i class="bi bi-question-circle"></i> FAQ</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.newsletter.*') && ! request()->routeIs('admin.newsletter.campaigns.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.newsletter.index') }}"><i class="bi bi-envelope-paper"></i> Newsletter</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.newsletter.campaigns.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.newsletter.campaigns.index') }}"><i class="bi bi-send"></i> Newsletter Campaigns</a>
                            <a class="admin-nav admin-nav-sub {{ request()->routeIs('admin.reviews.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.reviews.index') }}"><i class="bi bi-chat-square-quote"></i> Reviews</a>
                        </div>
                    </details>

                    <details class="admin-nav-group" data-sidebar-group @if($reportsActive) open @endif>
                        <summary class="admin-nav admin-nav-summary {{ $reportsActive ? 'admin-nav-group-active' : '' }}" aria-controls="sidebar-reports" aria-expanded="{{ $reportsActive ? 'true' : 'false' }}"><span><i class="bi bi-graph-up"></i> Reports</span><i class="bi bi-chevron-down admin-nav-chevron" aria-hidden="true"></i></summary>
                        <div id="sidebar-reports" class="admin-nav-children">
                            <a class="admin-nav admin-nav-sub {{ $reportsActive && $reportSection === 'customers' ? 'admin-nav-active' : '' }}" href="{{ route('admin.reports.index', ['section' => 'customers']) }}#customer-reports"><i class="bi bi-people"></i> Customer Reports</a>
                            <a class="admin-nav admin-nav-sub {{ $reportsActive && $reportSection === 'inventory' ? 'admin-nav-active' : '' }}" href="{{ route('admin.reports.index', ['section' => 'inventory']) }}#inventory-reports"><i class="bi bi-boxes"></i> Inventory Reports</a>
                            <a class="admin-nav admin-nav-sub {{ $reportsActive && $reportSection === 'products' ? 'admin-nav-active' : '' }}" href="{{ route('admin.reports.index', ['section' => 'products']) }}#product-reports"><i class="bi bi-box-seam"></i> Product Reports</a>
                            <a class="admin-nav admin-nav-sub {{ $reportsActive && $reportSection === 'sales' ? 'admin-nav-active' : '' }}" href="{{ route('admin.reports.index', ['section' => 'sales']) }}#sales-reports"><i class="bi bi-bar-chart-line"></i> Sales Reports</a>
                        </div>
                    </details>

                    <details class="admin-nav-group" data-sidebar-group @if($salesActive) open @endif>
                        <summary class="admin-nav admin-nav-summary {{ $salesActive ? 'admin-nav-group-active' : '' }}" aria-controls="sidebar-sales" aria-expanded="{{ $salesActive ? 'true' : 'false' }}"><span><i class="bi bi-handbag-fill"></i> Sales</span><i class="bi bi-chevron-down admin-nav-chevron" aria-hidden="true"></i></summary>
                        <div id="sidebar-sales" class="admin-nav-children">
                            <a class="admin-nav admin-nav-sub {{ $couriersActive ? 'admin-nav-active' : '' }}" href="{{ route('admin.couriers.index') }}"><i class="bi bi-truck-flatbed"></i> Couriers</a>
                            <a class="admin-nav admin-nav-sub {{ $deliveryMethodsActive ? 'admin-nav-active' : '' }}" href="{{ route('admin.delivery-methods.index') }}"><i class="bi bi-signpost-split"></i> Delivery Methods</a>
                            <a class="admin-nav admin-nav-sub {{ $ordersActive ? 'admin-nav-active' : '' }}" href="{{ route('admin.orders.index') }}"><i class="bi bi-receipt"></i> Orders</a>
                            <a class="admin-nav admin-nav-sub {{ $paymentVerificationActive ? 'admin-nav-active' : '' }}" href="{{ route('admin.payment-receipts.index') }}"><i class="bi bi-patch-check"></i> Payment Verification @if($pendingReceiptCount)<span class="ml-auto bg-gold px-2 py-0.5 text-xs text-wine">{{ $pendingReceiptCount }}</span>@endif</a>
                            <a class="admin-nav admin-nav-sub {{ $returnsActive ? 'admin-nav-active' : '' }}" href="{{ route('admin.returns.index') }}"><i class="bi bi-arrow-return-left"></i> Returns @if($pendingReturnCount)<span class="ml-auto bg-gold px-2 py-0.5 text-xs text-wine">{{ $pendingReturnCount }}</span>@endif</a>
                            <a class="admin-nav admin-nav-sub {{ $shipmentsActive ? 'admin-nav-active' : '' }}" href="{{ route('admin.shipments.index') }}"><i class="bi bi-truck"></i> Shipments</a>
                            <a class="admin-nav admin-nav-sub {{ $shippingZonesActive ? 'admin-nav-active' : '' }}" href="{{ route('admin.shipping-zones.index') }}"><i class="bi bi-map"></i> Shipping Zones</a>
                        </div>
                    </details>
                </div>

                <div class="mt-auto flex flex-col gap-1 border-t border-cream/15 pt-4">
                    <a class="admin-nav {{ request()->routeIs('admin.settings.*') ? 'admin-nav-active' : '' }}" href="{{ route('admin.settings.index') }}"><i class="bi bi-gear"></i> Settings</a>
                    <a class="admin-nav" href="{{ route('home') }}"><i class="bi bi-shop"></i> View Store</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf <button class="admin-nav w-full" type="submit"><i class="bi bi-box-arrow-right"></i> Sign Out</button></form>
                </div>
            </nav>
        </aside>
        <main class="min-w-0 flex-1">{{ $slot }}</main>
    </div>
</body>
</html>
