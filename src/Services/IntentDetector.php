<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Services;

/**
 * IntentDetector
 * ==============
 * Classifies a raw user message into a named intent.
 * Used by ChatbotService to decide whether a product search is needed
 * and which type of search to run.
 *
 * INTENTS
 * -------
 *   product_search   — "show me wireless headphones", "I want a cheap laptop"
 *   product_find     — "find the Sony WH-1000XM5", "details for air fryer"
 *   category_search  — "what's in the electronics category"
 *   price_query      — "how much is the yoga mat"
 *   stock_query      — "is the speaker in stock"
 *   greeting         — "hi", "hello", "good morning"
 *   chitchat         — "thanks", "ok", "great", "bye"
 *   off_topic        — weather, news, coding, recipes, etc.
 *   unclear          — too short or ambiguous to classify
 *
 * EXTENDING
 * ---------
 * Subclass and override detectIntent() to add domain-specific intents,
 * or bind your own implementation in AppServiceProvider:
 *
 *   $this->app->bind(IntentDetector::class, MyIntentDetector::class);
 */
class IntentDetector
{
    /**
     * Classify the message and return an intent string.
     */
    public function detect(string $message): string
    {
        $msg = strtolower(trim($message));

        if (strlen($msg) === 0) {
            return 'unclear';
        }

        return $this->matchGreeting($msg)
            ?? $this->matchChitchat($msg)
            ?? $this->matchOffTopic($msg)
            ?? $this->matchStockQuery($msg)
            ?? $this->matchPriceQuery($msg)
            ?? $this->matchCategorySearch($msg)
            ?? $this->matchProductFind($msg)
            ?? $this->matchProductSearch($msg)
            ?? $this->matchBareNounPhrase($msg)
            ?? 'unclear';
    }

    /**
     * Returns true for intents that require a product database lookup.
     */
    public function requiresProductSearch(string $intent): bool
    {
        return in_array($intent, [
            'product_search',
            'product_find',
            'category_search',
            'price_query',
            'stock_query',
        ], true);
    }

