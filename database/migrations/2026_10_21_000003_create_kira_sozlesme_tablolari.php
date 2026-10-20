<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sözleşme, yıl bazlı bedeller ve kiralanan alanlar.
 *
 * Referansta bedel "Yıllık Kira Bedeli / 2.Yıl / 3.Yıl" diye ayrı sütunlardı;
 * dördüncü yıl gerektiğinde şema değişmek zorunda kalıyordu. Burada her yıl
 * bir satır: artış, TÜFE oranı ve güncel bedel aynı satırda durur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kira_sozlesmeleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kira_dosya_id')->constrained('kira_dosyalari')->cascadeOnDelete();
            $table->string('sozlesme_no', 60)->nullable();

            $table->date('baslangic_tarihi');
            $table->date('bitis_tarihi');
            // Süre tarihlerden hesaplanabilir; yine de sözleşmede yazan değer
            // farklı olabildiği için ayrıca tutulur (örn. 36 ay = 3 yıl).
            $table->unsignedSmallInteger('sure_ay')->nullable();

            $table->unsignedTinyInteger('taksit_sayisi')->nullable();
            $table->string('odeme_sekli', 80)->nullable();
            $table->string('odeme_zamani', 80)->nullable();
            $table->unsignedTinyInteger('son_odeme_gunu')->nullable();

            $table->date('sozlesme_tarihi')->nullable();
            $table->date('noter_tarihi')->nullable();
            $table->string('yevmiye_no', 60)->nullable();

            $table->decimal('kdv_orani', 5, 2)->nullable();
            $table->boolean('kdv_dahil_mi')->default(false);

            $table->string('artirma_yontemi', 100)->nullable()->comment('TÜFE, sabit oran vb.');
            $table->unsignedTinyInteger('artirma_ayi')->nullable();

            $table->boolean('aktif_mi')->default(true);
            $table->text('aciklama')->nullable();
            $table->timestamps();

            // "Süresi dolanlar / son bir ay kalanlar" sorguları bu indexi kullanır
            $table->index(['aktif_mi', 'bitis_tarihi'], 'kira_sozlesmeleri_bitis_index');
            $table->index(['kira_dosya_id', 'aktif_mi']);
            $table->index('baslangic_tarihi');
        });

        Schema::create('kira_bedelleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kira_sozlesme_id')->constrained('kira_sozlesmeleri')->cascadeOnDelete();
            $table->unsignedTinyInteger('yil_sirasi')->comment('Sözleşmenin kaçıncı yılı');
            $table->date('donem_baslangic')->nullable();
            $table->date('donem_bitis')->nullable();

            $table->decimal('yillik_bedel', 15, 2);
            $table->decimal('aylik_bedel', 15, 2)->nullable();
            // Artışın dayandığı oran, hesaplandığı anda kopyalanır: oran tablosu
            // sonradan düzeltilse bile geçmiş bedel değişmemeli.
            $table->decimal('tufe_orani', 6, 3)->nullable();
            $table->decimal('guncel_yillik_bedel', 15, 2)->nullable();
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->unique(['kira_sozlesme_id', 'yil_sirasi'], 'kira_bedelleri_yil_unique');
        });

        /**
         * Kiralanan alanlar: bir dosyada birden çok yer kiraya verilebiliyor
         * (referans: geoemlak_kiracilar_kira_alanlari). Her alanın kendi
         * geometrisi var, harita bunları ayrı katman olarak çizer.
         */
        Schema::create('kira_alanlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kira_dosya_id')->constrained('kira_dosyalari')->cascadeOnDelete();
            $table->string('ad', 200)->nullable();
            $table->text('adres')->nullable();
            $table->decimal('alan', 15, 2)->nullable()->comment('m2');
            $table->string('faaliyet_konusu', 200)->nullable();
            $table->string('kiralama_amaci', 200)->nullable();
            $table->foreignId('is_kolu_id')->nullable()->constrained('kira_is_kollari')->nullOnDelete();
            $table->decimal('yillik_bedel', 15, 2)->nullable();
            $table->date('baslangic_tarihi')->nullable();
            $table->date('bitis_tarihi')->nullable();
            $table->boolean('aktif_mi')->default(true);
            $table->text('aciklama')->nullable();

            $table->json('koordinat')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->decimal('min_lat', 10, 7)->nullable();
            $table->decimal('max_lat', 10, 7)->nullable();
            $table->decimal('min_lng', 10, 7)->nullable();
            $table->decimal('max_lng', 10, 7)->nullable();
            $table->decimal('merkez_lat', 10, 7)->nullable();
            $table->decimal('merkez_lng', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['kira_dosya_id', 'aktif_mi']);
            $table->index(['min_lat', 'max_lat', 'min_lng', 'max_lng'], 'kira_alanlari_sinir_index');
            $table->index(['merkez_lat', 'merkez_lng'], 'kira_alanlari_merkez_index');
        });
    }

    public function down(): void
    {
        foreach (['kira_alanlari', 'kira_bedelleri', 'kira_sozlesmeleri'] as $tablo) {
            Schema::dropIfExists($tablo);
        }
    }
};
