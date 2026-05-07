<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('label', 100)->nullable();
            $table->tinyInteger('day_of_week')->nullable(); // 1=ma .. 7=zo, null=elke dag
            $table->time('deadline_time'); // bijv. "09:00"
            $table->unsignedTinyInteger('target_soc'); // bijv. 80
            $table->unsignedTinyInteger('min_soc')->nullable(); // minimaal vereist %
            $table->unsignedSmallInteger('plan_ahead_hours')->default(24);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        \Illuminate\Support\Facades\DB::table('charge_schedules')->insert([
            [
                'label'            => 'Doordeweeks ochtend',
                'day_of_week'      => null,
                'deadline_time'    => '09:00:00',
                'target_soc'       => 80,
                'min_soc'          => null,
                'plan_ahead_hours' => 24,
                'active'           => 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_schedules');
    }
};
