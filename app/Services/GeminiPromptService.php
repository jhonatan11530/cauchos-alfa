<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class GeminiPromptService
{
    public const PROMPT_KEY = 'PROMPT_GEMINI';
    public const CACHE_KEY = 'setting_prompt_gemini';
    public const DEFAULT_TTL = 3600;

    public const CANONICAL_FALLBACK_PROMPT = 'no mantegas el diseño original luego recrea la imagen, Mejora la imagen aplicando un estilo de fotografía de estudio en primer plano y de alta calidad sobre un fondo blanco puro y continuo, alineada de extremo a extremo, respetando su orientación original en una vista estrictamente horizontal o vertical sin ningún grado de inclinación en los bordes los bordes deben tener el mismo tamaño y sin distorsiones de perspectiva';

    /**
     * Tier 1: In-memory static cache for current PHP process / request.
     */
    protected static ?string $memoryCache = null;

    /**
     * Unix timestamp when the Tier 1 in-memory cache expires.
     */
    protected static int $memoryExpiresAt = 0;

    /**
     * Configurable cache TTL in seconds.
     */
    protected static int $ttl = self::DEFAULT_TTL;

    /**
     * Configure cache TTL in seconds.
     */
    public static function setTtl(int $seconds): void
    {
        self::$ttl = $seconds;
    }

    /**
     * Retrieve current cache TTL in seconds.
     */
    public static function getTtl(): int
    {
        return self::$ttl;
    }

    /**
     * Clear both Tier 1 in-memory and Tier 2 Laravel Cache stores.
     */
    public static function clearCache(): void
    {
        self::$memoryCache = null;
        self::$memoryExpiresAt = 0;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Retrieve the active Gemini prompt from memory, cache, or database.
     */
    public static function getPrompt(): string
    {
        $now = now()->timestamp;

        // Tier 1: In-memory cache
        if (self::$memoryCache !== null && $now < self::$memoryExpiresAt) {
            return self::$memoryCache;
        }

        // Tier 2: Laravel Cache
        $cached = Cache::get(self::CACHE_KEY);
        if (
            is_array($cached)
            && isset($cached['value'])
            && is_string($cached['value'])
            && isset($cached['cached_at'])
            && ($now - $cached['cached_at'] < self::$ttl)
        ) {
            self::$memoryCache = $cached['value'];
            self::$memoryExpiresAt = $cached['cached_at'] + self::$ttl;
            return self::$memoryCache;
        }

        // Tier 3: Database query and cache populate
        return self::loadFromDatabaseAndCache();
    }

    /**
     * Load prompt from database, populate Tier 1 and Tier 2 caches, and return prompt.
     */
    protected static function loadFromDatabaseAndCache(): string
    {
        $prompt = null;
        $updatedAt = null;

        try {
            $setting = Setting::where('key', self::PROMPT_KEY)->first();
            if ($setting && $setting->value !== null) {
                $prompt = (string) $setting->value;
                $updatedAt = $setting->updated_at ? $setting->updated_at->timestamp : now()->timestamp;
            }
        } catch (\Throwable $e) {
            $prompt = null;
        }

        // Graceful fallback if database has no record or table is empty
        if ($prompt === null) {
            $configPrompt = config('services.gemini.prompt');
            $prompt = !empty($configPrompt) ? (string) $configPrompt : self::CANONICAL_FALLBACK_PROMPT;
            $updatedAt = now()->timestamp;
        }

        $now = now()->timestamp;
        $payload = [
            'value' => $prompt,
            'hash' => hash('sha256', $prompt),
            'updated_at' => $updatedAt,
            'cached_at' => $now,
        ];

        Cache::put(self::CACHE_KEY, $payload, self::$ttl);

        self::$memoryCache = $prompt;
        self::$memoryExpiresAt = $now + self::$ttl;

        return $prompt;
    }

    /**
     * Explicit revalidation: check if database prompt changed by SHA-256 hash.
     * Updates cache and returns true if changed (or cache was empty), false if unchanged.
     */
    public static function refreshIfChanged(): bool
    {
        $cached = Cache::get(self::CACHE_KEY);
        $cachedHash = (is_array($cached) && isset($cached['hash'])) ? $cached['hash'] : null;

        $setting = Setting::where('key', self::PROMPT_KEY)->first();
        if (!$setting || $setting->value === null) {
            if ($cachedHash !== null) {
                self::clearCache();
                return true;
            }
            return false;
        }

        $currentValue = (string) $setting->value;
        $currentHash = hash('sha256', $currentValue);

        if ($cachedHash === null || $cachedHash !== $currentHash) {
            $now = now()->timestamp;
            $updatedAt = $setting->updated_at ? $setting->updated_at->timestamp : $now;
            $payload = [
                'value' => $currentValue,
                'hash' => $currentHash,
                'updated_at' => $updatedAt,
                'cached_at' => $now,
            ];

            Cache::put(self::CACHE_KEY, $payload, self::$ttl);
            self::$memoryCache = $currentValue;
            self::$memoryExpiresAt = $now + self::$ttl;

            return true;
        }

        // Renew cache timestamp
        $now = now()->timestamp;
        $cached['cached_at'] = $now;
        Cache::put(self::CACHE_KEY, $cached, self::$ttl);
        self::$memoryCache = $currentValue;
        self::$memoryExpiresAt = $now + self::$ttl;

        return false;
    }

    /**
     * Generic accessor or alias for getPrompt().
     */
    public static function get(string $key = self::PROMPT_KEY): string
    {
        if ($key === self::PROMPT_KEY) {
            return self::getPrompt();
        }

        return (string) (Setting::get($key, '') ?? '');
    }
}