    /**
     * Extract a category name from a category_search message.
     * Returns null if none can be extracted.
     *
     * e.g. "what's in the electronics category" → "electronics"
     */
    public function extractCategory(string $message): ?string
    {
        $patterns = [
            '/\bin\s+(?:the\s+)?([a-z][a-z\s]{2,24}?)\s+(?:category|section|department)\b/i',
            '/\b(?:category|section|department)[:\s]+([a-z][a-z\s]{2,24})\b/i',
            '/\bunder\s+(?:the\s+)?([a-z][a-z\s]{2,24})\b/i',
            '/\bshow\s+(?:me\s+)?(?:all\s+)?([a-z][a-z\s]{2,24})\s+(?:products?|items?)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $m)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    /**
     * Extract a product identifier from a product_find message.
     * Returns null if none can be extracted.
     *
     * e.g. "find the Sony WH-1000XM5" → "Sony WH-1000XM5"
     */
    public function extractProductIdentifier(string $message): ?string
    {
        $patterns = [
            '/\b(?:find|get)\s+(?:the\s+)?(.{3,80}?)(?:\?|$)/i',
            '/\bdetails?\s+(?:of|for)\s+(?:the\s+)?(.{3,80}?)(?:\?|$)/i',
            '/\btell\s+me\s+about\s+(?:the\s+)?(.{3,80}?)(?:\?|$)/i',
            '/\bshow\s+me\s+the\s+(.{3,80}?)(?:\?|$)/i',
            '/\babout\s+(?:the\s+)?(.{3,80}?)(?:\?|$)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $m)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Matchers — each returns the intent string or null to fall through
    // -------------------------------------------------------------------------

    protected function matchGreeting(string $msg): ?string
    {
        return preg_match(
            '/^(hi|hello|hey|howdy|hiya|sup|yo|good\s+(morning|afternoon|evening|day))\b/i',
            $msg
        ) ? 'greeting' : null;
    }

    protected function matchChitchat(string $msg): ?string
    {
        $patterns = [
            '/^(thanks?|thank\s+you|thx|cheers|great)\s*[.!]?\s*$/i',
            '/^(ok|okay|got\s+it|perfect|awesome|cool|nice)\s*[.!]?\s*$/i',
            '/^(sounds\s+good|bye|goodbye|see\s+you|cya)\s*[.!]?\s*$/i',
            '/^(yes|no|yep|nope|sure|maybe|alright|right)\s*[.!]?\s*$/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $msg)) {
                return 'chitchat';
            }
        }

        return null;
    }

    protected function matchOffTopic(string $msg): ?string
    {
        $patterns = [
            // People & titles — catches "who is X", "who was X", "tell me about X"
            '/\bwho\s+(is|was|are|were)\b/i',
            '/\b(president|prime\s+minister|minister|chancellor|governor|senator|mayor|king|queen|emperor|ceo\s+of|founder\s+of)\b/i',

            // Geography & facts
            '/\b(capital\s+of|population\s+of|currency\s+of|located\s+in|flag\s+of|history\s+of|area\s+of)\b/i',

            // News & current events
            '/\b(weather|forecast|temperature|news|headline|breaking|politics|election|vote|war|conflict|protest)\b/i',

            // Sports & entertainment
            '/\b(sport|score|match\s+result|cricket|football|soccer|nba|nfl|world\s+cup|movie|film|actor|actress|music|song|album|singer|band)\b/i',

            // General knowledge tasks
            '/\b(recipe|how\s+to\s+cook|how\s+to\s+make|how\s+to\s+fix|how\s+to\s+install)\b/i',
            '/\b(joke|funny|riddle|poem|essay|story|translate|definition\s+of|meaning\s+of|explain\s+what)\b/i',

            // Coding / technical
            '/\b(code|program|script|function|algorithm|debug|error\s+in|how\s+to\s+use\s+[a-z]+\s+(api|sdk|library))\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $msg)) {
                return 'off_topic';
            }
        }

        return null;
    }

    protected function matchStockQuery(string $msg): ?string
    {
        return preg_match(
            '/\b(in\s+stock|out\s+of\s+stock|available|availability|how\s+many\s+left|still\s+(have|got)|any\s+left|do\s+you\s+still\s+have|is\s+it\s+available)\b/i',
            $msg
        ) ? 'stock_query' : null;
    }

    protected function matchPriceQuery(string $msg): ?string
    {
        return preg_match(
            '/\b(how\s+much|what.*cost|price\s+of|what.*price|pricing|cost\s+of|how\s+expensive|what\s+does.*cost)\b/i',
            $msg
        ) ? 'price_query' : null;
    }

    protected function matchCategorySearch(string $msg): ?string
    {
        return preg_match(
            '/\b(category|categories|section|department|browse|all\s+products?|everything\s+in)\b/i',
            $msg
        ) || preg_match(
            '/\bin\s+(?:the\s+)?[a-z\s]{3,25}\s+(?:category|section|department)\b/i',
            $msg
        ) ? 'category_search' : null;
    }

    protected function matchProductFind(string $msg): ?string
    {
        return preg_match(
            '/\b(find\s+the|get\s+the|details?\s+(?:of|for|about)|tell\s+me\s+about\s+the|show\s+me\s+the|looking\s+for\s+(?:the\s+)?specific)\b/i',
            $msg
        ) ? 'product_find' : null;
    }

    protected function matchProductSearch(string $msg): ?string
    {
        return preg_match(
            '/\b(show|search|recommend|suggest|what.*(have|got|sell)|do\s+you\s+(have|sell|carry|stock)|any\s+good|best|top|cheap|cheapest|affordable|buy|purchase|order|want|need|looking\s+for|i.m\s+looking|i\s+need|i\s+want|find\s+me|get\s+me)\b/i',
            $msg
        ) ? 'product_search' : null;
    }

    /**
     * A bare noun phrase of 2–6 words with no verb is almost always a product search.
     * e.g. "air fryer", "wireless headphones under 50", "running shoes size 10"
     */
    protected function matchBareNounPhrase(string $msg): ?string
    {
        $wordCount = str_word_count($msg);

        if ($wordCount < 2 || $wordCount > 6) {
            return null;
        }

        $hasVerb = preg_match(
            '/\b(is|are|was|were|do|did|can|could|would|should|will|am|be|have|has|had|make|made|go|went|come|came|see|saw|know|knew|think|feel|tell|told)\b/i',
            $msg
        );

        return $hasVerb ? null : 'product_search';
    }
}
