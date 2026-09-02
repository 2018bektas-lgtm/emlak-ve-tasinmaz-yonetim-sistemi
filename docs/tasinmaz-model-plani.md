# Taşınmaz Modeli — Tasarım Planı

## Domain kararı

Türkiye tapu sisteminde iki farklı kayıt tipi var:

- **Tasinmaz** (arsa/parsel) — TAKBİS'teki ana taşınmaz. Kendi ada/parsel/mahalle bilgisi, kendi tapusu, kendi imarı, kendi koordinatı olur. Bir tarla, boş arsa veya kat mülkiyeti binasının altındaki arsa.
- **BagimsizBolum** (BBN) — Kat mülkiyet birimi. Bir arsa üzerindeki daire/dükkan/depo. Kendi kat mülkiyet tapusu, arsa payı ve BBN'ye özgü fiziksel bilgileri (blok, kat, m², oda) olur. Ada/parsel/mahalle bilgisi kendi tabelasında yok — parent Tasinmaz'dan gelir.

**İki entity ayrı tablolarda**, çünkü:
- Kolonları farklı: BBN'de blok/kat/BBN no/arsa payı var, arsada koordinat/imar var
- Aynı tabloya sıkıştırmak sparse (yarısı NULL) ve karışıklık yaratır
- Türkiye tapu gerçeğine birebir uyar

**Ortak yapılar** (tapu, meclis kararı, resim) hem Tasinmaz hem BagimsizBolum'a
bağlanır. Bunlar için **polimorfik ilişki** kullanılır — tek tablo, `sahip_type`
kolonu ile hangi entity'ye bağlı olduğu belli olur.

### Polimorfik ilişki nedir? (kısaca)

Bir tablo iki farklı parent tipine bağlanabilir:

```
tapular
-------
sahip_type       | sahip_id | takbis_zemin_no
Tasinmaz         |    1     |  ...      → arsa 1'in tapusu
BagimsizBolum    |   45     |  ...      → daire 45'in tapusu
BagimsizBolum    |   46     |  ...      → daire 46'nın tapusu
```

Laravel'de `$arsa->tapu`, `$daire->tapu` doğal olarak çalışır. Tek dosya yönetim
mantığı, tek observer, ilerde eklenecek entity'ler (Sozlesme, Ihale) de aynı
tabloya bağlanabilir → **büyük projede en esnek yaklaşım**.

## Diyagram

```
       iller ─ ilceler ─ mahalleler
                    │
                    ▼
        ┌────── Tasinmaz ──────┐
        │  (arsa/parsel)        │
        │  ada, parsel, alan,   │
        │  imar, koordinat...   │
        └──┬────┬────┬────┬─────┘
           │1:1 │1:1  │1:1 │1:N
           ▼    ▼     ▼    ▼
       Imar  Koord.  ┐  BagimsizBolum
        │            │      (BBN)
        ▼            │   blok, kat, brut/net m²,
   ImarDurumu        │   oda, cephe, arsa payı
   (parametrik)      │      ┌──────┴──────┐
                     │      │             │
        ┌────────────┴──────┴─────────────┴─────────┐
        │  Polimorfik — hem Tasinmaz hem BagimsizBolum'a bağlanır  │
        ├──────────────────────────────────────────┤
        │  Tapu (1:1)    MeclisKarari (1:N)    Resim (1:N)         │
        └────────────────────────────────────────────────────────────┘
```

---

## Modeller

### 1. Tasinmaz — `tasinmazlar`

Arsa/parsel/tarla — TAKBİS ana taşınmaz.

```sql
id                        BIGINT PK
il_id                     FK → iller.id           -- denormalize + observer
ilce_id                   FK → ilceler.id         -- denormalize + observer
mahalle_id                FK → mahalleler.id

ada                       VARCHAR(20)
parsel                    VARCHAR(20)
alan                      DECIMAL(15,2)           -- m² (parselin toplam alanı)
nitelik                   VARCHAR(100)            -- Arsa | Tarla | Bahçe | Bağ (tapu niteliği)

muhasebe_kayit_id         FK → muhasebe_kayitlari.id  (parametrik)
kayit_turu_id             FK → kayit_turleri.id       (parametrik)
mevcut_kullanim_sekli     VARCHAR(100)
isgal_durumu              VARCHAR(20)             -- yok | var | kismi
uzeri_bina_var_mi         BOOLEAN
meclis_satis_karari_var   BOOLEAN                 -- denormalize flag (observer ile)
aciklama                  TEXT NULL

timestamps

INDEX (mahalle_id, ada, parsel)
INDEX (il_id, ilce_id)
INDEX (nitelik)
INDEX (meclis_satis_karari_var)
```

