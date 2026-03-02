<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Providers\Product;

use Illuminate\Support\Collection;
use Rakibdevs\AiShopbot\Contracts\ProductData;
use Rakibdevs\AiShopbot\Contracts\ProductProvider;

/**
 * Static/in-memory provider — useful for testing or demo purposes.
 *
 * Usage:
 *   StaticProductProvider::withProducts([
 *       ProductData::fromArray([...]),
 *   ]);
 */
class StaticProductProvider implements ProductProvider
{
    /** @var Collection<ProductData> */
    private Collection $products;

    public function __construct(array $products = [])
    {
        $this->products = collect($products);
    }

    public static function withProducts(array $products): self
    {
        return new self($products);
    }

    public function search(string $query, int $limit = 5): Collection
    {
        $q = strtolower($query);

        return $this->products
            ->filter(fn (ProductData $p) =>
                str_contains(strtolower($p->name), $q) ||
                str_contains(strtolower($p->description), $q) ||
                str_contains(strtolower($p->category), $q)
            )
            ->take($limit)
            ->values();
    }

    public function searchByCategory(string|int $category, int $limit = 5): Collection
    {
        $cat = strtolower((string) $category);

        return $this->products
            ->filter(fn (ProductData $p) =>
                str_contains(strtolower($p->category), $cat)
            )
            ->take($limit)
            ->values();
    }

    public function find(string|int $identifier): ?ProductData
    {
        return $this->products->first(fn (ProductData $p) =>
            (string) $p->id === (string) $identifier ||
            $p->slug === $identifier ||
            strtolower($p->name) === strtolower((string) $identifier)
        );
    }

    public function featured(int $limit = 4): Collection
    {
        return $this->products->take($limit)->values();
    }
}
