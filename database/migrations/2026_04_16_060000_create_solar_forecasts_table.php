<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solar_forecasts', function (Blueprint $table) {
            $table->id();
            $table->timestamp('hour')->unique();
            $table->float('wh_expected');       // verwachte opwekking in Wh
            $table->float('gti_wm2')->nullable(); // Global Tilted Irradiance W/m² (bron)
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solar_forecasts');
    }
};
