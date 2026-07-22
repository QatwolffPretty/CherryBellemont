<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('public-sitemap:v1', now()->addHour(), function (): string {
            $entries = [
                ['url' => route('home'), 'lastmod' => null],
                ['url' => route('collection'), 'lastmod' => null],
                ['url' => route('about'), 'lastmod' => null],
                ['url' => route('faq.index'), 'lastmod' => null],
                ['url' => route('contact'), 'lastmod' => null],
                ['url' => route('shipping.policy'), 'lastmod' => null],
                ['url' => route('refund.policy'), 'lastmod' => null],
                ['url' => route('privacy.policy'), 'lastmod' => null],
                ['url' => route('terms.policy'), 'lastmod' => null],
            ];

            Product::query()
                ->where('status', 'active')
                ->orderBy('id')
                ->cursor()
                ->each(function (Product $product) use (&$entries): void {
                    $entries[] = [
                        'url' => route('products.show', $product),
                        'lastmod' => $product->updated_at,
                    ];
                });

            $urls = collect($entries)->map(function (array $entry): string {
                $location = htmlspecialchars($entry['url'], ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $lastmod = $entry['lastmod']?->toAtomString();

                return '  <url><loc>'.$location.'</loc>'.($lastmod ? '<lastmod>'.$lastmod.'</lastmod>' : '').'</url>';
            })->implode(PHP_EOL);

            return '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL
                .$urls.PHP_EOL
                .'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
