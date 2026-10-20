<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * İhale, karar/onaylar, süreç olayları, işletme bilgisi ve belgeler.
 *
 * Bu dört konu referansta ana tablonun içindeydi (yalnızca ihale ~35 sütun) ve
 * dosyaların çoğunda boştu. Ayrı tablolara alınınca çekirdek tablo dar kalıyor
 * ve ihalesiz dosyalar için hiç satır açılmıyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kira_ihaleleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kira_dosya_id')->constrained('kira_dosyalari')->cascadeOnDelete();
            $table->enum('usul', ['acik_teklif', 'kapali_teklif', 'pazarlik', 'yarisma'])->nullable();
            $table->string('konu', 300)->nullable();

            $table->date('ilan_tarihi_1')->nullable();
            $table->date('ilan_tarihi_2')->nullable();
            $table->string('ilan_sekli', 150)->nullable();
            $table->dateTime('ihale_tarihi')->nullable();
            $table->dateTime('son_basvuru_tarihi')->nullable();
            $table->dateTime('dosya_son_teslim_tarihi')->nullable();

            $table->decimal('muhammen_bedel', 15, 2)->nullable();
            $table->decimal('ihale_bedeli', 15, 2)->nullable();
            $table->decimal('sartname_bedeli', 15, 2)->nullable();
            $table->decimal('gecici_teminat', 15, 2)->nullable();
            $table->decimal('kati_teminat', 15, 2)->nullable();
            $table->decimal('istirak_teminati', 15, 2)->nullable();
            $table->decimal('teminat_yuzdesi', 5, 2)->nullable();
            $table->decimal('damga_vergisi', 15, 2)->nullable();
            $table->decimal('karar_pulu', 15, 2)->nullable();

            $table->string('on_onay_no', 60)->nullable();
            $table->date('on_onay_tarihi')->nullable();
            $table->string('onay_no', 60)->nullable();
            $table->date('onay_tarihi')->nullable();
            $table->string('iptal_olur_no', 60)->nullable();
            $table->date('iptal_olur_tarihi')->nullable();

            $table->text('katilim_sartlari')->nullable();
            $table->text('ozel_sartlar')->nullable();
            $table->timestamps();

            $table->index(['kira_dosya_id']);
            $table->index('ihale_tarihi');
        });

        // Komisyon başkanı + üyeler; referansta Üye-1..4 diye sabit sütunlardı
        Schema::create('kira_ihale_komisyonu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kira_ihale_id')->constrained('kira_ihaleleri')->cascadeOnDelete();
            $table->enum('gorev', ['baskan', 'uye'])->default('uye');
            $table->string('ad_soyad', 200);
            $table->string('unvan', 150)->nullable();
            $table->unsignedTinyInteger('sira')->default(0);
            $table->timestamps();

            $table->index('kira_ihale_id');
        });

        Schema::create('kira_ihale_katilimcilari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kira_ihale_id')->constrained('kira_ihaleleri')->cascadeOnDelete();
            $table->string('ad_unvan', 250);
            $table->string('tc_vergi_no', 20)->nullable();
            $table->string('telefon', 30)->nullable();
            $table->decimal('teklif_bedeli', 15, 2)->nullable();
            $table->boolean('kazandi_mi')->default(false);
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->index(['kira_ihale_id', 'kazandi_mi']);
        });

        /** Encümen / meclis / olur / başkanlık onayı — hepsi tek tabloda türlü. */
        Schema::create('kira_kararlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kira_dosya_id')->constrained('kira_dosyalari')->cascadeOnDelete();
            $table->enum('tur', ['encumen', 'meclis', 'olur', 'baskanlik_onay', 'kurum_yazisi', 'talep_yazisi']);
            $table->string('karar_no', 100)->nullable();
            $table->date('karar_tarihi')->nullable();
            $table->string('ilgili_kurum', 200)->nullable();
            $table->text('aciklama')->nullable();
            $table->string('evrak', 500)->nullable();
            $table->timestamps();

            $table->index(['kira_dosya_id', 'tur']);
            $table->index('karar_tarihi');
        });

        /** Devir, fesih, tebligat, tahliye ihbarnamesi — dosyanın zaman çizelgesi. */
        Schema::create('kira_surec_olaylari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kira_dosya_id')->constrained('kira_dosyalari')->cascadeOnDelete();
            $table->enum('tur', ['devir', 'fesih', 'tebligat', 'tahliye_ihbarnamesi', 'yenileme', 'teslim', 'denetim']);
            $table->date('olay_tarihi');
            $table->date('dilekce_tarihi')->nullable();
            $table->string('onay_no', 100)->nullable();
            $table->date('onay_tarihi')->nullable();
            $table->date('ulasma_tarihi')->nullable()->comment('Tebligatın kiracıya ulaştığı tarih');
            $table->string('ilgili_kisi', 250)->nullable()->comment('Devirde devralan kiracı');
            $table->text('aciklama')->nullable();
            $table->string('evrak', 500)->nullable();
            $table->foreignId('olusturan_id')->nullable()->constrained('kullanicilar')->nullOnDelete();
            $table->timestamps();

            $table->index(['kira_dosya_id', 'tur']);
            $table->index('olay_tarihi');
        });

        /** Abonelikler, demirbaş ve denetim — dosya başına tek satır. */
        Schema::create('kira_isletme_bilgileri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kira_dosya_id')->unique()->constrained('kira_dosyalari')->cascadeOnDelete();
            $table->string('su_abone_no', 60)->nullable();
            $table->string('elektrik_abone_no', 60)->nullable();
            $table->string('dogalgaz_abone_no', 60)->nullable();
            $table->text('abone_aciklama')->nullable();
            $table->text('demirbaslar')->nullable()->comment('İşyerinde teslim edilen demirbaşlar');
            $table->date('isyeri_teslim_tarihi')->nullable();
            $table->date('son_denetim_tarihi')->nullable();
            $table->text('denetim_notu')->nullable();
            $table->timestamps();
        });

        /** Türlenmiş belgeler (referans: "Dökümante Alanı"). */
        Schema::create('kira_belgeleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kira_dosya_id')->constrained('kira_dosyalari')->cascadeOnDelete();
            $table->enum('tur', ['encumen_karari', 'kira_sozlesmesi', 'yazisma', 'tebligat', 'ihale_evraki', 'kroki', 'diger'])->default('diger');
            $table->string('ad', 250);
            $table->string('dosya_yolu', 500);
            $table->unsignedBigInteger('boyut')->nullable()->comment('bayt');
            $table->text('aciklama')->nullable();
            $table->foreignId('yukleyen_id')->nullable()->constrained('kullanicilar')->nullOnDelete();
            $table->timestamps();

            $table->index(['kira_dosya_id', 'tur']);
        });
    }

    public function down(): void
    {
        foreach ([
            'kira_belgeleri',
            'kira_isletme_bilgileri',
            'kira_surec_olaylari',
            'kira_kararlari',
            'kira_ihale_katilimcilari',
            'kira_ihale_komisyonu',
            'kira_ihaleleri',
        ] as $tablo) {
            Schema::dropIfExists($tablo);
        }
    }
};
