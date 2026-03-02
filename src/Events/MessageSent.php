<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $sessionId,
        public readonly string $reply,
        public readonly int    $productsFound,
    ) {}
}
