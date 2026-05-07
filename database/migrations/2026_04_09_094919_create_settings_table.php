<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->string('label')->nullable();
            $table->string('type')->default('string');
            $table->string('group')->default('general');
            $table->timestamps();
        });

        $defaults = [
            ['key' => 'peblar_ip', 'value' => env('PEBLAR_IP', ''), 'label' => 'Peblar IP-adres', 'type' => 'string', 'group' => 'peblar'],
            ['key' => 'peblar_token', 'value' => env('PEBLAR_TOKEN', ''), 'label' => 'Peblar API Token', 'type' => 'string', 'group' => 'peblar'],
            ['key' => 'solax_ip', 'value' => env('SOLAX_IP', ''), 'label' => 'Solax IP-adres', 'type' => 'string', 'group' => 'solax'],
            ['key' => 'solax_password', 'value' => env('SOLAX_PASSWORD', ''), 'label' => 'Solax Wachtwoord (serienummer)', 'type' => 'string', 'group' => 'solax'],
            ['key' => 'p1_ip', 'value' => env('P1_IP', ''), 'label' => 'Slimmelezer IP-adres', 'type' => 'string', 'group' => 'p1'],
            ['key' => 'zonneplan_email', 'value' => env('ZONNEPLAN_EMAIL', ''), 'label' => 'Zonneplan E-mail', 'type' => 'string', 'group' => 'zonneplan'],
            ['key' => 'zonneplan_password', 'value' => env('ZONNEPLAN_PASSWORD', ''), 'label' => 'Zonneplan Wachtwoord', 'type' => 'string', 'group' => 'zonneplan'],
            ['key' => 'max_charge_power_w', 'value' => env('MAX_CHARGE_POWER_W', 13000), 'label' => 'Max laadvermogen (W)', 'type' => 'integer', 'group' => 'balancer'],
            ['key' => 'grid_capacity_w', 'value' => env('GRID_CAPACITY_W', 25000), 'label' => 'Netcapaciteit (W)', 'type' => 'integer', 'group' => 'balancer'],
            ['key' => 'grid_buffer_w', 'value' => env('GRID_BUFFER_W', 500), 'label' => 'Grid buffer (W)', 'type' => 'integer', 'group' => 'balancer'],
            ['key' => 'phase_count', 'value' => env('PHASE_COUNT', 3), 'label' => 'Aantal fases', 'type' => 'integer', 'group' => 'balancer'],
            ['key' => 'price_threshold_high', 'value' => '0.15', 'label' => 'Prijs drempel hoog (€/kWh)', 'type' => 'float', 'group' => 'balancer'],
            ['key' => 'price_threshold_normal', 'value' => '0.25', 'label' => 'Prijs drempel normaal (€/kWh)', 'type' => 'float', 'group' => 'balancer'],
            ['key' => 'solar_min_surplus_w', 'value' => '1500', 'label' => 'Minimaal zonne-overschot (W)', 'type' => 'integer', 'group' => 'balancer'],
            ['key' => 'hysteresis_ma', 'value' => '500', 'label' => 'Hysteresis (mA)', 'type' => 'integer', 'group' => 'balancer'],
            ['key' => 'balancer_enabled', 'value' => '1', 'label' => 'Load balancer actief', 'type' => 'boolean', 'group' => 'balancer'],
        ];

        foreach ($defaults as $setting) {
            DB::table('settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
