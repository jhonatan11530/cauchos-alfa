<?php

namespace App\Models;

use App\Services\GeminiPromptService;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saved(function (Setting $setting) {
            if ($setting->key === 'PROMPT_GEMINI') {
                if (class_exists(GeminiPromptService::class)) {
                    GeminiPromptService::clearCache();
                }
            }
        });

        static::deleted(function (Setting $setting) {
            if ($setting->key === 'PROMPT_GEMINI') {
                if (class_exists(GeminiPromptService::class)) {
                    GeminiPromptService::clearCache();
                }
            }
        });
    }

    /**
     * Retrieve a setting value by its key, or fallback to default.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $setting = static::where('key', $key)->first();

        return ($setting && $setting->value !== null) ? (string) $setting->value : $default;
    }

    /**
     * Persist or update a setting record by key.
     */
    public static function set(string $key, ?string $value, ?string $description = null): self
    {
        $setting = static::firstOrNew(['key' => $key]);
        $setting->value = $value;

        if ($description !== null) {
            $setting->description = $description;
        }

        $setting->save();

        return $setting;
    }
}
