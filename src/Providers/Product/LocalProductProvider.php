<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Providers\Product;

use Illuminate\Support\Collection;
use Rakibdevs\AiShopbot\Contracts\ProductData;
use Rakibdevs\AiShopbot\Contracts\ProductProvider;
class LocalProductProvider implements ProductProvider
{
    /**
     * All dummy products live here.
     * Add / remove / edit freely to match your real catalogue shape.
     *
     * Fields mirror ProductData::fromArray() exactly.
     */
    private function catalogue(): array
    {
        return [
            // ── Electronics ───────────────────────────────────────────────────
            [
                'id'               => 1,
                'name'             => 'Wireless Noise-Cancelling Headphones',
                'slug'             => 'noise-cancelling-headphones',
                'price'            => 149.99,
                'discounted_price' => 99.99,
                'stock'            => 23,
                'in_stock'         => true,
                'category'         => 'Electronics',
                'description'      => 'Premium over-ear headphones with 40h battery life, active noise cancellation and foldable design.',
                'thumbnail'        => 'https://picsum.photos/seed/headphones/300/300',
                'meta'             => ['brand' => 'SoundMax', 'sku' => 'SM-WH-001', 'rating' => '4.8/5'],
            ],
            [
                'id'               => 2,
                'name'             => 'Bluetooth Portable Speaker',
                'slug'             => 'bluetooth-portable-speaker',
                'price'            => 79.99,
                'discounted_price' => 59.99,
                'stock'            => 41,
                'in_stock'         => true,
                'category'         => 'Electronics',
                'description'      => 'IPX7 waterproof speaker, 360° sound, 20h playtime. Perfect for outdoor use.',
                'thumbnail'        => 'https://picsum.photos/seed/speaker/300/300',
                'meta'             => ['brand' => 'AudioPro', 'sku' => 'AP-BS-202'],
            ],
            [
                'id'               => 3,
                'name'             => 'Mechanical Gaming Keyboard',
                'slug'             => 'mechanical-gaming-keyboard',
                'price'            => 129.00,
                'discounted_price' => 99.00,
                'stock'            => 15,
                'in_stock'         => true,
                'category'         => 'Electronics',
                'description'      => 'TKL layout, Cherry MX Blue switches, per-key RGB backlighting, aluminium body.',
                'thumbnail'        => 'https://picsum.photos/seed/keyboard/300/300',
                'meta'             => ['brand' => 'TypeMaster', 'sku' => 'TM-KB-TKL'],
            ],
            [
                'id'               => 4,
                'name'             => 'Wireless Charging Pad',
                'slug'             => 'wireless-charging-pad',
                'price'            => 34.99,
                'discounted_price' => 24.99,
                'stock'            => 60,
                'in_stock'         => true,
                'category'         => 'Electronics',
                'description'      => '15W fast wireless charging, Qi-certified, compatible with iPhone & Android.',
                'thumbnail'        => 'https://picsum.photos/seed/charger/300/300',
                'meta'             => ['brand' => 'ChargeFast', 'sku' => 'CF-WC-15W'],
            ],
            [
                'id'               => 5,
                'name'             => 'USB-C Hub 7-in-1',
                'slug'             => 'usbc-hub-7in1',
                'price'            => 49.99,
                'discounted_price' => 49.99,
                'stock'            => 0,
                'in_stock'         => false,
                'category'         => 'Electronics',
                'description'      => '4K HDMI, 3× USB-A 3.0, SD/microSD, 100W PD pass-through. Out of stock.',
                'thumbnail'        => 'https://picsum.photos/seed/hub/300/300',
                'meta'             => ['brand' => 'ConnectPro', 'sku' => 'CP-HUB-7'],
            ],

            // ── Footwear ──────────────────────────────────────────────────────
            [
                'id'               => 6,
                'name'             => 'Men\'s Trail Running Shoes',
                'slug'             => 'mens-trail-running-shoes',
                'price'            => 119.99,
                'discounted_price' => 89.99,
                'stock'            => 18,
                'in_stock'         => true,
                'category'         => 'Footwear',
                'description'      => 'Lightweight mesh upper, aggressive lugged outsole for off-road traction. Sizes 7–13.',
                'thumbnail'        => 'https://picsum.photos/seed/shoes1/300/300',
                'meta'             => ['brand' => 'TrailX', 'sku' => 'TX-MS-001', 'sizes' => '7-13'],
            ],
            [
                'id'               => 7,
                'name'             => 'Women\'s Running Sneakers',
                'slug'             => 'womens-running-sneakers',
                'price'            => 109.99,
                'discounted_price' => 109.99,
                'stock'            => 9,
                'in_stock'         => true,
                'category'         => 'Footwear',
                'description'      => 'Responsive cushioning, breathable knit upper, heel-to-toe drop 8mm.',
                'thumbnail'        => 'https://picsum.photos/seed/shoes2/300/300',
                'meta'             => ['brand' => 'RunFit', 'sku' => 'RF-WS-002', 'sizes' => '5-11'],
            ],
            [
                'id'               => 8,
                'name'             => 'Leather Chelsea Boots',
                'slug'             => 'leather-chelsea-boots',
                'price'            => 189.00,
                'discounted_price' => 149.00,
                'stock'            => 0,
                'in_stock'         => false,
                'category'         => 'Footwear',
                'description'      => 'Full-grain leather, elastic side panels, leather sole. Currently out of stock.',
                'thumbnail'        => 'https://picsum.photos/seed/boots/300/300',
                'meta'             => ['brand' => 'UrbanStep', 'sku' => 'US-CB-001'],
            ],

            // ── Sports & Fitness ──────────────────────────────────────────────
            [
                'id'               => 9,
                'name'             => 'Non-Slip Yoga Mat',
                'slug'             => 'non-slip-yoga-mat',
                'price'            => 45.00,
                'discounted_price' => 35.00,
                'stock'            => 55,
                'in_stock'         => true,
                'category'         => 'Sports & Fitness',
                'description'      => '6mm thick TPE foam, eco-friendly, includes carrying strap. 183cm × 61cm.',
                'thumbnail'        => 'https://picsum.photos/seed/yoga/300/300',
                'meta'             => ['brand' => 'FlexFit', 'sku' => 'FF-YM-6MM'],
            ],
            [
                'id'               => 10,
                'name'             => 'Adjustable Dumbbell Set 5–52.5 lbs',
                'slug'             => 'adjustable-dumbbell-set',
                'price'            => 349.00,
                'discounted_price' => 299.00,
                'stock'            => 7,
                'in_stock'         => true,
                'category'         => 'Sports & Fitness',
                'description'      => 'Replaces 15 sets of weights. Dial-adjust mechanism, space-saving tray included.',
                'thumbnail'        => 'https://picsum.photos/seed/dumbbell/300/300',
                'meta'             => ['brand' => 'PowerLift', 'sku' => 'PL-AD-5525'],
            ],
            [
                'id'               => 11,
                'name'             => 'Jump Rope — Speed Cable',
                'slug'             => 'speed-jump-rope',
                'price'            => 18.99,
                'discounted_price' => 14.99,
                'stock'            => 120,
                'in_stock'         => true,
                'category'         => 'Sports & Fitness',
                'description'      => 'Adjustable steel cable, ball-bearing handles, ideal for HIIT and boxing.',
                'thumbnail'        => 'https://picsum.photos/seed/jumprope/300/300',
                'meta'             => ['brand' => 'FlexFit', 'sku' => 'FF-JR-CABLE'],
            ],

            // ── Kitchen & Home ────────────────────────────────────────────────
            [
                'id'               => 12,
                'name'             => 'Stainless Steel Water Bottle 1L',
                'slug'             => 'steel-water-bottle-1l',
                'price'            => 29.99,
                'discounted_price' => 22.99,
                'stock'            => 88,
                'in_stock'         => true,
                'category'         => 'Kitchen & Home',
                'description'      => 'Double-wall vacuum insulated, keeps cold 24h / hot 12h, BPA-free lid.',
                'thumbnail'        => 'https://picsum.photos/seed/bottle/300/300',
                'meta'             => ['brand' => 'HydroKeep', 'sku' => 'HK-SB-1L'],
            ],
            [
                'id'               => 13,
                'name'             => 'Air Fryer 5.5L Digital',
                'slug'             => 'air-fryer-5l-digital',
                'price'            => 129.99,
                'discounted_price' => 99.99,
                'stock'            => 14,
                'in_stock'         => true,
                'category'         => 'Kitchen & Home',
                'description'      => '1700W, 8 presets, digital touchscreen, dishwasher-safe basket.',
                'thumbnail'        => 'https://picsum.photos/seed/airfryer/300/300',
                'meta'             => ['brand' => 'CrispAir', 'sku' => 'CA-AF-55D'],
            ],
            [
                'id'               => 14,
                'name'             => 'Bamboo Cutting Board Set (3-piece)',
                'slug'             => 'bamboo-cutting-board-set',
                'price'            => 39.99,
                'discounted_price' => 29.99,
                'stock'            => 33,
                'in_stock'         => true,
                'category'         => 'Kitchen & Home',
                'description'      => 'Small, medium and large boards. Juice groove, easy-grip handle. Eco-friendly bamboo.',
                'thumbnail'        => 'https://picsum.photos/seed/cuttingboard/300/300',
                'meta'             => ['brand' => 'GreenChef', 'sku' => 'GC-BB-3PC'],
            ],

            // ── Accessories ───────────────────────────────────────────────────
            [
                'id'               => 15,
                'name'             => 'Aluminium Laptop Stand',
                'slug'             => 'aluminium-laptop-stand',
                'price'            => 49.99,
                'discounted_price' => 49.99,
                'stock'            => 27,
                'in_stock'         => true,
                'category'         => 'Accessories',
                'description'      => 'Adjustable 6 angles, foldable, compatible with 10–17" laptops. Space grey.',
                'thumbnail'        => 'https://picsum.photos/seed/laptopstand/300/300',
                'meta'             => ['brand' => 'DeskPro', 'sku' => 'DP-LS-ALU'],
            ],
            [
                'id'               => 16,
                'name'             => 'Minimalist Leather Wallet',
                'slug'             => 'minimalist-leather-wallet',
                'price'            => 44.00,
                'discounted_price' => 34.00,
                'stock'            => 50,
                'in_stock'         => true,
                'category'         => 'Accessories',
                'description'      => 'Genuine leather, RFID blocking, holds 6 cards + cash slot. Slim 8mm profile.',
                'thumbnail'        => 'https://picsum.photos/seed/wallet/300/300',
                'meta'             => ['brand' => 'SlimCarry', 'sku' => 'SC-LW-RFID'],
            ],
            [
                'id'               => 17,
                'name'             => 'Canvas Tote Bag',
                'slug'             => 'canvas-tote-bag',
                'price'            => 19.99,
                'discounted_price' => 14.99,
                'stock'            => 200,
                'in_stock'         => true,
                'category'         => 'Accessories',
                'description'      => 'Heavy-duty 12oz canvas, inner zip pocket, 15L capacity. Washable.',
                'thumbnail'        => 'https://picsum.photos/seed/tote/300/300',
                'meta'             => ['brand' => 'EcoBag', 'sku' => 'EB-CT-12OZ'],
            ],

            // ── Skincare ──────────────────────────────────────────────────────
            [
                'id'               => 18,
                'name'             => 'Vitamin C Serum 30ml',
                'slug'             => 'vitamin-c-serum-30ml',
                'price'            => 34.99,
                'discounted_price' => 27.99,
                'stock'            => 65,
                'in_stock'         => true,
                'category'         => 'Skincare',
                'description'      => '20% L-Ascorbic Acid, hyaluronic acid & Vitamin E. Brightens and firms skin.',
                'thumbnail'        => 'https://picsum.photos/seed/serum/300/300',
                'meta'             => ['brand' => 'GlowLab', 'sku' => 'GL-VC-30ML'],
            ],
            [
                'id'               => 19,
                'name'             => 'SPF 50 Daily Moisturiser',
                'slug'             => 'spf50-daily-moisturiser',
                'price'            => 24.99,
                'discounted_price' => 24.99,
                'stock'            => 0,
                'in_stock'         => false,
                'category'         => 'Skincare',
                'description'      => 'Lightweight, non-greasy SPF 50 PA++++. Suitable for all skin types. Restocking soon.',
                'thumbnail'        => 'https://picsum.photos/seed/moisturiser/300/300',
                'meta'             => ['brand' => 'GlowLab', 'sku' => 'GL-SPF-50'],
            ],
        ];
    }

