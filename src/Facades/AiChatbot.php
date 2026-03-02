<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Facades;

use Illuminate\Support\Facades\Facade;
use Rakibdevs\AiShopbot\Services\ChatbotService;

/**
 * @method static string     startSession()
 * @method static array      processMessage(string $sessionId, string $message)
 * @method static \Illuminate\Support\Collection suggest(string $query, int $limit = 5)
 * @method static \Illuminate\Support\Collection featured(int $limit = 4)
 *
 * @see ChatbotService
 */
class AiShopbot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ChatbotService::class;
    }
}
