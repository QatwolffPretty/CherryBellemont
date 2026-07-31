<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_restores_every_existing_module_in_its_workflow_group(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Dashboard', 'Accounting', 'Catalog', 'Marketing', 'Reports', 'Sales', 'Settings'])
            ->assertSeeInOrder(['Accounting Dashboard', 'Cash Flow', 'Chart of Accounts', 'Expenses', 'Financial Settings', 'General Ledger', 'Income', 'Owner Compensation', 'Profit &amp; Loss', 'Sales Summary', 'Transactions'], false)
            ->assertSeeInOrder(['Back-in-Stock Requests', 'Categories', 'Collection Tags', 'Colours', 'Inventory', 'Products', 'Sizes'], false)
            ->assertSeeInOrder(['Coupons', 'Customers', 'FAQ', 'Newsletter', 'Newsletter Campaigns', 'Reviews'], false)
            ->assertSeeInOrder(['Customer Reports', 'Inventory Reports', 'Product Reports', 'Sales Reports'], false)
            ->assertSeeInOrder(['Couriers', 'Delivery Methods', 'Orders', 'Payment Verification', 'Returns', 'Shipments', 'Shipping Zones'], false)
            ->assertSee('href="'.route('admin.newsletter.index').'"', false)
            ->assertSee('href="'.route('admin.newsletter.campaigns.index').'"', false)
            ->assertSee('href="'.route('admin.payment-receipts.index').'"', false)
            ->assertSee('href="'.route('admin.product-stock-notifications.index').'"', false)
            ->assertSee('href="'.route('admin.couriers.index').'"', false);
    }

    public function test_active_child_routes_expand_and_highlight_their_parent_group(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('id="sidebar-marketing"', false)
            ->assertSee('admin-nav-group-active', false)
            ->assertSee('href="'.route('admin.customers.index').'"', false);

        $this->actingAs($admin)->get(route('admin.accounting.cash-flow.index'))
            ->assertOk()
            ->assertSee('id="sidebar-accounting"', false)
            ->assertSee('href="'.route('admin.accounting.cash-flow.index').'"', false);

        $this->actingAs($admin)->get(route('admin.reports.index', ['section' => 'inventory']))
            ->assertOk()
            ->assertSee('id="sidebar-reports"', false)
            ->assertSee('id="inventory-reports"', false);

        $this->actingAs($admin)->get(route('admin.payment-receipts.index'))
            ->assertOk()
            ->assertSee('id="sidebar-sales"', false)
            ->assertSee('href="'.route('admin.payment-receipts.index').'"', false);
    }
}
