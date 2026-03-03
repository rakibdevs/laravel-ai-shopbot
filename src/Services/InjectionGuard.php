<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Services;

use Rakibdevs\AiShopbot\Exceptions\InjectionGuardException;

/**
 * Prompt injection guard.
 *
 * Rejects user messages that attempt to override the system prompt,
 * change the bot's persona, or extract internal instructions.
 *
 * The default pattern list covers the most common jailbreak vectors.
 * Extend via config('ai_shopbot.injection_guard.extra_patterns').
 */
class InjectionGuard
{
    /**
     * Core patterns — always active regardless of config.
     * These cover well-known jailbreak phrasing.
     */
    private const BASE_PATTERNS = [
        '/ignore\s+(all\s+)?(previous|above|prior)\s+instructions/i',
        '/forget\s+(all\s+)?(previous|above|prior|your)\s+instructions/i',
        '/disregard\s+(all\s+)?(previous|above|prior)\s+instructions/i',
        '/override\s+(the\s+)?(system|your)\s+(prompt|instructions)/i',
        '/you\s+are\s+now\s+(?!a\s+shopping)/i',   // "you are now X" — allow "you are now a shopping..."
        '/pretend\s+(you\s+are|to\s+be)\b/i',
        '/act\s+as\s+(a|an|if)\b/i',
        '/\bjailbreak\b/i',
        '/\bDAN\s+mode\b/i',
        '/reveal\s+(your|the)\s+(system\s+)?prompt/i',
        '/print\s+(your|the)\s+(system\s+)?prompt/i',
        '/show\s+me\s+(your|the)\s+(system\s+)?prompt/i',
        '/what\s+are\s+your\s+instructions/i',
        '/output\s+(your|the)\s+(instructions|prompt)/i',
        '/repeat\s+everything\s+(above|before)/i',
        '/\[SYSTEM\]/i',
        '/<\s*system\s*>/i',
    ];

    private bool  $enabled;
    private array $extraPatterns;
    private string $blockedMessage;

    public function __construct()
    {
        $this->enabled        = (bool)   config('ai_shopbot.injection_guard.enabled', true);
        $this->extraPatterns  = (array)  config('ai_shopbot.injection_guard.extra_patterns', []);
        $this->blockedMessage = (string) config(
            'ai_shopbot.injection_guard.blocked_message',
            "That message can't be processed by the shopping assistant. Feel free to ask about products!"
        );
    }

    /**
     * @throws InjectionGuardException  if an injection pattern is detected
     */
    public function check(string $message): void
    {
        if (! $this->enabled) {
            return;
        }

        $allPatterns = array_merge(self::BASE_PATTERNS, $this->extraPatterns);

        foreach ($allPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                throw new InjectionGuardException($this->blockedMessage);
            }
        }
    }
}
