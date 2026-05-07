<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'price_threshold'],
            [
                'value'      => '0.22',
                'label'      => 'Prijsdrempel (€/kWh)',
                'type'       => 'float',
                'group'      => 'balancer',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        DB::table('settings')->whereIn('key', ['price_threshold_high', 'price_threshold_normal'])->delete();
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'price_threshold')->delete();

        DB::table('settings')->insertOrIgnore([
            [
                'key'        => 'price_threshold_high',
                'value'      => '0.15',
                'label'      => 'Prijsdrempel hoog (€/kWh)',
                'type'       => 'float',
                'group'      => 'balancer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key'        => 'price_threshold_normal',
                'value'      => '0.25',
                'label'      => 'Prijsdrempel normaal (€/kWh)',
                'type'       => 'float',
                'group'      => 'balancer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
};
