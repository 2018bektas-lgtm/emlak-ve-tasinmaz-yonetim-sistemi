<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor: `tasinmaz_birimler` tek tablo yaklaşımını kavram-bazlı ayrımla değiştir.
 *
 *   tasinmaz_kategori (hasOne)  = muhasebe/kayıt türü/mevcut kullanım
 *   tasinmaz_ekbilgi (hasOne)   = işgal/satış/tahsis/ust_hakki/kira/meclis/açıklama
 *   tasinmaz_yapi (hasMany)     = BBN'ler (fiziksel + kendi tüm durum alanları)
 *
 * Data taşıma:
 *   birimler.tip = 'arsa'  →  tasinmaz_kategori + tasinmaz_ekbilgi
 *   birimler.tip != 'arsa' →  tasinmaz_yapi
 *
 * Polimorfik referanslar (tapular/meclis_kararlari/resimler) arsa-birim sahibi ise
 * doğrudan tasinmaz'a (sahip_type='tasinmaz'), BBN-birim sahibi ise yeni yapi'ya
 * (sahip_type='yapi') taşınır.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) YENİ TABLOLAR
        Schema::create('tasinmaz_kategori', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->unique()
                ->constrained('tasinmazlar')->cascadeOnDelete();
            $table->foreignId('muhasebe_kayit_id')->nullable()
                ->constrained('muhasebe_kayitlari')->nullOnDelete();
            $table->foreignId('kayit_turu_id')->nullable()
                ->constrained('kayit_turleri')->nullOnDelete();
            $table->string('mevcut_kullanim_sekli', 150)->nullable();
            $table->timestamps();
        });

        Schema::create('tasinmaz_ekbilgi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->unique()
                ->constrained('tasinmazlar')->cascadeOnDelete();
            $table->string('isgal_durumu', 20)->nullable();
            $table->string('satis_durumu', 20)->default('envanterde');
            $table->boolean('meclis_satis_karari_var')->default(false);
            $table->boolean('tahsis_var')->default(false);
            $table->boolean('ust_hakki_var')->default(false);
            $table->boolean('kira_var')->default(false);
            $table->text('aciklama')->nullable();
            $table->timestamps();
            $table->index('satis_durumu');
            $table->index('meclis_satis_karari_var');
        });

        Schema::create('tasinmaz_yapi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')
                ->constrained('tasinmazlar')->cascadeOnDelete();
            $table->unsignedInteger('sira')->default(0);

            // Fiziksel
            $table->string('blok_no', 20)->nullable();
            $table->string('kat_no', 10)->nullable();
            $table->string('bagimsiz_bolum_no', 20)->nullable();
            $table->string('nitelik', 50)->nullable();
            $table->decimal('brut_alan', 10, 2)->nullable();
            $table->decimal('net_alan', 10, 2)->nullable();
            $table->string('oda_sayisi', 10)->nullable();
            $table->string('cephe', 50)->nullable();

            // Sınıflandırma (BBN kendi kaydını taşıyabilir — arsa'dan bağımsız)
            $table->foreignId('muhasebe_kayit_id')->nullable()
                ->constrained('muhasebe_kayitlari')->nullOnDelete();
            $table->foreignId('kayit_turu_id')->nullable()
                ->constrained('kayit_turleri')->nullOnDelete();
            $table->string('mevcut_kullanim_sekli', 150)->nullable();

            // Durum
            $table->string('isgal_durumu', 20)->nullable();
            $table->string('satis_durumu', 20)->default('envanterde');
            $table->boolean('meclis_satis_karari_var')->default(false);
            $table->boolean('tahsis_var')->default(false);
            $table->boolean('ust_hakki_var')->default(false);
            $table->boolean('kira_var')->default(false);

            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->index(['tasinmaz_id', 'sira']);
            $table->index('satis_durumu');
        });

        // 2) DATA TAŞIMA — birimler tablosundan yeni tablolara
        if (Schema::hasTable('tasinmaz_birimler')) {
            $birimMap = []; // eski_birim_id → ['tur' => 'tasinmaz'|'yapi', 'yeni_id' => X]

            DB::table('tasinmaz_birimler')->orderBy('tasinmaz_id')->orderBy('sira')->orderBy('id')
                ->each(function ($b) use (&$birimMap) {
                    if ($b->tip === 'arsa') {
                        // Kategori — bu tasinmaz için tek satır (unique constraint)
                        DB::table('tasinmaz_kategori')->updateOrInsert(
                            ['tasinmaz_id' => $b->tasinmaz_id],
                            [
                                'muhasebe_kayit_id' => $b->muhasebe_kayit_id,
                                'kayit_turu_id' => $b->kayit_turu_id,
                                'mevcut_kullanim_sekli' => $b->mevcut_kullanim_sekli,
                                'updated_at' => now(),
                                'created_at' => $b->created_at ?? now(),
                            ]
                        );
                        // Ekbilgi — tek satır
                        DB::table('tasinmaz_ekbilgi')->updateOrInsert(
                            ['tasinmaz_id' => $b->tasinmaz_id],
                            [
                                'isgal_durumu' => $b->isgal_durumu,
                                'satis_durumu' => $b->satis_durumu ?? 'envanterde',
                                'meclis_satis_karari_var' => (int) ($b->meclis_satis_karari_var ?? 0),
                                'tahsis_var' => (int) ($b->tahsis_var ?? 0),
                                'ust_hakki_var' => (int) ($b->ust_hakki_var ?? 0),
                                'kira_var' => (int) ($b->kira_var ?? 0),
                                'aciklama' => $b->aciklama,
                                'updated_at' => now(),
                                'created_at' => $b->created_at ?? now(),
                            ]
                        );
                        // Polimorfik sahiplik: bu birim üzerindeki tapu/karar/resim
                        // artık tasinmaz'a (arsa=tasinmaz) devrolur
                        $birimMap[$b->id] = ['tur' => 'tasinmaz', 'yeni_id' => $b->tasinmaz_id];
                    } else {
                        // BBN → tasinmaz_yapi (kendi tüm durum alanlarıyla)
                        $yeniId = DB::table('tasinmaz_yapi')->insertGetId([
                            'tasinmaz_id' => $b->tasinmaz_id,
                            'sira' => $b->sira,
                            'blok_no' => $b->blok_no,
                            'kat_no' => $b->kat_no,
                            'bagimsiz_bolum_no' => $b->bagimsiz_bolum_no,
                            'nitelik' => $b->nitelik,
                            'brut_alan' => $b->brut_alan,
                            'net_alan' => $b->net_alan,
                            'oda_sayisi' => $b->oda_sayisi,
                            'cephe' => $b->cephe,
                            'muhasebe_kayit_id' => $b->muhasebe_kayit_id,
                            'kayit_turu_id' => $b->kayit_turu_id,
                            'mevcut_kullanim_sekli' => $b->mevcut_kullanim_sekli,
                            'isgal_durumu' => $b->isgal_durumu,
                            'satis_durumu' => $b->satis_durumu ?? 'envanterde',
                            'meclis_satis_karari_var' => (int) ($b->meclis_satis_karari_var ?? 0),
                            'tahsis_var' => (int) ($b->tahsis_var ?? 0),
                            'ust_hakki_var' => (int) ($b->ust_hakki_var ?? 0),
                            'kira_var' => (int) ($b->kira_var ?? 0),
                            'aciklama' => $b->aciklama,
                            'created_at' => $b->created_at ?? now(),
                            'updated_at' => $b->updated_at ?? now(),
                        ]);
                        $birimMap[$b->id] = ['tur' => 'yapi', 'yeni_id' => $yeniId];
                    }
                });

            // 3) POLİMORFİK sahiplik migrate: sahip_type='birim' | 'bagimsiz_bolum'
            $polyTablolari = ['tapular', 'meclis_kararlari', 'resimler'];
            foreach ($polyTablolari as $tablo) {
                if (! Schema::hasTable($tablo)) continue;
                if (! Schema::hasColumn($tablo, 'sahip_type') || ! Schema::hasColumn($tablo, 'sahip_id')) continue;

                foreach ($birimMap as $eski => $harita) {
                    $yeniAlias = $harita['tur']; // 'tasinmaz' veya 'yapi'
                    $yeniId = $harita['yeni_id'];
                    DB::table($tablo)
                        ->whereIn('sahip_type', ['birim', 'bagimsiz_bolum'])
                        ->where('sahip_id', $eski)
                        ->update(['sahip_type' => $yeniAlias, 'sahip_id' => $yeniId]);
                }
            }

            // 4) tasinmaz_birimler tablosunu drop
            Schema::dropIfExists('tasinmaz_birimler');
        }
    }

    public function down(): void
    {
        // Rollback — ayrılan tablolardan birimler'e geri toplama
        if (! Schema::hasTable('tasinmaz_birimler')) {
            Schema::create('tasinmaz_birimler', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tasinmaz_id')->constrained('tasinmazlar')->cascadeOnDelete();
                $table->string('tip', 30)->default('arsa');
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
                $table->string('satis_durumu', 20)->default('envanterde');
                $table->boolean('meclis_satis_karari_var')->default(false);
                $table->boolean('tahsis_var')->default(false);
                $table->boolean('ust_hakki_var')->default(false);
                $table->boolean('kira_var')->default(false);
                $table->text('aciklama')->nullable();
                $table->timestamps();
            });
        }

        // Kategori + Ekbilgi → birimler (tip='arsa')
        DB::table('tasinmazlar')->orderBy('id')->each(function ($t) {
            $k = DB::table('tasinmaz_kategori')->where('tasinmaz_id', $t->id)->first();
            $e = DB::table('tasinmaz_ekbilgi')->where('tasinmaz_id', $t->id)->first();
            if (! $k && ! $e) return;
            DB::table('tasinmaz_birimler')->insert([
                'tasinmaz_id' => $t->id,
                'tip' => 'arsa',
                'sira' => 0,
                'nitelik' => $t->nitelik ?? null,
                'muhasebe_kayit_id' => $k->muhasebe_kayit_id ?? null,
                'kayit_turu_id' => $k->kayit_turu_id ?? null,
                'mevcut_kullanim_sekli' => $k->mevcut_kullanim_sekli ?? null,
                'isgal_durumu' => $e->isgal_durumu ?? null,
                'satis_durumu' => $e->satis_durumu ?? 'envanterde',
                'meclis_satis_karari_var' => (int) ($e->meclis_satis_karari_var ?? 0),
                'tahsis_var' => (int) ($e->tahsis_var ?? 0),
                'ust_hakki_var' => (int) ($e->ust_hakki_var ?? 0),
                'kira_var' => (int) ($e->kira_var ?? 0),
                'aciklama' => $e->aciklama ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        DB::table('tasinmaz_yapi')->orderBy('id')->each(function ($y) {
            DB::table('tasinmaz_birimler')->insert([
                'tasinmaz_id' => $y->tasinmaz_id,
                'tip' => 'bbn',
                'sira' => $y->sira,
                'blok_no' => $y->blok_no,
                'kat_no' => $y->kat_no,
                'bagimsiz_bolum_no' => $y->bagimsiz_bolum_no,
                'nitelik' => $y->nitelik,
                'brut_alan' => $y->brut_alan,
                'net_alan' => $y->net_alan,
                'oda_sayisi' => $y->oda_sayisi,
                'cephe' => $y->cephe,
                'muhasebe_kayit_id' => $y->muhasebe_kayit_id,
                'kayit_turu_id' => $y->kayit_turu_id,
                'mevcut_kullanim_sekli' => $y->mevcut_kullanim_sekli,
                'isgal_durumu' => $y->isgal_durumu,
                'satis_durumu' => $y->satis_durumu,
                'meclis_satis_karari_var' => $y->meclis_satis_karari_var,
                'tahsis_var' => $y->tahsis_var,
                'ust_hakki_var' => $y->ust_hakki_var,
                'kira_var' => $y->kira_var,
                'aciklama' => $y->aciklama,
                'created_at' => $y->created_at,
                'updated_at' => $y->updated_at,
            ]);
        });

        Schema::dropIfExists('tasinmaz_yapi');
        Schema::dropIfExists('tasinmaz_ekbilgi');
        Schema::dropIfExists('tasinmaz_kategori');
    }
};
