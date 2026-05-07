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
        Schema::create('price_forecasts', function (Blueprint $table) {
            $table->id();
            $table->timestamp('hour')->unique();
            $table->decimal('price_eur_incl_tax', 8, 4);
            $table->decimal('price_eur_excl_tax', 8, 4)->nullable();
            $table->string('source')->default('zonneplan');
            $table->timestamp('fetched_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_forecasts');
    }
};
