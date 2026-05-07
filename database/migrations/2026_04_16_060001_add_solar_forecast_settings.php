<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            ['key' => 'solar_forecast_enabled', 'value' => '0',        'label' => 'Solar forecast actief',       'type' => 'boolean', 'group' => 'solar'],
            ['key' => 'solar_panel_power_kwp',   'value' => '5',        'label' => 'Paneelvermogen (kWp)',         'type' => 'float',   'group' => 'solar'],
            ['key' => 'solar_panel_tilt',        'value' => '30',       'label' => 'Hellingshoek (graden)',        'type' => 'integer', 'group' => 'solar'],
            ['key' => 'solar_panel_azimuth',     'value' => '315',      'label' => 'Oriëntatie / azimuth (graden)', 'type' => 'integer', 'group' => 'solar'],
            ['key' => 'solar_latitude',          'value' => '52.3702',  'label' => 'Breedtegraad (latitude)',      'type' => 'float',   'group' => 'solar'],
            ['key' => 'solar_longitude',         'value' => '4.8952',   'label' => 'Lengtegraad (longitude)',      'type' => 'float',   'group' => 'solar'],
        ];

        foreach ($defaults as $setting) {
            DB::table('settings')->insertOrIgnore(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'solar_forecast_enabled',
            'solar_panel_power_kwp',
            'solar_panel_tilt',
            'solar_panel_azimuth',
            'solar_latitude',
            'solar_longitude',
        ])->delete();
    }
};
