<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, mixed $value, ?string $description = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'description' => $description]
        );
    }

    public static function getShopifyPriceType(): string
    {
        return static::get('shopify_price_type', 'ttc');
    }

    /**
     * @return list<string>
     */
    public static function getList(string $key, array $default = []): array
    {
        $raw = static::get($key);
        if ($raw === null || $raw === '') {
            return $default;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $lines = preg_split('/\r\n|\r|\n/', (string) $raw) ?: [];

            return array_values(array_filter(array_map('trim', $lines)));
        }

        return array_values(array_filter(array_map('strval', $decoded)));
    }

    /**
     * @param  list<string>  $items
     */
    public static function setList(string $key, array $items, ?string $description = null): void
    {
        $clean = array_values(array_unique(array_filter(array_map('trim', $items))));
        static::set($key, json_encode($clean, JSON_UNESCAPED_UNICODE), $description);
    }
}
