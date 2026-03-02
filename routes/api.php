<?php

use Illuminate\Support\Facades\Route;
use Rakibdevs\AiShopbot\Http\Controllers\ChatbotController;

$prefix     = config('ai_shopbot.route.prefix', 'api/chatbot');
$middleware = config('ai_shopbot.route.middleware', ['throttle:60,1']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('ai_shopbot.')
    ->group(function () {
        Route::post('/session', [ChatbotController::class, 'startSession'])
            ->name('session');

        Route::post('/message', [ChatbotController::class, 'message'])
            ->name('message');

        Route::get('/suggest', [ChatbotController::class, 'suggest'])
            ->name('suggest');

        Route::get('/featured', [ChatbotController::class, 'featured'])
            ->name('featured');
    });
