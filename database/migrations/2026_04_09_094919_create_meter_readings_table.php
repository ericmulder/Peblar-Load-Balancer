<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->timestamp('recorded_at')->index();
            // Peblar
            $table->integer('peblar_power_total')->nullable();
            $table->integer('peblar_power_l1')->nullable();
            $table->integer('peblar_power_l2')->nullable();
            $table->integer('peblar_power_l3')->nullable();
            $table->integer('peblar_current_l1')->nullable();
            $table->integer('peblar_current_l2')->nullable();
            $table->integer('peblar_current_l3')->nullable();
            $table->integer('peblar_voltage_l1')->nullable();
            $table->integer('peblar_voltage_l2')->nullable();
            $table->integer('peblar_voltage_l3')->nullable();
            $table->bigInteger('peblar_energy_session')->nullable();
            $table->bigInteger('peblar_energy_total')->nullable();
            $table->string('peblar_cp_state')->nullable();
            $table->integer('peblar_charge_current_limit')->nullable();
            $table->integer('peblar_charge_current_actual')->nullable();
            // P1
            $table->integer('p1_power_consumed')->nullable();
            $table->integer('p1_power_produced')->nullable();
            $table->decimal('p1_voltage_l1', 6, 1)->nullable();
            $table->decimal('p1_voltage_l2', 6, 1)->nullable();
            $table->decimal('p1_voltage_l3', 6, 1)->nullable();
            // Solax
            $table->integer('solax_pv_power')->nullable();
            $table->integer('solax_battery_soc')->nullable();
            $table->integer('solax_battery_power')->nullable();
            $table->integer('solax_grid_power')->nullable();
            // Zonneplan
            $table->decimal('price_current', 8, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_readings');
    }
};
