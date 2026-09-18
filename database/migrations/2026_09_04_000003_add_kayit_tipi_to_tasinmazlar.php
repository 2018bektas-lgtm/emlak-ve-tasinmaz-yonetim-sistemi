<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasinmazlar', function (Blueprint $table) {
            // Kayit tipi:
            //  bos_parsel          : Üzerinde yapı yok (arsa/arazi)
            //  kat_mulkiyetli      : Kendi TAKBIS zemin nosu olan tek BBN
            //  kat_mulkiyetsiz_bina: Tapuda arsa görünür, üstünde bina var — çoklu BBN
            $table->enum('kayit_tipi', ['bos_parsel', 'kat_mulkiyetli', 'kat_mulkiyetsiz_bina'])
                ->default('bos_parsel')
                ->after('uzeri_bina_var_mi')
                ->index();
        });

        // Mevcut kayıtları uzeri_bina_var_mi + yapı sayısına göre otomatik doldur
        \Illuminate\Support\Facades\DB::statement("
            UPDATE tasinmazlar t
            LEFT JOIN (
                SELECT tasinmaz_id, COUNT(*) AS say FROM tasinmaz_yapi GROUP BY tasinmaz_id
            ) y ON y.tasinmaz_id = t.id
            SET t.kayit_tipi = CASE
                WHEN (t.uzeri_bina_var_mi = 0 OR t.uzeri_bina_var_mi IS NULL) AND (y.say IS NULL OR y.say = 0) THEN 'bos_parsel'
                WHEN y.say > 1 THEN 'kat_mulkiyetsiz_bina'
                ELSE 'kat_mulkiyetli'
            END
        ");
    }

    public function down(): void
    {
        Schema::table('tasinmazlar', function (Blueprint $table) {
            $table->dropIndex(['kayit_tipi']);
            $table->dropColumn('kayit_tipi');
        });
    }
};
