<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taşınmaz değerleme: envanterdeki bir taşınmazın (arsa) ya da yapılarından
 * birinin (bağımsız bölüm) rayiç değer raporu. Değerleme taşınmaz
 * oluşturmaz; yalnızca kayıtlı taşınmazdan açılır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasinmaz_degerlemeleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->index()->constrained('tasinmazlar')->restrictOnDelete();
            $table->string('tip', 10)->index()->comment('arsa | bb');
            // BB değerlemesinde hangi yapı satırı (varsa); bb_* alanları rapor anındaki kopya
            $table->foreignId('yapi_id')->nullable()->index()->constrained('tasinmaz_yapi')->nullOnDelete();
            $table->string('bb_no', 30)->nullable();
            $table->string('kat', 30)->nullable();
            $table->string('bb_nitelik', 100)->nullable();
            $table->string('oda_sayisi', 20)->nullable()->comment('Örn. 3+1');
            $table->decimal('brut_alan', 12, 2)->nullable();
            // Arsa değerlemesinde değerlenen (kurum hissesi) alan
            $table->decimal('hisse_alan', 14, 2)->nullable();
            $table->decimal('birim_fiyat', 15, 2)->nullable()->comment('TL/m²');
            $table->decimal('toplam_bedel', 18, 2);
            $table->string('rapor_no', 60)->nullable()->index();
            $table->date('rapor_tarihi')->nullable()->index();
            $table->foreignId('hazirlayan_id')->nullable()->constrained('kullanicilar')->nullOnDelete();
            $table->foreignId('olusturan_id')->nullable()->constrained('kullanicilar')->nullOnDelete();
            $table->string('evrak')->nullable()->comment('Rapor PDF (public disk)');
            $table->text('aciklama')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasinmaz_degerlemeleri');
    }
};