**İlişkiler:**
```php
belongsTo   il, ilce, mahalle
belongsTo   muhasebeKayit (MuhasebeKayit)        // parametrik
belongsTo   kayitTuru (KayitTuru)                // parametrik
hasOne      koordinat (TasinmazKoordinat)
hasOne      imar (TasinmazImar)
hasMany     bagimsizBolumler (BagimsizBolum)
morphOne    tapu (Tapu)                          // polimorfik 1:1
morphMany   meclisKararlari (MeclisKarari)       // polimorfik 1:N
morphMany   resimler (Resim)                      // polimorfik 1:N
```

---

### 2. BagimsizBolum — `bagimsiz_bolumler`

Kat mülkiyet birimi (daire, dükkan, depo). Her BBN kendi tapusuna sahip.

```sql
id                        BIGINT PK
tasinmaz_id               FK → tasinmazlar.id     -- 1:N (ana taşınmaz)

blok_no                   VARCHAR(20) NULL
kat_no                    VARCHAR(10)             -- bodrum | zemin | 1 | B1 (string)
bagimsiz_bolum_no         VARCHAR(20)             -- BBN numarası
nitelik                   VARCHAR(50)             -- Mesken | Dükkan | Depo | İşyeri | Ortak Alan
brut_alan                 DECIMAL(10,2) NULL      -- m²
net_alan                  DECIMAL(10,2) NULL      -- m²
oda_sayisi                VARCHAR(10) NULL        -- 1+1 | 3.5+1 (string)
cephe                     VARCHAR(50) NULL        -- "kuzey,doğu"
arsa_payi_pay             INT NULL                -- kat mülkiyette arsa payı X/Y
arsa_payi_payda           INT NULL

muhasebe_kayit_id         FK → muhasebe_kayitlari.id  (parametrik)
kayit_turu_id             FK → kayit_turleri.id       (parametrik)
mevcut_kullanim_sekli     VARCHAR(100)
isgal_durumu              VARCHAR(20)             -- BBN'nin işgal durumu (kiracı vs.)
meclis_satis_karari_var   BOOLEAN                 -- denormalize flag (observer)
aciklama                  TEXT NULL

timestamps

UNIQUE (tasinmaz_id, blok_no, bagimsiz_bolum_no)
INDEX (tasinmaz_id)
INDEX (nitelik)
INDEX (meclis_satis_karari_var)
```

**İlişkiler:**
```php
belongsTo   tasinmaz (Tasinmaz)                  // ana taşınmaz
belongsTo   muhasebeKayit (MuhasebeKayit)        // parametrik
belongsTo   kayitTuru (KayitTuru)                // parametrik
morphOne    tapu (Tapu)                          // polimorfik 1:1
morphMany   meclisKararlari (MeclisKarari)       // polimorfik 1:N
morphMany   resimler (Resim)                      // polimorfik 1:N
```

Ada/parsel/mahalle bilgisi burada yok — `$bbn->tasinmaz->ada` üzerinden erişilir.

---

### 3. Tapu — `tapular` *(polimorfik)*

Hem Tasinmaz hem BagimsizBolum'un tapusu bu tabloda. BBN-specific alanlar
(arsa payı, kat mülkiyet no) nullable.

```sql
id                  BIGINT PK
sahip_type          VARCHAR(255)                  -- App\Models\Tasinmaz | App\Models\BagimsizBolum
sahip_id            BIGINT
takbis_zemin_no     VARCHAR(30)
cilt_no             VARCHAR(20)
sayfa_no            VARCHAR(20)
tapu_durumu         VARCHAR(20)                   -- aktif | pasif
tapu_kaydi_pdf      VARCHAR(500) NULL
tapu_tarihi         DATE NULL
timestamps

UNIQUE (sahip_type, sahip_id)                     -- her sahibin tek tapusu (1:1)
INDEX (takbis_zemin_no)
INDEX (tapu_durumu)
```

**İlişki:** `morphTo sahip` (Tasinmaz veya BagimsizBolum)

---

### 4. TasinmazImar — `tasinmaz_imarlar`