    // ── ProductProvider contract ───────────────────────────────────────────────

    public function search(string $query, int $limit = 5): Collection
    {
        $terms = $this->tokenize($query);

        if (empty($terms)) {
            return $this->featured($limit);
        }

        return collect($this->catalogue())
            ->filter(function (array $p) use ($terms) {
                $haystack = strtolower(
                    $p['name'] . ' ' .
                    $p['description'] . ' ' .
                    $p['category'] . ' ' .
                    implode(' ', $p['meta'])
                );
                foreach ($terms as $term) {
                    if (str_contains($haystack, $term)) {
                        return true;
                    }
                }
                return false;
            })
            ->sortByDesc(fn ($p) => $p['in_stock']) // in-stock first
            ->take($limit)
            ->values()
            ->map(fn ($p) => ProductData::fromArray($p));
    }

    public function searchByCategory(string|int $category, int $limit = 5): Collection
    {
        $cat = strtolower((string) $category);

        return collect($this->catalogue())
            ->filter(fn ($p) =>
                str_contains(strtolower($p['category']), $cat) ||
                (string) $p['id'] === (string) $category
            )
            ->sortByDesc(fn ($p) => $p['in_stock'])
            ->take($limit)
            ->values()
            ->map(fn ($p) => ProductData::fromArray($p));
    }

    public function find(string|int $identifier): ?ProductData
    {
        $item = collect($this->catalogue())->first(
            fn ($p) =>
                (string) $p['id']   === (string) $identifier ||
                $p['slug']          === (string) $identifier ||
                strtolower($p['name']) === strtolower((string) $identifier)
        );

        return $item ? ProductData::fromArray($item) : null;
    }

    public function featured(int $limit = 4): Collection
    {
        return collect($this->catalogue())
            ->filter(fn ($p) => $p['in_stock'])
            ->sortByDesc(fn ($p) => $p['discounted_price']) // highest value first
            ->take($limit)
            ->values()
            ->map(fn ($p) => ProductData::fromArray($p));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function tokenize(string $query): array
    {
        static $stop = [
            'a','an','the','is','are','do','i','me','find','show','get',
            'want','need','looking','for','some','any','have','can',
            'available','product','products','item','items','buy',
            'please','hello','hi','hey','tell','about','what',
        ];

        return array_values(array_unique(array_filter(
            preg_split('/[\s,;]+/', strtolower(trim($query))),
            fn ($t) => strlen($t) > 2 && !in_array($t, $stop, true)
        )));
    }
}