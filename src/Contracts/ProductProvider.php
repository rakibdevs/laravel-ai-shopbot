<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Contracts;

use Illuminate\Support\Collection;

/**
 * Implement this interface to plug your product source into the AI chatbot.
 *
 * Examples:
 *   - ActiveEcommerceProductProvider  (queries the Active eCommerce DB schema)
 *   - WooCommerceProductProvider      (calls the WC REST API)
 *   - ShopifyProductProvider          (calls the Shopify GraphQL API)
 *   - ElasticSearchProductProvider    (queries an ES index)
 *   - StaticProductProvider           (returns hard-coded fixture data for testing)
 */
interface ProductProvider
{
    /**
     * Full-text search across product name, description, tags, and category.
     *
     * @param  string              $query   Raw user query, e.g. "wireless headphones under 50"
     * @param  int                 $limit   Max number of results
     * @return Collection<ProductData>
     */
    public function search(string $query, int $limit = 5): Collection;

    /**
     * Return products belonging to a specific category.
     *
     * @param  string|int          $category  Category name or id
     * @param  int                 $limit
     * @return Collection<ProductData>
     */
    public function searchByCategory(string|int $category, int $limit = 5): Collection;

    /**
     * Find a single product by its slug, SKU, or ID.
     *
     * @param  string|int          $identifier
     * @return ProductData|null
     */
    public function find(string|int $identifier): ?ProductData;

    /**
     * Return featured / best-selling / newest products.
     * Used when the user asks "what's popular?" or opens the chat for the first time.
     *
     * @param  int                 $limit
     * @return Collection<ProductData>
     */
    public function featured(int $limit = 4): Collection;
}
