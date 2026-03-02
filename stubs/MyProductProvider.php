<?php

declare(strict_types=1);

namespace App\Chatbot;

use Illuminate\Support\Collection;
use Rakibdevs\AiShopbot\Contracts\ProductData;
use Rakibdevs\AiShopbot\Contracts\ProductProvider;
use App\Models\Product;   // <-- your own Eloquent model

/**
 * ============================================================
 * EXAMPLE: Custom product provider using your own Eloquent model
 * ============================================================
 *
 * 1. Copy this file to app/Chatbot/MyProductProvider.php
 * 2. Update the imports and logic to match your DB schema
 * 3. Register it in config/ai_shopbot.php:
 *
 *    'product_provider' => \App\Chatbot\MyProductProvider::class,
 *
 * Or override in AppServiceProvider:
 *
 *    $this->app->bind(
 *        \Rakibdevs\AiShopbot\Contracts\ProductProvider::class,
 *        \App\Chatbot\MyProductProvider::class,
 *    );
 * ============================================================
 */
class MyProductProvider implements ProductProvider
{
    public function search(string $query, int $limit = 5): Collection
    {
        return Product::query()
            ->where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%")
                  ->orWhere('tags', 'LIKE', "%{$query}%");
            })
            ->with('category')
            ->limit($limit)
            ->get()
            ->map(fn (Product $p) => $this->toProductData($p));
    }

    public function searchByCategory(string|int $category, int $limit = 5): Collection
    {
        return Product::query()
            ->where('status', 'active')
            ->whereHas('category', fn ($q) =>
                $q->where('name', 'LIKE', "%{$category}%")
                  ->orWhere('id', $category)
            )
            ->with('category')
            ->limit($limit)
            ->get()
            ->map(fn (Product $p) => $this->toProductData($p));
    }

    public function find(string|int $identifier): ?ProductData
    {
        $product = Product::query()
            ->where('status', 'active')
            ->where(fn ($q) =>
                $q->where('id', $identifier)
                  ->orWhere('slug', $identifier)
                  ->orWhere('sku', $identifier)
            )
            ->with('category')
            ->first();

        return $product ? $this->toProductData($product) : null;
    }

    public function featured(int $limit = 4): Collection
    {
        return Product::query()
            ->where('status', 'active')
            ->where('featured', true)
            ->with('category')
            ->orderByDesc('sales_count')
            ->limit($limit)
            ->get()
            ->map(fn (Product $p) => $this->toProductData($p));
    }

    // ── Private ────────────────────────────────────────────────────────────────

    private function toProductData(Product $p): ProductData
    {
        return new ProductData(
            id:              $p->id,
            name:            $p->name,
            slug:            $p->slug,
            price:           (float) $p->price,
            discountedPrice: (float) ($p->sale_price ?? $p->price),
            stock:           (int)   $p->stock_qty,
            inStock:         $p->stock_qty > 0,
            category:        $p->category?->name ?? '',
            description:     $p->short_description ?? $p->description ?? '',
            thumbnail:       $p->thumbnail ?? '',
            // Pass any extra fields you want the AI to reference:
            meta:            [
                'SKU'    => $p->sku    ?? '',
                'Brand'  => $p->brand  ?? '',
                'Rating' => $p->rating ?? '',
            ],
        );
    }
}
