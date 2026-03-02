<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Contracts;

/**
 * Immutable data-transfer object that every ProductProvider must return.
 * Implement your own provider and return a collection of these.
 */
final class ProductData
{
    public function __construct(
        public readonly string|int $id,
        public readonly string     $name,
        public readonly string     $slug,
        public readonly float      $price,
        public readonly float      $discountedPrice,
        public readonly int        $stock,
        public readonly bool       $inStock,
        public readonly string     $category      = '',
        public readonly string     $description   = '',
        public readonly string     $thumbnail     = '',
        public readonly array      $meta          = [],   // Any extra fields you want the AI to see
    ) {}

    /**
     * Build from a plain array — handy when mapping Eloquent results.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id:              $data['id'],
            name:            $data['name'],
            slug:            $data['slug'],
            price:           (float) ($data['price'] ?? 0),
            discountedPrice: (float) ($data['discounted_price'] ?? $data['price'] ?? 0),
            stock:           (int)   ($data['stock'] ?? 0),
            inStock:         (bool)  ($data['in_stock'] ?? false),
            category:        (string)($data['category'] ?? ''),
            description:     (string)($data['description'] ?? ''),
            thumbnail:       (string)($data['thumbnail'] ?? ''),
            meta:            (array) ($data['meta'] ?? []),
        );
    }

    /**
     * Render a human-readable summary for injection into the AI system prompt.
     */
    public function toContextLine(int $index): string
    {
        $availability = $this->inStock
            ? "In Stock ({$this->stock} units)"
            : 'Out of Stock';

        $price = $this->discountedPrice < $this->price
            ? "{$this->discountedPrice} (was {$this->price})"
            : (string) $this->price;

        $lines = [
            "{$index}. {$this->name}",
            "   Category   : {$this->category}",
            "   Price      : {$price}",
            "   Availability: {$availability}",
        ];

        if ($this->description !== '') {
            $lines[] = '   Description: ' . mb_substr($this->description, 0, 140);
        }

        // Append any extra meta fields (e.g. brand, rating, SKU)
        foreach ($this->meta as $key => $value) {
            $lines[] = "   {$key}: {$value}";
        }

        $lines[] = '   URL: ' . url("product/{$this->slug}");

        return implode("\n", $lines);
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'slug'            => $this->slug,
            'price'           => $this->price,
            'discounted_price'=> $this->discountedPrice,
            'stock'           => $this->stock,
            'in_stock'        => $this->inStock,
            'category'        => $this->category,
            'description'     => $this->description,
            'thumbnail'       => $this->thumbnail,
            'meta'            => $this->meta,
        ];
    }
}
