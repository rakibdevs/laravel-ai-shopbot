<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Providers\Product;    

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Rakibdevs\AiShopbot\Contracts\ProductData;
use Rakibdevs\AiShopbot\Contracts\ProductProvider;

/**
 * Product provider for the Active eCommerce Laravel CMS database schema.
 *
 * Tables used:
 *   - products          (id, name, slug, unit_price, discount, discount_type,
 *                        thumbnail_img, short_description, description, tags,
 *                        published, approved, category_id)
 *   - product_stocks    (product_id, qty)
 *   - categories        (id, name)
 */
class ActiveEcommerceProductProvider implements ProductProvider
{
    private int  $minStock;
    private bool $includeOutOfStock;

    public function __construct()
    {
        $this->minStock          = (int)  config('ai_shopbot.search.min_stock', 1);
        $this->includeOutOfStock = (bool) config('ai_shopbot.search.include_out_of_stock', false);
    }

    // -------------------------------------------------------------------------
    // ProductProvider contract
    // -------------------------------------------------------------------------

    public function search(string $query, int $limit = 5): Collection
    {
        $terms = $this->tokenize($query);

        if (empty($terms)) {
            return collect();
        }

        return $this->baseQuery()
            ->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $q->orWhere('p.name', 'LIKE', "%{$term}%")
                      ->orWhere('p.tags', 'LIKE', "%{$term}%")
                      ->orWhere('p.short_description', 'LIKE', "%{$term}%")
                      ->orWhere('p.description', 'LIKE', "%{$term}%")
                      ->orWhere('c.name', 'LIKE', "%{$term}%");
                }
            })
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->hydrate($row));
    }

    public function searchByCategory(string|int $category, int $limit = 5): Collection
    {
        return $this->baseQuery()
            ->where(function ($q) use ($category) {
                $q->where('c.name', 'LIKE', "%{$category}%")
                  ->orWhere('c.id', $category);
            })
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->hydrate($row));
    }

    public function find(string|int $identifier): ?ProductData
    {
        $row = $this->baseQuery()
            ->where(function ($q) use ($identifier) {
                $q->where('p.slug', $identifier)
                  ->orWhere('p.id', $identifier)
                  ->orWhere('p.name', 'LIKE', "%{$identifier}%");
            })
            ->first();

        return $row ? $this->hydrate($row) : null;
    }

    public function featured(int $limit = 4): Collection
    {
        return $this->baseQuery()
            ->orderByDesc('p.num_of_sale')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->hydrate($row));
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function baseQuery()
    {
        $builder = DB::table('products as p')
            ->leftJoin('product_stocks as ps', 'ps.product_id', '=', 'p.id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->select([
                'p.id',
                'p.name',
                'p.slug',
                'p.unit_price',
                'p.discount',
                'p.discount_type',
                'p.thumbnail_img',
                'p.short_description',
                'c.name as category_name',
                DB::raw('COALESCE(SUM(ps.qty), 0) as total_stock'),
            ])
            ->where('p.published', 1)
            ->where('p.approved', 1)
            ->groupBy(
                'p.id', 'p.name', 'p.slug', 'p.unit_price',
                'p.discount', 'p.discount_type', 'p.thumbnail_img',
                'p.short_description', 'c.name', 'p.num_of_sale'
            )
            ->orderByDesc('total_stock');

        if (!$this->includeOutOfStock) {
            $builder->havingRaw('COALESCE(SUM(ps.qty), 0) >= ?', [$this->minStock]);
        }

        return $builder;
    }

    private function hydrate(object $row): ProductData
    {
        $price    = (float) $row->unit_price;
        $discount = (float) ($row->discount ?? 0);
        $stock    = (int)   ($row->total_stock ?? 0);

        $discountedPrice = match ($row->discount_type ?? '') {
            'percent' => $price - ($price * ($discount / 100)),
            'flat'    => max(0.0, $price - $discount),
            default   => $price,
        };

        return new ProductData(
            id:              $row->id,
            name:            $row->name,
            slug:            $row->slug,
            price:           round($price, 2),
            discountedPrice: round($discountedPrice, 2),
            stock:           $stock,
            inStock:         $stock >= $this->minStock,
            category:        $row->category_name ?? '',
            description:     $row->short_description ?? '',
            thumbnail:       $row->thumbnail_img ?? '',
        );
    }

    /**
     * Tokenise a raw user query, remove stop-words and short tokens.
     */
    private function tokenize(string $query): array
    {
        static $stopWords = [
            'a','an','the','is','are','was','were','do','does','did',
            'i','me','my','we','you','your','find','show','get','want',
            'need','looking','for','some','any','have','has','can','could',
            'available','products','product','item','items','buy','cheap',
            'please','hello','hi','hey','tell','about','price','cost',
        ];

        $tokens = preg_split('/[\s,;]+/', strtolower(trim($query)));

        return array_values(
            array_unique(
                array_filter(
                    $tokens,
                    fn ($t) => strlen($t) > 2 && !in_array($t, $stopWords, true)
                )
            )
        );
    }
}
