<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rakibdevs\AiShopbot\Contracts\ProductData;

class ProductDataTest extends TestCase
{
    public function test_from_array_creates_correctly(): void
    {
        $data = ProductData::fromArray([
            'id'               => 1,
            'name'             => 'Wireless Headphones',
            'slug'             => 'wireless-headphones',
            'price'            => '99.99',
            'discounted_price' => '79.99',
            'stock'            => 15,
            'in_stock'         => true,
            'category'         => 'Electronics',
            'description'      => 'Great headphones',
            'thumbnail'        => '/images/h.jpg',
        ]);

        $this->assertSame(1, $data->id);
        $this->assertSame('Wireless Headphones', $data->name);
        $this->assertSame(99.99, $data->price);
        $this->assertSame(79.99, $data->discountedPrice);
        $this->assertTrue($data->inStock);
        $this->assertSame(15, $data->stock);
    }

    public function test_to_array_round_trips(): void
    {
        $original = [
            'id'               => 5,
            'name'             => 'Sneakers',
            'slug'             => 'sneakers',
            'price'            => 49.99,
            'discounted_price' => 39.99,
            'stock'            => 3,
            'in_stock'         => true,
            'category'         => 'Shoes',
            'description'      => '',
            'thumbnail'        => '',
            'meta'             => [],
        ];

        $data = ProductData::fromArray($original);

        $this->assertEquals($original, $data->toArray());
    }

    public function test_context_line_includes_availability(): void
    {
        $p = new ProductData(
            id: 1, name: 'Laptop', slug: 'laptop',
            price: 999.0, discountedPrice: 899.0,
            stock: 0, inStock: false,
            category: 'Computers'
        );

        $line = $p->toContextLine(1);

        $this->assertStringContainsString('Out of Stock', $line);
        $this->assertStringContainsString('Laptop', $line);
    }
}
