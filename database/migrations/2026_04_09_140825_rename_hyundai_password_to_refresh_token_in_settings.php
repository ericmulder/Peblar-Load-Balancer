<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Als er nog een oud 'hyundai_password' record bestaat, verplaats de waarde
        // naar 'hyundai_refresh_token' en verwijder het oude record.
        $old = DB::table('settings')->where('key', 'hyundai_password')->first();
        if ($old) {
            DB::table('settings')
                ->where('key', 'hyundai_refresh_token')
                ->update(['value' => $old->value]);

            DB::table('settings')->where('key', 'hyundai_password')->delete();
        }

        // Zorg dat het refresh_token record bestaat (voor het geval de vorige migratie werd overgeslagen)
        DB::table('settings')->insertOrIgnore([
            ['key' => 'hyundai_refresh_token', 'value' => '', 'type' => 'string', 'label' => 'BlueLink refresh token', 'group' => 'hyundai'],
        ]);
    }

    public function down(): void
    {
        // Niets te herstellen
    }
};
