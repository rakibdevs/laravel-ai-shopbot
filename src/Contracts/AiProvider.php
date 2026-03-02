<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Contracts;

/**
 * Implement this interface to swap out the underlying AI model.
 */
interface AiProvider
{
    /**
     * Send a conversation to the AI model and get a response.
     *
     * @param  string  $systemPrompt  Instructions / injected product context
     * @param  array   $messages      Array of ['role' => 'user'|'assistant', 'content' => '...']
     * @return string  The AI's reply
     */
    public function chat(string $systemPrompt, array $messages): string;
}
