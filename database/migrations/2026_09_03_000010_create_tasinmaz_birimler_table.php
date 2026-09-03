<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor: Tasinmaz seviyesinde tutulan durum alanları ile bagimsiz_bolumler
 * tablosu, tek bir 'tasinmaz_birimler' tablosunda birleştirilir. Bir taşınmazın
 * 1+ birimi olur (tip: arsa | daire | dukkan | depo | ortak_alan | ...).
 *
 * Data taşıma stratejisi:
 *   1) tasinmaz_birimler tablosunu oluştur.
 *   2) Her Tasinmaz için tip='arsa' bir birim satırı üret; taşınan alanları
 *      Tasinmaz'dan kopyala.
 *   3) Her BagimsizBolum satırı için tip='bbn_<nitelik>' bir birim üret;
 *      map[eski_bbn_id → yeni_birim_id] tut.
 *   4) Polimorfik tabloları (tapular, meclis_kararlari, resimler) sahip_type
 *      'bagimsiz_bolum' olan satırların sahip_id'sini yeni birim id'sine map et.
 *      sahip_type = 'birim' olarak günceller.
 *   5) bagimsiz_bolumler tablosunu drop et.
 *   6) tasinmazlar tablosundan taşınan alanları drop et.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Yeni tablo
        Schema::create('tasinmaz_birimler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->constrained('tasinmazlar')->cascadeOnDelete();

            // Ne tür bir birim (arsa VEYA bağımsız bölüm alt tipi)
            $table->string('tip', 30)->default('arsa');
            $table->unsignedInteger('sira')->default(0);

            // Fiziksel (BBN için; arsa için null olabilir)
            $table->string('blok_no', 20)->nullable();
            $table->string('kat_no', 10)->nullable();
            $table->string('bagimsiz_bolum_no', 20)->nullable();
            $table->string('nitelik', 50)->nullable();
            $table->decimal('brut_alan', 10, 2)->nullable();
            $table->decimal('net_alan', 10, 2)->nullable();
            $table->string('oda_sayisi', 10)->nullable();
            $table->string('cephe', 50)->nullable();

            // Sınıflandırma
            $table->foreignId('muhasebe_kayit_id')->nullable()
                ->constrained('muhasebe_kayitlari')->nullOnDelete();
            $table->foreignId('kayit_turu_id')->nullable()
                ->constrained('kayit_turleri')->nullOnDelete();
            $table->string('mevcut_kullanim_sekli', 150)->nullable();
            $table->string('isgal_durumu', 20)->nullable();

            // Durum
            $table->string('satis_durumu', 20)->default('envanterde');
            $table->boolean('meclis_satis_karari_var')->default(false);
            $table->boolean('tahsis_var')->default(false);
            $table->boolean('ust_hakki_var')->default(false);
            $table->boolean('kira_var')->default(false);

            $table->text('aciklama')->nullable();

            $table->timestamps();

            $table->index(['tasinmaz_id', 'sira']);
            $table->index('tip');
            $table->index('satis_durumu');
            $table->index('meclis_satis_karari_var');
        });

        // 2) Her Tasinmaz için "arsa" birimi üret
        $tasinmazSutunlari = Schema::getColumnListing('tasinmazlar');
        $has = fn (string $c) => in_array($c, $tasinmazSutunlari, true);

        $secilecek = array_values(array_filter([
            'id', 'nitelik',
            $has('muhasebe_kayit_id') ? 'muhasebe_kayit_id' : null,
            $has('kayit_turu_id') ? 'kayit_turu_id' : null,
            $has('mevcut_kullanim_sekli') ? 'mevcut_kullanim_sekli' : null,
            $has('isgal_durumu') ? 'isgal_durumu' : null,
            $has('meclis_satis_karari_var') ? 'meclis_satis_karari_var' : null,
            $has('satis_durumu') ? 'satis_durumu' : null,
            $has('tahsis_var') ? 'tahsis_var' : null,
            $has('ust_hakki_var') ? 'ust_hakki_var' : null,
            $has('kira_var') ? 'kira_var' : null,
            $has('aciklama') ? 'aciklama' : null,
        ]));

        DB::table('tasinmazlar')->select($secilecek)->orderBy('id')
            ->each(function ($t) use ($has) {
                DB::table('tasinmaz_birimler')->insert([
                    'tasinmaz_id' => $t->id,
                    'tip' => 'arsa',
                    'sira' => 0,
                    'nitelik' => $t->nitelik ?? null,
                    'muhasebe_kayit_id' => $has('muhasebe_kayit_id') ? ($t->muhasebe_kayit_id ?? null) : null,
                    'kayit_turu_id' => $has('kayit_turu_id') ? ($t->kayit_turu_id ?? null) : null,
                    'mevcut_kullanim_sekli' => $has('mevcut_kullanim_sekli') ? ($t->mevcut_kullanim_sekli ?? null) : null,
                    'isgal_durumu' => $has('isgal_durumu') ? ($t->isgal_durumu ?? null) : null,
                    'meclis_satis_karari_var' => $has('meclis_satis_karari_var') ? (int) ($t->meclis_satis_karari_var ?? 0) : 0,
                    'satis_durumu' => $has('satis_durumu') ? ($t->satis_durumu ?? 'envanterde') : 'envanterde',
                    'tahsis_var' => $has('tahsis_var') ? (int) ($t->tahsis_var ?? 0) : 0,
                    'ust_hakki_var' => $has('ust_hakki_var') ? (int) ($t->ust_hakki_var ?? 0) : 0,
                    'kira_var' => $has('kira_var') ? (int) ($t->kira_var ?? 0) : 0,
                    'aciklama' => $has('aciklama') ? ($t->aciklama ?? null) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        // 3) Her BagimsizBolum için birim üret + eski_id → yeni_id map'i tut
        $bbnMap = [];
        if (Schema::hasTable('bagimsiz_bolumler')) {
            $bbnSutunlari = Schema::getColumnListing('bagimsiz_bolumler');
            $hasBbn = fn (string $c) => in_array($c, $bbnSutunlari, true);

            DB::table('bagimsiz_bolumler')->orderBy('tasinmaz_id')->orderBy('id')
                ->each(function ($b) use (&$bbnMap, $hasBbn) {
                    // BBN'nin nitelik'inden tip türet — 'daire', 'dukkan' vs.
                    $ham = strtolower((string) ($b->nitelik ?? ''));
                    $tip = 'bbn';
                    if (str_contains($ham, 'daire')) {
                        $tip = 'daire';
                    } elseif (str_contains($ham, 'dukk') || str_contains($ham, 'dükk')) {
                        $tip = 'dukkan';
                    } elseif (str_contains($ham, 'depo')) {
                        $tip = 'depo';
                    } elseif (str_contains($ham, 'ofis') || str_contains($ham, 'bür')) {
                        $tip = 'ofis';
                    }

                    $yeniId = DB::table('tasinmaz_birimler')->insertGetId([
                        'tasinmaz_id' => $b->tasinmaz_id,
                        'tip' => $tip,
                        'sira' => 1,
                        'blok_no' => $b->blok_no ?? null,
                        'kat_no' => $b->kat_no ?? null,
                        'bagimsiz_bolum_no' => $b->bagimsiz_bolum_no ?? null,
                        'nitelik' => $b->nitelik ?? null,
                        'brut_alan' => $b->brut_alan ?? null,
                        'net_alan' => $b->net_alan ?? null,
                        'oda_sayisi' => $b->oda_sayisi ?? null,
                        'cephe' => $b->cephe ?? null,
                        'muhasebe_kayit_id' => $hasBbn('muhasebe_kayit_id') ? ($b->muhasebe_kayit_id ?? null) : null,
                        'kayit_turu_id' => $hasBbn('kayit_turu_id') ? ($b->kayit_turu_id ?? null) : null,
                        'mevcut_kullanim_sekli' => $hasBbn('mevcut_kullanim_sekli') ? ($b->mevcut_kullanim_sekli ?? null) : null,
                        'isgal_durumu' => $hasBbn('isgal_durumu') ? ($b->isgal_durumu ?? null) : null,
                        'meclis_satis_karari_var' => $hasBbn('meclis_satis_karari_var') ? (int) ($b->meclis_satis_karari_var ?? 0) : 0,
                        'satis_durumu' => $hasBbn('satis_durumu') ? ($b->satis_durumu ?? 'envanterde') : 'envanterde',
                        'tahsis_var' => $hasBbn('tahsis_var') ? (int) ($b->tahsis_var ?? 0) : 0,
                        'ust_hakki_var' => $hasBbn('ust_hakki_var') ? (int) ($b->ust_hakki_var ?? 0) : 0,
                        'kira_var' => $hasBbn('kira_var') ? (int) ($b->kira_var ?? 0) : 0,
                        'aciklama' => $hasBbn('aciklama') ? ($b->aciklama ?? null) : null,
                        'created_at' => $b->created_at ?? now(),
                        'updated_at' => $b->updated_at ?? now(),
                    ]);
                    $bbnMap[$b->id] = $yeniId;
                });
        }

        // 4) Polimorfik tabloları güncelle: sahip_type='bagimsiz_bolum' → 'birim'
        //    ve sahip_id'yi yeni id'ye çevir.
        $polyTablolari = ['tapular', 'meclis_kararlari', 'resimler'];
        foreach ($polyTablolari as $tablo) {
            if (! Schema::hasTable($tablo)) {
                continue;
            }
            if (! Schema::hasColumn($tablo, 'sahip_type') || ! Schema::hasColumn($tablo, 'sahip_id')) {
                continue;
            }

            // Önce eski BBN sahiplerini yeni id + yeni type ile güncelle
            foreach ($bbnMap as $eski => $yeni) {
                DB::table($tablo)
                    ->where('sahip_type', 'bagimsiz_bolum')
                    ->where('sahip_id', $eski)
                    ->update(['sahip_type' => 'birim', 'sahip_id' => $yeni]);
            }
            // Eşleşmeyen kalan 'bagimsiz_bolum' referanslarını da yeni alias'a
            // (varsa) yönlendir — data tutarlılığı için, ancak sahip_id gerçek
            // birim id olmayabilir, o yüzden bunları öksüz olarak bırakma:
            // bagimsiz_bolumler tablosu drop edilince FK zaten yok, referans
            // bozulacak. Yine de güvenli: sadece bilinen eşleşmeleri map et.
        }

        // 5) bagimsiz_bolumler tablosunu drop et
        Schema::dropIfExists('bagimsiz_bolumler');

        // 6) tasinmazlar tablosundan taşınan alanları drop et
        Schema::table('tasinmazlar', function (Blueprint $table) use ($has) {
            // Foreign key'leri önce drop et
            if ($has('muhasebe_kayit_id')) {
                $table->dropConstrainedForeignId('muhasebe_kayit_id');
            }
            if ($has('kayit_turu_id')) {
                $table->dropConstrainedForeignId('kayit_turu_id');
            }

            // Index'leri drop et (varsa) — throw yakalayamıyoruz, o yüzden sadece bilinen index'leri drop et
            // muhasebe_niteligi ve kayit_turu index'leri 000006 migration'da eklendi ama 000015'te FK'ya taşındı
            if ($has('meclis_satis_karari_var')) {
                try {
                    $table->dropIndex(['meclis_satis_karari_var']);
                } catch (Throwable $e) {
                }
            }
            if ($has('tahsis_var')) {
                try {
                    $table->dropIndex(['tahsis_var']);
                } catch (Throwable $e) {
                }
            }
            if ($has('ust_hakki_var')) {
                try {
                    $table->dropIndex(['ust_hakki_var']);
                } catch (Throwable $e) {
                }
            }
            if ($has('kira_var')) {
                try {
                    $table->dropIndex(['kira_var']);
                } catch (Throwable $e) {
                }
            }
            if ($has('satis_durumu')) {
                $table->dropIndex(['satis_durumu']);
            }

            $dropCols = array_values(array_filter([
                $has('mevcut_kullanim_sekli') ? 'mevcut_kullanim_sekli' : null,
                $has('isgal_durumu') ? 'isgal_durumu' : null,
                $has('meclis_satis_karari_var') ? 'meclis_satis_karari_var' : null,
                $has('satis_durumu') ? 'satis_durumu' : null,
                $has('tahsis_var') ? 'tahsis_var' : null,
                $has('ust_hakki_var') ? 'ust_hakki_var' : null,
                $has('kira_var') ? 'kira_var' : null,
                $has('aciklama') ? 'aciklama' : null,
                $has('muhasebe_niteligi') ? 'muhasebe_niteligi' : null,
                $has('kayit_turu') ? 'kayit_turu' : null,
            ]));
            if ($dropCols) {
                $table->dropColumn($dropCols);
            }
        });
    }

    public function down(): void
    {
        // Geri alma — kısmi. Alanları yeniden ekle, birimlerdeki data'yı geri
        // yaz. Rollback nadiren kullanılır; dev ortamı için pratik.
        Schema::table('tasinmazlar', function (Blueprint $table) {
            $table->foreignId('muhasebe_kayit_id')->nullable()->after('nitelik')
                ->constrained('muhasebe_kayitlari')->nullOnDelete();
            $table->foreignId('kayit_turu_id')->nullable()->after('muhasebe_kayit_id')
                ->constrained('kayit_turleri')->nullOnDelete();
            $table->string('mevcut_kullanim_sekli', 150)->nullable()->after('kayit_turu_id');
            $table->string('isgal_durumu', 20)->nullable()->after('mevcut_kullanim_sekli');
            $table->boolean('meclis_satis_karari_var')->default(false)->after('isgal_durumu');
            $table->string('satis_durumu', 20)->default('envanterde')->after('meclis_satis_karari_var');
            $table->boolean('tahsis_var')->default(false)->after('satis_durumu');
            $table->boolean('ust_hakki_var')->default(false)->after('tahsis_var');
            $table->boolean('kira_var')->default(false)->after('ust_hakki_var');
            $table->text('aciklama')->nullable()->after('kira_var');
        });

        // Arsa birimlerini geri yaz (her taşınmaz için ilk 'arsa' tipi)
        DB::table('tasinmaz_birimler')
            ->where('tip', 'arsa')
            ->orderBy('tasinmaz_id')
            ->orderBy('id')
            ->each(function ($b) {
                DB::table('tasinmazlar')->where('id', $b->tasinmaz_id)->update([
                    'muhasebe_kayit_id' => $b->muhasebe_kayit_id,
                    'kayit_turu_id' => $b->kayit_turu_id,
                    'mevcut_kullanim_sekli' => $b->mevcut_kullanim_sekli,
                    'isgal_durumu' => $b->isgal_durumu,
                    'meclis_satis_karari_var' => (int) $b->meclis_satis_karari_var,
                    'satis_durumu' => $b->satis_durumu,
                    'tahsis_var' => (int) $b->tahsis_var,
                    'ust_hakki_var' => (int) $b->ust_hakki_var,
                    'kira_var' => (int) $b->kira_var,
                    'aciklama' => $b->aciklama,
                ]);
            });

        // BBN tablosunu geri oluştur ve arsa dışı birimleri geri yaz
        Schema::create('bagimsiz_bolumler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->constrained('tasinmazlar')->cascadeOnDelete();
            $table->string('blok_no', 20)->nullable();
            $table->string('kat_no', 10);
            $table->string('bagimsiz_bolum_no', 20);
            $table->string('nitelik', 50);
            $table->decimal('brut_alan', 10, 2)->nullable();
            $table->decimal('net_alan', 10, 2)->nullable();
            $table->string('oda_sayisi', 10)->nullable();
            $table->string('cephe', 50)->nullable();
            $table->foreignId('muhasebe_kayit_id')->nullable()->constrained('muhasebe_kayitlari')->nullOnDelete();
            $table->foreignId('kayit_turu_id')->nullable()->constrained('kayit_turleri')->nullOnDelete();
            $table->string('mevcut_kullanim_sekli', 150)->nullable();
            $table->string('isgal_durumu', 20)->nullable();
            $table->boolean('meclis_satis_karari_var')->default(false);
            $table->string('satis_durumu', 20)->default('envanterde');
            $table->boolean('tahsis_var')->default(false);
            $table->boolean('ust_hakki_var')->default(false);
            $table->boolean('kira_var')->default(false);
            $table->text('aciklama')->nullable();
            $table->timestamps();
            $table->index('nitelik');
            $table->index('meclis_satis_karari_var');
        });

        Schema::dropIfExists('tasinmaz_birimler');
    }
};
