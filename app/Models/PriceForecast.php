<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceForecast extends Model
{
    protected $fillable = [
        'hour', 'price_eur_incl_tax', 'price_eur_excl_tax', 'source', 'fetched_at',
    ];

    protected $casts = [
        'hour' => 'datetime',
        'fetched_at' => 'datetime',
    ];

    public static function currentPrice(): ?float
    {
        $record = static::where('hour', '<=', now())
            ->where('hour', '>', now()->subHour())
            ->orderByDesc('hour')
            ->first();

        return $record?->price_eur_incl_tax;
    }
}
