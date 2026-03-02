<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product Provider
    |--------------------------------------------------------------------------
    |
    | The class responsible for fetching products. It must implement:
    |   Rakibdevs\AiShopbot\Contracts\ProductProvider
    |
    |
    */
    'product_provider' => env(
        'SHOPBOT_PRODUCT_PROVIDER',
        \Rakibdevs\AiShopbot\Providers\Product\LocalProductProvider::class
    ),

    /*
    |--------------------------------------------------------------------------
    | EloquentProductProvider field mappings
    |--------------------------------------------------------------------------
    | Only relevant when using EloquentProductProvider.
    */
    'eloquent' => [
        'model'         => env('SHOPBOT_PRODUCT_MODEL', \App\Models\Product::class),
        'fields'        => [
            'id'          => 'id',
            'name'        => 'name',
            'slug'        => 'slug',
            'price'       => 'price',
            'discounted'  => 'sale_price',
            'stock'       => 'stock_qty',
            'category'    => 'category.name', // dot-notation for belongsTo
            'description' => 'description',
            'thumbnail'   => 'thumbnail',
        ],
        'search_fields' => ['name', 'description', 'tags'],
        'active_scope'  => 'active',         // optional model scope
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    |
    | Options: "openai" | "anthropic" | "gemini" | "ollama"
    |
    | You can register custom drivers via ai_providers map.
    |
    */
    'ai_provider' => env('SHOPBOT_AI_PROVIDER', 'openai'),

    /*
    | Register additional AI provider drivers:
    |   'ai_providers' => ['my_llm' => App\Chatbot\MyLlmProvider::class],
    */
    'ai_providers' => [],

    'openai' => [
        'api_key'     => env('OPENAI_API_KEY'),
        'model'       => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'max_tokens'  => (int) env('OPENAI_MAX_TOKENS', 600),
        'temperature' => (float) env('OPENAI_TEMPERATURE', 0.4),
    ],

    'anthropic' => [
        'api_key'    => env('ANTHROPIC_API_KEY'),
        'model'      => env('ANTHROPIC_MODEL', 'claude-3-haiku-20240307'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 600),
    ],

    'gemini' => [
        'api_key'    => env('GEMINI_API_KEY'),
        'model'      => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'max_tokens' => (int) env('GEMINI_MAX_TOKENS', 600),
        'temperature'=> (float) env('GEMINI_TEMPERATURE', 0.4),
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Search Settings
    |--------------------------------------------------------------------------
    */
    'search' => [
        'max_results'          => (int)  env('SHOPBOT_MAX_RESULTS', 5),
        'min_stock'            => (int)  env('SHOPBOT_MIN_STOCK', 1),
        'include_out_of_stock' => (bool) env('SHOPBOT_INCLUDE_OOS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session / History
    |--------------------------------------------------------------------------
    */
    'session' => [
        'max_history'  => (int) env('SHOPBOT_MAX_HISTORY', 10),
        'ttl_minutes'  => (int) env('SHOPBOT_SESSION_TTL', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Route Settings
    |--------------------------------------------------------------------------
    */
    'route' => [
        'prefix'     => env('SHOPBOT_ROUTE_PREFIX', 'api/chatbot'),
        'middleware' => ['throttle:60,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat Widget
    |--------------------------------------------------------------------------
    */
    'widget' => [
        'enabled'       => (bool) env('SHOPBOT_WIDGET_ENABLED', true),
        'title'         => env('SHOPBOT_WIDGET_TITLE', 'Shopping Assistant'),
        'placeholder'   => env('SHOPBOT_WIDGET_PLACEHOLDER', 'Ask me about any product...'),
        'primary_color' => env('SHOPBOT_WIDGET_COLOR', '#007bff'),
        'position'      => env('SHOPBOT_WIDGET_POSITION', 'bottom-right'), // bottom-right | bottom-left
        'greeting'      => env('SHOPBOT_WIDGET_GREETING', "👋 Hi! I'm your shopping assistant. Ask me about any product, or check availability!"),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback message when AI is unreachable
    |--------------------------------------------------------------------------
    */
    'fallback_message' => "I'm having trouble connecting right now. Please try again in a moment.",

    /*
    |--------------------------------------------------------------------------
    | System Prompt
    |--------------------------------------------------------------------------
    |
    | You can override this entirely, or keep the default and add to it.
    |
    */
    'system_prompt' => <<<PROMPT
You are a shopping assistant for this store. Talk like a helpful human shop assistant — natural, warm, concise.

LENGTH — THIS IS YOUR MOST IMPORTANT RULE:
- Every reply MUST be 1 to 3 sentences. Except if customer ask for details.
- Count your sentences before replying. If you have written more than 3, cut it down.
- Never write a paragraph followed by a list followed by another paragraph.
- If showing multiple products, the numbered list counts as ONE sentence equivalent. Add at most one sentence before or after it, not both.
- BAD (too long): "We have the X in stock at $99. It features noise cancellation and 30h battery. It's perfect for travel. You might also like Y at $79. Let me know if you need help!"
- GOOD: "We have the **X** at $99 — 30h battery, noise cancellation, in stock. Want to know more?"

IDENTITY & KNOWLEDGE:
- You work here. The inventory is your own knowledge. NEVER say "based on the data provided", "according to the product data", or anything similar.
- Never fabricate products, prices, policies, or availability.
- Never mention suppliers, warehouses, restocking timelines, or backend operations.

STOCK RULES:
- Out of stock: say "We're out of stock on that — it'll be back soon." Nothing more.
- Low stock: you may say "only a few left" at most.
- Never suggest contacting suppliers, split orders, or going elsewhere.

TONE:
- No filler openers: no "Sure!", "Of course!", "Great question!", "Certainly!", "Absolutely!", "Of course!".
- Never start with "I" as the first word.
- No sign-off lines like "Let me know how I can assist you further!".

FORMATTING:
- Plain prose. No markdown headers (#) or horizontal rules (---).
- Multiple products: numbered list "1. **Name** — Price (In Stock)". One sentence max before or after.
- Bold product names only.
- No URLs — the UI shows links automatically.
PROMPT,
];
