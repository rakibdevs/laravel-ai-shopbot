<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Rakibdevs\AiShopbot\Services\ChatbotService;

class ShopbotTestCommand extends Command
{
    protected $signature   = 'shopbot:test {--query= : Test a product search query} {--provider : Show which providers are bound}';
    protected $description = 'Test the AI chatbot from the command line.';

    public function handle(ChatbotService $chatbot): int
    {
        if ($this->option('provider')) {
            $this->showProviderInfo();
            return self::SUCCESS;
        }

        $query = $this->option('query') ?? $this->ask('Enter a test message for the chatbot');

        if (empty($query)) {
            $this->error('Query cannot be empty.');
            return self::FAILURE;
        }

        $this->info("🔍 Processing: \"{$query}\"");
        $this->newLine();

        $sessionId = $chatbot->startSession();
        $result    = $chatbot->processMessage($sessionId, $query);

        $this->line('<fg=cyan>🤖 AI Response:</>');
        $this->line($result['message']);
        $this->newLine();

        if (!empty($result['products'])) {
            $this->line('<fg=green>📦 Products Found:</>');
            $headers = ['ID', 'Name', 'Price', 'Stock', 'In Stock'];
            $rows    = array_map(fn ($p) => [
                $p['id'],
                Str::limit($p['name'], 35),
                $p['discounted_price'],
                $p['stock'],
                $p['in_stock'] ? '✓' : '✗',
            ], $result['products']);

            $this->table($headers, $rows);
        } else {
            $this->warn('No products matched.');
        }

        return self::SUCCESS;
    }

    private function showProviderInfo(): void
    {
        $productProvider = config('ai_shopbot.product_provider', 'NOT SET');
        $aiProvider      = config('ai_shopbot.ai_provider', 'NOT SET');

        $this->table(
            ['Setting', 'Value'],
            [
                ['Product Provider', $productProvider],
                ['AI Provider',      $aiProvider],
                ['AI Model (OpenAI)', config('ai_shopbot.openai.model', '-')],
                ['AI Model (Anthropic)', config('ai_shopbot.anthropic.model', '-')],
                ['Max Results',      config('ai_shopbot.search.max_results')],
                ['Min Stock',        config('ai_shopbot.search.min_stock')],
            ]
        );
    }
}
