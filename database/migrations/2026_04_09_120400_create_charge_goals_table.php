<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_goals', function (Blueprint $table) {
            $table->id();
            $table->timestamp('depart_at');                          // Wanneer vertrekt de auto
            $table->unsignedTinyInteger('target_soc');               // Gewenst laadniveau % (bijv. 90)
            $table->unsignedTinyInteger('current_soc');              // Huidig laadniveau % bij invoer
            $table->decimal('energy_needed_kwh', 6, 2);             // (target-current)/100 * capacity
            $table->decimal('energy_added_kwh', 6, 2)->default(0);  // Bijgehouden via EnergySession
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Voertuig-instellingen toevoegen
        DB::table('settings')->insertOrIgnore([
            ['key' => 'battery_capacity_kwh', 'value' => '60',  'type' => 'float',   'label' => 'Batterijcapaciteit (kWh)', 'group' => 'vehicle'],
            ['key' => 'default_target_soc',   'value' => '90',  'type' => 'integer', 'label' => 'Standaard doel SoC (%)',   'group' => 'vehicle'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_goals');
        DB::table('settings')->whereIn('key', ['battery_capacity_kwh', 'default_target_soc'])->delete();
    }
};
