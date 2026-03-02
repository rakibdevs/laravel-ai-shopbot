<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Providers\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Rakibdevs\AiShopbot\Contracts\AiProvider;
use Rakibdevs\AiShopbot\Exceptions\AiProviderException;

class OpenAiProvider implements AiProvider
{
    public function chat(string $systemPrompt, array $messages): string
    {
        $cfg = config('ai_shopbot.openai');

        $payload = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        );

        try {
            $response = Http::withToken($cfg['api_key'])
                ->timeout(30)
                ->retry(2, 500)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'             => $cfg['model'] ?? 'gpt-4o-mini',
                    'messages'          => $payload,
                    'max_tokens'        => $cfg['max_tokens'] ?? 600,
                    'temperature'       => $cfg['temperature'] ?? 0.4,
                    'presence_penalty'  => 0.1,
                    'frequency_penalty' => 0.1,
                ]);
                
            if ($response->failed()) {
                Log::error('[AiShopbot] OpenAI error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new AiProviderException('OpenAI request failed: ' . $response->status());
            }

            return $response->json('choices.0.message.content')
                ?? throw new AiProviderException('Empty response from OpenAI.');

        } catch (AiProviderException $e) {
            
            throw $e;
        } catch (\Throwable $e) {
            throw new AiProviderException('OpenAI connection error: ' . $e->getMessage(), 0, $e);
        }
    }
}