**Sadece Tasinmaz'a** bağlı — BBN'nin ayrı imarı yok, arsa imarına tabidir.

```sql
id                  BIGINT PK
tasinmaz_id         FK UNIQUE → tasinmazlar.id    -- 1:1
imar_durumu_id      FK NULL → imar_durumlari.id
emsal               DECIMAL(5,2) NULL             -- KAKS
yenaz_yencok        VARCHAR(50) NULL              -- "Yençok: 12.50m" | "Serbest"
imar_notu           TEXT NULL
timestamps

INDEX (imar_durumu_id)
```

**İlişkiler:** `belongsTo tasinmaz`, `belongsTo imarDurumu`

---

### 5. ImarDurumu — `imar_durumlari` *(parametrik referans)*

Yönetici panelinden yönetilir.

```sql
id                  BIGINT PK
ad                  VARCHAR(100) UNIQUE           -- Konut Alanı | Ticaret Alanı | Park | ...
kod                 VARCHAR(20) UNIQUE NULL
aciklama            TEXT NULL
sira                SMALLINT DEFAULT 0
aktif_mi            BOOLEAN DEFAULT true
timestamps

INDEX (aktif_mi, sira)
```

**İlişki:** `hasMany tasinmazImarlari` — `restrictOnDelete`

---

### 5b. MuhasebeKayit — `muhasebe_kayitlari` *(parametrik)*
### 5c. KayitTuru — `kayit_turleri` *(parametrik)*

Aynı yapıda (id, ad UNIQUE, kod, aciklama, sira, aktif_mi, timestamps).
- **MuhasebeKayit**: Araziler, Binalar, Diğer Duran Varlıklar… (hesap planı)
- **KayitTuru**: Satın Alma, Kamulaştırma, Hibe, Tahsis, Devir, İmar Uygulaması, Kat Mülkiyeti Tesisi

Her ikisinden de `Tasinmaz` ve `BagimsizBolum` FK ile referans verir; `restrictOnDelete` (kullanılan silinemez).

**İlişkiler:** `hasMany tasinmazlar`, `hasMany bagimsizBolumler`

---

### 6. TasinmazKoordinat — `tasinmaz_koordinatlar` *(mevcut)*

**Sadece Tasinmaz'a** bağlı — arsanın polygon geometrisi. BBN'nin ayrı polygon'u yok.

```sql
id                  BIGINT PK
tasinmaz_id         FK UNIQUE → tasinmazlar.id    -- 1:1
koordinat           JSON NULL                     -- GeoJSON polygon
lat                 DECIMAL(10,7) NULL
lng                 DECIMAL(10,7) NULL
timestamps

INDEX (lat, lng)
```

**İlişki:** `belongsTo tasinmaz`

---

### 7. MeclisKarari — `meclis_kararlari` *(polimorfik)*

Hem Tasinmaz hem BagimsizBolum için ayrı ayrı meclis kararları.

```sql
id                  BIGINT PK
sahip_type          VARCHAR(255)                  -- Tasinmaz | BagimsizBolum
sahip_id            BIGINT
karar_tipi          VARCHAR(30)                   -- satis | tahsis | kamulastirma | iptal | diger
karar_no            VARCHAR(50)
karar_tarihi        DATE
karar_ozeti         TEXT NULL
karar_pdf           VARCHAR(500) NULL
timestamps

INDEX (sahip_type, sahip_id, karar_tarihi DESC)
INDEX (karar_tipi)
```

**İlişki:** `morphTo sahip`

**Observer:** `saved / deleted` → polimorfik sahibin `meclis_satis_karari_var` flag'ini senkronize et (Tasinmaz veya BagimsizBolum tablosunda).

---

### 8. Resim — `resimler` *(polimorfik)*

Hem Tasinmaz hem BagimsizBolum'un kendi galerisi.

```sql
id                  BIGINT PK
sahip_type          VARCHAR(255)
sahip_id            BIGINT
dosya_yolu          VARCHAR(500)
kucuk_yolu          VARCHAR(500) NULL
sira                SMALLINT DEFAULT 0
kapak_mi            BOOLEAN DEFAULT false
aciklama            VARCHAR(255) NULL
mime_type           VARCHAR(50)
boyut               BIGINT
timestamps

INDEX (sahip_type, sahip_id, sira)
```

**İlişki:** `morphTo sahip`

