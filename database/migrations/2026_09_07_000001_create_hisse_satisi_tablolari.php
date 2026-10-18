<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hisse satışı: kurumun hisseli taşınmazdaki payının paydaşlara satışı.
 * Bir taşınmazın birlikte yürüyen başvuruları bir "dosya"dır (grup_no =
 * dosyadaki ilk başvurunun id'si). Aşamalar: başvuru → ön tebligat →
 * görüş/imar → encümen → satış tebligatı → ödeme → tapu tescili.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hisse_basvurulari', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tasinmaz_id');
            $table->unsignedBigInteger('grup_no')->nullable()->index();
            $table->string('ad_soyad', 150);
            $table->string('tc_kimlik', 11);
            $table->string('gsm_no', 20)->nullable();
            $table->date('basvuru_tarihi');
            $table->decimal('tapu_hisse', 15, 2)->nullable();
            $table->decimal('talep_edilen_hisse', 15, 2)->nullable();
            $table->string('basvuru_evrak', 500)->nullable();
            $table->text('aciklama')->nullable();
            $table->string('durum', 40)->default('basvuruldu')->index();
            $table->timestamps();

            $table->index(['tasinmaz_id', 'durum']);
            $table->foreign('tasinmaz_id')->references('id')->on('tasinmazlar')->cascadeOnDelete();
        });

        // Ön tebligat: diğer hissedarlara 15 günlük itiraz bildirimi
        Schema::create('hisse_tebligatlari', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grup_no')->index();
            $table->foreignId('tasinmaz_id')->constrained('tasinmazlar')->cascadeOnDelete();
            $table->string('ad_soyad', 150);
            $table->string('tc_kimlik', 11);
            $table->decimal('tapu_hisse', 15, 2)->nullable();
            $table->date('tebligat_tarihi')->nullable();
            $table->date('ulastigi_tarihi')->nullable();
            $table->boolean('basvurdu')->default(false);
            $table->boolean('tebligat_ulasmadi')->default(false);
            $table->timestamps();
        });

        Schema::create('hisse_goruslar', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grup_no')->index();
            $table->foreignId('tasinmaz_id')->constrained('tasinmazlar')->cascadeOnDelete();
            $table->string('gorus_sube', 200);
            $table->date('giden_tarih')->nullable();
            $table->string('giden_yazi', 100)->nullable();
            $table->string('giden_evrak', 500)->nullable();
            $table->date('gelen_tarih')->nullable();
            $table->string('gelen_yazi', 100)->nullable();
            $table->string('gelen_evrak', 500)->nullable();
            $table->string('engel_var_yok', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('hisse_imar_evraklar', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grup_no')->index();
            $table->foreignId('tasinmaz_id')->constrained('tasinmazlar')->cascadeOnDelete();
            $table->string('imar_giden_yazi', 100)->nullable();
            $table->date('imar_giden_tarih')->nullable();
            $table->string('imar_giden_evrak', 500)->nullable();
            $table->string('imar_gelen_yazi', 100)->nullable();
            $table->date('imar_gelen_tarih')->nullable();
            $table->string('imar_gelen_evrak', 500)->nullable();
            $table->string('imar_durum', 150)->nullable();
            $table->string('emsal', 30)->nullable();
            $table->string('yencok', 30)->nullable();
            $table->text('plan_notlari')->nullable();
            $table->timestamps();
        });

        // Encümen kararı: birim fiyat satış tebligatlarına esas alınır
        Schema::create('tasinmaz_encumenleri', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grup_no')->index();
            $table->foreignId('tasinmaz_id')->constrained('tasinmazlar')->cascadeOnDelete();
            $table->string('karar_no', 100)->nullable();
            $table->string('kayit_no', 100)->nullable();
            $table->decimal('birim_fiyat', 15, 2)->nullable();
            $table->date('gelen_tarih')->nullable();
            $table->date('giden_tarih')->nullable();
            $table->string('gelen_evrak', 500)->nullable();
            $table->string('giden_evrak', 500)->nullable();
            $table->text('aciklama')->nullable();
            $table->timestamps();
        });

        // Başvuru başına tek satış tebligatı; satış/tapu varken başvuru silinemez
        Schema::create('hisse_satis_tebligatlari', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('basvuru_id');
            $table->unsignedBigInteger('grup_no')->index();
            $table->date('tebligat_tarihi');
            $table->date('ulastigi_tarihi')->nullable();
            $table->decimal('hisseye_dusen_yuzolcum', 15, 2);
            $table->decimal('birim_fiyat', 15, 2)->nullable();
            $table->decimal('toplam_bedel', 15, 2);
            $table->boolean('odedi')->default(false);
            $table->boolean('tebligat_ulasmadi')->default(false);
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->unique('basvuru_id', 'hisse_satis_tebligatlari_basvuru_unique');
            $table->foreign('basvuru_id')->references('id')->on('hisse_basvurulari');
        });

        // Tapu tescili; envanter_islem: kurum hissesinde yapılan değişiklikler (geri alma için)
        Schema::create('hisse_satis_tapular', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('basvuru_id');
            $table->unsignedBigInteger('grup_no')->index();
            $table->date('tescil_tarihi');
            $table->string('yevmiye_no', 100)->nullable();
            $table->decimal('tescil_edilen_yuzolcum', 15, 2)->nullable();
            $table->string('tescil_evrak', 500)->nullable();
            $table->text('aciklama')->nullable();
            $table->json('envanter_islem')->nullable();
            $table->timestamps();

            $table->unique('basvuru_id');
            $table->foreign('basvuru_id')->references('id')->on('hisse_basvurulari');
        });

        Schema::create('hisse_islem_gecmisi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grup_no')->index();
            $table->unsignedBigInteger('basvuru_id')->nullable();
            $table->foreignId('kullanici_id')->nullable()->constrained('kullanicilar')->nullOnDelete();
            $table->string('islem', 60);
            $table->text('aciklama')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['hisse_islem_gecmisi', 'hisse_satis_tapular', 'hisse_satis_tebligatlari', 'tasinmaz_encumenleri', 'hisse_imar_evraklar', 'hisse_goruslar', 'hisse_tebligatlari', 'hisse_basvurulari'] as $tablo) {
            Schema::dropIfExists($tablo);
        }
    }
};
