<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Max laadstroom per fase (beschermt de groepenkast-automaat)
        DB::table('settings')->insertOrIgnore([
            'key'        => 'max_charge_current_a',
            'value'      => '13',
            'label'      => 'Max laadstroom per fase (A) — bijv. 13 of 16',
            'type'       => 'integer',
            'group'      => 'balancer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Max totale belasting op het net (alle fases × 230V)
        // 25A × 3 fases × 230V = 17.250W — gebruik dit als veiligheidsgrens
        DB::table('settings')
            ->where('key', 'grid_capacity_w')
            ->where('value', '25000') // alleen als nog op default staat
            ->update(['value' => '17250', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'max_charge_current_a')->delete();
    }
};
