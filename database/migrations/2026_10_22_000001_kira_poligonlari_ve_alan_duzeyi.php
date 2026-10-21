<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Geometri dosyadan alana taşınır ve yeniden kullanılabilir hale gelir.
 *
 * Referans sistemde (ABB BELNET) kira alanı sayısı dosya sayısından fazla:
 * 5.693 alan / 4.353 dosya. Bir dosyanın birden çok kira alanı var ve
 * geometri alanın kendisine ait — parselin değil, parsel içindeki bölümün
 * sınırı (ör. Göksu Parkı dosyasının içindeki tenis kortu).
 *
 * Ayrıca aynı poligon birden çok kayıtta kullanılabilmeli: kayıt açarken
 * haritadan mevcut poligon seçilir, yeniden çizilmez. Bu yüzden geometri
 * ayrı bir tabloda (`kira_poligonlari`) tutulur ve alanlar ona bağlanır.
 *
 * Eski yapı: kira_koordinatlari (dosya ile 1-1) + kira_alanlari içinde
 * satır içi geometri sütunları. İkisi de bu ihtiyaca uymuyordu.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Yeniden kullanılabilir geometri
        Schema::create('kira_poligonlari', function (Blueprint $table) {
            $table->id();
            $table->string('ad', 200)->nullable()->comment('Haritada tanımak için: "Göksu Parkı tenis kortu"');
            $table->enum('kaynak', ['tkgm', 'cizim', 'ice_aktarim'])->default('cizim');

            // Parselden geldiyse izi kalsın; aynı parselin poligonu tekrar aranmasın
            $table->foreignId('mahalle_id')->nullable()->constrained('mahalleler')->nullOnDelete();
            $table->string('ada', 20)->nullable();
            $table->string('parsel', 20)->nullable();

            $table->json('koordinat')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->decimal('min_lat', 10, 7)->nullable();
            $table->decimal('max_lat', 10, 7)->nullable();
            $table->decimal('min_lng', 10, 7)->nullable();
            $table->decimal('max_lng', 10, 7)->nullable();
            $table->decimal('merkez_lat', 10, 7)->nullable();
            $table->decimal('merkez_lng', 10, 7)->nullable();
            $table->decimal('alan', 15, 2)->nullable()->comment('Geometriden hesaplanan m2');

            $table->foreignId('olusturan_id')->nullable()->constrained('kullanicilar')->nullOnDelete();
            $table->timestamps();

            $table->index(['min_lat', 'max_lat', 'min_lng', 'max_lng'], 'kira_poligonlari_sinir_index');
            $table->index(['merkez_lat', 'merkez_lng'], 'kira_poligonlari_merkez_index');
            $table->index(['mahalle_id', 'ada', 'parsel'], 'kira_poligonlari_konum_index');
        });

        Schema::table('kira_alanlari', function (Blueprint $table) {
            // Çok alan → tek poligon olabilir; poligon silinirse alan kalır
            $table->foreignId('poligon_id')->nullable()->after('kira_dosya_id')
                ->constrained('kira_poligonlari')->nullOnDelete();

            // Alan artık kendi başına bir kiralama birimi (referanstaki gibi)
            $table->foreignId('kiracilik_durum_id')->nullable()->after('aktif_mi')
                ->constrained('kira_kiracilik_durumlari')->nullOnDelete();
            $table->foreignId('hukuki_durum_id')->nullable()->after('kiracilik_durum_id')
                ->constrained('kira_hukuki_durumlar')->nullOnDelete();
            $table->enum('dolu_bos', ['dolu', 'bos'])->default('dolu')->after('hukuki_durum_id');
            $table->string('kiraci_ad_unvan', 250)->nullable()->after('dolu_bos');
            $table->string('alt_kiraci', 250)->nullable()->after('kiraci_ad_unvan');
            $table->unsignedTinyInteger('taksit_sayisi')->nullable()->after('yillik_bedel');
            $table->boolean('tahliye_ihbarnamesi_mi')->default(false)->after('taksit_sayisi');

            $table->index(['kira_dosya_id', 'dolu_bos'], 'kira_alanlari_doluluk_index');
            $table->index(['aktif_mi', 'bitis_tarihi'], 'kira_alanlari_bitis_index');
        });

        // Belgeler artık alana da bağlanabilir (referansta 43 belge alan düzeyinde)
        Schema::table('kira_belgeleri', function (Blueprint $table) {
            $table->foreignId('kira_alan_id')->nullable()->after('kira_dosya_id')
                ->constrained('kira_alanlari')->cascadeOnDelete();
            $table->index(['kira_alan_id', 'tur'], 'kira_belgeleri_alan_tur_index');
        });

        // Dosyanın mevcut geometrisi, birincil alan olarak taşınır
        $this->koordinatlariAlanaTasi();

        Schema::dropIfExists('kira_koordinatlari');

        // Alanın satır içi geometri sütunları artık poligonda
        Schema::table('kira_alanlari', function (Blueprint $table) {
            $table->dropIndex('kira_alanlari_sinir_index');
            $table->dropIndex('kira_alanlari_merkez_index');
            $table->dropColumn([
                'koordinat', 'lat', 'lng',
                'min_lat', 'max_lat', 'min_lng', 'max_lng', 'merkez_lat', 'merkez_lng',
            ]);
        });
    }

    /**
     * Dosya düzeyindeki geometriyi poligona, dosyayı da bir "birincil alan"a
     * çevirir. Böylece mevcut kayıtlar haritada kaybolmaz.
     */
    private function koordinatlariAlanaTasi(): void
    {
        if (! Schema::hasTable('kira_koordinatlari')) {
            return;
        }

        DB::table('kira_koordinatlari')->whereNotNull('koordinat')->orderBy('id')
            ->chunkById(200, function ($kayitlar) {
                foreach ($kayitlar as $k) {
                    $dosya = DB::table('kira_dosyalari')->where('id', $k->kira_dosya_id)->first();
                    if (! $dosya) {
                        continue;
                    }
                    $tasinmaz = DB::table('kira_tasinmazlari')->where('kira_dosya_id', $dosya->id)->first();

                    $poligonId = DB::table('kira_poligonlari')->insertGetId([
                        'ad' => $dosya->isyeri_adi ?: $dosya->dosya_no,
                        'kaynak' => 'tkgm',
                        'mahalle_id' => $tasinmaz->mahalle_id ?? null,
                        'ada' => $tasinmaz->ada ?? null,
                        'parsel' => $tasinmaz->parsel ?? null,
                        'koordinat' => $k->koordinat,
                        'lat' => $k->lat,
                        'lng' => $k->lng,
                        'min_lat' => $k->min_lat,
                        'max_lat' => $k->max_lat,
                        'min_lng' => $k->min_lng,
                        'max_lng' => $k->max_lng,
                        'merkez_lat' => $k->merkez_lat,
                        'merkez_lng' => $k->merkez_lng,
                        'alan' => $tasinmaz->yuzolcum ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Dosyanın alanı yoksa birincil alanı oluştur, varsa ilkine bağla
                    $mevcut = DB::table('kira_alanlari')->where('kira_dosya_id', $dosya->id)->first();
                    if ($mevcut) {
                        DB::table('kira_alanlari')->where('id', $mevcut->id)->update(['poligon_id' => $poligonId]);

                        continue;
                    }

                    DB::table('kira_alanlari')->insert([
                        'kira_dosya_id' => $dosya->id,
                        'poligon_id' => $poligonId,
                        'ad' => $dosya->isyeri_adi ?: 'Ana alan',
                        'adres' => $tasinmaz->adres ?? null,
                        'alan' => $tasinmaz->yuzolcum ?? null,
                        'is_kolu_id' => $dosya->is_kolu_id,
                        'dolu_bos' => $dosya->dolu_bos,
                        'aktif_mi' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('kira_belgeleri', function (Blueprint $table) {
            $table->dropForeign(['kira_alan_id']);
            $table->dropColumn('kira_alan_id');
        });

        Schema::table('kira_alanlari', function (Blueprint $table) {
            $table->json('koordinat')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->decimal('min_lat', 10, 7)->nullable();
            $table->decimal('max_lat', 10, 7)->nullable();
            $table->decimal('min_lng', 10, 7)->nullable();
            $table->decimal('max_lng', 10, 7)->nullable();
            $table->decimal('merkez_lat', 10, 7)->nullable();
            $table->decimal('merkez_lng', 10, 7)->nullable();
            $table->index(['min_lat', 'max_lat', 'min_lng', 'max_lng'], 'kira_alanlari_sinir_index');
            $table->index(['merkez_lat', 'merkez_lng'], 'kira_alanlari_merkez_index');

            $table->dropForeign(['poligon_id']);
            $table->dropForeign(['kiracilik_durum_id']);
            $table->dropForeign(['hukuki_durum_id']);
            $table->dropIndex('kira_alanlari_doluluk_index');
            $table->dropIndex('kira_alanlari_bitis_index');
            $table->dropColumn([
                'poligon_id', 'kiracilik_durum_id', 'hukuki_durum_id', 'dolu_bos',
                'kiraci_ad_unvan', 'alt_kiraci', 'taksit_sayisi', 'tahliye_ihbarnamesi_mi',
            ]);
        });

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

        Schema::dropIfExists('kira_poligonlari');
    }
};