**Observer:**
- `kapak_mi = true` → aynı sahibin diğer resimlerini `false` yap
- `deleting` → fiziksel dosya sil

---

## Migration sırası

```
1.  000006_extend_tasinmazlar_table              → yeni kolonlar
2.  000007_create_imar_durumlari_table           → parametrik
3.  000008_create_tasinmaz_imarlar_table         → imar_durumlari'ne FK
4.  000009_create_bagimsiz_bolumler_table        → tasinmazlar'a FK
5.  000010_create_tapular_table                  → polimorfik
6.  000011_create_meclis_kararlari_table         → polimorfik
7.  000012_create_resimler_table                 → polimorfik
8.  000013_create_muhasebe_kayitlari_table       → parametrik
9.  000014_create_kayit_turleri_table            → parametrik
10. 000015_alter_tasinmazlar_muhasebe_kayit_turu_fk       → VARCHAR → FK
11. 000016_alter_bagimsiz_bolumler_muhasebe_kayit_turu_fk → VARCHAR → FK
```

## Dosya yükleme

```
storage/app/public/
├── tapular/{yıl}/{ay}/{uuid}.pdf
├── meclis_kararlari/{yıl}/{ay}/{uuid}.pdf
└── resimler/{sahip_type}/{sahip_id}/{uuid}.jpg
```

- Model `deleting` event → fiziksel dosya sil
- Adı UUID + orijinal uzantı
- PDF ≤ 20 MB, resim ≤ 8 MB (form request)

---

## Kararlar özet

| Karar | Neden |
|---|---|
| **`Tasinmaz` ve `BagimsizBolum` ayrı entity** | Kolonları farklı; sıkıştırma sparse table olur; Türkiye tapu gerçeği (kat mülkiyet birimi ≠ arsa) |
| **Ada/parsel/mahalle sadece Tasinmaz'da** | BBN'ler parent'tan miras alır, tekrar yok, tek güncelleme noktası |
| **`imar`, `koordinat` sadece Tasinmaz'da** | Anlamlıları arsa; BBN imarı yok, BBN polygon'u yok |
| **`Tapu`, `MeclisKarari`, `Resim` polimorfik** | İki entity'ye de bağlanabilir; tek yönetim mantığı; ilerde Sozlesme/Ihale gibi entity'ler eklenirse aynı tabloları kullanabilir |
| **`imar_durumlari` parametrik** | Yönetici panelinden yönetilebilir |
| **`meclis_satis_karari_var` denormalize flag** | Liste sorgularında JOIN'suz filtre, observer ile tutarlı |
| **BBN'de `arsa_payi_pay/payda`** | Kat mülkiyet tapusunda zorunlu bilgi (X/Y arsa payı) |
| **BBN'de kendi `nitelik`, `muhasebe_niteligi`, `isgal_durumu`** | Her BBN farklı olabilir (bir daire mesken, diğeri işyeri; birinde kiracı var) |

---

## Kullanım örneği

```php
// Boş arsa
$arsa = Tasinmaz::create([
    'mahalle_id' => 1, 'ada' => '123', 'parsel' => '45',
    'alan' => 850.75, 'nitelik' => 'Arsa',
    'kayit_turu' => 'satın_alma', ...
]);
$arsa->tapu()->create([...]);
$arsa->imar()->create([...]);
$arsa->koordinat()->create([...]);
$arsa->meclisKararlari()->create([...]);
$arsa->resimler()->create([...]);

// Kat mülkiyetli bina — arsa + 30 daire
$daire = $arsa->bagimsizBolumler()->create([
    'blok_no' => 'A', 'kat_no' => '3', 'bagimsiz_bolum_no' => '12',
    'nitelik' => 'Mesken', 'brut_alan' => 120.50, 'net_alan' => 105.00,
    'oda_sayisi' => '3+1', 'arsa_payi_pay' => 5, 'arsa_payi_payda' => 500,
]);
$daire->tapu()->create([...]);           // dairenin kat mülkiyet tapusu
$daire->meclisKararlari()->create([...]);// dairenin satış kararı
$daire->resimler()->create([...]);       // dairenin fotoları

// Erişim
$arsa->bagimsizBolumler        // 30 daire
$arsa->tapu                    // arsanın tapusu (varsa)
$daire->tasinmaz               // parent arsa
$daire->tapu                   // dairenin kendi tapusu
$daire->tasinmaz->mahalle->ad  // ada/parsel/mahalle parent'tan
```
