<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Rakibdevs\AiShopbot\Contracts\AiProvider;
use Rakibdevs\AiShopbot\Exceptions\AiProviderException;

class AnthropicProvider implements AiProvider
{
    public function chat(string $systemPrompt, array $messages): string
    {
        $cfg = config('ai_shopbot.anthropic');

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $cfg['api_key'],
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
            ->timeout(30)
            ->retry(2, 500)
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => $cfg['model'] ?? 'claude-3-haiku-20240307',
                'max_tokens' => $cfg['max_tokens'] ?? 600,
                'system'     => $systemPrompt,
                'messages'   => $messages,
            ]);

            if ($response->failed()) {
                Log::error('[AiShopbot] Anthropic error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new AiProviderException('Anthropic request failed: ' . $response->status());
            }

            return $response->json('content.0.text')
                ?? throw new AiProviderException('Empty response from Anthropic.');

        } catch (AiProviderException $e) {
            throw $e;
        } catch (\Throwable $e) {
            dd($e);
            throw new AiProviderException('Anthropic connection error: ' . $e->getMessage(), 0, $e);
        }
    }
}
