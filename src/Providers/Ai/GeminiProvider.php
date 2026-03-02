<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Providers\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Rakibdevs\AiShopbot\Contracts\AiProvider;
use Rakibdevs\AiShopbot\Exceptions\AiProviderException;

/**
 * Google Gemini AI Provider — FREE TIER
 * ======================================
 * No credit card required. Get your key at: https://aistudio.google.com
 *
 * Free limits (as of Feb 2026):
 *   - gemini-2.5-flash : 15 RPM, 1,000 RPD
 *   - gemini-2.5-flash-lite : 30 RPM, 1,500 RPD  ← best for high volume
 *
 * .env setup:
 *   CHATBOT_AI_PROVIDER=gemini
 *   GEMINI_API_KEY=AIzaSy...
 *   GEMINI_MODEL=gemini-2.5-flash
 */
class GeminiProvider implements AiProvider
{
    public function chat(string $systemPrompt, array $messages): string
    {
        $cfg = config('ai_shopbot.gemini');
        $apiKey = $cfg['api_key'] ?? null;
        $model  = $cfg['model'] ?? 'gemini-2.5-flash';
        // ── Convert message history to Gemini "contents" format ───────────────
        $contents = [];

        foreach ($messages as $msg) {
            $contents[] = [
                'role'  => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]],
            ];
        }

        // ── System prompt goes in "systemInstruction" ─────────────────────────
        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents'          => $contents,
            'generationConfig'  => [
                'maxOutputTokens' => config('ai_shopbot.gemini.max_tokens', 600),
                'temperature'     => config('ai_shopbot.gemini.temperature', 0.4),
                'topP'            => 0.9,
            ],
            'safetySettings'    => [
                // Relax safety thresholds for shopping content (prices, products)
                ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ],
        ];

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $response = Http::timeout(30)
                ->retry(2, 800, function ($exception, $request) {
                    // Retry on 429 (rate limit) and 503 (overloaded)
                    if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                        return in_array($exception->response->status(), [429, 503]);
                    }
                    return false;
                })
                ->post($url, $payload);

            if ($response->failed()) {
                $error = $response->json('error.message', 'Unknown error');
                $status = $response->status();

                Log::error('[AiChatbot] Gemini error', [
                    'status'  => $status,
                    'message' => $error,
                    'model'   => $model,
                ]);

                // Give a helpful message for common free-tier errors
                if ($status === 429) {
                    throw new AiProviderException(
                        "Gemini free tier rate limit reached. Wait a minute and try again, " .
                            "or switch to gemini-2.5-flash-lite for higher limits. Error: {$error}"
                    );
                }

                if ($status === 400 && str_contains($error, 'API_KEY_INVALID')) {
                    throw new AiProviderException(
                        "Invalid Gemini API key. Check GEMINI_API_KEY in your .env. " .
                            "Get a free key at https://aistudio.google.com"
                    );
                }

                throw new AiProviderException("Gemini request failed (HTTP {$status}): {$error}");
            }
            
            // ── Extract text from Gemini response ─────────────────────────────
            // Response shape: candidates[0].content.parts[0].text
            $body = $response->json();
            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (empty($text)) {
                // Check if blocked by safety filters
                $finishReason = $response->json('candidates.0.finishReason');
                if ($finishReason === 'SAFETY') {
                    throw new AiProviderException('Gemini blocked the response due to safety filters.');
                }

                // Check for promptFeedback block
                $blockReason = $response->json('promptFeedback.blockReason');
                if ($blockReason) {
                    throw new AiProviderException("Gemini blocked the prompt: {$blockReason}");
                }

                throw new AiProviderException('Gemini returned an empty response.');
            }

            return trim($text);
        } catch (AiProviderException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new AiProviderException(
                'Gemini connection error: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
