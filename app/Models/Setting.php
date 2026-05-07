<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['key', 'value', 'label', 'type', 'group'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::find($key);
        if (!$setting) return $default;

        return match ($setting->type) {
            'integer' => (int) $setting->value,
            'float'   => (float) $setting->value,
            'boolean' => (bool) $setting->value,
            default   => $setting->value,
        };
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()]
        );
    }
}
