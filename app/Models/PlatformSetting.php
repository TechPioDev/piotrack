<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * A platform-wide setting. NOT tenant-scoped, by design — these belong to the
 * platform operator (AI provider, API keys), and the values are encrypted at
 * rest because some of them are credentials.
 *
 * @property string $key
 * @property string|null $value
 */
class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'encrypted'];
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->first()->value ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            static::query()->where('key', $key)->delete();

            return;
        }

        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * All settings under a prefix, as a flat map. Safe to call before the table
     * exists (fresh installs, mid-migration artisan): it just answers empty.
     *
     * @return array<string, string>
     */
    public static function under(string $prefix): array
    {
        try {
            if (! Schema::hasTable('platform_settings')) {
                return [];
            }

            return static::query()
                ->where('key', 'like', $prefix.'%')
                ->pluck('value', 'key')
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
