<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Rakibdevs\AiShopbot\Services\ChatbotService;

class ChatbotController extends Controller
{
    public function __construct(
        private readonly ChatbotService $chatbot
    ) {}

    /**
     * POST /api/chatbot/session
     * Start a new chat session.
     */
    public function startSession(): JsonResponse
    {
        return response()->json([
            'session_id' => $this->chatbot->startSession(),
            'greeting'   => config('ai_shopbot.widget.greeting'),
            'featured'   => $this->chatbot->featured(3)->map->toArray()->values(),
        ]);
    }

    /**
     * POST /api/chatbot/message
     * Send a user message and receive an AI reply.
     */
    public function message(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:64'],
            'message'    => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $result = $this->chatbot->processMessage(
            $validated['session_id'],
            $validated['message']
        );

        return response()->json($result);
    }

    /**
     * GET /api/chatbot/suggest?q=wireless
     * Live search-as-you-type product suggestions.
     */
    public function suggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q'     => ['required', 'string', 'min:2', 'max:200'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        $products = $this->chatbot->suggest(
            $validated['q'],
            (int) ($validated['limit'] ?? 5)
        );

        return response()->json([
            'products' => $products->map->toArray()->values(),
        ]);
    }

    /**
     * GET /api/chatbot/featured
     * Return featured products for the welcome message.
     */
    public function featured(Request $request): JsonResponse
    {
        $limit    = min((int) $request->query('limit', 4), 10);
        $products = $this->chatbot->featured($limit);

        return response()->json([
            'products' => $products->map->toArray()->values(),
        ]);
    }
}
