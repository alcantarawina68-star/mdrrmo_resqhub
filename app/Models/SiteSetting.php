<?php

namespace App\Models;

use Database\Factories\SiteSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasFactory;

    public const CACHE_KEY = 'site_settings';

    public const DEFAULTS = [
        'agency_short_name' => 'MDRRMO Camalaniugan',
        'agency_name' => 'Municipal Disaster Risk Reduction and Management Office',
        'municipality' => 'Camalaniugan, Cagayan',
        'hotline' => '0917 123 4567',
        'website' => 'resqhub.ph',
        'contact_email' => 'info@resqhub.ph',
    ];

    protected $fillable = [
        'key',
        'value',
    ];

    public static function value(string $key, ?string $default = null): ?string
    {
        $settings = Cache::rememberForever(self::CACHE_KEY, function () {
            return self::query()->pluck('value', 'key')->all();
        });

        $fallback = self::DEFAULTS[$key] ?? $default;

        return $settings[$key] ?? $fallback;
    }

    public static function set(string $key, ?string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
