<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kiracı artık dosyaya değil, sözleşme dönemine bağlanır.
 *
 * Bir dosya numarasında (örn. K0050) yıllar içinde birbiri ardına farklı
 * kiracılar olur: 5 ayrı 1 yıllık dönem, 5 ayrı firma. Kiracı yalnız dosyaya
 * bağlıyken bu geçmiş tutulamıyordu — aynı anda tek kiracı görünüyordu.
 *
 * kira_sozlesme_id boş bırakılabilir: döneme bağlanmamış satır dosyanın
 * genel kiracısı sayılır (eski kayıtlar ve veri aktarımı için).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kira_kiracilari', function (Blueprint $tablo) {
            $tablo->foreignId('kira_sozlesme_id')->nullable()->after('kira_dosya_id')
                ->constrained('kira_sozlesmeleri')->nullOnDelete();
            // Dönem kartı kiracılarını bu index üzerinden okur
            $tablo->index(['kira_sozlesme_id', 'tip'], 'kira_kiracilari_donem_index');
        });

        Schema::table('kira_belgeleri', function (Blueprint $tablo) {
            $tablo->foreignId('kira_sozlesme_id')->nullable()->after('kira_alan_id')
                ->constrained('kira_sozlesmeleri')->nullOnDelete();
            $tablo->index('kira_sozlesme_id', 'kira_belgeleri_donem_index');
        });

        // Mevcut kiracılar dosyanın yürürlükteki dönemine bağlanır; böylece
        // geçmiş listesinde doğru dönemin altında görünürler.
        $this->mevcutKiracilariDonemeBagla();
    }

    public function down(): void
    {
        Schema::table('kira_belgeleri', function (Blueprint $tablo) {
            $tablo->dropIndex('kira_belgeleri_donem_index');
            $tablo->dropConstrainedForeignId('kira_sozlesme_id');
        });

        Schema::table('kira_kiracilari', function (Blueprint $tablo) {
            $tablo->dropIndex('kira_kiracilari_donem_index');
            $tablo->dropConstrainedForeignId('kira_sozlesme_id');
        });
    }

    /** Dosya başına tek sorgu değil; tüm dosyalar için tek UPDATE. */
    private function mevcutKiracilariDonemeBagla(): void
    {
        // Her dosyanın yürürlükteki (yoksa en yeni biten) sözleşmesi
        $donemler = DB::table('kira_sozlesmeleri')
            ->orderBy('aktif_mi')
            ->orderBy('bitis_tarihi')
            ->get(['id', 'kira_dosya_id'])
            ->keyBy('kira_dosya_id');

        foreach ($donemler as $dosyaId => $donem) {
            DB::table('kira_kiracilari')
                ->where('kira_dosya_id', $dosyaId)
                ->whereNull('kira_sozlesme_id')
                ->update(['kira_sozlesme_id' => $donem->id]);
        }
    }
};
