<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StorefrontNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_storefront_pages_render_and_mark_their_navigation_item_as_active(): void
    {
        $product = Product::create([
            'name' => 'Navigation Test Piece',
            'description' => 'A considered Cherry Bellemont piece.',
            'price' => '120.00',
            'status' => 'active',
            'stock' => 3,
            'image_path' => 'products/navigation-test-piece.jpg',
        ]);

        $this->assertActiveNavigation(route('home'), 'Home');
        $this->assertActiveNavigation(route('collection'), 'Collection');
        $this->assertActiveNavigation(route('products.show', $product), 'Collection');
        $this->assertActiveNavigation(route('about'), 'About');
    }

    public function test_an_unnamed_route_can_render_the_storefront_layout(): void
    {
        Route::middleware('web')->get('/layout-without-a-route-name', fn () => view('storefront.about'));

        $this->get('/layout-without-a-route-name')
            ->assertOk()
            ->assertSee('CHERRY BELLEMONT');
    }

    private function assertActiveNavigation(string $url, string $expectedLabel): void
    {
        $response = $this->get($url)->assertOk();
        preg_match_all(
            '/<header.*?<a[^>]*aria-current="page"[^>]*>(.*?)<\/a>/s',
            $response->getContent(),
            $matches,
        );

        $this->assertCount(1, $matches[1]);
        $this->assertSame($expectedLabel, trim(strip_tags($matches[1][0])));
    }
}
