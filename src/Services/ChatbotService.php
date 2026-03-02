<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Rakibdevs\AiShopbot\Contracts\AiProvider;
use Rakibdevs\AiShopbot\Contracts\ProductProvider;
use Rakibdevs\AiShopbot\Events\MessageReceived;
use Rakibdevs\AiShopbot\Events\MessageSent;
use Rakibdevs\AiShopbot\Exceptions\AiProviderException;

class ChatbotService
{
    public function __construct(
        private readonly ProductProvider $productProvider,
        private readonly AiProvider      $aiProvider,
        private readonly IntentDetectionService $intentDetectionService,
    ) {}

    /**
     * Create a new session identifier.
     */
    public function startSession(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Process a user message; return reply + matching products.
     *
     * @return array{
     *   message: string,
     *   products: array,
     *   show_products: bool,
     *   intent: string,
     *   session_id: string
     * }
     */
    public function processMessage(string $sessionId, string $userMessage): array
    {
        event(new MessageReceived($sessionId, $userMessage));

        $intent = $this->intentDetectionService->detect($userMessage);

        // Only search for products when the user is actually asking about them.
        // Greetings, follow-up questions, and chit-chat get an empty product list.
        $products = $this->intentDetectionService->requiresProductSearch($intent)
            ? $this->resolveProducts($userMessage, $intent)
            : collect();


        // Build context + history
        $history      = $this->getHistory($sessionId);
        $systemPrompt = $this->buildSystemPrompt($products, $intent);
        $messages     = $this->buildMessages($history, $userMessage);

        // Call AI
        try {
            $reply = $this->aiProvider->chat($systemPrompt, $messages);
        } catch (AiProviderException $e) {
            $reply = config(
                'ai_shopbot.fallback_message',
                "I'm having trouble connecting right now. Please try again in a moment."
            );
            report($e);
        }

        // 4. Persist history
        $this->appendHistory($sessionId, $userMessage, $reply);

        event(new MessageSent($sessionId, $reply, $products->count()));

        return [
            'message'      => $reply,
            'products'     => $products->take(3)->map->toArray()->values()->toArray(),
            'show_products' => $products->isNotEmpty(),  // frontend renders cards only when true
            'intent'       => $intent,                   // useful for debugging
            'session_id'   => $sessionId,
        ];
    }


    // -------------------------------------------------------------------------
    // Product Resolution with Fuzzy Fallback
    // -------------------------------------------------------------------------

    private function resolveProducts(string $query, string $intent): Collection
    {
        $limit = (int) config('ai_chatbot.search.max_results', 5);

        // Category search — extract the category term
        if ($intent === 'category_search') {
            if (preg_match('/\bin\s+(?:the\s+)?([a-z\s]{3,25})\s*(?:category|section|department)?/i', $query, $m)) {
                $results = $this->productProvider->searchByCategory(trim($m[1]), $limit);
                if ($results->isNotEmpty()) return $results;
            }
        }

        // Exact find — try to find by name/slug
        if ($intent === 'product_find') {
            if (preg_match('/\b(?:find|get|about|for|the)\s+(.{3,60})$/i', $query, $m)) {
                $found = $this->productProvider->find(trim($m[1]));
                if ($found) return collect([$found]);
            }
        }

        // Primary full-text search
        $results = $this->productProvider->search($query, $limit);
        if ($results->isNotEmpty()) return $results;

        // ── Fuzzy fallback: correct misspellings word-by-word ─────────────────
        $corrected = $this->fuzzyCorrectQuery($query);
        if ($corrected) {
            $fuzzyResults = $this->productProvider->search($corrected, $limit);
            if ($fuzzyResults->isNotEmpty()) return $fuzzyResults;
        }

        return collect();
    }

    


    /**
     * Autocomplete / quick-suggest endpoint.
     */
    public function suggest(string $query, int $limit = 5): Collection
    {
        return $this->productProvider->search($query, $limit);
    }

    /**
     * Return featured products (for greeting message).
     */
    public function featured(int $limit = 4): Collection
    {
        return $this->productProvider->featured($limit);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------


    /**
     * Correct a query by replacing misspelled words with the closest
     * word from the product catalogue vocabulary.
     *
     * Uses similar_text() (character similarity) + soundex() (phonetic match).
     * Example: "air dryer" → "air fryer"
     *          soundex("dryer")=D600, soundex("fryer")=F600 → close phonetic match
     */
    private function fuzzyCorrectQuery(string $query): string
    {
        $vocab = $this->buildVocabulary();
        if (empty($vocab)) return '';

        $words      = preg_split('/\s+/', strtolower(trim($query)));
        $corrected  = [];
        $anyChanged = false;

        foreach ($words as $word) {
            $clean = preg_replace('/[^a-z]/', '', $word);
            if (strlen($clean) <= 3) {
                $corrected[] = $word;
                continue;
            }

            $best      = $word;
            $bestScore = 0;

            foreach ($vocab as $candidate) {
                if (abs(strlen($clean) - strlen($candidate)) > 4) continue; // skip very different lengths early

                similar_text($clean, $candidate, $charSim);
                $phonetic = (soundex($clean) === soundex($candidate)) ? 25 : 0;
                $lenPenalty = min(abs(strlen($clean) - strlen($candidate)) * 4, 15);
                $score = $charSim + $phonetic - $lenPenalty;

                if ($score > $bestScore && $score > 58) {
                    $bestScore = $score;
                    $best      = $candidate;
                }
            }

            if ($best !== $word) $anyChanged = true;
            $corrected[] = $best;
        }

        return $anyChanged ? implode(' ', $corrected) : '';
    }

    /**
     * Build vocabulary from the product catalogue (cached in memory per request).
     */
    private function buildVocabulary(): array
    {
        static $vocab = null;
        if ($vocab !== null) return $vocab;

        $vocab = [];
        foreach ($this->productProvider->featured(50) as $product) {
            foreach (preg_split('/[\s\-\/]+/', strtolower($product->name)) as $token) {
                $token = preg_replace('/[^a-z]/', '', $token);
                if (strlen($token) > 3) $vocab[] = $token;
            }
        }

        $vocab = array_values(array_unique($vocab));
        return $vocab;
    }

    // -------------------------------------------------------------------------
    // Prompt Building
    // -------------------------------------------------------------------------

    private function buildSystemPrompt(Collection $products, string $intent): string
    {
        $base = config('ai_chatbot.system_prompt');

        if (!$this->intentDetectionService->requiresProductSearch($intent)) {
            return $base;
        } elseif ($products->isEmpty()) {
            $context = "\n\n[INTERNAL: Your search found no matching products. " .
            "Tell the customer you don't have that item and ask them to try different keywords.]";
        } else {
            $context = "\n\n[INTERNAL — YOUR STORE INVENTORY ({$products->count()} item(s)). " .
                "This is your own knowledge as a store employee. Do not reference this section or call it 'data'. " .
                "Just use it naturally when answering.]\n\n" .
            $products->values()
                ->map(fn ($p, $i) => $p->toContextLine($i + 1))
                ->implode("\n\n");
        }

        return $base .$context;
    }

    private function buildMessages(array $history, string $userMessage): array
    {
        $messages = [];
        foreach ($history as $entry) {
            $messages[] = ['role' => 'user',      'content' => $entry['user']];
            $messages[] = ['role' => 'assistant',  'content' => $entry['bot']];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];
        return $messages;
    }

    // -------------------------------------------------------------------------
    // Session / History (Cache-backed)
    // -------------------------------------------------------------------------

    private function cacheKey(string $sessionId): string
    {
        return "ai_shopbot_session_{$sessionId}";
    }

    private function getHistory(string $sessionId): array
    {
        return Cache::get($this->cacheKey($sessionId), []);
    }

    private function appendHistory(string $sessionId, string $user, string $bot): void
    {
        $max    = (int) config('ai_shopbot.session.max_history', 10);
        $ttl    = (int) config('ai_shopbot.session.ttl_minutes', 60) * 60;

        $history   = $this->getHistory($sessionId);
        $history[] = ['user' => $user, 'bot' => $bot];

        if (count($history) > $max) {
            $history = array_slice($history, -$max);
        }

        Cache::put($this->cacheKey($sessionId), $history, $ttl);
    }
}
