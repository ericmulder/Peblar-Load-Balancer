<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meter_readings', function (Blueprint $table) {
            $table->unsignedTinyInteger('vehicle_soc')->nullable();          // SoC % van de auto
            $table->boolean('vehicle_charging')->nullable();                  // Aan het laden?
            $table->boolean('vehicle_plugged_in')->nullable();                // Kabel in?
            $table->unsignedSmallInteger('vehicle_range_km')->nullable();    // Geschat rijbereik
            $table->unsignedSmallInteger('vehicle_minutes_to_full')->nullable(); // Resterende laadtijd
        });

        // Hyundai BlueLink instellingen
        DB::table('settings')->insertOrIgnore([
            ['key' => 'hyundai_username',      'value' => '', 'type' => 'string',  'label' => 'BlueLink e-mailadres',    'group' => 'hyundai'],
            ['key' => 'hyundai_refresh_token', 'value' => '', 'type' => 'string',  'label' => 'BlueLink refresh token',  'group' => 'hyundai'],
            ['key' => 'hyundai_pin',           'value' => '', 'type' => 'string',  'label' => 'BlueLink PIN (optioneel)', 'group' => 'hyundai'],
            ['key' => 'hyundai_enabled',       'value' => '0','type' => 'boolean', 'label' => 'Voertuigdata ophalen',     'group' => 'hyundai'],
        ]);
    }

    public function down(): void
    {
        Schema::table('meter_readings', function (Blueprint $table) {
            $table->dropColumn(['vehicle_soc', 'vehicle_charging', 'vehicle_plugged_in', 'vehicle_range_km', 'vehicle_minutes_to_full']);
        });

        DB::table('settings')->whereIn('key', ['hyundai_username', 'hyundai_refresh_token', 'hyundai_pin', 'hyundai_enabled'])->delete();
    }
};
