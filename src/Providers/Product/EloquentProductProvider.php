<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Providers\Product;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Rakibdevs\AiShopbot\Contracts\ProductData;
use Rakibdevs\AiShopbot\Contracts\ProductProvider;
use Rakibdevs\AiShopbot\Exceptions\ChatbotException;

/**
 * A flexible provider for any Eloquent model.
 *
 * Configure via ai_shopbot.php:
 *
 *   'eloquent' => [
 *       'model'  => App\Models\Product::class,
 *       'fields' => [
 *           'id'          => 'id',
 *           'name'        => 'name',
 *           'slug'        => 'slug',
 *           'price'       => 'price',
 *           'discounted'  => 'sale_price',   // nullable
 *           'stock'       => 'stock_qty',
 *           'category'    => 'category_name',// can be a relation string "category.name"
 *           'description' => 'description',
 *           'thumbnail'   => 'image',
 *       ],
 *       'search_fields' => ['name', 'description', 'tags'],
 *       'active_scope'  => 'active',   // optional Eloquent scope name
 *   ],
 */
class EloquentProductProvider implements ProductProvider
{
    private Model  $model;
    private array  $fields;
    private array  $searchFields;
    private ?string $activeScope;
    private int    $minStock;

    public function __construct()
    {
        $cfg = config('ai_shopbot.eloquent');

        if (empty($cfg['model'])) {
            throw new ChatbotException(
                'EloquentProductProvider requires ai_shopbot.eloquent.model to be set.'
            );
        }

        $this->model        = app($cfg['model']);
        $this->fields       = $cfg['fields']         ?? [];
        $this->searchFields = $cfg['search_fields']  ?? ['name', 'description'];
        $this->activeScope  = $cfg['active_scope']   ?? null;
        $this->minStock     = (int) config('ai_shopbot.search.min_stock', 1);
    }

    public function search(string $query, int $limit = 5): Collection
    {
        $query = trim($query);

        return $this->newQuery()
            ->where(function (Builder $q) use ($query) {
                foreach ($this->searchFields as $field) {
                    $q->orWhere($field, 'LIKE', "%{$query}%");
                }
            })
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->hydrate($row));
    }

    public function searchByCategory(string|int $category, int $limit = 5): Collection
    {
        $catField = $this->fields['category'] ?? 'category';

        // Support dot-notation for relations (e.g. "category.name")
        if (str_contains($catField, '.')) {
            [$relation, $column] = explode('.', $catField, 2);

            return $this->newQuery()
                ->whereHas($relation, fn (Builder $q) =>
                    $q->where($column, 'LIKE', "%{$category}%")
                      ->orWhere('id', $category)
                )
                ->limit($limit)
                ->get()
                ->map(fn ($row) => $this->hydrate($row));
        }

        return $this->newQuery()
            ->where(fn (Builder $q) =>
                $q->where($catField, 'LIKE', "%{$category}%")
                  ->orWhere($catField, $category)
            )
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->hydrate($row));
    }

    public function find(string|int $identifier): ?ProductData
    {
        $row = $this->newQuery()
            ->where(fn (Builder $q) =>
                $q->where($this->fields['id']   ?? 'id',   $identifier)
                  ->orWhere($this->fields['slug'] ?? 'slug', $identifier)
                  ->orWhere($this->fields['name'] ?? 'name', 'LIKE', "%{$identifier}%")
            )
            ->first();

        return $row ? $this->hydrate($row) : null;
    }

    public function featured(int $limit = 4): Collection
    {
        return $this->newQuery()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->hydrate($row));
    }

    // -------------------------------------------------------------------------

    private function newQuery(): Builder
    {
        $query = $this->model->newQuery();

        if ($this->activeScope) {
            $query->{$this->activeScope}();
        }

        return $query;
    }

    private function hydrate(Model $row): ProductData
    {
        $get = fn (string $key, mixed $default = '') =>
            data_get($row, $this->fields[$key] ?? $key, $default);

        $price    = (float) $get('price', 0);
        $discounted = (float) ($get('discounted') ?: $price);
        $stock    = (int)   $get('stock', 0);

        return new ProductData(
            id:              $get('id'),
            name:            (string) $get('name'),
            slug:            (string) $get('slug'),
            price:           round($price, 2),
            discountedPrice: round($discounted, 2),
            stock:           $stock,
            inStock:         $stock >= $this->minStock,
            category:        (string) $get('category', ''),
            description:     (string) $get('description', ''),
            thumbnail:       (string) $get('thumbnail', ''),
        );
    }
}
