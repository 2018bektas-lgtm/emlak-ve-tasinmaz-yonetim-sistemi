<?php

namespace Database\Seeders;

use App\Models\Kullanici;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Türkiye geneline yayılan yük testi: 100.000 taşınmaz ve aynı sayıda kira,
 * değerleme, hisse başvurusu, ecrimisil ve emsal. Her kayıt haritada
 * görünen bir poligon taşır.
 *
 *   php artisan db:seed --class=HaritaYukSeeder
 *   php artisan db:seed --class=TasinmazKiraYukSeeder
 *
 * DatabaseSeeder bunu çağırmaz. Tekrar çalıştırılabilir: önce kendi eklediği
 * kayıtları siler. Örnek kira verisi (ORN- / «Örnek veri») durur.
 *
 * İşaret: açıklama «Yük testi», dosya no YK-0000001 …, taşınmaz ada YK,
 * emsal ada YE. Önce KonumSeeder (81 il), imar/muhasebe/kayıt türü ve kira
 * kod tabloları dolu olmalı.
 */
class TasinmazKiraYukSeeder extends Seeder
{
    public const ISARET = 'Yük testi';

    public const TASINMAZ_SAYISI = 100000;

    public const KIRA_SAYISI = 100000;

    private const PARTI = 10000;

    /** İl adı (ASCII) => [enlem, boylam]. Mahalle ızgarası bu merkezin çevresine serpilir. */
    private const MERKEZLER = [
        'adana' => [37.000, 35.321], 'adiyaman' => [37.764, 38.278], 'afyonkarahisar' => [38.757, 30.538],
        'agri' => [39.719, 43.051], 'aksaray' => [38.368, 34.037], 'amasya' => [40.650, 35.833],
        'ankara' => [39.933, 32.860], 'antalya' => [36.897, 30.713], 'ardahan' => [41.111, 42.702],
        'artvin' => [41.182, 41.819], 'aydin' => [37.845, 27.840], 'balikesir' => [39.648, 27.883],
        'bartin' => [41.635, 32.338], 'batman' => [37.881, 41.135], 'bayburt' => [40.256, 40.225],
        'bilecik' => [40.142, 29.979], 'bingol' => [38.885, 40.498], 'bitlis' => [38.400, 42.109],
        'bolu' => [40.732, 31.608], 'burdur' => [37.721, 30.291], 'bursa' => [40.195, 29.061],
        'canakkale' => [40.147, 26.409], 'cankiri' => [40.600, 33.616], 'corum' => [40.550, 34.954],
        'denizli' => [37.776, 29.086], 'diyarbakir' => [37.914, 40.231], 'duzce' => [40.844, 31.156],
        'edirne' => [41.677, 26.556], 'elazig' => [38.675, 39.223], 'erzincan' => [39.750, 39.493],
        'erzurum' => [39.905, 41.265], 'eskisehir' => [39.777, 30.521], 'gaziantep' => [37.066, 37.383],
        'giresun' => [40.913, 38.390], 'gumushane' => [40.460, 39.481], 'hakkari' => [37.574, 43.741],
        'hatay' => [36.401, 36.349], 'igdir' => [39.923, 44.045], 'isparta' => [37.765, 30.556],
        'istanbul' => [41.008, 28.978], 'izmir' => [38.424, 27.143], 'kahramanmaras' => [37.575, 36.923],
        'karabuk' => [41.206, 32.623], 'karaman' => [37.181, 33.215], 'kars' => [40.602, 43.098],
        'kastamonu' => [41.376, 33.777], 'kayseri' => [38.721, 35.487], 'kilis' => [36.718, 37.115],
        'kirikkale' => [39.846, 33.515], 'kirklareli' => [41.735, 27.225], 'kirsehir' => [39.146, 34.164],
        'kocaeli' => [40.766, 29.917], 'konya' => [37.872, 32.493], 'kutahya' => [39.420, 29.983],
        'malatya' => [38.355, 38.309], 'manisa' => [38.614, 27.429], 'mardin' => [37.313, 40.735],
        'mersin' => [36.812, 34.641], 'mugla' => [37.215, 28.364], 'mus' => [38.734, 41.491],
        'nevsehir' => [38.624, 34.714], 'nigde' => [37.970, 34.679], 'ordu' => [40.986, 37.880],
        'osmaniye' => [37.074, 36.248], 'rize' => [41.025, 40.518], 'sakarya' => [40.774, 30.395],
        'samsun' => [41.286, 36.332], 'siirt' => [37.933, 41.940], 'sinop' => [42.027, 35.151],
        'sivas' => [39.750, 37.015], 'sanliurfa' => [37.167, 38.796], 'sirnak' => [37.519, 42.454],
        'tekirdag' => [40.978, 27.511], 'tokat' => [40.314, 36.554], 'trabzon' => [41.003, 39.717],
        'tunceli' => [39.106, 39.548], 'usak' => [38.674, 29.406], 'van' => [38.494, 43.380],
        'yalova' => [40.655, 29.277], 'yozgat' => [39.820, 34.805], 'zonguldak' => [41.453, 31.789],
    ];

    public function run(): void
    {
        if (self::KIRA_SAYISI > self::TASINMAZ_SAYISI) {
            throw new RuntimeException('Kira sayısı taşınmaz sayısından büyük olamaz; her kira bir taşınmaza bağlanır.');
        }

        DB::disableQueryLog();
        DB::statement('SET synchronous_commit = off');
        DB::statement("SET statement_timeout = '0'");
        // Poligonların GiST indeksi ve kapanıştaki ANALYZE bu havuzu kullanır.
        // Sunucu varsayılanı 64MB; o değerle indeks inşası diske taşıyor.
        DB::statement("SET maintenance_work_mem = '1GB'");

        try {
            $ornOnce = (int) DB::table('kira_dosyalari')->where('dosya_no', 'like', 'ORN-%')->count();
            $this->adim('Önceki yük testi siliniyor', fn () => self::temizle());

            if (! DB::table('iller')->exists() || ! DB::table('mahalleler')->exists()) {
                throw new RuntimeException('İl veya mahalle yok. Önce KonumSeeder çalıştırılmalı.');
            }

            $this->merkezleriYukle();
            $mahalleSayisi = $this->mahalleIzgarasi();
            $kodlar = $this->kodlar();
            $this->plan($mahalleSayisi);
            $this->tasinmazlar();
            $this->tasinmazDetay($kodlar);
            $this->koordinatlar();
            $this->kiraPlan();
            $this->poligonlar();
            $this->kiraKayitlari($kodlar);
            $this->degerlemeler();
            $this->hisseBasvurulari();
            $this->ecrimisiller();
            $this->emsaller($kodlar);
            $this->adim('İstatistikler', fn () => DB::unprepared(
                'ANALYZE tasinmazlar, tasinmaz_ekbilgi, tasinmaz_hisseler, tasinmaz_koordinatlar, tasinmaz_kategori, tasinmaz_imarlar, tasinmaz_yapi, kira_dosyalari, kira_alanlari, kira_poligonlari, kira_tasinmazlari, kira_sozlesmeleri, kira_bedelleri, kira_kiracilari, tasinmaz_degerlemeleri, hisse_basvurulari, ecrimisil_isgalciler, ecrimisil_tutanaklari, emsal_parselleri, emsaller'
            ));
            $this->dogrula($ornOnce);
        } finally {
            DB::statement('SET synchronous_commit = on');
        }
    }

