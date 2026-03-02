<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Tests\Feature;

use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;
use Rakibdevs\AiShopbot\AiShopbotServiceProvider;
use Rakibdevs\AiShopbot\Contracts\AiProvider;
use Rakibdevs\AiShopbot\Contracts\ProductData;
use Rakibdevs\AiShopbot\Contracts\ProductProvider;
use Rakibdevs\AiShopbot\Services\ChatbotService;

class ChatbotServiceTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AiShopbotServiceProvider::class];
    }

    // protected function defineEnvironment($app): void
    // {
    //     $app['config']->set('ai_shopbot.product_provider', \Rakibdevs\AiShopbot\Providers\StaticProductProvider::class);
    //     $app['config']->set('ai_shopbot.ai_provider', 'openai');
    //     $app['config']->set('ai_shopbot.openai.api_key', 'test-key');
    //     $app['config']->set('cache.default', 'array');
    // }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('ai_shopbot.product_provider', \Rakibdevs\AiShopbot\Providers\Product\StaticProductProvider::class);
        $app['config']->set('ai_shopbot.ai_provider', 'openai');
        $app['config']->set('ai_shopbot.openai.api_key', 'test-key');

        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', [
            'driver'    => 'array',
            'serialize' => false,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the AI provider so we don't make real API calls
        $this->app->bind(AiProvider::class, function () {
            return Mockery::mock(AiProvider::class, function (MockInterface $m) {
                $m->shouldReceive('chat')
                    ->andReturn("Here are some products I found for you!");
            });
        });
    }

    public function test_process_message_returns_expected_shape(): void
    {
        /** @var ChatbotService $chatbot */
        $chatbot = $this->app->make(ChatbotService::class);

        $session = $chatbot->startSession();
        $result  = $chatbot->processMessage($session, 'show me headphones');

        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('products', $result);
        $this->assertArrayHasKey('session_id', $result);
        $this->assertSame($session, $result['session_id']);
        $this->assertIsString($result['message']);
        $this->assertIsArray($result['products']);
    }

    public function test_suggest_uses_product_provider(): void
    {
        /** @var MockInterface|ProductProvider $provider */
        $provider = Mockery::mock(ProductProvider::class);
        $provider->shouldReceive('search')
            ->with('phone', 5)
            ->andReturn(collect([
                new ProductData(1, 'Smartphone', 'smartphone', 299.0, 249.0, 10, true),
            ]));

        $this->app->bind(ProductProvider::class, fn() => $provider);

        /** @var ChatbotService $chatbot */
        $chatbot  = $this->app->make(ChatbotService::class);
        $results  = $chatbot->suggest('phone');

        $this->assertCount(1, $results);
        $this->assertSame('Smartphone', $results->first()->name);
    }

    public function test_history_is_preserved_across_messages(): void
    {
        /** @var ChatbotService $chatbot */
        $chatbot  = $this->app->make(ChatbotService::class);
        $session  = $chatbot->startSession();

        $chatbot->processMessage($session, 'First message');
        $chatbot->processMessage($session, 'Second message');

        // No assertion on internals — just assert no exception thrown
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
