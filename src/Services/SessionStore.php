<?php

declare(strict_types=1);

namespace Rakibdevs\AiShopbot\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Issue 6 — Replaces raw Cache calls scattered across ChatbotService.
 *
 * Supported drivers (set via SHOPBOT_SESSION_DRIVER):
 *   - "file"     → Laravel file cache (default, works on single-server setups)
 *   - "redis"    → Redis (recommended for production / multi-server)
 *   - "memcached"→ Memcached
 *   - "database" → shopbot_sessions table (survives cache:clear, survives restarts)
 *
 * Any other value is treated as a named Laravel cache store.
 */
class SessionStore
{
    private string $driver;
    private int    $ttlSeconds;
    private int    $maxHistory;

    public function __construct()
    {
        $this->driver     = (string) config('ai_shopbot.session.driver',      'file');
        $this->ttlSeconds = (int)    config('ai_shopbot.session.ttl_minutes', 60) * 60;
        $this->maxHistory = (int)    config('ai_shopbot.session.max_history', 10);
    }

    /**
     * Load the full conversation history for a session.
     *
     * @return array<int, array{user: string, bot: string}>
     */
    public function get(string $sessionId): array
    {
        return $this->driver === 'database'
            ? $this->dbGet($sessionId)
            : Cache::store($this->driver)->get($this->cacheKey($sessionId), []);
    }

    /**
     * Append one user/bot exchange and persist the updated history.
     * Trims to max_history turns so old context doesn't bloat LLM calls.
     */
    public function append(string $sessionId, string $userMessage, string $botReply): void
    {
        $history   = $this->get($sessionId);
        $history[] = ['user' => $userMessage, 'bot' => $botReply];

        if (count($history) > $this->maxHistory) {
            $history = array_slice($history, -$this->maxHistory);
        }

        $this->driver === 'database'
            ? $this->dbPut($sessionId, $history)
            : Cache::store($this->driver)->put(
                $this->cacheKey($sessionId),
                $history,
                $this->ttlSeconds
            );
    }

    /**
     * Destroy a session (e.g. on explicit "reset" command).
     */
    public function forget(string $sessionId): void
    {
        if ($this->driver === 'database') {
            DB::table('shopbot_sessions')->where('session_id', $sessionId)->delete();
            return;
        }
        Cache::store($this->driver)->forget($this->cacheKey($sessionId));
    }

    // -------------------------------------------------------------------------
    // Database driver helpers
    // -------------------------------------------------------------------------

    private function dbGet(string $sessionId): array
    {
        $row = DB::table('shopbot_sessions')
            ->where('session_id', $sessionId)
            ->first();

        if (! $row) {
            return [];
        }

        // Honour TTL manually — the DB driver has no built-in expiry
        if ((time() - strtotime((string) $row->last_activity_at)) > $this->ttlSeconds) {
            $this->forget($sessionId);
            return [];
        }

        return json_decode((string) $row->messages, true) ?: [];
    }

    private function dbPut(string $sessionId, array $history): void
    {
        DB::table('shopbot_sessions')->upsert(
            [
                'session_id'       => $sessionId,
                'messages'         => json_encode($history),
                'last_activity_at' => now(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            ['session_id'],
            ['messages', 'last_activity_at', 'updated_at']
        );
    }

    // -------------------------------------------------------------------------

    private function cacheKey(string $sessionId): string
    {
        return "ai_shopbot_session_{$sessionId}";
    }
}
