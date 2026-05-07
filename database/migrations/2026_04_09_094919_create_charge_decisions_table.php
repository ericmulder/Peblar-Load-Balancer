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
        Schema::create('charge_decisions', function (Blueprint $table) {
            $table->id();
            $table->timestamp('decided_at')->index();
            $table->tinyInteger('priority')->default(0);
            $table->string('priority_label')->nullable();
            $table->integer('desired_power_w')->default(0);
            $table->integer('charge_current_ma')->default(0);
            $table->integer('previous_current_ma')->default(0);
            $table->string('reason')->nullable();
            $table->decimal('price_eur', 8, 4)->nullable();
            $table->integer('solar_surplus_w')->nullable();
            $table->integer('household_consumption_w')->nullable();
            $table->integer('available_grid_w')->nullable();
            $table->boolean('command_sent')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_decisions');
    }
};
