<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Geometri artık `json` metin değil, PostGIS `geometry` tipinde.
 *
 * Önce geometri yalnız `koordinat` (json) sütununda duruyordu; veritabanı onu
 * metin olarak gördüğü için "bu noktaya hangi poligon düşüyor" gibi sorular
 * PHP'de (App\Support\Geometri, ray-casting) hesaplanmak zorundaydı. Yeni
 * `sinir` sütunu GiST ile indexlenir: aynı sorular index üzerinden tek
 * sorguda cevaplanır ve 100 binlerce kayıtta da hızlı kalır.
 *
 * `koordinat` kaldırılmadı ve kaynak olmaya devam ediyor — istemciye giden
 * GeoJSON ve tüm yazma yolları onu kullanıyor. Ondan TÜRETİLEN her şey
 * (`sinir` ve sınır kutusu sütunları) bir tetikleyiciyle üretilir, böylece
 * Eloquent olaylarını atlayan yollarda da (toplu insert, ham SQL, COPY)
 * tutarlı kalır. Bu sütunlara elle değer yazmak anlamsızdır; tetikleyici
 * onları `koordinat`/`lat`/`lng`'den yeniden hesaplar.
 *
 * SinirKutusuTutar trait'indeki (ve TasinmazKoordinat/EmsalParsel'deki) PHP
 * hesabı yerinde kalıyor: aynı değerleri üretir ve kayıt henüz veritabanından
 * okunmadan bellekte de doğru olmasını sağlar. Tetikleyici onun yerine geçmez,
 * ağını altına gerer.
 */
return new class extends Migration
{
    /** Geometri taşıyan tablolar — üçü de aynı düzende. */
    private const TABLOLAR = ['tasinmaz_koordinatlar', 'kira_poligonlari', 'emsal_parselleri'];

    public function up(): void
    {
        // Türetilmiş sütunların tek tanımı. Sınır kutusu ve merkez, PHP'deki
        // SinirKutusuTutar::sinirKutusu() ile birebir aynı kuralı izler:
        // merkez, lat/lng verilmişse o, yoksa kutunun ortası.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION geometri_sinir_guncelle() RETURNS trigger AS $$
            BEGIN
                NEW.sinir := CASE
                    WHEN NEW.koordinat IS NOT NULL AND NEW.koordinat->>'type' IS NOT NULL
                         THEN ST_SetSRID(ST_GeomFromGeoJSON(NEW.koordinat::text), 4326)
                    WHEN NEW.lat IS NOT NULL AND NEW.lng IS NOT NULL
                         THEN ST_SetSRID(ST_MakePoint(NEW.lng::double precision, NEW.lat::double precision), 4326)
                    ELSE NULL
                END;

                IF NEW.sinir IS NULL THEN
                    NEW.min_lat := NULL;
                    NEW.max_lat := NULL;
                    NEW.min_lng := NULL;
                    NEW.max_lng := NULL;
                    NEW.merkez_lat := NULL;
                    NEW.merkez_lng := NULL;
                ELSE
                    NEW.min_lng := ST_XMin(NEW.sinir);
                    NEW.max_lng := ST_XMax(NEW.sinir);
                    NEW.min_lat := ST_YMin(NEW.sinir);
                    NEW.max_lat := ST_YMax(NEW.sinir);
                    NEW.merkez_lng := COALESCE(NEW.lng, ((ST_XMin(NEW.sinir) + ST_XMax(NEW.sinir)) / 2)::numeric);
                    NEW.merkez_lat := COALESCE(NEW.lat, ((ST_YMin(NEW.sinir) + ST_YMax(NEW.sinir)) / 2)::numeric);
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        foreach (self::TABLOLAR as $tablo) {
            Schema::table($tablo, function (Blueprint $t) {
                $t->geometry('sinir', 'geometry', 4326)->nullable()->after('koordinat');
            });

            DB::unprepared("
                CREATE TRIGGER {$tablo}_sinir_tetik
                BEFORE INSERT OR UPDATE OF koordinat, lat, lng ON {$tablo}
                FOR EACH ROW EXECUTE FUNCTION geometri_sinir_guncelle()
            ");

            // Mevcut kayıtları doldur: dokunulmuş gibi güncelleyip tetikleyiciyi
            // çalıştırıyoruz, dönüşüm tek yerde tanımlı kalsın.
            DB::statement("UPDATE {$tablo} SET koordinat = koordinat");

            DB::statement("CREATE INDEX {$tablo}_sinir_gist ON {$tablo} USING gist (sinir)");
        }
    }

    public function down(): void
    {
        foreach (self::TABLOLAR as $tablo) {
            DB::statement("DROP INDEX IF EXISTS {$tablo}_sinir_gist");
            DB::unprepared("DROP TRIGGER IF EXISTS {$tablo}_sinir_tetik ON {$tablo}");

            Schema::table($tablo, function (Blueprint $t) {
                $t->dropColumn('sinir');
            });
        }

        DB::unprepared('DROP FUNCTION IF EXISTS geometri_sinir_guncelle()');
    }
};
