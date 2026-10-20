<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kira modülünün çekirdeği.
 *
 * Referans sistemde tek tabloda 253 sütun vardı; burada konu başına bölündü.
 * `kira_dosyalari` dar tutulur çünkü liste, harita ve sayaçlar hep onu okur —
 * kiracı, taşınmaz, bedel ve süreç bilgileri ayrı tablolarda durur ve yalnızca
 * detayda açılır.
 *
 * Hesaplanabilir değerler (kira süresi, bitişe kalan gün, "son bir ayı
 * kalanlar") saklanmaz; sorguda üretilir, böylece bayatlamaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kira_dosyalari', function (Blueprint $table) {
            $table->id();
            $table->string('dosya_no', 50)->unique();
            $table->string('eski_dosya_no', 50)->nullable()->index();
            // Ana dosya ↔ alt dosya: bir ana dosyanın altında birden çok kiralama
            $table->foreignId('ana_dosya_id')->nullable()->constrained('kira_dosyalari')->nullOnDelete();

            $table->string('isyeri_adi', 200)->nullable();
            $table->foreignId('is_kolu_id')->nullable()->constrained('kira_is_kollari')->nullOnDelete();

            $table->enum('dolu_bos', ['dolu', 'bos'])->default('dolu');
            $table->enum('ihale_turu', ['ihaleli', 'ihalesiz'])->default('ihalesiz');

            $table->foreignId('hukuki_durum_id')->nullable()->constrained('kira_hukuki_durumlar')->nullOnDelete();
            $table->foreignId('kiracilik_durum_id')->nullable()->constrained('kira_kiracilik_durumlari')->nullOnDelete();
            $table->foreignId('muhasebe_niteligi_id')->nullable()->constrained('kira_muhasebe_nitelikleri')->nullOnDelete();

            $table->boolean('sozlesme_aktif_mi')->default(true);
            $table->boolean('tahliye_ihbarnamesi_mi')->default(false);
            $table->unsignedInteger('arac_kapasitesi')->nullable();

            $table->string('uyari', 300)->nullable()->comment('Listede gösterilen kısa uyarı');
            $table->text('aciklama')->nullable();

            $table->foreignId('sorumlu_personel_id')->nullable()->constrained('kullanicilar')->nullOnDelete();
            $table->foreignId('olusturan_id')->nullable()->constrained('kullanicilar')->nullOnDelete();
            $table->timestamps();

            // Liste sekmeleri ve sayaçlar bu indexlerden okunur
            $table->index(['dolu_bos', 'ihale_turu'], 'kira_dosyalari_durum_index');
            $table->index(['sozlesme_aktif_mi', 'dolu_bos'], 'kira_dosyalari_aktif_index');
            $table->index('isyeri_adi');
        });

        /**
         * Kiracılar. Ana ve alt kiracı aynı tabloda `tip` ile ayrılır; bir
         * dosyada birden çok alt kiracı olabildiği için ayrı satır tutulur.
         */
        Schema::create('kira_kiracilari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kira_dosya_id')->constrained('kira_dosyalari')->cascadeOnDelete();
            $table->enum('tip', ['ana', 'alt'])->default('ana');
            $table->string('ad_unvan', 250);
            $table->string('tc_vergi_no', 20)->nullable();
            $table->string('telefon', 30)->nullable();
            $table->string('eposta', 150)->nullable();
            $table->text('adres')->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('avukat', 200)->nullable();
            $table->timestamps();

            $table->index(['kira_dosya_id', 'tip']);
            $table->index('tc_vergi_no');
            $table->index('ad_unvan');
        });

        /**
         * Kiralanan taşınmazın konum ve tapu bilgisi. Dosya başına tek satır;
         * envanterdeki taşınmaza bağlanabilir ama bağlanmak zorunda değildir
         * (kiralanan yer her zaman envanterde kayıtlı olmayabiliyor).
         */
        Schema::create('kira_tasinmazlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kira_dosya_id')->unique()->constrained('kira_dosyalari')->cascadeOnDelete();
            $table->foreignId('tasinmaz_id')->nullable()->constrained('tasinmazlar')->nullOnDelete();

            $table->foreignId('il_id')->nullable()->constrained('iller')->nullOnDelete();
            $table->foreignId('ilce_id')->nullable()->constrained('ilceler')->nullOnDelete();
            $table->foreignId('mahalle_id')->nullable()->constrained('mahalleler')->nullOnDelete();
            $table->string('tapu_mahalle_adi', 150)->nullable();
            $table->string('ada', 20)->nullable();
            $table->string('parsel', 20)->nullable();

            $table->text('adres')->nullable()->comment('Kiralanan yerin açık adresi');
            $table->string('yol_adi', 150)->nullable();
            $table->string('diger_cadde_sokak', 150)->nullable();
            $table->string('kapi_no', 20)->nullable();
            $table->string('daire_no', 20)->nullable();
            $table->string('blok_kat_bolum', 60)->nullable();

            $table->decimal('yuzolcum', 15, 2)->nullable()->comment('m2');
            $table->decimal('bina_alani', 15, 2)->nullable();
            $table->decimal('kapali_servis_alani', 15, 2)->nullable();
            $table->foreignId('imar_durumu_id')->nullable()->constrained('imar_durumlari')->nullOnDelete();
            $table->timestamps();

            $table->index(['ilce_id', 'mahalle_id']);
            $table->index(['mahalle_id', 'ada', 'parsel']);
        });

        // Dosya düzeyi geometri — harita bu tabloyu bbox ile okur
        Schema::create('kira_koordinatlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kira_dosya_id')->unique()->constrained('kira_dosyalari')->cascadeOnDelete();
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

            $table->index(['min_lat', 'max_lat', 'min_lng', 'max_lng'], 'kira_koordinatlari_sinir_index');
            $table->index(['merkez_lat', 'merkez_lng'], 'kira_koordinatlari_merkez_index');
        });
    }

    public function down(): void
    {
        foreach (['kira_koordinatlari', 'kira_tasinmazlari', 'kira_kiracilari', 'kira_dosyalari'] as $tablo) {
            Schema::dropIfExists($tablo);
        }
    }
};
