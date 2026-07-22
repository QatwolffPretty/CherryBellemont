<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_responses_include_the_baseline_security_headers(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_private_customer_and_admin_pages_are_not_indexable(): void
    {
        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_sitemap_contains_only_public_pages_and_active_products(): void
    {
        Cache::forget('public-sitemap:v1');
        $active = Product::create([
            'name' => 'Sitemap Dress',
            'description' => 'A public collection piece.',
            'price' => '120.00',
            'stock' => 3,
            'status' => 'active',
        ]);
        $hidden = Product::create([
            'name' => 'Hidden Dress',
            'description' => 'Not public.',
            'price' => '120.00',
            'stock' => 3,
            'status' => 'draft',
        ]);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('home'), false)
            ->assertSee(route('products.show', $active), false)
            ->assertDontSee(route('products.show', $hidden), false)
            ->assertDontSee('/orders/', false)
            ->assertDontSee('/admin/', false);
    }

    public function test_missing_pages_use_the_branded_safe_404_response(): void
    {
        config()->set('app.debug', false);

        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertDontSee('Stack trace');
    }
}
