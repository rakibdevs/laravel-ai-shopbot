# laravel-ai-shopbot

> A plug-and-play AI shopping chatbot for Laravel — **bring your own product provider**.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue) ![Laravel](https://img.shields.io/badge/Laravel-10%2F12-red) ![License](https://img.shields.io/badge/license-MIT-green)

---

## Features

- 🔌 **Provider pattern** — swap your product source without touching the package
- 🤖 **Multi-AI** — OpenAI (GPT-4o-mini, GPT-4o) or Anthropic (Claude)
- 🔍 **Live search** — search-as-you-type suggestions via a lightweight API
- 📦 **Product cards** — renders matching products inline in the chat
- 🧠 **Conversation memory** — maintains session history via Laravel Cache
- 🎨 **Zero-dep widget** — single CSS + JS file, fully configurable color/position
- 🧪 **Testable** — interfaces + StaticProductProvider make unit tests easy

---

## Installation

```bash
composer require rakibdevs/laravel-ai-shopbot
```

Publish config and assets:

```bash
php artisan vendor:publish --tag=ai-shopbot-config
php artisan vendor:publish --tag=ai-shopbot-assets
php artisan migrate
```

---

## Quick Start

### 1. Set your AI key in `.env`

```env
# Use OpenAI (default)
SHOPBOT_AI_PROVIDER=openai
OPENAI_API_KEY=sk-...

# OR use Anthropic
SHOPBOT_AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=sk-ant-...
```

### 2. Choose a product provider

The package ships with three built-in providers:

| Provider | Use when |
|---|---|
| `ActiveEcommerceProductProvider` | You use the Active eCommerce Laravel CMS |
| `EloquentProductProvider` | You have a standard Eloquent `Product` model |
| `LocalProductProvider` | Testing / demo |

Set it in `config/ai_shopbot.php`:

```php
'product_provider' => \Rakibdevs\AiShopbot\Providers\ActiveEcommerceProductProvider::class,
```

Or in `.env`:

```env
SHOPBOT_PRODUCT_PROVIDER="\Rakibdevs\AiShopbot\Providers\ActiveEcommerceProductProvider"
```

### 3. Add the widget to your layout

```blade
{{-- resources/views/layouts/app.blade.php, before </body> --}}
@include('ai_shopbot::widget.chatbot')
```

That's it. 🎉

---

## Writing a Custom Product Provider

Implement the `ProductProvider` interface:

```php
<?php

namespace App\Chatbot;

use Illuminate\Support\Collection;
use Rakibdevs\AiShopbot\Contracts\ProductData;
use Rakibdevs\AiShopbot\Contracts\ProductProvider;

class CustomProductProvider implements ProductProvider
{
    public function search(string $query, int $limit = 5): Collection
    {
        // Call Custom GraphQL, your own DB, Elasticsearch — anything!
        $results = CustomClient::searchProducts($query, $limit);

        return collect($results)->map(fn ($item) => new ProductData(
            id:              $item['id'],
            name:            $item['title'],
            slug:            $item['handle'],
            price:           (float) $item['variants'][0]['price'],
            discountedPrice: (float) ($item['variants'][0]['compare_at_price'] ?? $item['variants'][0]['price']),
            stock:           (int)   $item['variants'][0]['inventory_quantity'],
            inStock:         $item['variants'][0]['inventory_quantity'] > 0,
            category:        $item['product_type'],
            description:     $item['body_html'],
            thumbnail:       $item['image']['src'] ?? '',
        ));
    }

    public function searchByCategory(string|int $category, int $limit = 5): Collection { /* ... */ }
    public function find(string|int $identifier): ?ProductData { /* ... */ }
    public function featured(int $limit = 4): Collection { /* ... */ }
}
```

Register it in `config/ai_shopbot.php`:

```php
'product_provider' => \App\Chatbot\CustomProductProvider::class,
```

Or override in `AppServiceProvider`:

```php
use Rakibdevs\AiShopbot\Contracts\ProductProvider;
use App\Chatbot\CustomProductProvider;

$this->app->bind(ProductProvider::class, CustomProductProvider::class);
```

A copy-paste stub lives at `stubs/MyProductProvider.php`.

---

## Using the EloquentProductProvider

If your products are in a standard Eloquent model, configure the field mappings:

```php
// config/ai_shopbot.php
'product_provider' => \Rakibdevs\AiShopbot\Providers\EloquentProductProvider::class,

'eloquent' => [
    'model'         => \App\Models\Product::class,
    'fields'        => [
        'id'          => 'id',
        'name'        => 'name',
        'slug'        => 'slug',
        'price'       => 'regular_price',
        'discounted'  => 'sale_price',      // nullable — falls back to price
        'stock'       => 'qty_in_stock',
        'category'    => 'category.name',   // dot-notation: belongsTo relation
        'description' => 'short_description',
        'thumbnail'   => 'cover_image',
    ],
    'search_fields' => ['name', 'short_description', 'tags'],
    'active_scope'  => 'published',          // optional Eloquent scope
],
```

---

## Registering a Custom AI Provider

```php
// config/ai_shopbot.php
'ai_provider'  => 'my_llm',
'ai_providers' => [
    'my_llm' => \App\Chatbot\OllamaProvider::class,
],
```

Your class must implement `Rakibdevs\AiShopbot\Contracts\AiProvider`:

```php
class OllamaProvider implements AiProvider
{
    public function chat(string $systemPrompt, array $messages): string
    {
        // Call your local Ollama instance, Azure OpenAI, etc.
    }
}
```

---

## Using the Facade

```php
use Rakibdevs\AiShopbot\Facades\AiShopbot;

$session  = AiShopbot::startSession();
$response = AiShopbot::processMessage($session, 'Show me wireless headphones under $50');

// $response['message']  → AI reply string
// $response['products'] → array of ProductData::toArray()
```

---

## REST API

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/shopbot/session` | Start session, get greeting + featured |
| `POST` | `/api/shopbot/message` | Send message, receive AI reply + products |
| `GET`  | `/api/shopbot/suggest?q=...` | Live search suggestions |
| `GET`  | `/api/shopbot/featured` | Featured products |

Route prefix is configurable via `config/ai_shopbot.php → route.prefix`.

---

## CLI Testing

```bash
# Test a query
php artisan shopbot:test --query="wireless headphones"

# Show bound providers
php artisan shopbot:test --provider
```

---

## Events

Listen to chatbot events in your `EventServiceProvider`:

```php
use Rakibdevs\AiShopbot\Events\MessageReceived;
use Rakibdevs\AiShopbot\Events\MessageSent;

Event::listen(MessageReceived::class, function ($e) {
    Log::info("Chatbot message received", ['session' => $e->sessionId]);
});

Event::listen(MessageSent::class, function ($e) {
    // Log to analytics, store in DB, etc.
});
```

---

## Configuration Reference

```env
SHOPBOT_PRODUCT_PROVIDER="\Rakibdevs\AiShopbot\Providers\ActiveEcommerceProductProvider"
SHOPBOT_AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-3-haiku-20240307
SHOPBOT_MAX_RESULTS=5
SHOPBOT_MIN_STOCK=1
SHOPBOT_INCLUDE_OOS=false
SHOPBOT_SESSION_TTL=60
SHOPBOT_WIDGET_ENABLED=true
SHOPBOT_WIDGET_COLOR=#007bff
SHOPBOT_WIDGET_POSITION=bottom-right
SHOPBOT_WIDGET_TITLE="Shopping Assistant"
```

---

## Testing

```bash
composer test
```

---

## License

MIT
