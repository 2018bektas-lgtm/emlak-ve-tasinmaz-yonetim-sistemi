<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emsal (karşılaştırılabilir satış) kayıtları. Emsal parseli çoğunlukla
 * kuruma ait değildir, bu yüzden envanter tablosu `tasinmazlar`a bağlanmaz;
 * `emsal_parselleri` haritadan ada/parsel sorgusuyla oluşan hafif bir parsel
 * kaydıdır ve aynı parsele birden çok emsal (encümen + senet + mahkeme)
 * bağlanabilir.
 *
 * Yüz binlerce satır beklendiği için:
 *  - Harita yalnızca `emsal_parselleri`ni okur; emsal özeti (adet, son tarih,
 *    ortalama fiyat) parsel satırında tutulur, böylece bbox sorgusu join'siz
 *    ve tek tabloda kalır.
 *  - Sınır kutusu sütunları tasinmaz_koordinatlar ile aynı konvansiyonda.
 *  - Liste süzgeçleri (tür + tarih, fiyat, konum) için kapsayan indexler var.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emsal_parselleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('il_id')->nullable()->constrained('iller')->nullOnDelete();
            $table->foreignId('ilce_id')->nullable()->constrained('ilceler')->nullOnDelete();
            $table->foreignId('mahalle_id')->nullable()->constrained('mahalleler')->nullOnDelete();
            $table->string('ada', 20);
            $table->string('parsel', 20);
            $table->decimal('alan', 15, 2)->nullable()->comment('m2');
            $table->foreignId('imar_durumu_id')->nullable()->constrained('imar_durumlari')->nullOnDelete();
            $table->decimal('emsal', 5, 2)->nullable()->comment('KAKS');
            $table->string('yenaz_yencok', 50)->nullable();

            // Geometri — tasinmaz_koordinatlar ile aynı sütun düzeni
            $table->json('koordinat')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->decimal('min_lat', 10, 7)->nullable();
            $table->decimal('max_lat', 10, 7)->nullable();
            $table->decimal('min_lng', 10, 7)->nullable();
            $table->decimal('max_lng', 10, 7)->nullable();
            $table->decimal('merkez_lat', 10, 7)->nullable();
            $table->decimal('merkez_lng', 10, 7)->nullable();

            // Harita ve liste bu özetten okur; emsal yazıldıkça tazelenir
            $table->unsignedInteger('emsal_sayisi')->default(0);
            $table->date('son_emsal_tarihi')->nullable();
            $table->decimal('ortalama_birim_fiyat', 15, 2)->nullable();

            $table->timestamps();

            $table->unique(['mahalle_id', 'ada', 'parsel'], 'emsal_parselleri_konum_unique');
            $table->index(['min_lat', 'max_lat', 'min_lng', 'max_lng'], 'emsal_parselleri_sinir_index');
            $table->index(['merkez_lat', 'merkez_lng'], 'emsal_parselleri_merkez_index');
            $table->index(['il_id', 'ilce_id'], 'emsal_parselleri_il_ilce_index');
            // İmar durumuna göre katmanlama: süzgeç + bbox tek indexten okunur
            $table->index(['imar_durumu_id', 'merkez_lat', 'merkez_lng'], 'emsal_parselleri_imar_index');
        });

        Schema::create('emsaller', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emsal_parsel_id')->constrained('emsal_parselleri')->cascadeOnDelete();
            $table->enum('tip', ['encumen', 'senet', 'mahkeme']);

            // Her türde ortak
            $table->date('tarih')->nullable()->comment('Encümen/senet tarihi, mahkemede karar tarihi');
            $table->decimal('birim_fiyat', 15, 2)->nullable()->comment('TL/m2');
            $table->string('evrak', 500)->nullable();
            $table->text('aciklama')->nullable();

            // Türe özel (EmsalTipi::alanlar)
            $table->string('karar_no', 100)->nullable()->comment('encumen, mahkeme');
            $table->string('kayit_no', 100)->nullable()->comment('encumen');
            $table->string('satilan_hisse', 50)->nullable()->comment('encumen, senet');
            $table->string('yevmiye_no', 100)->nullable()->comment('senet');
            $table->string('satis_durumu', 50)->nullable()->comment('senet');
            $table->string('esas_no', 100)->nullable()->comment('mahkeme');
            $table->date('dava_tarihi')->nullable()->comment('mahkeme');

            $table->foreignId('olusturan_id')->nullable()->constrained('kullanicilar')->nullOnDelete();
            $table->timestamps();

            // Liste: tür süzgeci + tarih sıralaması tek indexten
            $table->index(['tip', 'tarih'], 'emsaller_tip_tarih_index');
            $table->index('tarih', 'emsaller_tarih_index');
            $table->index('birim_fiyat', 'emsaller_birim_fiyat_index');
            // Parsel detayı ve özet tazelemesi
            $table->index(['emsal_parsel_id', 'tip'], 'emsaller_parsel_tip_index');
        });

        Schema::create('emsal_bagimsiz_bolumler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emsal_id')->constrained('emsaller')->cascadeOnDelete();
            $table->string('bb_no', 30)->nullable();
            $table->string('kat', 30)->nullable();
            $table->string('cephe', 50)->nullable();
            $table->decimal('brut_alan', 10, 2)->nullable();
            $table->string('nitelik', 100)->nullable();
            $table->string('bb_durumu', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['emsal_bagimsiz_bolumler', 'emsaller', 'emsal_parselleri'] as $tablo) {
            Schema::dropIfExists($tablo);
        }
    }
};
