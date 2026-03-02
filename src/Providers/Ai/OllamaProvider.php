<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Providers\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Rakibdevs\AiShopbot\Contracts\AiProvider;
use Rakibdevs\AiShopbot\Exceptions\AiProviderException;

/**
 * Ollama AI Provider
 * ==============================================
 *
 * SETUP
 * -----
 * 1. Install Ollama: https://ollama.com/download  (Mac: brew install ollama)
 * 2. Pull a model:
 *      ollama pull llama3.2          # best all-round, 2GB
 *      ollama pull mistral           # great for chat, 4GB
 *      ollama pull qwen2.5:3b        # lightweight, fast on older Macs, 2GB
 *      ollama pull deepseek-r1:7b    # strong reasoning, 4GB
 * 3. Ollama starts automatically. Verify: curl http://localhost:11434
 *
 * .env setup:
 *   SHOPBOT_AI_PROVIDER=ollama
 *   OLLAMA_HOST=http://localhost:11434   (default, change if running remotely)
 *   OLLAMA_MODEL=llama3.2
 */
class OllamaProvider implements AiProvider
{
    public function chat(string $systemPrompt, array $messages): string
    {
        $host  = rtrim(config('ai_shopbot.ollama.host', 'http://localhost:11434'), '/');
        $model = config('ai_shopbot.ollama.model', 'qwen2.5-coder:7b-instruct');

        // ── Build messages array ───────────────────────────────────────────────
        // Ollama /api/chat supports system role natively — prepend it
        $ollamaMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages   // already in { role, content } format — no conversion needed
        );

        try {
            $response = Http::timeout(120)  // local models can be slow on first load
                ->retry(2, 1000, function ($exception) {
                    // Only retry on connection errors (model loading), not on 4xx
                    return !($exception instanceof \Illuminate\Http\Client\RequestException)
                        || $exception->response->status() >= 500;
                })
                ->timeout(15)
                ->post("{$host}/api/chat", [
                    'model'    => $model,
                    'messages' => $ollamaMessages,
                    'stream'   => false,    // get full response at once, not streamed
                    'options'  => [
                        'temperature'   => config('ai_shopbot.ollama.temperature', 0.4),
                        'num_predict'   => config('ai_shopbot.ollama.max_tokens', 600),
                        'top_p'         => 0.9,
                    ],
                ]);

            if ($response->failed()) {
                $status = $response->status();
                $body   = $response->json();

                Log::error('[AiShopbot] Ollama error', [
                    'status' => $status,
                    'body'   => $body,
                    'model'  => $model,
                    'host'   => $host,
                ]);

                // Specific helpful errors
                if ($status === 404) {
                    throw new AiProviderException(
                        "Ollama model \"{$model}\" not found. " .
                        "Run: ollama pull {$model}   then try again."
                    );
                }

                $error = $body['error'] ?? "HTTP {$status}";
                throw new AiProviderException("Ollama request failed: {$error}");
            }

            // ── Extract reply ──────────────────────────────────────────────────
            // Response shape: { message: { role: "assistant", content: "..." }, done: true }
            $body    = $response->json();
            $content = $body['message']['content'] ?? null;

            if (empty($content)) {
                $done = $body['done'] ?? null;
                throw new AiProviderException(
                    "Ollama returned an empty response. done={$done}, model={$model}"
                );
            }

            return trim($content);

        } catch (AiProviderException $e) {
            throw $e;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Ollama is not running
            throw new AiProviderException(
                "Cannot connect to Ollama at {$host}. " .
                "Make sure Ollama is running: open the Ollama app, or run 'ollama serve' in a terminal. " .
                "Original error: " . $e->getMessage(),
                0,
                $e
            );
        } catch (\Throwable $e) {
            throw new AiProviderException(
                "Ollama error: " . $e->getMessage(), 0, $e
            );
        }
    }
}