<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot;

use Illuminate\Support\ServiceProvider;
use Rakibdevs\AiShopbot\Contracts\AiProvider;
use Rakibdevs\AiShopbot\Contracts\ProductProvider;
use Rakibdevs\AiShopbot\Exceptions\ChatbotException;
use Rakibdevs\AiShopbot\Providers\Ai\AnthropicProvider;
use Rakibdevs\AiShopbot\Providers\Ai\OpenAiProvider;
use Rakibdevs\AiShopbot\Providers\Ai\GeminiProvider;
use Rakibdevs\AiShopbot\Providers\Ai\OllamaProvider;
use Rakibdevs\AiShopbot\Services\ChatbotService;
use Rakibdevs\AiShopbot\Services\IntentDetector;

class AiShopbotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai_shopbot.php', 'ai_shopbot');

        // ── Product Provider ──────────────────────────────────────────────────
        // Resolved from config so the host app can swap it out:
        //
        //   // config/ai_shopbot.php
        //   'product_provider' => App\Chatbot\MyCustomProductProvider::class,
        //
        // Or override at runtime in AppServiceProvider:
        //
        //   $this->app->bind(ProductProvider::class, MyProvider::class);
        //
        $this->app->bind(ProductProvider::class, function ($app) {
            $class = config('ai_shopbot.product_provider');

            if (!$class) {
                throw new ChatbotException(
                    "No product provider configured. Set 'ai_shopbot.product_provider' in config/ai_shopbot.php."
                );
            }

            if (!class_exists($class)) {
                throw new ChatbotException("Product provider [{$class}] does not exist.");
            }

            if (!is_subclass_of($class, ProductProvider::class)) {
                throw new ChatbotException(
                    "Product provider [{$class}] must implement " . ProductProvider::class
                );
            }

            return $app->make($class);
        });

        // ── AI Provider ───────────────────────────────────────────────────────
        $this->app->bind(AiProvider::class, function ($app) {
            $driver = config('ai_shopbot.ai_provider', 'openai');

            $map = config('ai_shopbot.ai_providers', []);

            if (isset($map[$driver])) {
                return $app->make($map[$driver]);
            }

            return match ($driver) {
                'anthropic' => $app->make(AnthropicProvider::class),
                'openai'    => $app->make(OpenAiProvider::class),
                'gemini'    => $app->make(GeminiProvider::class),
                'ollama'    => $app->make(OllamaProvider::class),
                default     => throw new ChatbotException("Unknown AI provider [{$driver}]."),
            };
        });

        // ── Core Service ──────────────────────────────────────────────────────
        $this->app->singleton(ChatbotService::class);
        $this->app->singleton(IntentDetector::class);

    }

    public function boot(): void
    {
        if (config('ai_shopbot.widget.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ai_shopbot');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ai_shopbot');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/ai_shopbot.php' => config_path('ai_shopbot.php'),
            ], 'ai-shopbot-config');

            $this->publishes([
                __DIR__ . '/../public' => public_path('vendor/ai-shopbot'),
            ], 'ai-shopbot-assets');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'ai-shopbot-migrations');

            $this->commands([
                Console\Commands\ShopbotTestCommand::class,
            ]);
        }
    }
}
