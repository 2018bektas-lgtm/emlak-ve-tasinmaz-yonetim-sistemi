<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taşınmaz ana kaydı ve ona 1:1 / 1:N bağlı detay tabloları.
 *
 * Kayıt tipi:
 *  - bos_parsel           : üzerinde yapı yok (arsa/arazi)
 *  - kat_mulkiyetli       : kendi TAKBİS zemin nosu olan tek bağımsız bölüm
 *  - kat_mulkiyetsiz_bina : tapuda arsa görünür, üzerinde bina var (çoklu BB)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasinmazlar', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('il_id');
            $table->unsignedBigInteger('ilce_id');
            $table->unsignedBigInteger('mahalle_id');
            $table->string('ada', 20);
            $table->string('parsel', 20);
            $table->decimal('alan', 15, 2)->comment('m2');
            $table->string('nitelik', 100)->index();
            $table->boolean('uzeri_bina_var_mi')->default(false);
            $table->enum('kayit_tipi', ['bos_parsel', 'kat_mulkiyetli', 'kat_mulkiyetsiz_bina'])->default('bos_parsel')->index();
            $table->timestamps();

            $table->index(['mahalle_id', 'ada', 'parsel']);
            $table->index(['il_id', 'ilce_id']);
            $table->foreign('il_id')->references('id')->on('iller');
            $table->foreign('ilce_id')->references('id')->on('ilceler');
            $table->foreign('mahalle_id')->references('id')->on('mahalleler');
        });

        Schema::create('tasinmaz_koordinatlar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->unique()->constrained('tasinmazlar')->cascadeOnDelete();
            $table->json('koordinat')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
            $table->index(['lat', 'lng']);
        });

        Schema::create('tasinmaz_imarlar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->unique()->constrained('tasinmazlar')->cascadeOnDelete();
            $table->foreignId('imar_durumu_id')->nullable()->constrained('imar_durumlari');
            $table->decimal('emsal', 5, 2)->nullable();
            $table->string('yenaz_yencok', 50)->nullable();
            $table->text('imar_notu')->nullable();
            $table->timestamps();
        });

        Schema::create('tasinmaz_kategori', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->unique()->constrained('tasinmazlar')->cascadeOnDelete();
            $table->foreignId('muhasebe_kayit_id')->nullable()->constrained('muhasebe_kayitlari')->nullOnDelete();
            $table->foreignId('kayit_turu_id')->nullable()->constrained('kayit_turleri')->nullOnDelete();
            $table->string('mevcut_kullanim_sekli', 150)->nullable();
            $table->timestamps();
        });

        Schema::create('tasinmaz_ekbilgi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->unique()->constrained('tasinmazlar')->cascadeOnDelete();
            $table->string('isgal_durumu', 20)->nullable()->comment('yok | var | kismi');
            $table->string('satis_durumu', 20)->default('envanterde')->index();
            $table->boolean('meclis_satis_karari_var')->default(false)->index();
            $table->boolean('tahsis_var')->default(false);
            $table->boolean('ust_hakki_var')->default(false);
            $table->boolean('kira_var')->default(false);
            $table->text('aciklama')->nullable();
            $table->timestamps();
        });

        // Bağımsız bölümler / yapılar (kat mülkiyetsiz binada çok satır)
        Schema::create('tasinmaz_yapi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tasinmaz_id');
            $table->unsignedInteger('sira')->default(0);
            $table->string('blok_no', 20)->nullable();
            $table->string('kat_no', 10)->nullable();
            $table->string('bagimsiz_bolum_no', 20)->nullable();
            $table->string('nitelik', 50)->nullable();
            $table->decimal('brut_alan', 10, 2)->nullable();
            $table->decimal('net_alan', 10, 2)->nullable();
            $table->string('oda_sayisi', 10)->nullable();
            $table->string('cephe', 50)->nullable();
            $table->foreignId('muhasebe_kayit_id')->nullable()->constrained('muhasebe_kayitlari')->nullOnDelete();
            $table->foreignId('kayit_turu_id')->nullable()->constrained('kayit_turleri')->nullOnDelete();
            $table->string('mevcut_kullanim_sekli', 150)->nullable();
            $table->string('isgal_durumu', 20)->nullable();
            $table->string('satis_durumu', 20)->default('envanterde')->index();
            $table->boolean('meclis_satis_karari_var')->default(false);
            $table->boolean('tahsis_var')->default(false);
            $table->boolean('ust_hakki_var')->default(false);
            $table->boolean('kira_var')->default(false);
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->index(['tasinmaz_id', 'sira']);
            $table->foreign('tasinmaz_id')->references('id')->on('tasinmazlar')->cascadeOnDelete();
        });

        Schema::create('tasinmaz_hisseler', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tasinmaz_id');
            $table->unsignedInteger('hisse_no')->nullable();
            $table->decimal('hisse_pay', 20, 4)->nullable();
            $table->decimal('hisse_payda', 20, 4)->nullable();
            $table->decimal('hisse_yuzolcum', 15, 2)->nullable();
            $table->string('edinme_sekli', 100)->nullable();
            $table->unsignedInteger('yevmiye_no')->nullable();
            $table->date('edinme_tarihi')->nullable();
            $table->string('kayitlardan_cikis', 150)->nullable();
            $table->date('kayitlardan_cikistarihi')->nullable();
            $table->enum('hisse_durum', ['aktif', 'pasif'])->default('aktif');
            $table->enum('islem_tipi', ['alis', 'satis'])->nullable();
            $table->decimal('maliyet_bedeli', 15, 2)->nullable();
            $table->decimal('rayic_bedel', 15, 2)->nullable();
            $table->decimal('emlak_vd', 15, 2)->nullable();
            $table->decimal('iz_bedeli', 15, 2)->nullable();
            $table->unsignedSmallInteger('sira')->default(0);
            $table->timestamps();

            $table->index(['tasinmaz_id', 'hisse_durum']);
            $table->foreign('tasinmaz_id')->references('id')->on('tasinmazlar')->cascadeOnDelete();
        });

        // Tapudaki diğer hissedarlar (hisse satışı tebligatları için)
        Schema::create('tasinmaz_malikleri', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tasinmaz_id');
            $table->string('ad_soyad', 150);
            $table->string('tc_kimlik', 11)->nullable();
            $table->decimal('tapu_hisse', 15, 2)->nullable();
            $table->string('adres', 500)->nullable();
            $table->string('gsm_no', 20)->nullable();
            $table->timestamps();

            $table->index(['tasinmaz_id', 'tc_kimlik']);
            $table->foreign('tasinmaz_id')->references('id')->on('tasinmazlar')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['tasinmaz_malikleri', 'tasinmaz_hisseler', 'tasinmaz_yapi', 'tasinmaz_ekbilgi', 'tasinmaz_kategori', 'tasinmaz_imarlar', 'tasinmaz_koordinatlar', 'tasinmazlar'] as $tablo) {
            Schema::dropIfExists($tablo);
        }
    }
};