    /** Bu seeder'ın eklediği kayıtları siler. ORN- örnekleri kalır. */
    public static function temizle(): void
    {
        DB::statement("SET statement_timeout = '0'");

        // Çocuklar önce silinir; taşınmaz silinirken GiST artı restrict FK tek
        // cümlede dakikalarca kilit tutuyordu.
        self::parcaSil('kira_alanlari', "aciklama = 'Yük testi'");
        self::parcaSil('kira_dosyalari', "aciklama = 'Yük testi'");
        self::parcaSil(
            'kira_poligonlari',
            "ad LIKE 'Yük testi %' AND NOT EXISTS (SELECT 1 FROM kira_alanlari a WHERE a.poligon_id = kira_poligonlari.id)"
        );
        self::parcaSil('tasinmaz_degerlemeleri', "aciklama = 'Yük testi'");
        // Satış tebligatı ve tapu başvuruyu RESTRICT ile tutar. Yalnızca yük
        // testi başvurularının çocukları silinir; gerçek dosya (açıklaması boş
        // veya farklı olan) yerinde kalır.
        $yukBasvuru = "basvuru_id IN (SELECT b.id FROM hisse_basvurulari b WHERE b.aciklama = 'Yük testi')";
        self::parcaSil('hisse_satis_tapular', $yukBasvuru);
        self::parcaSil('hisse_satis_tebligatlari', $yukBasvuru);
        self::parcaSil('hisse_basvurulari', "aciklama = 'Yük testi'");
        self::parcaSil('ecrimisil_isgalciler', "aciklama = 'Yük testi'");
        self::parcaSil('emsaller', "aciklama = 'Yük testi'");
        self::parcaSil(
            'emsal_parselleri',
            "ada = 'YE' AND NOT EXISTS (SELECT 1 FROM emsaller e WHERE e.emsal_parsel_id = emsal_parselleri.id AND coalesce(e.aciklama, '') <> 'Yük testi')"
        );

        // Ada YK olup üzerinde gerçek hisse, değerleme, malik, lojman veya kira
        // kalan taşınmaz silinmez. Örnek: hisse dosyası yük testi parselinde açılmış olabilir.
        $yukTasinmaz = "id IN (SELECT t.id FROM tasinmazlar t WHERE t.ada = 'YK'
            AND NOT EXISTS (SELECT 1 FROM tasinmaz_ekbilgi e WHERE e.tasinmaz_id = t.id AND coalesce(e.aciklama, '') <> 'Yük testi')
            AND NOT EXISTS (SELECT 1 FROM hisse_basvurulari b WHERE b.tasinmaz_id = t.id AND coalesce(b.aciklama, '') <> 'Yük testi')
            AND NOT EXISTS (SELECT 1 FROM tasinmaz_degerlemeleri d WHERE d.tasinmaz_id = t.id AND coalesce(d.aciklama, '') <> 'Yük testi')
            AND NOT EXISTS (SELECT 1 FROM tasinmaz_malikleri m WHERE m.tasinmaz_id = t.id)
            AND NOT EXISTS (SELECT 1 FROM lojmanlar l WHERE l.tasinmaz_id = t.id)
            AND NOT EXISTS (SELECT 1 FROM kira_tasinmazlari kt WHERE kt.tasinmaz_id = t.id)
            AND NOT EXISTS (SELECT 1 FROM kira_alanlari ka WHERE ka.tasinmaz_id = t.id AND coalesce(ka.aciklama, '') <> 'Yük testi')
            AND NOT EXISTS (SELECT 1 FROM hisse_goruslar g WHERE g.tasinmaz_id = t.id)
            AND NOT EXISTS (SELECT 1 FROM tasinmaz_encumenleri n WHERE n.tasinmaz_id = t.id)
            AND NOT EXISTS (SELECT 1 FROM hisse_tebligatlari ht WHERE ht.tasinmaz_id = t.id)
        )";
        foreach (['tasinmaz_koordinatlar', 'tasinmaz_yapi', 'tasinmaz_imarlar', 'tasinmaz_kategori', 'tasinmaz_hisseler', 'tasinmaz_ekbilgi'] as $tablo) {
            self::parcaSil($tablo, 'tasinmaz_id IN (SELECT id FROM tasinmazlar WHERE '.$yukTasinmaz.')');
        }
        self::parcaSil('tasinmazlar', $yukTasinmaz);
    }

    /** Aynı tablodan parça parça siler. PostgreSQL UPDATE/DELETE alt sorguda LIMIT kabul eder. */
    private static function parcaSil(string $tablo, string $kosul): void
    {
        do {
            $silinen = DB::affectingStatement(
                'DELETE FROM '.$tablo.' WHERE id IN (SELECT id FROM '.$tablo.' WHERE '.$kosul.' LIMIT 20000)'
            );
        } while ($silinen > 0);
    }

    private function merkezleriYukle(): void
    {
        $iller = DB::table('iller')->orderBy('id')->get(['id', 'ad']);
        if ($iller->isEmpty()) {
            throw new RuntimeException('İl bulunamadı.');
        }

        $degerler = [];
        foreach ($iller as $il) {
            $anahtar = $this->anahtar($il->ad);
            if (! isset(self::MERKEZLER[$anahtar])) {
                $this->command?->warn("İl merkezi eşleşmedi, Türkiye ortası kullanıldı: {$il->ad}");
            }
            [$lat, $lng] = self::MERKEZLER[$anahtar] ?? [39.000, 35.000];
            $degerler[] = '('.(int) $il->id.','.$lat.','.$lng.')';
        }

        DB::unprepared('CREATE TEMP TABLE yt_merkez (il_id bigint PRIMARY KEY, lat double precision, lng double precision)');
        foreach (array_chunk($degerler, 40) as $parca) {
            DB::unprepared('INSERT INTO yt_merkez (il_id, lat, lng) VALUES '.implode(',', $parca));
        }
    }

    private function mahalleIzgarasi(): int
    {
        $this->adim('Mahalle ızgarası', fn () => DB::unprepared(<<<'SQL'
            CREATE TEMP TABLE yt_mahalle AS
            WITH ham AS (
                SELECT m.id AS mahalle_id, m.ilce_id, c.il_id, m.ad,
                       row_number() OVER (ORDER BY m.id)::int AS sira,
                       count(*) OVER ()::int AS n,
                       row_number() OVER (PARTITION BY c.il_id ORDER BY m.id)::int AS il_sira,
                       count(*) OVER (PARTITION BY c.il_id)::int AS il_n,
                       k.lat AS il_lat, k.lng AS il_lng
                FROM mahalleler m
                JOIN ilceler c ON c.id = m.ilce_id
                JOIN yt_merkez k ON k.il_id = c.il_id
            )
            SELECT mahalle_id, ilce_id, il_id, ad, sira, n,
                   il_lat + (((il_sira - 1) / cols) - (cols - 1) / 2.0) * (0.42 / cols) AS mlat,
                   il_lng + (((il_sira - 1) % cols) - (cols - 1) / 2.0) * (0.55 / cols) AS mlng
            FROM (
                SELECT ham.*, ceil(sqrt(il_n::float))::int AS cols FROM ham
            ) g
        SQL));

        $n = (int) DB::selectOne('SELECT count(*) AS n FROM yt_mahalle')->n;
        if ($n < 1) {
            throw new RuntimeException('Ankara mahallesi bulunamadı.');
        }
        $this->command?->info("  {$n} mahalle");

        return $n;
    }

    /**
     * @return array{imar: string, muhasebe: string, kayit: string, is_kolu: string, hukuki: string, kiracilik: string, muhasebe_nitelik: string}
     */
    private function kodlar(): array
    {
        $al = function (string $tablo): string {
            $idler = DB::table($tablo)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($idler === []) {
                throw new RuntimeException("{$tablo} boş. İlgili seeder önce çalıştırılmalı.");
            }

            return '(ARRAY['.implode(',', $idler).']::bigint[])';
        };

        return [
            'imar' => $al('imar_durumlari'),
            'muhasebe' => $al('muhasebe_kayitlari'),
            'kayit' => $al('kayit_turleri'),
            'is_kolu' => $al('kira_is_kollari'),
            'hukuki' => $al('kira_hukuki_durumlar'),
            'kiracilik' => $al('kira_kiracilik_durumlari'),
            'muhasebe_nitelik' => $al('kira_muhasebe_nitelikleri'),
        ];
    }

    private function plan(int $mahalleSayisi): void
    {
        $adet = self::TASINMAZ_SAYISI;
        $this->adim('Taşınmaz planı', fn () => DB::unprepared(<<<SQL
            CREATE TEMP TABLE yt AS
            SELECT gs AS sira,
                   m.mahalle_id, m.ilce_id, m.il_id, m.ad AS mahalle_ad,
                   'YK'::varchar(20) AS ada,
                   (((gs - 1) / {$mahalleSayisi}) + 1)::varchar(20) AS parsel,
                   round((180 + (gs % 7000) + (gs % 100) / 100.0)::numeric, 2) AS alan_m2,
                   (ARRAY['Arsa','Tarla','Bahçe','Konut','İşyeri','Arsa'])[1 + (gs % 6)] AS nitelik,
                   CASE WHEN gs % 10 = 0 THEN 'kat_mulkiyetli'
                        WHEN gs % 10 IN (1, 2) THEN 'kat_mulkiyetsiz_bina'
                        ELSE 'bos_parsel' END AS kayit_tipi,
                   round((m.mlat + ((((gs - 1) / {$mahalleSayisi}) / 5) - 2) * 0.00016)::numeric, 7) AS lat,
                   round((m.mlng + ((((gs - 1) / {$mahalleSayisi}) % 5) - 2) * 0.00020)::numeric, 7) AS lng
            FROM generate_series(1, {$adet}) gs
            JOIN yt_mahalle m ON m.sira = ((gs - 1) % {$mahalleSayisi}) + 1
        SQL));
        DB::unprepared('CREATE INDEX yt_sira ON yt (sira)');
        DB::unprepared('CREATE INDEX yt_konum ON yt (mahalle_id, ada, parsel)');
    }

    private function tasinmazlar(): void
    {
        $this->adim('Taşınmazlar', fn () => DB::unprepared(<<<'SQL'
            INSERT INTO tasinmazlar (il_id, ilce_id, mahalle_id, ada, parsel, alan, nitelik, uzeri_bina_var_mi, kayit_tipi, created_at, updated_at)
            SELECT il_id, ilce_id, mahalle_id, ada, parsel, alan_m2, nitelik,
                   kayit_tipi <> 'bos_parsel', kayit_tipi, now(), now()
            FROM yt
            ORDER BY sira
        SQL));

        DB::unprepared(<<<'SQL'
            CREATE TEMP TABLE yt_tasinmaz AS
            SELECT y.sira, t.id
            FROM yt y
            JOIN tasinmazlar t ON t.mahalle_id = y.mahalle_id AND t.ada = y.ada AND t.parsel = y.parsel
        SQL);
        DB::unprepared('CREATE INDEX yt_tasinmaz_sira ON yt_tasinmaz (sira)');

        $eslesen = DB::selectOne('SELECT count(*) AS n, count(DISTINCT sira) AS s FROM yt_tasinmaz');
        if ((int) $eslesen->n !== self::TASINMAZ_SAYISI || (int) $eslesen->s !== self::TASINMAZ_SAYISI) {
            throw new RuntimeException('Taşınmaz eşlemesi tutmadı. Ada YK başka kayıtlarla çakışıyor olabilir.');
        }
    }

    /** @param  array<string, string>  $kodlar */
    private function tasinmazDetay(array $kodlar): void
    {
        $kiraN = self::KIRA_SAYISI;
        $imar = $this->eleman($kodlar['imar'], 'y.sira');
        $muhasebe = $this->eleman($kodlar['muhasebe'], 'y.sira');
        $kayit = $this->eleman($kodlar['kayit'], 'y.sira');

        $this->adim('Ek bilgi, hisse, kategori, imar', fn () => DB::unprepared(<<<SQL
            INSERT INTO tasinmaz_ekbilgi (tasinmaz_id, isgal_durumu, satis_durumu, meclis_satis_karari_var, tahsis_var, ust_hakki_var, kira_var, aciklama, created_at, updated_at)
            SELECT i.id,
                   CASE WHEN y.sira % 17 = 0 THEN 'var' WHEN y.sira % 17 = 1 THEN 'kismi' ELSE 'yok' END,
                   CASE WHEN y.sira % 20 = 0 THEN 'satista' WHEN y.sira % 20 = 1 THEN 'hazirlik' WHEN y.sira % 20 = 2 THEN 'satildi' ELSE 'envanterde' END,
                   y.sira % 25 = 0, y.sira % 19 = 0, y.sira % 29 = 0, y.sira <= {$kiraN},
                   'Yük testi', now(), now()
            FROM yt y
            JOIN yt_tasinmaz i ON i.sira = y.sira;

            INSERT INTO tasinmaz_hisseler (tasinmaz_id, hisse_no, hisse_pay, hisse_payda, hisse_yuzolcum, edinme_sekli, yevmiye_no, edinme_tarihi, hisse_durum, islem_tipi, maliyet_bedeli, rayic_bedel, sira, created_at, updated_at)
            SELECT i.id, 1,
                   1, CASE WHEN y.sira % 7 = 0 THEN 2 ELSE 1 END,
                   round(y.alan_m2 / CASE WHEN y.sira % 7 = 0 THEN 2 ELSE 1 END, 2),
                   'Satın alma', 100000 + y.sira, DATE '2012-01-01' + (y.sira % 4000),
                   'aktif', 'alis',
                   round(y.alan_m2 * 850, 2), round(y.alan_m2 * 2400, 2),
                   0, now(), now()
            FROM yt y
            JOIN yt_tasinmaz i ON i.sira = y.sira;

            INSERT INTO tasinmaz_kategori (tasinmaz_id, muhasebe_kayit_id, kayit_turu_id, mevcut_kullanim_sekli, created_at, updated_at)
            SELECT i.id, {$muhasebe}, {$kayit},
                   (ARRAY['Boş arsa','Tarla','Park','Konut alanı','Ticari alan','Resmi kurum'])[1 + (y.sira % 6)],
                   now(), now()
            FROM yt y
            JOIN yt_tasinmaz i ON i.sira = y.sira;

            INSERT INTO tasinmaz_imarlar (tasinmaz_id, imar_durumu_id, emsal, yenaz_yencok, created_at, updated_at)
            SELECT i.id, {$imar},
                   round((0.20 + (y.sira % 30) / 10.0)::numeric, 2),
                   CASE WHEN y.sira % 3 = 0 THEN '3.50 / 9.50' ELSE NULL END,
                   now(), now()
            FROM yt y
            JOIN yt_tasinmaz i ON i.sira = y.sira;

            INSERT INTO tasinmaz_yapi (tasinmaz_id, sira, blok_no, kat_no, bagimsiz_bolum_no, nitelik, brut_alan, net_alan, satis_durumu, created_at, updated_at)
            SELECT i.id, 0, 'A', 'Zemin', ((y.sira % 40) + 1)::varchar, y.nitelik,
                   round(y.alan_m2 * 0.45, 2), round(y.alan_m2 * 0.38, 2), 'envanterde', now(), now()
            FROM yt y
            JOIN yt_tasinmaz i ON i.sira = y.sira
            WHERE y.kayit_tipi <> 'bos_parsel'
        SQL));
    }

    private function koordinatlar(): void
    {
        $this->parti('Koordinatlar', self::TASINMAZ_SAYISI, function (int $bas, int $son) {
            DB::unprepared(<<<SQL
                INSERT INTO tasinmaz_koordinatlar (tasinmaz_id, koordinat, lat, lng, created_at, updated_at)
                SELECT i.id,
                       json_build_object(
                           'type', 'Polygon',
                           'coordinates', json_build_array(json_build_array(
                               json_build_array(y.lng - 0.00008, y.lat - 0.00006),
                               json_build_array(y.lng + 0.00008, y.lat - 0.00006),
                               json_build_array(y.lng + 0.00008, y.lat + 0.00006),
                               json_build_array(y.lng - 0.00008, y.lat + 0.00006),
                               json_build_array(y.lng - 0.00008, y.lat - 0.00006)
                           ))
                       ),
                       y.lat, y.lng, now(), now()
                FROM yt y
                JOIN yt_tasinmaz i ON i.sira = y.sira
                WHERE y.sira BETWEEN {$bas} AND {$son}
            SQL);
        });
    }

    private function kiraPlan(): void
    {
        $kiraN = self::KIRA_SAYISI;
        $this->adim('Kira planı', fn () => DB::unprepared(<<<SQL
            CREATE TEMP TABLE yt_kira AS
            SELECT y.*, i.id AS tasinmaz_id,
                   'YK-' || lpad(y.sira::text, 7, '0') AS dosya_no,
                   (ARRAY['Park büfesi','Market','Çay bahçesi','Açık otopark','Halı saha','Düğün salonu','Akaryakıt istasyonu','Billboard','Baz istasyonu','Bakkal','Dükkan','Depo','Kiosk','Sosyal tesis'])[1 + (y.sira % 14)]
                       || ' ' || lpad(y.sira::text, 7, '0') AS isyeri,
                   CASE WHEN y.sira % 7 = 0
                        THEN (ARRAY['Yılmaz Gıda Ltd. Şti.','Kaya İnşaat Tic. A.Ş.','Demir Turizm Ltd. Şti.','Şahin Spor Kulübü Derneği','Çelik Petrol Ürünleri Ltd. Şti.','Yıldız Otoparkçılık Ltd. Şti.','Öztürk İletişim Hizmetleri A.Ş.'])[1 + ((y.sira / 7) % 7)]
                        ELSE (ARRAY['Ahmet Yılmaz','Mehmet Kaya','Ayşe Demir','Fatma Şahin','Mustafa Çelik','Zeynep Yıldız','Ali Yıldırım','Elif Öztürk','Hasan Aydın','Selin Özdemir','Emre Arslan','Hatice Doğan','Burak Kılıç','Meryem Aslan','Osman Çetin','Hüseyin Kara','Gökhan Koç','İbrahim Kurt','Murat Özkan','Serkan Şimşek','Yusuf Polat','Emine Erdoğan'])[1 + (y.sira % 22)]
                   END AS kiraci,
                   CASE WHEN y.sira % 10 = 0 THEN 'bos' ELSE 'dolu' END AS dolu_bos,
                   CASE WHEN y.sira % 3 = 0 THEN 'ihaleli' ELSE 'ihalesiz' END AS ihale_turu,
                   (y.sira % 10 <> 0) AS sozlesme_aktif,
                   CASE
                       WHEN y.sira % 10 = 1 THEN (CURRENT_DATE + ((y.sira % 25) + 1) - INTERVAL '36 months')
                       WHEN y.sira % 10 = 2 THEN (CURRENT_DATE - ((y.sira % 120) + 1) - INTERVAL '12 months')
                       WHEN y.sira % 10 = 0 THEN (DATE '2021-03-01' + (y.sira % 500))
                       ELSE (DATE '2024-01-01' + (y.sira % 600))
                   END AS baslangic,
                   CASE
                       WHEN y.sira % 10 = 1 THEN (CURRENT_DATE + ((y.sira % 25) + 1))
                       WHEN y.sira % 10 = 2 THEN (CURRENT_DATE - ((y.sira % 120) + 1))
                       WHEN y.sira % 10 = 0 THEN (DATE '2024-03-01' + (y.sira % 400))
                       ELSE (DATE '2024-01-01' + (y.sira % 600) + INTERVAL '36 months')
                   END AS bitis,
                   (8000 + (y.sira % 4000) * 25)::numeric(15,2) AS yillik
            FROM yt y
            JOIN yt_tasinmaz i ON i.sira = y.sira
            WHERE y.sira <= {$kiraN}
        SQL));
        DB::unprepared('CREATE INDEX yt_kira_sira ON yt_kira (sira)');
    }

    private function poligonlar(): void
    {
        $kullaniciId = Kullanici::query()->value('id');
        $kullaniciSql = $kullaniciId ? (string) (int) $kullaniciId : 'NULL';

        $this->parti('Kira poligonları', self::KIRA_SAYISI, function (int $bas, int $son) use ($kullaniciSql) {
            DB::unprepared(<<<SQL
                INSERT INTO kira_poligonlari (ad, kaynak, mahalle_id, ada, parsel, koordinat, lat, lng, alan, olusturan_id, created_at, updated_at)
                SELECT 'Yük testi ' || lpad(sira::text, 7, '0'), 'cizim', mahalle_id, ada, parsel,
                       json_build_object(
                           'type', 'Polygon',
                           'coordinates', json_build_array(json_build_array(
                               json_build_array(lng - 0.00008, lat - 0.00006),
                               json_build_array(lng + 0.00008, lat - 0.00006),
                               json_build_array(lng + 0.00008, lat + 0.00006),
                               json_build_array(lng - 0.00008, lat + 0.00006),
                               json_build_array(lng - 0.00008, lat - 0.00006)
                           ))
                       ),
                       lat, lng, alan_m2, {$kullaniciSql}, now(), now()
                FROM yt_kira
                WHERE sira BETWEEN {$bas} AND {$son}
            SQL);
        });

        DB::unprepared(<<<'SQL'
            CREATE TEMP TABLE yt_poligon AS
            SELECT (substring(ad from 11))::int AS sira, id
            FROM kira_poligonlari
            WHERE ad LIKE 'Yük testi %'
        SQL);
        DB::unprepared('CREATE INDEX yt_poligon_sira ON yt_poligon (sira)');
    }

    /** @param  array<string, string>  $kodlar */
    private function kiraKayitlari(array $kodlar): void
    {
        $isKolu = $this->eleman($kodlar['is_kolu'], 'k.sira');
        $hukuki = $this->eleman($kodlar['hukuki'], 'k.sira');
        $kiracilik = $this->eleman($kodlar['kiracilik'], 'k.sira');
        $muhasebe = $this->eleman($kodlar['muhasebe_nitelik'], 'k.sira');
        $imar = $this->eleman($kodlar['imar'], 'k.sira');
        $kullaniciId = Kullanici::query()->value('id');
        $kullaniciSql = $kullaniciId ? (string) (int) $kullaniciId : 'NULL';

        $this->adim('Kira dosyaları', fn () => DB::unprepared(<<<SQL
            INSERT INTO kira_dosyalari (
                dosya_no, isyeri_adi, is_kolu_id, dolu_bos, ihale_turu, hukuki_durum_id, kiracilik_durum_id,
                muhasebe_niteligi_id, sozlesme_aktif_mi, tahliye_ihbarnamesi_mi, aciklama, olusturan_id, created_at, updated_at
            )
            SELECT k.dosya_no, k.isyeri, {$isKolu}, k.dolu_bos, k.ihale_turu, {$hukuki}, {$kiracilik}, {$muhasebe},
                   k.sozlesme_aktif, false, 'Yük testi', {$kullaniciSql}, now(), now()
            FROM yt_kira k
            ORDER BY k.sira
        SQL));

        $this->adim('Dosya konumu', fn () => DB::unprepared(<<<SQL
            CREATE TEMP TABLE yt_dosya AS
            SELECT (substring(dosya_no from 4))::int AS sira, id
            FROM kira_dosyalari
            WHERE dosya_no LIKE 'YK-%' AND aciklama = 'Yük testi';
            CREATE INDEX yt_dosya_sira ON yt_dosya (sira);

            INSERT INTO kira_tasinmazlari (
                kira_dosya_id, tasinmaz_id, il_id, ilce_id, mahalle_id, tapu_mahalle_adi, ada, parsel, adres,
                yuzolcum, imar_durumu_id, created_at, updated_at
            )
            SELECT d.id, k.tasinmaz_id, k.il_id, k.ilce_id, k.mahalle_id, k.mahalle_ad, k.ada, k.parsel,
                   k.mahalle_ad || ' Mah. Ada ' || k.ada || ' Parsel ' || k.parsel,
                   k.alan_m2, {$imar}, now(), now()
            FROM yt_kira k
            JOIN yt_dosya d ON d.sira = k.sira
        SQL));

        $this->adim('Sözleşme, bedel, kiracı, alan', fn () => DB::unprepared(<<<SQL
            INSERT INTO kira_sozlesmeleri (
                kira_dosya_id, sozlesme_no, baslangic_tarihi, bitis_tarihi, sure_ay, taksit_sayisi,
                odeme_sekli, kdv_orani, kdv_dahil_mi, artirma_yontemi, aktif_mi, aciklama, created_at, updated_at
            )
            SELECT d.id, k.dosya_no, k.baslangic::date, k.bitis::date,
                   CASE WHEN k.sira % 10 = 2 THEN 12 ELSE 36 END,
                   CASE WHEN k.sira % 3 = 0 THEN 1 WHEN k.sira % 3 = 1 THEN 4 ELSE 12 END,
                   CASE WHEN k.sira % 3 = 0 THEN 'Peşin' ELSE 'Taksit' END,
                   20, false, 'TÜFE', k.sozlesme_aktif, 'Yük testi', now(), now()
            FROM yt_kira k
            JOIN yt_dosya d ON d.sira = k.sira;

            CREATE TEMP TABLE yt_sozlesme AS
            SELECT (substring(sozlesme_no from 4))::int AS sira, id
            FROM kira_sozlesmeleri
            WHERE aciklama = 'Yük testi';
            CREATE INDEX yt_sozlesme_sira ON yt_sozlesme (sira);

            INSERT INTO kira_bedelleri (kira_sozlesme_id, yil_sirasi, donem_baslangic, donem_bitis, yillik_bedel, aylik_bedel, guncel_yillik_bedel, created_at, updated_at)
            SELECT s.id, 1, k.baslangic::date, k.bitis::date, k.yillik, round(k.yillik / 12, 2), k.yillik, now(), now()
            FROM yt_kira k
            JOIN yt_sozlesme s ON s.sira = k.sira;

            INSERT INTO kira_kiracilari (kira_dosya_id, kira_sozlesme_id, tip, ad_unvan, tc_vergi_no, telefon, created_at, updated_at)
            SELECT d.id, s.id, 'ana', k.kiraci,
                   lpad((10000000000 + k.sira)::text, 11, '0'),
                   '0532' || lpad((k.sira % 10000000)::text, 7, '0'),
                   now(), now()
            FROM yt_kira k
            JOIN yt_dosya d ON d.sira = k.sira
            JOIN yt_sozlesme s ON s.sira = k.sira;

            INSERT INTO kira_alanlari (
                kira_dosya_id, poligon_id, tasinmaz_id, ad, adres, alan, faaliyet_konusu, is_kolu_id,
                yillik_bedel, baslangic_tarihi, bitis_tarihi, aktif_mi, aciklama,
                kiracilik_durum_id, hukuki_durum_id, dolu_bos, kiraci_ad_unvan, taksit_sayisi, tahliye_ihbarnamesi_mi,
                created_at, updated_at
            )
            SELECT d.id, p.id, k.tasinmaz_id, k.isyeri,
                   k.mahalle_ad || ' Mah. Ada ' || k.ada || ' Parsel ' || k.parsel,
                   k.alan_m2, k.isyeri, {$isKolu}, k.yillik, k.baslangic::date, k.bitis::date,
                   true, 'Yük testi', {$kiracilik}, {$hukuki}, k.dolu_bos, k.kiraci,
                   CASE WHEN k.sira % 3 = 0 THEN 1 WHEN k.sira % 3 = 1 THEN 4 ELSE 12 END,
                   false, now(), now()
            FROM yt_kira k
            JOIN yt_dosya d ON d.sira = k.sira
            JOIN yt_poligon p ON p.sira = k.sira
        SQL));
    }

    private function degerlemeler(): void
    {
        $kullanici = $this->kullaniciSql();
        $this->adim('Değerleme raporları', fn () => DB::unprepared(<<<SQL
            INSERT INTO tasinmaz_degerlemeleri (
                tasinmaz_id, tip, yapi_id, bb_no, kat, bb_nitelik, brut_alan, hisse_alan,
                birim_fiyat, toplam_bedel, rapor_no, rapor_tarihi, hazirlayan_id, olusturan_id, aciklama,
                created_at, updated_at
            )
            SELECT i.id,
                   CASE WHEN y.kayit_tipi <> 'bos_parsel' AND y.sira % 5 = 0 THEN 'bb' ELSE 'arsa' END,
                   CASE WHEN y.kayit_tipi <> 'bos_parsel' AND y.sira % 5 = 0 THEN yapi.id ELSE NULL END,
                   CASE WHEN y.kayit_tipi <> 'bos_parsel' AND y.sira % 5 = 0 THEN yapi.bagimsiz_bolum_no ELSE NULL END,
                   CASE WHEN y.kayit_tipi <> 'bos_parsel' AND y.sira % 5 = 0 THEN yapi.kat_no ELSE NULL END,
                   CASE WHEN y.kayit_tipi <> 'bos_parsel' AND y.sira % 5 = 0 THEN yapi.nitelik ELSE NULL END,
                   CASE WHEN y.kayit_tipi <> 'bos_parsel' AND y.sira % 5 = 0 THEN yapi.brut_alan ELSE NULL END,
                   CASE WHEN y.kayit_tipi <> 'bos_parsel' AND y.sira % 5 = 0 THEN NULL ELSE y.alan_m2 END,
                   (800 + (y.sira % 12000))::numeric(15,2),
                   round(y.alan_m2 * (800 + (y.sira % 12000)), 2),
                   'DR-' || lpad(y.sira::text, 7, '0'),
                   DATE '2021-03-01' + (y.sira % 1600),
                   {$kullanici}, {$kullanici}, 'Yük testi', now(), now()
            FROM yt y
            JOIN yt_tasinmaz i ON i.sira = y.sira
            LEFT JOIN tasinmaz_yapi yapi ON yapi.tasinmaz_id = i.id AND yapi.sira = 0
        SQL));
    }

    private function hisseBasvurulari(): void
    {
        $this->adim('Hisse başvuruları', fn () => DB::unprepared(<<<'SQL'
            INSERT INTO hisse_basvurulari (
                tasinmaz_id, grup_no, ad_soyad, tc_kimlik, gsm_no, basvuru_tarihi,
                tapu_hisse, talep_edilen_hisse, aciklama, durum, created_at, updated_at
            )
            SELECT i.id,
                   2000000000 + y.sira,
                   (ARRAY['Ahmet Yılmaz','Mehmet Kaya','Ayşe Demir','Fatma Şahin','Mustafa Çelik','Zeynep Yıldız','Ali Yıldırım','Elif Öztürk','Hasan Aydın','Selin Özdemir','Emre Arslan','Hatice Doğan','Burak Kılıç','Meryem Aslan','Osman Çetin','Hüseyin Kara','Gökhan Koç','İbrahim Kurt','Murat Özkan','Serkan Şimşek','Yusuf Polat','Emine Erdoğan'])[1 + (y.sira % 22)],
                   lpad((30000000000 + y.sira)::text, 11, '0'),
                   '0533' || lpad((y.sira % 10000000)::text, 7, '0'),
                   DATE '2023-01-01' + (y.sira % 1000),
                   CASE WHEN y.sira % 7 = 0 THEN 50 ELSE 100 END,
                   25,
                   'Yük testi',
                   CASE WHEN y.sira % 5 = 0 THEN 'reddedildi' WHEN y.sira % 5 = 1 THEN 'tamamlandi' ELSE 'basvuruldu' END,
                   now(), now()
            FROM yt y
            JOIN yt_tasinmaz i ON i.sira = y.sira
        SQL));
    }

    private function ecrimisiller(): void
    {
        $kullanici = $this->kullaniciSql();
        $poligon = $this->poligonIfadesi('y.lng', 'y.lat');
        $this->parti('Ecrimisil', self::TASINMAZ_SAYISI, function (int $bas, int $son) use ($kullanici, $poligon) {
            DB::unprepared(<<<SQL
                INSERT INTO ecrimisil_isgalciler (
                    tasinmaz_id, kullanici_id, ad_soyad_unvan, tc_vergi_no, adres, cadde_sokak, nitelik,
                    koordinat, lat, lng, aciklama, created_at, updated_at
                )
                SELECT i.id, {$kullanici},
                       (ARRAY['Ahmet Yılmaz','Mehmet Kaya','Ayşe Demir','Fatma Şahin','Mustafa Çelik','Zeynep Yıldız','Ali Yıldırım','Elif Öztürk','Hasan Aydın','Selin Özdemir','Emre Arslan','Hatice Doğan','Burak Kılıç','Meryem Aslan','Osman Çetin','Hüseyin Kara','Gökhan Koç','İbrahim Kurt','Murat Özkan','Serkan Şimşek','Yusuf Polat','Emine Erdoğan'])[1 + (y.sira % 22)],
                       lpad((40000000000 + y.sira)::text, 11, '0'),
                       y.mahalle_ad || ' Mah. Ada ' || y.ada || ' Parsel ' || y.parsel,
                       'Yük testi sokak',
                       (ARRAY['Bakkal','Büfe','Otopark','Depo','Sera','Satış ofisi'])[1 + (y.sira % 6)],
                       {$poligon},
                       y.lat, y.lng, 'Yük testi', now(), now()
                FROM yt y
                JOIN yt_tasinmaz i ON i.sira = y.sira
                WHERE y.sira BETWEEN {$bas} AND {$son}
            SQL);
        });

        $this->adim('Ecrimisil tutanakları', fn () => DB::unprepared(<<<'SQL'
            INSERT INTO ecrimisil_tutanaklari (
                isgalci_id, seri_no, tutanak_tarihi, isgal_baslangic_tarihi, isgal_bitis_tarihi, aciklama, created_at, updated_at
            )
            SELECT e.id,
                   'YT-' || lpad(y.sira::text, 7, '0'),
                   CURRENT_DATE - 40,
                   CURRENT_DATE - 500,
                   CASE WHEN y.sira % 3 = 1 THEN NULL ELSE CURRENT_DATE - 15 END,
                   'Yük testi', now(), now()
            FROM ecrimisil_isgalciler e
            JOIN yt_tasinmaz i ON i.id = e.tasinmaz_id
            JOIN yt y ON y.sira = i.sira
            WHERE e.aciklama = 'Yük testi' AND y.sira % 3 <> 0
        SQL));

        DB::unprepared('CREATE INDEX IF NOT EXISTS ecrimisil_isgalciler_lat_lng_idx ON ecrimisil_isgalciler (lat, lng)');
    }

    /** @param  array<string, string>  $kodlar */
    private function emsaller(array $kodlar): void
    {
        $imar = $this->eleman($kodlar['imar'], 'y.sira');
        $kullanici = $this->kullaniciSql();
        $poligon = $this->poligonIfadesi('y.lng', 'y.lat');

        $this->parti('Emsal parselleri', self::TASINMAZ_SAYISI, function (int $bas, int $son) use ($imar, $poligon) {
            DB::unprepared(<<<SQL
                INSERT INTO emsal_parselleri (
                    il_id, ilce_id, mahalle_id, ada, parsel, alan, imar_durumu_id, emsal, yenaz_yencok,
                    koordinat, lat, lng, emsal_sayisi, son_emsal_tarihi, ortalama_birim_fiyat, created_at, updated_at
                )
                SELECT y.il_id, y.ilce_id, y.mahalle_id, 'YE', y.parsel, y.alan_m2, {$imar},
                       round((0.20 + (y.sira % 30) / 10.0)::numeric, 2),
                       CASE WHEN y.sira % 3 = 0 THEN '3.50 / 9.50' ELSE NULL END,
                       {$poligon},
                       y.lat, y.lng,
                       1,
                       DATE '2023-02-01' + (y.sira % 900),
                       (1200 + (y.sira % 9000))::numeric(15,2),
                       now(), now()
                FROM yt y
                WHERE y.sira BETWEEN {$bas} AND {$son}
            SQL);
        });

        $this->adim('Emsal satırları', fn () => DB::unprepared(<<<SQL
            INSERT INTO emsaller (
                emsal_parsel_id, tip, tarih, birim_fiyat, aciklama, karar_no, yevmiye_no, esas_no, olusturan_id, created_at, updated_at
            )
            SELECT p.id,
                   (ARRAY['encumen','senet','mahkeme'])[1 + (y.sira % 3)],
                   DATE '2023-02-01' + (y.sira % 900),
                   (1200 + (y.sira % 9000))::numeric(15,2),
                   'Yük testi',
                   CASE WHEN y.sira % 3 <> 1 THEN 'KR-' || lpad(y.sira::text, 7, '0') ELSE NULL END,
                   CASE WHEN y.sira % 3 = 1 THEN 'YV-' || lpad(y.sira::text, 7, '0') ELSE NULL END,
                   CASE WHEN y.sira % 3 = 2 THEN 'ES-' || lpad(y.sira::text, 7, '0') ELSE NULL END,
                   {$kullanici}, now(), now()
            FROM yt y
            JOIN emsal_parselleri p ON p.mahalle_id = y.mahalle_id AND p.ada = 'YE' AND p.parsel = y.parsel
        SQL));
    }

    private function dogrula(int $ornOnce): void
    {
        $beklenen = self::TASINMAZ_SAYISI;
        $ozet = DB::selectOne(<<<SQL
            SELECT
                (SELECT count(*) FROM tasinmaz_ekbilgi WHERE aciklama = 'Yük testi') AS tasinmaz,
                (SELECT count(*) FROM tasinmaz_koordinatlar k JOIN tasinmaz_ekbilgi e ON e.tasinmaz_id = k.tasinmaz_id WHERE e.aciklama = 'Yük testi' AND k.sinir IS NULL) AS koordinat_sinirsiz,
                (SELECT count(*) FROM kira_dosyalari WHERE aciklama = 'Yük testi') AS kira_dosya,
                (SELECT count(*) FROM kira_alanlari WHERE aciklama = 'Yük testi') AS kira_alan,
                (SELECT count(*) FROM kira_poligonlari WHERE ad LIKE 'Yük testi %' AND sinir IS NULL) AS poligon_sinirsiz,
                (SELECT count(*) FROM tasinmaz_degerlemeleri WHERE aciklama = 'Yük testi') AS degerleme,
                (SELECT count(*) FROM hisse_basvurulari WHERE aciklama = 'Yük testi') AS hisse,
                (SELECT count(*) FROM ecrimisil_isgalciler WHERE aciklama = 'Yük testi' AND (koordinat IS NULL OR lat IS NULL)) AS ecrimisil_eksik,
                (SELECT count(*) FROM ecrimisil_isgalciler WHERE aciklama = 'Yük testi') AS ecrimisil,
                (SELECT count(*) FROM emsal_parselleri WHERE ada = 'YE' AND (emsal_sayisi < 1 OR sinir IS NULL)) AS emsal_eksik,
                (SELECT count(*) FROM emsaller WHERE aciklama = 'Yük testi') AS emsal,
                (SELECT CASE WHEN min(k.min_lng) < 28 AND max(k.max_lng) > 42 AND min(k.min_lat) < 37 AND max(k.max_lat) > 41 THEN 1 ELSE 0 END FROM tasinmaz_koordinatlar k JOIN tasinmaz_ekbilgi e ON e.tasinmaz_id = k.tasinmaz_id WHERE e.aciklama = 'Yük testi') AS turkiye,
                (SELECT count(*) FROM kira_dosyalari WHERE dosya_no LIKE 'ORN-%') AS ornek_dosya,
                (SELECT count(*) FROM (
                    SELECT mahalle_id, ada, parsel FROM tasinmazlar WHERE ada = 'YK' GROUP BY 1, 2, 3 HAVING count(*) > 1
                ) c) AS cakisma
        SQL);

        $tutmadi = (int) $ozet->tasinmaz !== $beklenen
            || (int) $ozet->kira_dosya !== self::KIRA_SAYISI
            || (int) $ozet->kira_alan !== self::KIRA_SAYISI
            || (int) $ozet->degerleme !== $beklenen
            || (int) $ozet->hisse !== $beklenen
            || (int) $ozet->ecrimisil !== $beklenen
            || (int) $ozet->emsal !== $beklenen
            || (int) $ozet->koordinat_sinirsiz !== 0
            || (int) $ozet->poligon_sinirsiz !== 0
            || (int) $ozet->ecrimisil_eksik !== 0
            || (int) $ozet->emsal_eksik !== 0
            || (int) $ozet->turkiye !== 1
            || (int) $ozet->cakisma !== 0
            || (int) $ozet->ornek_dosya !== $ornOnce;

        if ($tutmadi) {
            throw new RuntimeException('Yük testi doğrulaması tutmadı: '.json_encode($ozet, JSON_UNESCAPED_UNICODE));
        }

        $this->command?->info(sprintf(
            'Türkiye geneli %d taşınmaz, kira, değerleme, hisse, ecrimisil ve emsal eklendi. Örnek dosya (ORN-) sayısı: %d.',
            $beklenen,
            $ornOnce
        ));
    }

    private function kullaniciSql(): string
    {
        $kullaniciId = Kullanici::query()->value('id');

        return $kullaniciId ? (string) (int) $kullaniciId : 'NULL';
    }

    private function poligonIfadesi(string $lng, string $lat): string
    {
        return "json_build_object('type', 'Polygon', 'coordinates', json_build_array(json_build_array("
            ."json_build_array({$lng} - 0.00008, {$lat} - 0.00006),"
            ."json_build_array({$lng} + 0.00008, {$lat} - 0.00006),"
            ."json_build_array({$lng} + 0.00008, {$lat} + 0.00006),"
            ."json_build_array({$lng} - 0.00008, {$lat} + 0.00006),"
            ."json_build_array({$lng} - 0.00008, {$lat} - 0.00006))))";
    }

    private function eleman(string $dizi, string $sira): string
    {
        return "{$dizi}[1 + (({$sira} - 1) % array_length({$dizi}, 1))]";
    }

    private function adim(string $baslik, callable $is): void
    {
        $t = microtime(true);
        $is();
        $this->command?->info(sprintf('%s (%.1f sn)', $baslik, microtime(true) - $t));
        flush();
    }

    /** @param  callable(int, int): void  $parti */
    private function parti(string $baslik, int $adet, callable $parti): void
    {
        $t = microtime(true);
        for ($i = 1; $i <= $adet; $i += self::PARTI) {
            $parti($i, min($adet, $i + self::PARTI - 1));
        }
        $this->command?->info(sprintf('%s (%.1f sn)', $baslik, microtime(true) - $t));
        flush();
    }

    private function anahtar(string $ad): string
    {
        $ad = str_replace(['İ', 'I'], ['i', 'ı'], $ad);
        $ad = mb_strtolower($ad, 'UTF-8');
        $ad = strtr($ad, ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'â' => 'a', 'î' => 'i', 'û' => 'u']);
        $ad = str_replace("i\u{0307}", 'i', $ad);

        return preg_replace('/[^a-z]/', '', $ad) ?? '';
    }
}
