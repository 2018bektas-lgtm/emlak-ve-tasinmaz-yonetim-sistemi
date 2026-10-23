/* ------------------------------------------------------------------
 * TAŞINMAZ HARİTASI — ArcGIS Maps SDK for JavaScript 4.30
 *
 * Altlıklar      : Google mt (hibrit / uydu / yol / arazi) + OpenStreetMap
 * Veri katmanları: Sistem parselleri (GeoJSON) + Ankara BB MapServer'ları
 * Yapılandırma   : window.ETYS_HARITA (blade tarafından basılır)
 * ------------------------------------------------------------------ */
(function () {
    const mapEl = document.getElementById('hrm-map');
    const API = window.ETYS_HARITA;
    if (!mapEl || !API || typeof require !== 'function') return;

    const BASKENTCBS = 'https://baskentcbs.ankara.bel.tr/server/rest/services';

    require([
        'esri/Map',
        'esri/Basemap',
        'esri/views/MapView',
        'esri/layers/WebTileLayer',
        'esri/layers/BaseTileLayer',
        'esri/layers/BaseDynamicLayer',
        'esri/layers/support/TileInfo',
        'esri/layers/GraphicsLayer',
        'esri/Graphic',
        'esri/geometry/Polygon',
        'esri/geometry/Point',
        'esri/geometry/Extent',
        'esri/geometry/geometryEngine',
        'esri/geometry/support/webMercatorUtils',
        'esri/core/reactiveUtils',
        'esri/widgets/Sketch/SketchViewModel',
    ], function (EsriMap, Basemap, MapView, WebTileLayer, BaseTileLayer, BaseDynamicLayer, TileInfo, GraphicsLayer,
                 Graphic, Polygon, Point, Extent, geometryEngine, webMercatorUtils, reactiveUtils, SketchViewModel) {

        /* =========================================================
         * 1) ALTLIKLAR — Google mt + OSM
         * ========================================================= */
        const GOOGLE_ALT = ['mt0', 'mt1', 'mt2', 'mt3'];

        function googleAltlik(lyrs) {
            return new WebTileLayer({
                urlTemplate: 'https://{subDomain}.google.com/vt/lyrs=' + lyrs + '&x={col}&y={row}&z={level}',
                subDomains: GOOGLE_ALT,
                copyright: 'Google',
            });
        }

        const altlikTanim = [
            { id: 'hibrit', ad: 'Uydu + Etiket', ozet: 'Google', preview: 'https://mt1.google.com/vt/lyrs=y&x=299&y=193&z=9', yap: function () { return googleAltlik('y'); } },
            { id: 'uydu',   ad: 'Uydu',          ozet: 'Google', preview: 'https://mt1.google.com/vt/lyrs=s&x=299&y=193&z=9', yap: function () { return googleAltlik('s'); } },
            { id: 'yol',    ad: 'Yol Haritası',  ozet: 'Google', preview: 'https://mt1.google.com/vt/lyrs=m&x=299&y=193&z=9', yap: function () { return googleAltlik('m'); } },
            { id: 'arazi',  ad: 'Arazi',         ozet: 'Google', preview: 'https://mt1.google.com/vt/lyrs=p&x=299&y=193&z=9', yap: function () { return googleAltlik('p'); } },
            { id: 'osm',    ad: 'OpenStreetMap', ozet: 'OSM',    preview: 'https://a.tile.openstreetmap.org/9/299/193.png', yap: function () {
                return new WebTileLayer({
                    urlTemplate: 'https://{subDomain}.tile.openstreetmap.org/{level}/{col}/{row}.png',
                    subDomains: ['a', 'b', 'c'],
                    copyright: '&copy; OpenStreetMap katkıcıları',
                });
            } },
        ];

        const altlikOnbellek = {};
        function altlikBasemap(id) {
            if (!altlikOnbellek[id]) {
                const tanim = altlikTanim.find(function (a) { return a.id === id; });
                altlikOnbellek[id] = new Basemap({ baseLayers: [tanim.yap()], title: tanim.ad });
            }
            return altlikOnbellek[id];
        }

        let aktifAltlikId = localStorage.getItem('etys-harita-altlik') || 'hibrit';
        if (!altlikTanim.some(function (a) { return a.id === aktifAltlikId; })) aktifAltlikId = 'hibrit';

        /* =========================================================
         * 2) VERİ KATMANLARI
         * ========================================================= */
        // Ankara BB · Uygulama İmar Planı (baskentcbs · uip_esri3). Önbellekli servis,
        // kareler doğrudan baskentcbs'den: /tile/{z}/{y}/{x}, 256 px, EPSG:3857.
        // Servis 24 seviye tanımlıyor ama karesi yalnızca 19'a kadar var (20+ → 404);
        // seviyeler 0–19 verilir, daha yakın zoomda harita 19'un karesini büyütür
        // (yoksa katman 19'dan sonra kayboluyordu).
        // minScale: katmanın görünmeye başladığı yakınlaştırma (ölçek = 591.657.527 / 2^zoom);
        // daha uzakta istek atılmaz. İmar 15+, parselasyon 16+, yapı 17+, numarataj 19+.
        const ABB_KARE_SEVIYE = TileInfo.create({ size: 256, numLODs: 20, spatialReference: { wkid: 3857 } });

        // baskentcbs aynı anda gelen çok isteği 429 ile kesiyor. 429 cevabında
        // CORS başlığı olmadığı için tarayıcı bunu "CORS hatası" diye gösteriyor.
        // WebTileLayer başarısız kareyi her karede yeniden istediğinden sınır
        // bir kez dolunca istek hiç durmuyordu. Burada en fazla 3 istek aynı
        // anda çıkar, başarısız kare bir kez bekleyip yeniden dener, olmazsa
        // boş döner (SDK'nın sonsuz yeniden denemesi devreye girmez).
        const ABB_AYNI_ANDA = 3;
        const kareOnbellek = new Map();
        const kuyruk = [];
        let kuyrukAktif = 0;

        function kuyrukBosalt() {
            while (kuyrukAktif < ABB_AYNI_ANDA && kuyruk.length) {
                const is = kuyruk.shift();
                if (is.sinyal && is.sinyal.aborted) {
                    is.reject(new DOMException('İptal', 'AbortError'));
                    continue;
                }
                kuyrukAktif++;
                is.calistir().then(is.resolve, is.reject).then(function () {
                    kuyrukAktif--;
                    setTimeout(kuyrukBosalt, 80);
                });
            }
        }

        function sirayaAl(calistir, sinyal) {
            return new Promise(function (resolve, reject) {
                kuyruk.push({ calistir: calistir, sinyal: sinyal, resolve: resolve, reject: reject });
                kuyrukBosalt();
            });
        }

        function imgYukle(adres, sinyal) {
            return new Promise(function (resolve, reject) {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                const vazgec = function () { img.src = ''; reject(new DOMException('İptal', 'AbortError')); };
                if (sinyal) {
                    if (sinyal.aborted) { vazgec(); return; }
                    sinyal.addEventListener('abort', vazgec, { once: true });
                }
                img.onload = function () {
                    if (sinyal) sinyal.removeEventListener('abort', vazgec);
                    resolve(img);
                };
                img.onerror = function () {
                    if (sinyal) sinyal.removeEventListener('abort', vazgec);
                    reject(new Error('kare'));
                };
                img.src = adres;
            });
        }

        function kareYukle(adres, sinyal) {
            const hazir = kareOnbellek.get(adres);
            if (hazir) return hazir;
            const istek = sirayaAl(function () { return imgYukle(adres, sinyal); }, sinyal).catch(function (hata) {
                if (hata && hata.name === 'AbortError') throw hata;
                return new Promise(function (resolve) { setTimeout(resolve, 1200); })
                    .then(function () { return sirayaAl(function () { return imgYukle(adres, sinyal); }, sinyal); });
            });
            const saklanan = istek.then(function (img) { return img; });
            kareOnbellek.set(adres, saklanan);
            saklanan.catch(function () {
                if (kareOnbellek.get(adres) === saklanan) kareOnbellek.delete(adres);
            });
            return saklanan;
        }

        function bosKare() {
            const tuval = document.createElement('canvas');
            tuval.width = 256;
            tuval.height = 256;
            return tuval;
        }

        const AbbKareKatman = BaseTileLayer.createSubclass({
            properties: { servis: null },
            getTileUrl: function (level, row, col) {
                return this.servis + '/tile/' + level + '/' + row + '/' + col;
            },
            fetchTile: function (level, row, col, options) {
                const sinyal = options && options.signal;
                return kareYukle(this.getTileUrl(level, row, col), sinyal).catch(function (hata) {
                    if (hata && hata.name === 'AbortError') throw hata;
                    return bosKare();
                });
            },
        });

        function onbellekKatmani(servis, secenek) {
            return new AbbKareKatman({
                servis: servis,
                tileInfo: ABB_KARE_SEVIYE,
                minScale: secenek.minScale,
                opacity: secenek.opacity,
                visible: false,
                title: secenek.title,
            });
        }

        const imarKatman = onbellekKatmani(BASKENTCBS + '/plan/uip_esri3/MapServer', {
            title: 'ABB · İmar Planı',
            minScale: 18100, // zoom 15+
            opacity: 0.75,
        });

        // ParselAktifCached hazır kareleri 11–19. seviyede duruyor; daha yakında
        // son kare büyütülür. Yazılar kareden geldiği için net, istek de önbellekten.
        const parselasyonKatman = onbellekKatmani(BASKENTCBS + '/ortak/ParselAktifCached/MapServer', {
            title: 'ABB · Parselasyon',
            minScale: 9100, // zoom 16+
            opacity: 0.80,
        });

        /* ---------------------------------------------------------
         * Ankara BB · baskentcbs (dışa açık sunucu)
         * Bu beş katman önceden iç ağdaki abbcbs proxy'sinden geliyordu;
         * o sunucu yalnızca belediye ağından erişilebildiği için uygulama
         * ancak iç ağda çalışabiliyordu. baskentcbs dışarıdan da açık ve
         * aynı verileri yayınlıyor — katman numaraları da birebir aynı.
         * Artık Laravel köprüsü gerekmiyor, tarayıcı doğrudan çağırıyor.
         *
         * Ölçek pencereleri (servis tanımından):
         *   Numarataj  1:1.500'den yakın
         *   Yapı       1:5.000'den yakın
         *   Mahalle    1:10.000 – 1:150.000
         *   İlçe       1:50.000'den uzak
         * --------------------------------------------------------- */
        const ADRES_SERVIS = BASKENTCBS + '/ortak/adres/MapServer';

        // Belediye Taşınmazları — hisse_duru (TAM / HİSSELİ / HATALI)
        // ayrımı sunucu sembolojisinde hazır geliyor.
        // Önbelleği olmayan servis. MapImageLayer her karede fetch() kullanıyor;
        // 429 olunca CORS'suz cevap yüzünden katman sonsuza dek yeniden deniyordu.
        // İstek kare kuyruğuna girer. Kaydırma sırasında son resim yerinde kalır,
        // yeni resim ancak görüntü durunca istenir.
        const gorunum = { view: null };

        const AbbExportKatman = BaseDynamicLayer.createSubclass({
            properties: { servis: null, katmanNo: '0' },
            fetchImage: function (extent, width, height) {
                const gorunen = gorunum.view;
                if (gorunen && !gorunen.stationary && this._son) {
                    const katman = this;
                    if (!katman._yenileme) {
                        katman._yenileme = gorunen.watch('stationary', function (durdu) {
                            if (!durdu) return;
                            katman._yenileme.remove();
                            katman._yenileme = null;
                            if (katman.visible) katman.refresh();
                        });
                    }
                    return Promise.resolve(this._son);
                }
                let w = Math.max(1, Math.round(width));
                let h = Math.max(1, Math.round(height));
                const sigdir = Math.min(1, 4096 / Math.max(w, h));
                w = Math.max(1, Math.round(w * sigdir));
                h = Math.max(1, Math.round(h * sigdir));
                const adres = this.servis + '/export?' + new URLSearchParams({
                    bbox: [extent.xmin, extent.ymin, extent.xmax, extent.ymax].map(function (v) { return v.toFixed(2); }).join(','),
                    bboxSR: '3857',
                    imageSR: '3857',
                    size: w + ',' + h,
                    dpi: String(Math.max(96, Math.round(96 * sigdir))),
                    format: 'png32',
                    transparent: 'true',
                    layers: 'show:' + this.katmanNo,
                    f: 'image',
                }).toString();
                const katman = this;
                return kareYukle(adres, null).then(function (img) {
                    katman._son = img;
                    return img;
                }).catch(function (hata) {
                    if (hata && hata.name === 'AbortError') throw hata;
                    return katman._son || bosKare();
                });
            },
        });

        function abbDinamik(baslik, url, katmanId, secenek) {
            return new AbbExportKatman({
                title: baslik,
                servis: url,
                katmanNo: String(katmanId),
                opacity: secenek.opacity,
                minScale: secenek.minScale || 0,
                visible: false,
            });
        }

        const belediyeKatman = abbDinamik('ABB · Belediye Taşınmazları', BASKENTCBS + '/tasinmaz/tasinmazservice/MapServer', 0, { opacity: 0.80 });
        const abbIlceKatman = abbDinamik('ABB · İlçe Sınırı', ADRES_SERVIS, 4, { opacity: 0.80 });
        const abbMahalleKatman = abbDinamik('ABB · Mahalle Sınırı', ADRES_SERVIS, 3, { opacity: 0.80 });
        const abbYapiKatman = abbDinamik('ABB · Yapı', ADRES_SERVIS, 2, { opacity: 0.85, minScale: 4550 });
        const abbNumaratajKatman = abbDinamik('ABB · Numarataj', ADRES_SERVIS, 0, { opacity: 0.90, minScale: 1500 });

        const parselKatman = new GraphicsLayer({ title: 'Taşınmaz Parselleri', opacity: 1 });
        const emsalKatman = new GraphicsLayer({ title: 'Emsaller', opacity: 0.70, visible: true });
        const sorguKatman = new GraphicsLayer({ title: 'Sorgu Sonucu' });

        /* ---- Kümeleme nerede yapılır? ----
         *
         * Bir ara istemci tarafı denendi: tüm kayıtların merkez noktası bir kez
         * indirilip ArcGIS featureReduction ile yakınlığa göre kümeleniyordu.
         * Gezinme sunucuya hiç gitmiyordu ama açılış bedeli kabul edilemezdi —
         * 100 bin kayıtta ölçülen: 2,5 MB indirme (localhost'ta 13 ms, sorun
         * değil), 100 bin Graphic nesnesi 4,6 sn, applyEdits 4,7 sn. Yani ana
         * iş parçacığı ~9,4 sn kilitli kalıyor ve harita 15 sn sonra açılıyordu.
         * Düz nesne + FeatureLayer(source) da denendi: 0,2 sn + 3,9 sn, hâlâ
         * çok yavaş.
         *
         * Kümeleme bu yüzden sunucuda kalıyor: görünen alan için ızgara
         * toplaması her zoom'da 200-320 ms sürüyor ve yanıt birkaç yüz öğe.
         * Kayıt sayısı artsa da bu değişmez; istemci yükü sabit.
         */

        /* Rozet görünümü.
         *
         * Önceki sürüm tek bir simple-marker kullanıyordu: opak amber disk, ince
         * beyaz kontur, 24-54px arası sürekli ölçek. Ülke görünümünde 396 ile
         * 3,6B neredeyse aynı büyüklükte okunuyor, diskler üst üste binip tek bir
         * turuncu lekeye dönüşüyor ve opak dolgu altlığı tamamen kapatıyordu.
         *
         * Yerine kademeli rozet geldi. Üç şey birlikte değişir — çap, dolgu tonu
         * (amber → kiremit) ve hale halkasının kalınlığı — böylece yoğunluk küçük
         * bir ekran payında bile okunur. CIM kullanılır çünkü simple-marker tek
         * kontur verir; buradaki katmanlama (saydam hale halkası + koyu kasa +
         * beyaz halka + yarı saydam dolgu) tek grafikte ancak CIMPointSymbol ile
         * kurulabilir. Dolgu yarı saydamdır: üst üste binen kümeler koyulaşarak
         * yoğunluğun kendisini de gösterir.
         */
        // CIM çokgeni daire primitifi tanımaz; halka noktadan üretilir.
        function cemberHalka(yaricap, tersYon) {
            const n = 48;
            const halka = [];
            for (let i = 0; i <= n; i++) {
                const a = (tersYon ? -i : i) / n * Math.PI * 2;
                halka.push([
                    Number((Math.cos(a) * yaricap).toFixed(4)),
                    Number((Math.sin(a) * yaricap).toFixed(4)),
                ]);
            }
            return halka;
        }

        // Tek bir CIMVectorMarker katmanı. Çerçeve -5..5 olduğu için içerideki
        // kontur kalınlıkları da çerçeve birimindedir: boy ile orantılı büyürler.
        function cimDaire(boyPt, yaricap, katmanlar, delik) {
            const halkalar = [cemberHalka(yaricap)];
            if (delik) halkalar.push(cemberHalka(delik, true));
            return {
                type: 'CIMVectorMarker',
                enable: true,
                size: boyPt,
                frame: { xmin: -5, ymin: -5, xmax: 5, ymax: 5 },
                markerGraphics: [{
                    type: 'CIMMarkerGraphic',
                    geometry: { rings: halkalar },
                    symbol: { type: 'CIMPolygonSymbol', symbolLayers: katmanlar },
                }],
            };
        }

        // capPx: rozetin dış çapı. CIM boyutları PUNTO cinsindendir (px * 0.75).
        function kumeSembolu(capPx, dolgu, hale) {
            const pt = capPx * 0.75;
            return {
                type: 'cim',
                data: {
                    type: 'CIMSymbolReference',
                    symbol: {
                        type: 'CIMPointSymbol',
                        // Katmanlar üstten alta çizilir: ilk eleman en üstte kalır.
                        symbolLayers: [
                            // Rozet: beyaz halka her zeminde ayırır, altındaki koyu
                            // kasa uydu altlığının alacasında kenarı korur.
                            cimDaire(pt, 4.4, [
                                { type: 'CIMSolidStroke', enable: true, width: 0.62, color: [255, 255, 255, 248] },
                                { type: 'CIMSolidStroke', enable: true, width: 1.3, color: [8, 13, 23, 115] },
                                { type: 'CIMSolidFill', enable: true, color: dolgu },
                            ]),
                            // Hale halkası: rozetin dışında duran saydam bir çember.
                            // Dolu disk yerine halka, çünkü altlık görünür kalmalı.
                            cimDaire(pt * 1.18, 5, [
                                { type: 'CIMSolidFill', enable: true, color: hale },
                            ], 4.25),
                        ],
                    },
                },
            };
        }

        /* Rozetin iki değişkeni var: TON ve ÇAP.
         *
         * Ton kademeli (amber → kiremit), çap sürekli. İkisi de MUTLAK: aynı
         * adet her görünümde aynı rozeti verir. Eskiden çap ekrandaki en
         * kalabalık kümeye göre normalize ediliyordu, bu yüzden aynı küme bir
         * kaydırmadan sonra büyüyüp küçülüyor, iki görünüm karşılaştırılamıyordu.
         *
         * Çap logaritmik: ızgara kümelemesinde aynı zoom'daki hücreler benzer
         * adetler taşır (2-10 ya da 5.000-11.000 gibi). Doğrusal ölçekte bu
         * farklar görünmez, log ölçekte okunur.
         */
        const KUME_KADEME = [
            { alt: 2,    dolgu: [250, 190, 84, 205], hale: [252, 206, 130, 46] },
            { alt: 10,   dolgu: [246, 163, 32, 207], hale: [250, 186, 92, 48] },
            { alt: 50,   dolgu: [240, 134, 24, 209], hale: [247, 166, 74, 50] },
            { alt: 200,  dolgu: [232, 106, 24, 211], hale: [243, 146, 68, 52] },
            { alt: 1000, dolgu: [214, 80, 28, 214],  hale: [237, 124, 64, 54] },
            { alt: 5000, dolgu: [189, 60, 34, 217],  hale: [228, 104, 62, 56] },
        ];

        const KUME_CAP_MIN = 28;
        const KUME_CAP_MAX = 72;
        const KUME_UST = 20000;

        function kumeKademesi(adet) {
            let secilen = KUME_KADEME[0];
            for (let i = 0; i < KUME_KADEME.length; i++) {
                if (adet >= KUME_KADEME[i].alt) secilen = KUME_KADEME[i];
            }
            return secilen;
        }

        const KUME_LOG_ALT = Math.log(2);
        const KUME_LOG_ARA = Math.log(KUME_UST) - KUME_LOG_ALT;

        function kumeCapi(adet) {
            const t = Math.min(1, Math.max(0, (Math.log(Math.max(2, adet)) - KUME_LOG_ALT) / KUME_LOG_ARA));
            // 2px kademeleri: sembol önbelleği birkaç düzine girdide kalır.
            return Math.round((KUME_CAP_MIN + (KUME_CAP_MAX - KUME_CAP_MIN) * t) / 2) * 2;
        }

        /* Ekranda yüzlerce küme olabiliyor; her biri için CIM yeniden üretmek
         * boşa iş. Çap 2px'e yuvarlandığı için önbellek küçük kalır. */
        const kumeSembolOnbellek = new Map();

        function kumeGorunumu(adet) {
            const cap = kumeCapi(adet);
            const kademe = kumeKademesi(adet);
            const anahtar = cap + ':' + kademe.alt;
            let sembol = kumeSembolOnbellek.get(anahtar);
            if (!sembol) {
                sembol = kumeSembolu(cap, kademe.dolgu, kademe.hale);
                kumeSembolOnbellek.set(anahtar, sembol);
            }
            return { sembol: sembol, cap: cap };
        }


        const harita = new EsriMap({
            basemap: altlikBasemap(aktifAltlikId),
            layers: [
                imarKatman,
                parselasyonKatman,
                belediyeKatman,
                abbIlceKatman,
                abbMahalleKatman,
                abbYapiKatman,
                abbNumaratajKatman,
                parselKatman,
                emsalKatman,
                sorguKatman,
            ],
        });

        // Adres parametreleri: ?merkez=enlem,boylam&zoom=Z → harita doğrudan o görünümle açılır
        const ADRES = new URLSearchParams(location.search);
        const ADRES_MERKEZ = (ADRES.get('merkez') || '').split(',').map(Number);
        const ADRES_KONUM = ADRES_MERKEZ.length === 2 && ADRES_MERKEZ.every(Number.isFinite);

        const view = gorunum.view = new MapView({
            container: 'hrm-map',
            map: harita,
            center: ADRES_KONUM ? [ADRES_MERKEZ[1], ADRES_MERKEZ[0]] : [32.85, 39.92],
            zoom: ADRES_KONUM ? (Number(ADRES.get('zoom')) || 17) : 12,
            constraints: { rotationEnabled: false },
            ui: { components: [] },
            popup: {
                dockEnabled: false,
                collapseEnabled: false,
                defaultPopupTemplateEnabled: false,
                autoOpenEnabled: true,
                // Esri'nin varsayılan "Yakınlaştır" aksiyon çubuğu ve gezinme başlığı gizlenir
                visibleElements: {
                    actionBar: false,
                    collapseButton: false,
                    featureNavigation: false,
                    spinner: false,
                },
            },
        });

        // Rozetin üzerine gelince vurgulamak için parsel katmanının görünümü.
        let parselLV = null;
        view.whenLayerView(parselKatman).then(function (lv) { parselLV = lv; }).catch(function () {});

        // Katman kapanınca ipucu balonu da kapanır; yoksa harita imlecin
        // altından kayıp gidince balon ekranda asılı kalıyordu.
        reactiveUtils.watch(function () { return parselKatman.visible; }, function (acik) {
            if (!acik) ipucuGizle();
        });

        /* =========================================================
         * 3) SEMBOLLER
         * ========================================================= */
        // Uydu altlığında ince renkli çizgi kayboluyor — kira katmanı bunu beyaz
        // konturla çözüyor (bkz. kiraDolgu). Taşınmazda amber imza rengi
        // korunsun diye casing kullanılır: altta kalın koyu hat, üstünde ince
        // parlak amber. Koyu hat sınırı her zeminden (uydu, yol, arazi) ayırır,
        // amber üstte kaldığı için katmanın kimliği durur.
        //
        // CIMSymbol tek grafikte çok katmanlı kontur verir; simple-fill'de tek
        // outline olduğu için aynı etki parsel başına ikinci bir grafik
        // gerektirirdi (1500 parselde 3000 grafik). CIM renkleri 0-255, alfa dahil.
        function cimKasali(dolgu, cizgi, kalinlik) {
            return {
                type: 'cim',
                data: {
                    type: 'CIMSymbolReference',
                    symbol: {
                        type: 'CIMPolygonSymbol',
                        // Katmanlar üstten alta çizilir: ilk eleman en üstte kalır.
                        symbolLayers: [
                            { type: 'CIMSolidStroke', enable: true, width: kalinlik === undefined ? 1.3 : kalinlik, color: cizgi },
                            { type: 'CIMSolidStroke', enable: true, width: 3.6, color: [8, 13, 23, 205] },
                            { type: 'CIMSolidFill', enable: true, color: dolgu },
                        ],
                    },
                },
            };
        }

        /* Parsel rengi: camgöbeği.
         *
         * Amberdi ve iki kez birden karışıyordu. Birincisi zeminle: uydu
         * altlığında Anadolu'nun toprak tonları (kahve, tan, hardal) amber
         * ailesinde, parsel tarlanın üstünde seçilmiyordu. İkincisi kendi
         * haritamızla: küme rozetleri de amber, "bu bir küme mi yoksa parsel
         * mi" ayrımı kayboluyordu.
         *
         * Camgöbeği toprak tonlarının tam karşısında durur ve uydu, yol
         * haritası, OSM — hepsinde ayrışır. Harita artık iki renkle konuşuyor:
         * AMBER = küme (yığın), CAMGÖBEĞİ = parselin kendisi.
         *
         * Şekli yerden ayıran beyaz anahtar çizgi ve altındaki koyu kasa kaldı:
         * beyaz hem koyu şehir dokusunda hem açık tarlada ayırır, koyu kasa da
         * beyazın parlak zeminlerde (beton, çatı, sera) kaybolmasını engeller.
         */
        const PARSEL_RENK = [34, 211, 238];
        const SEM_PARSEL = cimKasali(PARSEL_RENK.concat(118), [255, 255, 255, 245], 2.2);
        const SEM_NOKTA  = { type: 'simple-marker', style: 'circle', size: 11, color: PARSEL_RENK.concat(0.95), outline: { color: [255, 255, 255, 0.95], width: 2 } };

        /* Küçük parsel için damla iğne.
         *
         * Kümeleme çözülüp gerçek geometriye geçildiğinde bile parsel çoğu
         * zaman görünmüyordu: ilçe-mahalle arası zoom'da 300 m²'lik bir parsel
         * ekranda birkaç piksellik leke. "6 parsel" yazıyor ama gözle bulmak
         * mümkün değil. Şekil okunacak kadar büyük değilse üstüne iğne konur;
         * iğnenin ucu parselin merkezine oturur, gövdesi yukarıda kalır, yani
         * şekli kapatmaz, gösterir.
         */
        const SEM_IGNE = {
            type: 'simple-marker',
            style: 'path',
            // Yalnızca mutlak komut: Esri'nin yol çözümleyicisi göreli
            // komutlarda (c, s) sessizce hiçbir şey çizmiyor.
            path: 'M 16 30 C 14.6 24.5 5 17.4 5 11.6 C 5 5.7 9.9 1 16 1 C 22.1 1 27 5.7 27 11.6 C 27 17.4 17.4 24.5 16 30 Z',
            size: 24,
            color: PARSEL_RENK.concat(0.97),
            outline: { color: [255, 255, 255, 0.97], width: 1.8 },
            // Yol çizimde merkezde; ucu noktaya otursun diye yarı boy yukarı.
            yoffset: 12,
        };

        // Şekil ekranda bundan küçükse iğne konur.
        const PARSEL_IGNE_PX = 46;

        /* Geometrinin ekranda kapladığı en büyük kenar (piksel).
         *
         * Kapsam view.resolution ile karşılaştırılacağı için ikisi aynı
         * birimde olmalı. GeoJSON WGS84 geliyor ama grafiğe dönüşen geometri
         * görünümün uzamsal referansını (Web Mercator) alıyor; koşulsuz
         * çevirmek ölçüyü 111.319 katına çıkarıyordu — 7 piksellik parsel
         * 830.000 piksel görünüyor, iğne hiç konmuyordu. */
        function ekranBoyu(geom) {
            const e = geom && geom.extent;
            if (!e || !view.resolution) return 0;
            const sr = e.spatialReference;
            const olculen = sr && sr.isGeographic ? webMercatorUtils.geographicToWebMercator(e) : e;
            if (!olculen) return 0;
            return Math.max(olculen.width, olculen.height) / view.resolution;
        }

        /* ---- Değerleme kipi ----
         * Aynı modül iki haritayı çalıştırır. Altlıklar, ABB katmanları,
         * TKGM/e-İmar sorguları ve panel davranışı ortaktır; ayrışan yerler
         * yalnızca veri katmanı, sembol rengi ve parsel popup'ının içeriğidir.
         */
        const MOD = API.mod || 'tasinmaz';
        const DEG = API.degerleme || {};
        const KIRA = API.kira || {};
        const EMSAL = API.emsal || null;
        const IMAR_RENK = DEG.imarRenk || KIRA.imarRenk || {};
        const KIRA_DURUM_RENK = { dolu: '#dc2626', bos: '#16a34a' };
        // Kira sorgusu: listeyle aynı süzgeç harita katmanına da gider.
        // kiraTumunuYukle bir kerelik "seçilenlerin tümünü yükle" bayrağıdır.
        let kiraEkSorgu = '';
        let kiraTumunuYukle = false;
        const IMAR_YOK_RENK = '#94a3b8';
        const DEG_TUR_RENK = { arsa: '#0d9488', bb: '#7c3aed', ikisi: '#f59e0b' };

        // '#rrggbb' / 'hsl(...)' → Esri'nin kabul ettiği renk dizesi
        function imarRengi(id) {
            return (id === null || id === undefined) ? IMAR_YOK_RENK : (IMAR_RENK[id] || IMAR_YOK_RENK);
        }

        function semDoldur(renk, opaklik) {
            return { type: 'simple-fill', color: renkAlfa(renk, opaklik === undefined ? 0.3 : opaklik), outline: { color: renk, width: 1.8 } };
        }

        // Uydu altlığında ince renkli çizgi kayboluyor. Beyaz kontur şekli
        // yerden ayırır, dolgu rengi (dolu/boş) içeride kalır.
        function kiraDolgu(renk) {
            return {
                type: 'simple-fill',
                color: renkAlfa(renk, 0.62),
                outline: { color: [255, 255, 255, 1], width: 3 },
            };
        }

        function semNokta(renk) {
            return { type: 'simple-marker', style: 'circle', size: 11, color: renk, outline: { color: [255, 255, 255, 0.95], width: 1.6 } };
        }

        // Esri renk dizesine alfa katmak için rgba'ya çevrilir
        function renkAlfa(renk, alfa) {
            const el = document.createElement('span');
            el.style.color = renk;
            document.body.appendChild(el);
            const hesap = getComputedStyle(el).color; // rgb(r, g, b)
            el.remove();
            const p = hesap.match(/\d+/g) || [148, 163, 184];
            return [Number(p[0]), Number(p[1]), Number(p[2]), alfa];
        }
        const SEM_TKGM   = { type: 'simple-fill', color: [37, 99, 235, 0.18], outline: { color: [37, 99, 235, 0.95], width: 2 } };
        const SEM_EIMAR  = { type: 'simple-fill', color: [236, 72, 153, 0.14], outline: { color: [236, 72, 153, 0.90], width: 1.8, style: 'dash' } };
        const SEM_VURGU  = { type: 'simple-fill', color: [22, 163, 74, 0.20], outline: { color: [22, 163, 74, 0.95], width: 2.4 } };

        /* =========================================================
         * 4) YARDIMCILAR
         * ========================================================= */
        const WGS84 = { wkid: 4326 };

        // GeoJSON dış halkaları saatin tersine, ArcGIS saat yönünde bekler.
        // Ters halka delik sayılır ve hiç boyanmaz. Küçük kira sınırları
        // 4326 üzerinde sadeleştirilince de kaybolabildiği için önce yön
        // düzeltilir, sonra Web Mercator'da sadeleştirilir.
        function imzaliAlan(halka) {
            let alan = 0;
            for (let i = 0; i < halka.length - 1; i++) {
                alan += halka[i][0] * halka[i + 1][1] - halka[i + 1][0] * halka[i][1];
            }
            return alan;
        }

        function esriHalkalari(parcalar) {
            const halkalar = [];
            (parcalar || []).forEach(function (parca) {
                (parca || []).forEach(function (halka, indeks) {
                    if (!halka || halka.length < 4) return;
                    const saatYonunde = imzaliAlan(halka) < 0;
                    const disHalka = indeks === 0;
                    halkalar.push(saatYonunde === disHalka ? halka : halka.slice().reverse());
                });
            });
            return halkalar;
        }

        function halkalariDuzelt(polygon) {
            try {
                const mercator = polygon.spatialReference && polygon.spatialReference.isWGS84
                    ? webMercatorUtils.geographicToWebMercator(polygon)
                    : polygon;
                if (!mercator || !mercator.rings || !mercator.rings.length) return polygon;
                const sade = geometryEngine.simplify(mercator);
                if (sade && sade.rings && sade.rings.length) return sade;
                return mercator;
            } catch (e) {
                return polygon;
            }
        }

        function geoJsonGeometri(g) {
            if (!g || !g.type || !g.coordinates) return null;
            if (g.type === 'Point') {
                return new Point({ longitude: +g.coordinates[0], latitude: +g.coordinates[1], spatialReference: WGS84 });
            }
            if (g.type === 'Polygon') {
                const halkalar = esriHalkalari([g.coordinates]);
                return halkalar.length ? halkalariDuzelt(new Polygon({ rings: halkalar, spatialReference: WGS84 })) : null;
            }
            if (g.type === 'MultiPolygon') {
                const halkalar = esriHalkalari(g.coordinates);
                return halkalar.length ? halkalariDuzelt(new Polygon({ rings: halkalar, spatialReference: WGS84 })) : null;
            }
            if (g.type === 'LineString') {
                return halkalariDuzelt(new Polygon({ rings: [g.coordinates], spatialReference: WGS84 }));
            }
            return null;
        }

        function geometriExtent(geom) {
            if (!geom) return null;
            if (geom.type === 'point') {
                const d = 0.0009; // ~100 m
                return new Extent({
                    xmin: geom.longitude - d, ymin: geom.latitude - d,
                    xmax: geom.longitude + d, ymax: geom.latitude + d,
                    spatialReference: WGS84,
                });
            }
            return geom.extent ? geom.extent.clone() : null;
        }

        function kacis(deger) {
            if (deger === null || deger === undefined) return '';
            return String(deger).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        // Sistem verisi: ham ondalık sayı ("2522.11") → tr-TR biçimi
        function sayiBicim(deger, birim) {
            const n = parseFloat(deger);
            if (!Number.isFinite(n)) return null;
            return n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + (birim || '');
        }

        // TKGM alanı zaten tr-TR biçimli metindir ("2.522,11") — parseFloat bozar, olduğu gibi geçilir.
        function tkgmAlan(deger) {
            if (deger === null || deger === undefined) return null;
            const s = String(deger).trim();
            return s === '' ? null : s + ' m²';
        }

        /* ---- Popup yapı taşları (hrm-pop-* — kurumsal palet) ---- */

        function doluCiftler(ciftler) {
            return ciftler.filter(function (c) {
                return c[1] !== null && c[1] !== undefined && String(c[1]).trim() !== '';
            });
        }

        // Etiket/değer listesi
        function popListe(ciftler) {
            const dolu = doluCiftler(ciftler);
            if (!dolu.length) return '<p class="hrm-pop-bos">Öznitelik bulunamadı</p>';
            return '<dl class="hrm-pop-liste">' + dolu.map(function (c) {
                return '<div class="hrm-pop-satir"><dt>' + kacis(c[0]) + '</dt><dd>' + kacis(c[1]) + '</dd></div>';
            }).join('') + '</dl>';
        }

        // Üst şerit: kaynak rozeti + konum
        function popUst(kaynak, sinif, yer) {
            return '<div class="hrm-pop-ust">' +
                '<span class="hrm-pop-kaynak ' + sinif + '">' + kacis(kaynak) + '</span>' +
                (yer ? '<span class="hrm-pop-yer">' + kacis(yer) + '</span>' : '') +
            '</div>';
        }

        // Ada / Parsel / Alan vurgu bloğu
        function popHero(ada, parsel, alan) {
            if (!ada && !parsel && !alan) return '';
            const kutu = function (etiket, deger, ekSinif) {
                if (deger === null || deger === undefined || String(deger).trim() === '') return '';
                return '<div class="hrm-pop-hero-kutu' + (ekSinif || '') + '">' +
                    '<span class="hrm-pop-hero-etiket">' + kacis(etiket) + '</span>' +
                    '<strong class="hrm-pop-hero-deger">' + kacis(deger) + '</strong>' +
                '</div>';
            };
            return '<div class="hrm-pop-hero">' +
                kutu('Ada', ada) +
                kutu('Parsel', parsel) +
                kutu('Alan', alan, ' is-genis') +
            '</div>';
        }

        // Alt bölüm başlığı (e-İmar gibi ikincil bloklar için)
        function popBolumBaslik(metin, sinif) {
            return '<div class="hrm-pop-bolum-baslik ' + (sinif || '') + '">' + kacis(metin) + '</div>';
        }

        function popYukleniyor(metin) {
            return '<div class="hrm-pop-yukleniyor"><span class="hrm-pop-spin"></span>' + kacis(metin) + '</div>';
        }

        function popUyari(metin) {
            return '<div class="hrm-pop-uyari">' + kacis(metin) + '</div>';
        }

        async function jsonGetir(url) {
            const yanit = await fetch(url, { headers: { Accept: 'application/json' } });
            let govde = null;
            try { govde = await yanit.json(); } catch (e) { govde = null; }
            if (!yanit.ok) {
                throw new Error((govde && (govde.hata || govde.message)) || ('HTTP ' + yanit.status));
            }
            return govde;
        }

        function popupAc(konum, baslik, icerik) {
            const arg = { location: konum, title: baslik, content: icerik };
            if (typeof view.openPopup === 'function') view.openPopup(arg);
            else view.popup.open(arg);
        }

        /* =========================================================
         * 5) ALTLIK SEÇİM IZGARASI
         * ========================================================= */
        const altlikGrid = document.getElementById('hrm-altlik-grid');
        altlikTanim.forEach(function (tanim) {
            const dugme = document.createElement('button');
            dugme.type = 'button';
            dugme.className = 'hrt-layer-tile' + (tanim.id === aktifAltlikId ? ' is-aktif' : '');
            dugme.dataset.katman = tanim.id;
            dugme.innerHTML =
                '<span class="hrt-layer-thumb" style="background-image:url(\'' + tanim.preview + '\')"></span>' +
                '<span class="hrt-layer-meta">' +
                    '<span class="hrt-layer-ad">' + kacis(tanim.ad) + '</span>' +
                    '<span class="hrt-layer-kaynak">' + kacis(tanim.ozet) + '</span>' +
                '</span>';
            dugme.addEventListener('click', function () { altlikDegistir(tanim.id); });
            altlikGrid.appendChild(dugme);
        });

        function altlikDegistir(id) {
            if (id === aktifAltlikId) return;
            harita.basemap = altlikBasemap(id);
            aktifAltlikId = id;
            localStorage.setItem('etys-harita-altlik', id);
            altlikGrid.querySelectorAll('.hrt-layer-tile').forEach(function (t) {
                t.classList.toggle('is-aktif', t.dataset.katman === id);
            });
        }

        /* =========================================================
         * 6) VERİ KATMANI AÇ/KAPAT + OPAKLIK
         *    - localStorage'da görünürlük ve opaklık kalıcı
         *    - Grup master switch + aktif sayaç
         *    - Katman arama kutusu + "Hepsini Kapat"
         *    - Opaklık "varsayılana döndür"
         * ========================================================= */
        // Üç harita da aynı katman id'lerini (hrm-ov-parsel vb.) kullanıyor;
        // anahtar kipe göre ayrılmazsa birinde kapatılan katman diğerinde de
        // kapalı açılıyordu.
        const KATMAN_ONBELLEK_ANAHTAR = 'etys-harita-katman-v1-' + MOD;
        function katmanTercihOku() {
            try { return JSON.parse(localStorage.getItem(KATMAN_ONBELLEK_ANAHTAR)) || {}; }
            catch (e) { return {}; }
        }
        function katmanTercihYaz(obj) {
            try { localStorage.setItem(KATMAN_ONBELLEK_ANAHTAR, JSON.stringify(obj)); }
            catch (e) { /* sessiz */ }
        }
        const katmanTercih = katmanTercihOku();

        // katmanKayit: kart id → { kutu, kaydirici, kart, katman, deger, grup }
        const katmanKayit = {};

        function grupDurumGuncelle(grup) {
            const kartlar = Object.values(katmanKayit).filter(function (k) { return k.grup === grup; });
            if (!kartlar.length) return;
            const acik = kartlar.filter(function (k) { return k.kutu.checked; }).length;

            const sayacEl = document.querySelector('[data-grup-sayac="' + grup + '"]');
            if (sayacEl) {
                sayacEl.textContent = acik + '/' + kartlar.length;
                sayacEl.classList.toggle('is-aktif', acik > 0);
                sayacEl.classList.toggle('is-tam', acik === kartlar.length && acik > 0);
            }

            const master = document.querySelector('.hrm-grup-master[data-grup="' + grup + '"]');
            if (master) {
                master.checked = acik > 0;
                master.indeterminate = acik > 0 && acik < kartlar.length;
            }
        }

        function katmanRangeArkaplan(kaydirici) {
            const v = parseInt(kaydirici.value, 10);
            kaydirici.style.setProperty('--val', v + '%');
        }

        function katmanKontrolKur(kutuId, kaydiriciId, katman) {
            const kutu = document.getElementById(kutuId);
            const kaydirici = document.getElementById(kaydiriciId);
            const kart = kutu ? kutu.closest('.hrm-katman-kart') : null;
            const kartId = kart ? kart.dataset.katmanKart : null;
            const grup = kart ? kart.dataset.grup : null;
            const deger = document.querySelector('.hrm-opaklik-deger[data-hedef="' + kaydiriciId + '"]');
            const sifirla = document.querySelector('.hrm-opaklik-sifirla[data-hedef="' + kaydiriciId + '"]');

            // Kayıtlı tercihi uygula (yalnızca ilk kurulumda)
            if (kartId && katmanTercih[kartId]) {
                const t = katmanTercih[kartId];
                if (kutu && typeof t.aktif === 'boolean') kutu.checked = t.aktif;
                if (kaydirici && typeof t.opaklik === 'number' && t.opaklik >= 10 && t.opaklik <= 100) {
                    kaydirici.value = String(t.opaklik);
                }
            }

            if (kutu) {
                katman.visible = kutu.checked;
                if (kart) kart.dataset.aktif = kutu.checked ? '1' : '0';
                kutu.addEventListener('change', function () {
                    katman.visible = kutu.checked;
                    if (kart) kart.dataset.aktif = kutu.checked ? '1' : '0';
                    if (kartId) {
                        katmanTercih[kartId] = Object.assign({}, katmanTercih[kartId], { aktif: kutu.checked });
                        katmanTercihYaz(katmanTercih);
                    }
                    if (grup) grupDurumGuncelle(grup);
                });
            }
            if (kaydirici) {
                katman.opacity = kaydirici.value / 100;
                if (deger) deger.textContent = kaydirici.value + '%';
                katmanRangeArkaplan(kaydirici);
                kaydirici.addEventListener('input', function () {
                    katman.opacity = kaydirici.value / 100;
                    if (deger) deger.textContent = kaydirici.value + '%';
                    katmanRangeArkaplan(kaydirici);
                    if (kartId) {
                        katmanTercih[kartId] = Object.assign({}, katmanTercih[kartId], { opaklik: parseInt(kaydirici.value, 10) });
                        katmanTercihYaz(katmanTercih);
                    }
                });
            }
            if (sifirla && kaydirici) {
                sifirla.addEventListener('click', function () {
                    const varsayilan = parseInt(sifirla.dataset.varsayilan || '100', 10);
                    kaydirici.value = String(varsayilan);
                    kaydirici.dispatchEvent(new Event('input', { bubbles: true }));
                });
            }

            if (kartId) {
                katmanKayit[kartId] = { kutu: kutu, kaydirici: kaydirici, kart: kart, katman: katman, deger: deger, grup: grup };
            }
        }

        katmanKontrolKur('hrm-ov-parsel', 'hrm-ov-parsel-op', parselKatman);
        katmanKontrolKur('hrm-ov-emsal', 'hrm-ov-emsal-op', emsalKatman);
        katmanKontrolKur('hrm-ov-imar', 'hrm-ov-imar-op', imarKatman);
        katmanKontrolKur('hrm-ov-parselasyon', 'hrm-ov-parselasyon-op', parselasyonKatman);
        katmanKontrolKur('hrm-ov-belediye', 'hrm-ov-belediye-op', belediyeKatman);
        katmanKontrolKur('hrm-ov-abb-ilce', 'hrm-ov-abb-ilce-op', abbIlceKatman);
        katmanKontrolKur('hrm-ov-abb-mahalle', 'hrm-ov-abb-mahalle-op', abbMahalleKatman);
        katmanKontrolKur('hrm-ov-abb-yapi', 'hrm-ov-abb-yapi-op', abbYapiKatman);
        katmanKontrolKur('hrm-ov-abb-numarataj', 'hrm-ov-abb-numarataj-op', abbNumaratajKatman);

        /* --- Grup master switch --- */
        document.querySelectorAll('.hrm-grup-master').forEach(function (master) {
            // Summary'nin details toggle davranışını tetiklememeli
            const kabuk = master.closest('.hrm-grup-master-wrap');
            if (kabuk) {
                ['click', 'mousedown', 'pointerdown', 'keydown'].forEach(function (ev) {
                    kabuk.addEventListener(ev, function (e) { e.stopPropagation(); });
                });
            }
            master.addEventListener('change', function () {
                const grup = master.dataset.grup;
                const acilacak = master.checked;
                Object.values(katmanKayit)
                    .filter(function (k) { return k.grup === grup; })
                    .forEach(function (k) {
                        if (!k.kutu || k.kutu.checked === acilacak) return;
                        k.kutu.checked = acilacak;
                        k.kutu.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                grupDurumGuncelle(grup);
            });
        });

        // İlk sayaç durumunu bas
        ['sistem', 'abb-imar', 'abb-detay'].forEach(grupDurumGuncelle);

        /* --- "Hepsini Kapat" hızlı eylemi --- */
        const hepsiKapaBtn = document.getElementById('hrm-katman-hepsi-kapa');
        if (hepsiKapaBtn) {
            hepsiKapaBtn.addEventListener('click', function () {
                Object.values(katmanKayit).forEach(function (k) {
                    if (!k.kutu || !k.kutu.checked) return;
                    k.kutu.checked = false;
                    k.kutu.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
        }

        /* --- Katman arama filtresi --- */
        const araInput = document.getElementById('hrm-katman-ara');
        const araTemizle = document.getElementById('hrm-katman-ara-temizle');
        const araBosEl = document.getElementById('hrm-katman-bos');

        function turkceKucult(s) {
            return String(s || '')
                .toLocaleLowerCase('tr-TR')
                .replace(/ı/g, 'i').replace(/ş/g, 's').replace(/ç/g, 'c')
                .replace(/ğ/g, 'g').replace(/ü/g, 'u').replace(/ö/g, 'o');
        }

        function katmanFiltrele() {
            const q = araInput ? turkceKucult(araInput.value.trim()) : '';
            const filtreli = !!q;
            if (araTemizle) araTemizle.hidden = !filtreli;

            let toplamGorunur = 0;
            document.querySelectorAll('.hrm-grup').forEach(function (grup) {
                let grupGorunur = 0;
                grup.querySelectorAll('.hrm-katman-kart').forEach(function (kart) {
                    const ad = turkceKucult(kart.dataset.ad || kart.textContent);
                    const eslesir = !filtreli || ad.indexOf(q) !== -1;
                    kart.hidden = !eslesir;
                    if (eslesir) grupGorunur++;
                });
                grup.hidden = filtreli && grupGorunur === 0;
                if (filtreli && grupGorunur > 0 && !grup.open) grup.open = true;
                toplamGorunur += grupGorunur;
            });

            if (araBosEl) araBosEl.hidden = !(filtreli && toplamGorunur === 0);
        }

        if (araInput) {
            araInput.addEventListener('input', katmanFiltrele);
            araInput.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && araInput.value) {
                    araInput.value = '';
                    katmanFiltrele();
                }
            });
        }
        if (araTemizle) {
            araTemizle.addEventListener('click', function () {
                if (!araInput) return;
                araInput.value = '';
                katmanFiltrele();
                araInput.focus();
            });
        }

        /* =========================================================
         * 7) SİSTEM PARSELLERİ (GeoJSON)
         * ========================================================= */
        const sayiEl = document.getElementById('hrm-sayi');
        let tumExtent = null;

        function tasinmazPopup(p) {
            const kap = document.createElement('div');
            kap.className = 'hrm-pop';
            kap.innerHTML =
                popUst('Sistem Kaydı', 'is-sistem', [p.il, p.ilce, p.mahalle].filter(Boolean).join(' · ')) +
                popHero(p.ada, p.parsel, sayiBicim(p.alan, ' m²')) +
                popListe([
                    ['Nitelik', p.nitelik],
                    ['İmar Durumu', p.imar],
                    ['Mahalle TKGM No', p.mahalle_tkgm_id],
                ]) +
                (p.duzenle ? '<a class="hrm-pop-eylem" href="' + kacis(p.duzenle) + '">Kaydı Düzenle</a>' : '') +
                '<div class="hrm-pop-dipnot">Kayıt #' + kacis(p.id) + '</div>';
            return kap;
        }

        let birimAralik = [0, 0];

        /* ---- Kira kipi ---- */
        // Renk: doluluk (varsayılan), imar durumu ya da sözleşme bitişine kalan süre
        function kiraRenk(p) {
            const secim = document.getElementById('hrm-deg-renk');
            const mod = secim ? secim.value : 'durum';
            if (mod === 'imar') return imarRengi(p.imar_id);
            if (mod === 'bitis') {
                const g = p.kalan_gun;
                if (g === null || g === undefined) return IMAR_YOK_RENK;
                if (g < 0) return '#991b1b';
                if (g <= 30) return '#dc2626';
                if (g <= 90) return '#f59e0b';
                return '#16a34a';
            }
            return KIRA_DURUM_RENK[p.dolu_bos] || IMAR_YOK_RENK;
        }

        function degerlemePopup(p) {
            const kap = document.createElement('div');
            kap.className = 'hrm-pop';
            const turAd = p.tur === 'ikisi' ? 'Arsa + Bağımsız Bölüm' : (p.tur === 'bb' ? 'Bağımsız Bölüm' : 'Arsa');
            kap.innerHTML =
                popUst('Değerleme', 'is-sistem', [p.ilce, p.mahalle].filter(Boolean).join(' · ')) +
                popHero(p.ada, p.parsel, sayiBicim(p.alan, ' m²')) +
                popListe([
                    ['İmar Durumu', p.imar],
                    ['Değerleme Türü', turAd],
                    ['Arsa Raporu', p.arsa_sayisi || null],
                    ['BB Raporu', p.bb_sayisi || null],
                    ['Son Rapor Tarihi', p.son_tarih ? new Date(p.son_tarih).toLocaleDateString('tr-TR') : null],
                    ['Son Toplam Değer', p.son_toplam ? sayiBicim(p.son_toplam, ' TL') : null],
                    ['Arsa m² Değeri', p.birim ? sayiBicim(p.birim, ' TL/m²') : null],
                ]) +
                (DEG.tasinmaz ? '<a class="hrm-pop-eylem" href="' + kacis(DEG.tasinmaz.replace(/\/harita\/tasinmaz$/, '') + '?tasinmaz_id=' + p.id) + '">Değerlemeleri Aç</a>' : '');
            return kap;
        }

        function emsalPopup(p) {
            const kap = document.createElement('div');
            kap.className = 'hrm-pop';
            const turAdlari = (p.tipler || []).map(function (t) {
                const bulunan = (EMSAL && EMSAL.turler || []).find(function (x) { return x.deger === t; });
                return bulunan ? bulunan.etiket : t;
            }).join(', ');
            kap.innerHTML =
                popUst('Emsal', 'is-tkgm', [p.ilce, p.mahalle].filter(Boolean).join(' · ')) +
                popHero(p.ada, p.parsel, sayiBicim(p.alan, ' m²')) +
                popListe([
                    ['İmar Durumu', p.imar],
                    ['Emsal Sayısı', p.adet],
                    ['Türler', turAdlari],
                    ['Son Satış', p.son_tarih ? new Date(p.son_tarih).toLocaleDateString('tr-TR') : null],
                    ['Ortalama Birim Fiyat', p.ortalama ? sayiBicim(p.ortalama, ' TL/m²') : null],
                ]) +
                '<div id="hrm-emsal-detay">' + popYukleniyor('Emsaller getiriliyor…') + '</div>';
            if (EMSAL && EMSAL.parsel) emsalDetayiDoldur(p.id);
            return kap;
        }

        // Popup açıldıktan sonra parselin emsal satırları kutuya yazılır;
        // encümen/senet evrakı varsa yeni sekmede açılacak bağlantı konur.
        async function emsalDetayiDoldur(parselId) {
            let v = null;
            try { v = await jsonGetir(EMSAL.parsel + '/' + parselId); } catch (e) { v = null; }
            const kutu = document.getElementById('hrm-emsal-detay');
            if (!kutu) return;
            if (!v) { kutu.innerHTML = popUyari('Emsal bilgisi alınamadı.'); return; }
            kutu.innerHTML = '<div class="hrm-pop-bolum">' +
                popBolumBaslik('Emsaller (' + v.emsaller.length + ')', 'is-eimar') +
                (v.emsaller.length ? v.emsaller.map(function (e) {
                    return '<div class="hrm-pop-emsal" style="--em-renk:' + kacis(e.renk) + '">' +
                        '<div class="hrm-pop-emsal-ust"><strong>' + kacis(e.tip_ad) + '</strong>' +
                            '<span>' + kacis(e.belge_no || '—') + ' · ' + (e.tarih ? new Date(e.tarih).toLocaleDateString('tr-TR') : '—') + '</span></div>' +
                        '<div class="hrm-pop-emsal-fiyat">' + (e.birim_fiyat !== null ? sayiBicim(e.birim_fiyat, ' TL/m²') : '—') + '</div>' +
                        '<div class="hrm-pop-emsal-eylem">' +
                            (e.evrak ? '<a href="' + kacis(e.evrak) + '" target="_blank" rel="noopener">Evrakı aç ↗</a>' : '<span class="hrm-pop-evraksiz">Evrak yok</span>') +
                            '<a href="' + kacis(e.duzenle) + '">Düzenle</a>' +
                        '</div>' +
                    '</div>';
                }).join('') : '<p class="hrm-pop-bos">Emsal yok</p>') +
            '</div>';
        }

        /* ---- İmar durumu süzgeci (iki katmana birden) ---- */
        // Süzgeç sunucuya gider: kümeler orada hesaplandığı için istemcide
        // gizlemek küme sayılarını yanlış bırakırdı.
        function imarParam() {
            const kutular = Array.prototype.slice.call(document.querySelectorAll('[data-imar]'));
            if (!kutular.length) return null;
            const secili = kutular.filter(function (k) { return k.checked; }).map(function (k) { return k.dataset.imar; });
            return secili.length === kutular.length ? null : secili.join(',');
        }

        function imarEkle(url) {
            const imar = imarParam();
            if (imar === null) return url;
            return url + (url.indexOf('?') === -1 ? '?' : '&') + 'imar=' + encodeURIComponent(imar);
        }

        // Seçili kayıt modunda (?ids=) hepsi bir kerede yüklenir; aksi halde
        // yalnızca ekranda görünen alan istenir, kalabalık alanlar kümelenir.
        const SECILI_MOD = API.secilenSayi !== null && API.secilenSayi !== undefined;
        let yuklemeKontrol = null;
        let yuklemeZamanlayici = null;
        // Grafiklerin hangi zoom'da çizildiği; zoom değişince bayatlarlar.
        let cizilenZoom = null;



        /* Sunucunun döndürdüğü her küme tek bir rozete ve bir sayı etiketine
         * çevrilir. Çap ve ton kademeden gelir (bkz. KUME_KADEME); eskiden
         * ekrandaki en kalabalık kümeye göre normalize ediliyordu, bu yüzden
         * aynı küme farklı görünümlerde farklı boyda çıkıyor ve karşılaştırma
         * yapılamıyordu. Kademeler artık mutlak. */
        function kumeGrafikleri(f) {
            const p = f.properties;
            const konum = new Point({ longitude: f.geometry.coordinates[0], latitude: f.geometry.coordinates[1], spatialReference: WGS84 });
            const gorunus = kumeGorunumu(p.adet);
            const ozellik = { kume: true, adet: p.adet, bbox: p.bbox };
            const yazi = p.adet >= 1000
                ? (p.adet / 1000).toLocaleString('tr-TR', { maximumFractionDigits: p.adet >= 10000 ? 0 : 1 }) + 'B'
                : String(p.adet);

            return [
                new Graphic({ geometry: konum, attributes: ozellik, symbol: gorunus.sembol }),
                new Graphic({
                    geometry: konum,
                    attributes: ozellik,
                    symbol: {
                        type: 'text', text: yazi,
                        // Koyu hale, altlıktaki yer adlarının üstünde de okunsun diye.
                        color: [255, 255, 255, 1],
                        haloColor: [7, 11, 20, 0.85],
                        haloSize: '1.4px',
                        verticalAlignment: 'middle', horizontalAlignment: 'center',
                        font: { size: Math.max(11, Math.round(gorunus.cap * 0.31)) + 'px', weight: 'bold', family: 'sans-serif' },
                    },
                }),
            ];
        }

        // Değerleme kipinde renk, katman panelindeki "Renk" seçimine göre
        // imar durumundan / türden / m² değerinden gelir.
        function degerlemeRenk(p) {
            const secim = document.getElementById('hrm-deg-renk');
            const mod = secim ? secim.value : 'imar';
            if (mod === 'tur') return DEG_TUR_RENK[p.tur] || '#f59e0b';
            if (mod === 'birim') {
                if (p.birim === null || p.birim === undefined) return IMAR_YOK_RENK;
                const t = birimAralik[1] > birimAralik[0]
                    ? (Math.log(p.birim + 1) - Math.log(birimAralik[0] + 1)) / (Math.log(birimAralik[1] + 1) - Math.log(birimAralik[0] + 1))
                    : 0.5;
                return 'hsl(' + Math.round(140 - Math.max(0, Math.min(1, t)) * 140) + ', 78%, 45%)';
            }
            return imarRengi(p.imar_id);
        }

        function parselGrafigi(f) {
            const geom = geoJsonGeometri(f.geometry);
            if (!geom) return null;
            const p = f.properties || {};
            if (MOD === 'degerleme') {
                const renk = degerlemeRenk(p);
                return new Graphic({
                    geometry: geom,
                    symbol: geom.type === 'point' ? semNokta(renk) : semDoldur(renk),
                    attributes: p,
                    popupTemplate: {
                        title: (p.ada || '—') + ' / ' + (p.parsel || '—'),
                        content: function () { return degerlemePopup(p); },
                    },
                });
            }
            if (MOD === 'kira') {
                // Kirada popup yok: tıklanınca sağdaki bilgi paneli açılır,
                // aynı sınırda birden çok kayıt varsa panelde listeden seçilir.
                const renk = kiraRenk(p);
                return new Graphic({
                    geometry: geom,
                    symbol: geom.type === 'point' ? semNokta(renk) : kiraDolgu(renk),
                    attributes: p,
                });
            }
            return new Graphic({
                geometry: geom,
                symbol: geom.type === 'point' ? SEM_NOKTA : SEM_PARSEL,
                attributes: p,
                popupTemplate: {
                    title: (p.ada || '—') + ' / ' + (p.parsel || '—'),
                    content: function () { return tasinmazPopup(p); },
                },
            });
        }

        async function parselleriYukle() {
            let url = API.geojson;
            const tumunu = SECILI_MOD || kiraTumunuYukle;
            if (!tumunu) {
                const g = webMercatorUtils.webMercatorToGeographic(view.extent);
                // Kenardaki parseller kesilmesin diye alan biraz genişletilir
                const px = (g.xmax - g.xmin) * 0.1, py = (g.ymax - g.ymin) * 0.1;
                const bbox = [g.xmin - px, g.ymin - py, g.xmax + px, g.ymax + py].map(function (v) { return v.toFixed(6); });
                url += (url.indexOf('?') === -1 ? '?' : '&') + 'bbox=' + bbox.join(',') + '&zoom=' + Math.round(view.zoom);
            }
            if (kiraEkSorgu) url += (url.indexOf('?') === -1 ? '?' : '&') + kiraEkSorgu;
            url = imarEkle(url);

            if (yuklemeKontrol) yuklemeKontrol.abort();
            const kontrol = yuklemeKontrol = new AbortController();
            let fc;
            try {
                const yanit = await fetch(url, { headers: { Accept: 'application/json' }, signal: kontrol.signal });
                if (!yanit.ok) throw new Error('HTTP ' + yanit.status);
                fc = await yanit.json();
            } catch (e) {
                if (e.name !== 'AbortError') sayiEl.textContent = '0';
                return;
            }
            if (kontrol !== yuklemeKontrol) return;
            if (kiraTumunuYukle) tumExtent = null;

            const grafikler = [];
            (fc.features || []).forEach(function (f) {
                if (f.properties && f.properties.kume) {
                    kumeGrafikleri(f).forEach(function (g) { grafikler.push(g); });
                    return;
                }
                const g = parselGrafigi(f);
                if (!g) return;
                grafikler.push(g);
                // Şekil küçük kaldıysa iğne; aynı öznitelik ve popup, yani
                // iğneye tıklamak parsele tıklamakla aynı şey.
                if (MOD === 'tasinmaz' && g.geometry.type !== 'point' && ekranBoyu(g.geometry) < PARSEL_IGNE_PX) {
                    grafikler.push(new Graphic({
                        geometry: g.geometry.centroid || g.geometry.extent.center,
                        symbol: SEM_IGNE,
                        attributes: g.attributes,
                        popupTemplate: g.popupTemplate,
                    }));
                }
                // Şehir ölçeğinde 40 metrelik sınır bir pikselin altında kalıyor.
                // Yakınlaşana kadar merkezine okunur bir nokta konur.
                if (MOD === 'kira' && view.zoom < 15 && g.geometry && g.geometry.extent && g.geometry.type !== 'point') {
                    const e = g.geometry.extent;
                    grafikler.push(new Graphic({
                        geometry: new Point({
                            x: (e.xmin + e.xmax) / 2,
                            y: (e.ymin + e.ymax) / 2,
                            spatialReference: g.geometry.spatialReference,
                        }),
                        symbol: {
                            type: 'simple-marker', style: 'circle', size: 13,
                            color: renkAlfa(kiraRenk(f.properties || {}), 0.95),
                            outline: { color: [255, 255, 255, 1], width: 2 },
                        },
                        attributes: f.properties || {},
                    }));
                }
                if (tumunu) {
                    const e = geometriExtent(g.geometry);
                    if (e) tumExtent = tumExtent ? tumExtent.union(e) : e;
                }
            });
            parselKatman.removeAll();
            parselKatman.addMany(grafikler);
            cizilenZoom = view.zoom;

            sayiEl.textContent = (fc.toplam !== undefined ? fc.toplam : grafikler.length).toLocaleString('tr-TR');
            if (tumunu && tumExtent && grafikler.length && parselKatman.visible) {
                view.goTo(tumExtent.expand(1.25)).catch(function () {});
            }
        }

        /* ---- Emsal katmanı ---- */
        // Değerleme katmanıyla aynı bbox + kümeleme sözleşmesini konuşur,
        // yalnızca ayrı bir GraphicsLayer'a çizer.
        const emsalSayiEl = document.getElementById('hrm-emsal-sayi');
        let emsalKontrol = null;
        let emsalZamanlayici = null;

        function emsalAcik() {
            const kutu = document.getElementById('hrm-ov-emsal');
            return !!(EMSAL && kutu && kutu.checked);
        }

        function emsalTurleri() {
            return Array.prototype.slice.call(document.querySelectorAll('[data-emsal-tur]'))
                .filter(function (k) { return k.checked; })
                .map(function (k) { return k.dataset.emsalTur; });
        }

        function emsalGrafigi(f) {
            const geom = geoJsonGeometri(f.geometry);
            if (!geom) return null;
            const p = f.properties || {};
            const ilk = (p.tipler || [])[0];
            const bulunan = (EMSAL.turler || []).find(function (x) { return x.deger === ilk; });
            const renk = document.getElementById('hrm-deg-renk') && document.getElementById('hrm-deg-renk').value === 'imar'
                ? imarRengi(p.imar_id)
                : (bulunan ? bulunan.renk : '#0ea5e9');
            return new Graphic({
                geometry: geom,
                symbol: geom.type === 'point' ? semNokta(renk) : {
                    type: 'simple-fill', color: renkAlfa(renk, 0.22),
                    outline: { color: renk, width: 2, style: 'dash' },
                },
                attributes: p,
                popupTemplate: {
                    title: (p.ada || '—') + ' / ' + (p.parsel || '—') + ' · Emsal',
                    content: function () { return emsalPopup(p); },
                },
            });
        }

        async function emsalleriYukle() {
            if (!EMSAL) return;
            if (!emsalAcik()) { emsalKatman.removeAll(); if (emsalSayiEl) emsalSayiEl.textContent = '0'; return; }

            const g = webMercatorUtils.webMercatorToGeographic(view.extent);
            const px = (g.xmax - g.xmin) * 0.1, py = (g.ymax - g.ymin) * 0.1;
            const bbox = [g.xmin - px, g.ymin - py, g.xmax + px, g.ymax + py].map(function (v) { return v.toFixed(6); });
            let url = imarEkle(EMSAL.geojson + '?bbox=' + bbox.join(',') + '&zoom=' + Math.round(view.zoom));
            const turler = emsalTurleri();
            if (turler.length) url += '&tip=' + encodeURIComponent(turler.join(','));

            if (emsalKontrol) emsalKontrol.abort();
            const kontrol = emsalKontrol = new AbortController();
            let fc;
            try {
                const yanit = await fetch(url, { headers: { Accept: 'application/json' }, signal: kontrol.signal });
                if (!yanit.ok) throw new Error('HTTP ' + yanit.status);
                fc = await yanit.json();
            } catch (e) {
                if (e.name !== 'AbortError' && emsalSayiEl) emsalSayiEl.textContent = '0';
                return;
            }
            if (kontrol !== emsalKontrol) return;

            const grafikler = [];
            (fc.features || []).forEach(function (f) {
                if (f.properties && f.properties.kume) {
                    kumeGrafikleri(f).forEach(function (x) { grafikler.push(x); });
                    return;
                }
                const x = emsalGrafigi(f);
                if (x) grafikler.push(x);
            });
            emsalKatman.removeAll();
            emsalKatman.addMany(grafikler);
            if (emsalSayiEl) emsalSayiEl.textContent = (fc.toplam !== undefined ? fc.toplam : grafikler.length).toLocaleString('tr-TR');
        }

        // Yükleme harita durunca tetiklenir; üstüne uzun bir bekletme koymak
        // kümelerin geç açılıp geç kapanmasının başlıca sebebiydi. Sunucu tarafı
        // ~300ms olduğu için bekletmeyi kısa tutmak güvenli.
        function yuklemeyiZamanla() {
            clearTimeout(yuklemeZamanlayici);
            yuklemeZamanlayici = setTimeout(parselleriYukle, 90);
            if (EMSAL) {
                clearTimeout(emsalZamanlayici);
                emsalZamanlayici = setTimeout(emsalleriYukle, 90);
            }
        }

        /* =========================================================
         * KAYIT MODALİ — emsal / değerleme haritadan ayrılmadan eklenir
         * ========================================================= */
        const modalKatman = document.getElementById('hrm-modal');
        const modalGovde = document.getElementById('hrm-modal-govde');
        const modalBaslik = document.getElementById('hrm-modal-baslik');
        const modalAlt = document.getElementById('hrm-modal-alt');

        function modalKapat() {
            if (!modalKatman) return;
            modalKatman.hidden = true;
            modalGovde.innerHTML = '';
        }

        function modalAc(baslik, alt) {
            modalBaslik.textContent = baslik;
            modalAlt.textContent = alt || '';
            modalKatman.hidden = false;
        }

        function modalDurum(metin, hataMi) {
            let kutu = modalGovde.querySelector('.hrm-modal-durum');
            if (!kutu) {
                kutu = document.createElement('div');
                kutu.className = 'hrm-modal-durum';
                modalGovde.appendChild(kutu);
            }
            kutu.classList.toggle('is-hata', !!hataMi);
            kutu.textContent = metin;
            return kutu;
        }

        function ozetKutulari(ciftler) {
            return doluCiftler(ciftler).map(function (c) {
                return '<div><span>' + kacis(c[0]) + '</span><strong>' + kacis(c[1]) + '</strong></div>';
            }).join('');
        }

        // Formu şablondan kurar, alt düğmeleri ekler ve gönderimi bağlar.
        function modalFormKur(sablonId, gonder) {
            const sablon = document.getElementById(sablonId);
            if (!sablon) return null;
            modalGovde.innerHTML = '';
            modalGovde.appendChild(sablon.content.cloneNode(true));
            const form = modalGovde.querySelector('form');

            const ayak = document.createElement('div');
            ayak.className = 'hrm-modal-ayak';
            ayak.innerHTML = '<button type="button" class="btn-cancel" data-vazgec>Vazgeç</button>' +
                '<button type="submit" class="btn-submit">Kaydet</button>';
            form.appendChild(ayak);
            ayak.querySelector('[data-vazgec]').addEventListener('click', modalKapat);

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const dugme = ayak.querySelector('[type="submit"]');
                dugme.disabled = true;
                dugme.textContent = 'Kaydediliyor…';
                try {
                    await gonder(form);
                } finally {
                    dugme.disabled = false;
                    dugme.textContent = 'Kaydet';
                }
            });
            return form;
        }

        async function formGonder(url, form) {
            const veri = new FormData(form);
            veri.append('_token', API.csrf);
            const yanit = await fetch(url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: veri,
            });
            let govde = null;
            try { govde = await yanit.json(); } catch (e) { govde = null; }
            if (!yanit.ok) {
                // 422: alan bazlı doğrulama hataları tek satırda toplanır
                const hatalar = govde && govde.errors ? Object.values(govde.errors).flat().join(' · ') : null;
                throw new Error(hatalar || (govde && govde.message) || ('HTTP ' + yanit.status));
            }
            return govde;
        }

        /* ---- Emsal modali ---- */
        async function emsalModaliAc(sorgu) {
            modalAc('Emsal Ekle', '');
            modalGovde.innerHTML = '<div class="hrm-pop-yukleniyor"><span class="hrm-pop-spin"></span>Parsel bilgisi çözülüyor…</div>';

            let dolgu;
            try {
                dolgu = await jsonGetir(EMSAL.onDolgu + '?' + sorgu);
            } catch (e) {
                modalGovde.innerHTML = popUyari('Parsel bilgisi alınamadı: ' + e.message);
                return;
            }
            if (!dolgu || !dolgu.mahalle_id) {
                modalGovde.innerHTML = popUyari('Bu parselin mahallesi sistemde bulunamadı; emsal ekranından elle girebilirsiniz.');
                return;
            }

            const form = modalFormKur('hrm-emsal-form', async function (f) {
                try {
                    const s = await formGonder(EMSAL.kaydet, f);
                    modalKapat();
                    emsalleriYukle();
                    popupAc(view.center, 'Kaydedildi', (function () {
                        const d = document.createElement('div');
                        d.className = 'hrm-pop';
                        d.innerHTML = '<div class="hrm-pop-dipnot">' + kacis(s.mesaj) + '</div>';
                        return d;
                    })());
                } catch (e) {
                    modalDurum(e.message, true);
                }
            });
            if (!form) return;

            const p = new URLSearchParams(sorgu);
            modalAlt.textContent = (dolgu.ada || p.get('ada') || '') + '/' + (dolgu.parsel || p.get('parsel') || '');

            ['il_id', 'ilce_id', 'mahalle_id', 'ada', 'parsel', 'alan', 'emsal', 'yenaz_yencok', 'imar_durumu_id'].forEach(function (ad) {
                const alan = form.elements[ad];
                if (alan && dolgu[ad] !== undefined && dolgu[ad] !== null) alan.value = dolgu[ad];
            });
            const bugun = new Date().toISOString().slice(0, 10);
            if (form.elements.tarih && !form.elements.tarih.value) form.elements.tarih.value = bugun;

            form.querySelector('[data-ozet]').innerHTML = ozetKutulari([
                ['Ada / Parsel', (dolgu.ada || '') + ' / ' + (dolgu.parsel || '')],
                ['Alan', dolgu.alan ? sayiBicim(dolgu.alan, ' m²') : null],
                ['Konum', p.get('mahalle_tkgm') ? 'TKGM ile eşleşti' : null],
            ]);

            // e-İmar kullanımı listemizle eşleşmediyse ham metni ipucu göster
            if (dolgu.imar_ad_ham) {
                const alan = form.elements.imar_durumu_id.closest('.hrm-modal-alan');
                const ipucu = document.createElement('small');
                ipucu.textContent = 'e-İmar: ' + dolgu.imar_ad_ham;
                alan.appendChild(ipucu);
            }

            // Seçili türe ait olmayan alanlar gizlenir ve gönderilmez
            function turUygula() {
                const tur = form.querySelector('[name="tip"]:checked').value;
                form.querySelectorAll('[data-tur-alan]').forEach(function (alan) {
                    const gorunur = alan.dataset.turAlan.split(' ').indexOf(tur) !== -1;
                    alan.hidden = !gorunur;
                    alan.querySelectorAll('input, select, textarea').forEach(function (g) { g.disabled = !gorunur; });
                });
                form.querySelectorAll('.hrm-modal-tur-kart').forEach(function (k) {
                    k.classList.toggle('is-secili', k.querySelector('input').checked);
                });
            }
            form.querySelectorAll('[name="tip"]').forEach(function (r) { r.addEventListener('change', turUygula); });
            turUygula();
        }

        /* ---- Değerleme modali ---- */
        async function degerlemeModaliAc(sorgu) {
            modalAc('Değerleme Raporu Ekle', '');
            modalGovde.innerHTML = '<div class="hrm-pop-yukleniyor"><span class="hrm-pop-spin"></span>Taşınmaz envanterde aranıyor…</div>';

            let t;
            try {
                t = await jsonGetir(DEG.tasinmazBul + '?' + sorgu);
            } catch (e) {
                modalGovde.innerHTML = popUyari('Taşınmaz sorgulanamadı: ' + e.message);
                return;
            }
            if (!t.bulundu) {
                modalGovde.innerHTML = popUyari(t.mesaj) +
                    (t.ekle ? '<div class="hrm-modal-ayak"><a class="hrm-form-btn" href="' + kacis(t.ekle) + '">Taşınmaz Ekle</a></div>' : '');
                return;
            }

            const form = modalFormKur('hrm-degerleme-form', async function (f) {
                try {
                    const s = await formGonder(t.kaydet, f);
                    modalKapat();
                    parselleriYukle();
                    modalAc('Kaydedildi', '');
                    modalGovde.innerHTML = '<div class="hrm-pop-dipnot">' + kacis(s.mesaj) + '</div>' +
                        '<div class="hrm-modal-ayak"><button type="button" class="hrm-form-btn is-ghost" id="hrm-modal-tamam">Kapat</button>' +
                        '<a class="hrm-form-btn" href="' + kacis(s.detay) + '">Raporu Aç</a></div>';
                    document.getElementById('hrm-modal-tamam').addEventListener('click', modalKapat);
                } catch (e) {
                    modalDurum(e.message, true);
                }
            });
            if (!form) return;

            modalAlt.textContent = t.ada + '/' + t.parsel + ' · ' + [t.ilce, t.mahalle].filter(Boolean).join(' · ');
            form.querySelector('[data-ozet]').innerHTML = ozetKutulari([
                ['Ada / Parsel', t.ada + ' / ' + t.parsel],
                ['Parsel Alanı', t.alan ? sayiBicim(t.alan, ' m²') : null],
                ['Kurum Hissesi', t.hisse_alan ? sayiBicim(t.hisse_alan, ' m²') : null],
            ]);
            if (form.elements.hisse_alan && t.hisse_alan) form.elements.hisse_alan.value = t.hisse_alan;

            // Tür seçenekleri taşınmazın kayıt tipine göre sunucudan gelir
            const turKap = form.querySelector('[data-deg-tur]');
            const turAd = { arsa: 'Arsa', bb: 'Bağımsız Bölüm' };
            turKap.innerHTML = t.tipler.map(function (tip, i) {
                return '<label class="hrm-modal-tur-kart" style="--em-renk:' + (tip === 'bb' ? '#7c3aed' : '#0d9488') + '">' +
                    '<input type="radio" name="tip" value="' + kacis(tip) + '"' + (i === 0 ? ' checked' : '') + '>' +
                    '<span>' + kacis(turAd[tip] || tip) + '</span></label>';
            }).join('');

            function degTurUygula() {
                const tur = form.querySelector('[name="tip"]:checked').value;
                form.querySelectorAll('[data-deg-alan]').forEach(function (alan) {
                    const gorunur = alan.dataset.degAlan === tur;
                    alan.hidden = !gorunur;
                    alan.querySelectorAll('input, select, textarea').forEach(function (g) { g.disabled = !gorunur; });
                });
                // Arsa değerlemesinde "Bağımsız Bölüm" bölümü tamamen kalkar,
                // kalan bölüm numarası kaymasın diye yeniden numaralanır
                const bbBolum = form.querySelector('[data-deg-bolum="bb"]');
                if (bbBolum) bbBolum.hidden = tur !== 'bb';
                const num = form.querySelector('[data-deg-num]');
                if (num) num.textContent = tur === 'bb' ? '3' : '2';
                form.querySelectorAll('.hrm-modal-tur-kart').forEach(function (k) {
                    k.classList.toggle('is-secili', k.querySelector('input').checked);
                });
            }
            form.querySelectorAll('[name="tip"]').forEach(function (r) { r.addEventListener('change', degTurUygula); });
            degTurUygula();
        }

        // Popup içindeki düğmeler sonradan basıldığı için gövdeye tek dinleyici
        document.addEventListener('click', function (e) {
            const dugme = e.target.closest ? e.target.closest('[data-modal]') : null;
            if (!dugme) return;
            e.preventDefault();
            const sorgu = dugme.dataset.sorgu || '';
            if (dugme.dataset.modal === 'emsal') emsalModaliAc(sorgu);
            else degerlemeModaliAc(sorgu);
        });

        if (modalKatman) {
            document.getElementById('hrm-modal-kapat').addEventListener('click', modalKapat);
            modalKatman.addEventListener('click', function (e) { if (e.target === modalKatman) modalKapat(); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modalKatman.hidden) modalKapat(); });
        }

        /* ---- Kira kipi anahtarları ---- */
        if (MOD === 'kira') {
            let kiraZamanlayici = null;
            const kiraTazele = function () {
                clearTimeout(kiraZamanlayici);
                kiraZamanlayici = setTimeout(parselleriYukle, 200);
            };

            document.querySelectorAll('[data-imar]').forEach(function (k) {
                k.addEventListener('change', kiraTazele);
            });
            document.querySelectorAll('[data-imar-hepsi]').forEach(function (d) {
                d.addEventListener('click', function () {
                    const ac = d.dataset.imarHepsi === '1';
                    document.querySelectorAll('[data-imar]').forEach(function (k) { k.checked = ac; });
                    kiraTazele();
                });
            });

            const renkSecim = document.getElementById('hrm-deg-renk');
            if (renkSecim) renkSecim.addEventListener('change', parselleriYukle);
            kiraSorgusunuKur();
        }

        // Kira haritasının arama paneli: listeyle aynı süzgeç, sonuç listesi,
        // seçilenleri haritada gösterme ve Excel / KML / GeoJSON indirme.
        function kiraSorgusunuKur() {
            const form = document.getElementById('hrm-kr-sorgu');
            if (!form || !KIRA.ara) return;

            const mesaj = document.getElementById('hrm-kr-arama-mesaj');
            const liste = document.getElementById('hrm-kr-liste');
            const satirlar = document.getElementById('hrm-kr-satirlar');
            const sayi = document.getElementById('hrm-kr-sayi');
            const not = document.getElementById('hrm-kr-liste-not');
            const indirNot = document.getElementById('hrm-kr-indir-not');
            const hepsi = document.getElementById('hrm-kr-hepsi');
            const secilenBtn = document.getElementById('hrm-kr-secilen-goster');
            const detayBtn = document.getElementById('hrm-kr-detay-ac');
            const detay = document.getElementById('hrm-kr-detay');
            const ilce = document.getElementById('hrm-kr-ilce');
            const mahalle = document.getElementById('hrm-kr-mahalle');
            const rozet = document.getElementById('hrm-kr-filtre-rozet');
            const excel = document.getElementById('hrm-kr-excel');
            const kml = document.getElementById('hrm-kr-kml');
            const geojson = document.getElementById('hrm-kr-geojson');

            function mesajVer(metin, tur) {
                if (!mesaj) return;
                mesaj.hidden = !metin;
                mesaj.textContent = metin || '';
                mesaj.className = 'hrm-form-mesaj' + (tur ? ' is-' + tur : '');
            }

            function sorguMetni(ekIdler) {
                const veri = new FormData(form);
                const params = new URLSearchParams();
                veri.forEach(function (deger, anahtar) {
                    const metin = String(deger).trim();
                    if (metin !== '') params.append(anahtar, metin);
                });
                if (ekIdler && ekIdler.length) params.set('ids', ekIdler.join(','));
                return params.toString();
            }

            function seciliIdler() {
                return Array.prototype.map.call(satirlar.querySelectorAll('.hrm-kr-sec:checked'), function (k) {
                    return k.value;
                });
            }

            function rozetYaz() {
                if (rozet) rozet.hidden = !kiraEkSorgu;
            }

            function indirBaglariniYaz() {
                const secili = seciliIdler();
                const sorgu = sorguMetni(secili.length ? secili : null);
                const ek = sorgu ? '?' + sorgu : '';
                if (excel && KIRA.excel) excel.href = KIRA.excel + ek;
                if (kml && KIRA.kml) kml.href = KIRA.kml + ek;
                if (geojson && KIRA.geojsonIndir) geojson.href = KIRA.geojsonIndir + ek;
                if (secilenBtn) secilenBtn.disabled = secili.length === 0;
                if (indirNot) {
                    indirNot.textContent = secili.length
                        ? 'Seçilen ' + secili.length + ' dosya iner.'
                        : 'Satır seçilmezse süzgecin tamamı iner. Seçilirse yalnız o dosyalar.';
                }
                const kutular = satirlar.querySelectorAll('.hrm-kr-sec');
                if (hepsi) {
                    hepsi.checked = kutular.length > 0 && secili.length === kutular.length;
                    hepsi.indeterminate = secili.length > 0 && secili.length < kutular.length;
                }
            }

            async function kapsamaGit() {
                try {
                    const k = await jsonGetir(imarEkle(API.geojson + '?kapsam=1' + (kiraEkSorgu ? '&' + kiraEkSorgu : '')));
                    if (!k || !k.bbox) return false;
                    const d = 0.0009;
                    tumExtent = new Extent({
                        xmin: k.bbox[0] - d, ymin: k.bbox[1] - d,
                        xmax: k.bbox[2] + d, ymax: k.bbox[3] + d,
                        spatialReference: WGS84,
                    });
                    await view.goTo(tumExtent.expand(1.25)).catch(function () {});
                    return true;
                } catch (e) {
                    return false;
                }
            }

            async function haritadaGoster(idler) {
                if (!idler.length) {
                    mesajVer('Önce listeden kayıt seçin.', 'error');
                    return;
                }
                kiraEkSorgu = sorguMetni(idler);
                rozetYaz();
                indirBaglariniYaz();
                kiraTumunuYukle = true;
                try {
                    await parselleriYukle();
                } finally {
                    kiraTumunuYukle = false;
                }
                if (!tumExtent) mesajVer('Seçilen kayıtların çizili sınırı yok.', 'error');
                else mesajVer(idler.length + ' kayıt haritada gösteriliyor.', 'ok');
            }

            function listeyiYaz(veri) {
                const kayitlar = veri.kayitlar || [];
                satirlar.innerHTML = '';
                sayi.textContent = String(veri.toplam || 0);
                if (!kayitlar.length) {
                    satirlar.innerHTML = '<div class="hrm-arama-bos">Sorgunuza uyan kira dosyası yok.</div>';
                } else {
                    kayitlar.forEach(function (k) {
                        const konum = [k.kiraci, k.is_kolu, k.ilce, k.mahalle].filter(Boolean).join(' · ');
                        const ada = (k.ada || k.parsel) ? ((k.ada || '—') + '/' + (k.parsel || '—')) : '';
                        const satir = document.createElement('div');
                        satir.className = 'hrm-arama-satir hrm-kr-satir';
                        satir.innerHTML =
                            '<input type="checkbox" class="hrm-kr-sec" value="' + kacis(k.id) + '" aria-label="Seç">' +
                            '<div>' +
                                '<div class="hrm-arama-satir-baslik">' +
                                    '<span class="hrm-kr-nokta" style="background:' + (k.dolu_bos === 'bos' ? '#16a34a' : '#dc2626') + '"></span>' +
                                    '<span class="hrm-arama-tag">' + kacis(k.dosya_no || '—') + '</span>' +
                                    kacis(k.isyeri || '') +
                                '</div>' +
                                '<div class="hrm-arama-satir-alt">' + kacis([konum, ada, k.dolu_etiket].filter(Boolean).join(' · ')) + '</div>' +
                                '<div class="hrm-arama-satir-eylem">' +
                                    '<button type="button" class="hrm-arama-goster"' + (k.haritada ? '' : ' disabled') + '>Haritada göster</button>' +
                                    '<a class="hrm-arama-duzen" href="' + kacis(k.detay) + '">Dosya</a>' +
                                '</div>' +
                            '</div>';
                        const goster = satir.querySelector('.hrm-arama-goster');
                        if (k.haritada && goster) {
                            goster.addEventListener('click', function () { haritadaGoster([String(k.id)]); });
                        }
                        satirlar.appendChild(satir);
                    });
                }
                if (not) {
                    not.textContent = veri.toplam > kayitlar.length
                        ? 'Listede ilk ' + kayitlar.length + ' / ' + veri.toplam + ' kayıt gösteriliyor.'
                        : '';
                }
                liste.hidden = false;
                indirBaglariniYaz();
            }

            if (detayBtn && detay) {
                detayBtn.addEventListener('click', function () {
                    const acik = detay.hidden;
                    detay.hidden = !acik;
                    detayBtn.setAttribute('aria-expanded', acik ? 'true' : 'false');
                    detayBtn.textContent = acik ? 'Detaylı sorguyu gizle' : 'Detaylı sorgu';
                });
            }

            if (ilce && mahalle) {
                ilce.addEventListener('change', async function () {
                    mahalle.innerHTML = '<option value="">' + (ilce.value ? 'Tümü' : 'Önce ilçe seçin') + '</option>';
                    mahalle.disabled = true;
                    if (!ilce.value) return;
                    try {
                        const kayitlar = await jsonGetir(API.mahalleler + '/' + ilce.value);
                        kayitlar.forEach(function (m) {
                            const o = document.createElement('option');
                            o.value = m.id;
                            o.textContent = m.ad;
                            mahalle.appendChild(o);
                        });
                        mahalle.disabled = false;
                    } catch (e) { /* sessiz */ }
                });
            }

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const btn = document.getElementById('hrm-kr-sorgula');
                if (btn) btn.disabled = true;
                mesajVer('Sorgulanıyor…', 'info');
                kiraEkSorgu = sorguMetni(null);
                rozetYaz();
                try {
                    const veri = await jsonGetir(KIRA.ara + (kiraEkSorgu ? '?' + kiraEkSorgu : ''));
                    listeyiYaz(veri);
                    await parselleriYukle();
                    const gitti = await kapsamaGit();
                    mesajVer((veri.toplam || 0) + ' dosya bulundu.' + (gitti ? '' : ' Çizili sınırı olan kayıt yok.'), veri.toplam ? 'ok' : 'info');
                } catch (err) {
                    mesajVer(err.message || 'Sorgu tamamlanamadı.', 'error');
                } finally {
                    if (btn) btn.disabled = false;
                }
            });

            document.getElementById('hrm-kr-temizle').addEventListener('click', async function () {
                form.reset();
                if (mahalle) {
                    mahalle.innerHTML = '<option value="">Önce ilçe seçin</option>';
                    mahalle.disabled = true;
                }
                if (detay && detayBtn) {
                    detay.hidden = true;
                    detayBtn.setAttribute('aria-expanded', 'false');
                    detayBtn.textContent = 'Detaylı sorgu';
                }
                kiraEkSorgu = '';
                rozetYaz();
                liste.hidden = true;
                satirlar.innerHTML = '';
                indirBaglariniYaz();
                mesajVer('', null);
                await parselleriYukle();
                await kapsamaGit();
            });

            if (hepsi) {
                hepsi.addEventListener('change', function () {
                    satirlar.querySelectorAll('.hrm-kr-sec').forEach(function (k) { k.checked = hepsi.checked; });
                    indirBaglariniYaz();
                });
            }
            satirlar.addEventListener('change', function (e) {
                if (e.target && e.target.classList.contains('hrm-kr-sec')) indirBaglariniYaz();
            });
            if (secilenBtn) {
                secilenBtn.addEventListener('click', function () { haritadaGoster(seciliIdler()); });
            }
            indirBaglariniYaz();
        }

        /* =========================================================
         * KİRA — seçim ve kayıt araçları
         *
         * Referans sistemin KIRA_PANELI araç çubuğundaki işlemler:
         *   Seçim : Nokta Bilgisi · Alan İçinde Kalanlar · Çizgiyi Kesenler · Pencere
         *   Kayıt : Yeni Kayıt · Kayıt Güncelle · Kayıt Sil
         *
         * Çizim SketchViewModel ile yapılır, geometri WGS84'e çevrilip
         * sunucuya gönderilir; kesin kesişim testi sunucuda yapılır (geometri
         * spatial sütunda değil, GeoJSON olarak saklanıyor).
         * ========================================================= */
        if (MOD === 'kira' && KIRA.secim) {
            const cizimKatman = new GraphicsLayer({ title: 'Seçim', listMode: 'hide' });
            harita.add(cizimKatman);

            const svm = new SketchViewModel({
                view: view,
                layer: cizimKatman,
                updateOnGraphicClick: false,
                defaultCreateOptions: { mode: 'click' },
                polygonSymbol: {
                    type: 'simple-fill',
                    color: [14, 165, 233, 0.16],
                    outline: { color: '#0284c7', width: 2, style: 'short-dash' },
                },
                polylineSymbol: { type: 'simple-line', color: '#0284c7', width: 2.5, style: 'short-dash' },
                pointSymbol: {
                    type: 'simple-marker', style: 'circle', size: 11,
                    color: '#0ea5e9', outline: { color: '#fff', width: 2 },
                },
            });

            const sonucPanel = document.getElementById('hrm-kr-sonuc');
            const sonucGovde = document.getElementById('hrm-kr-sonuc-govde');
            const sonucBaslik = document.getElementById('hrm-kr-sonuc-baslik');
            const kayitMesaj = document.getElementById('hrm-kr-kayit-mesaj');
            const kesisenKutu = document.getElementById('hrm-kr-kesisen');

            // Etkin araç: aynı anda tek araç açık kalır
            let etkinArac = null;
            let tiklamaBekleyen = null; // 'guncelle' | 'sil' | null
            let guncellenenPoligon = null;
            let popupKilidi = null;

            function araclariBirak() {
                svm.cancel();
                etkinArac = null;
                tiklamaBekleyen = null;
                mapEl.classList.remove('hrm-cursor-help');
                // Parsel popup'ı yeniden açılabilsin. Esri popup'ı tıklamadan
                // sonra kendi hitTest'ini asenkron yaptığı için hemen açılmaz:
                // aracı bitiren tıklamanın popup açmaması için kısa süre beklenir.
                clearTimeout(popupKilidi);
                popupKilidi = setTimeout(function () { view.popupEnabled = true; }, 400);
                document.querySelectorAll('[data-kr-secim], [data-kr-kayit]').forEach(function (b) {
                    b.classList.remove('is-aktif');
                });
            }

            function aracSec(dugme, ad) {
                const ayni = etkinArac === ad;
                araclariBirak();
                if (ayni) return false;
                etkinArac = ad;
                dugme.classList.add('is-aktif');
                // Araç etkinken tıklama aracın işi; parsel popup'ı araya girmesin
                clearTimeout(popupKilidi);
                view.popupEnabled = false;
                if (view.popup) view.popup.close();

                return true;
            }

            function mesajVer(metin, hataMi) {
                if (!kayitMesaj) return;
                kayitMesaj.textContent = metin || '';
                kayitMesaj.hidden = !metin;
                kayitMesaj.classList.toggle('is-hata', !!hataMi);
            }

            /** JSON gövdeli istek — seçim ve poligon uçları FormData beklemiyor. */
            async function jsonGonder(url, yontem, govde) {
                const yanit = await fetch(url, {
                    method: yontem,
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': API.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: govde ? JSON.stringify(govde) : null,
                });
                let veri = null;
                try { veri = await yanit.json(); } catch (e) { veri = null; }
                if (!yanit.ok) {
                    const hatalar = veri && veri.errors ? Object.values(veri.errors).flat().join(' · ') : null;
                    const hata = new Error(hatalar || (veri && veri.mesaj) || (veri && veri.message) || ('HTTP ' + yanit.status));
                    hata.durum = yanit.status;
                    hata.veri = veri;
                    throw hata;
                }
                return veri;
            }

            /* ---- Geometri dönüşümleri ---- */

            // Esri geometrisi → GeoJSON ([lng, lat] düzeni)
            function geoJsonaCevir(geom) {
                const g = geom.spatialReference && geom.spatialReference.isWGS84
                    ? geom
                    : webMercatorUtils.webMercatorToGeographic(geom);
                if (g.type === 'point') return { type: 'Point', coordinates: [g.longitude, g.latitude] };
                if (g.type === 'polyline') {
                    const yollar = (g.paths || []).map(function (y) { return y.map(function (n) { return [n[0], n[1]]; }); });
                    return yollar.length === 1
                        ? { type: 'LineString', coordinates: yollar[0] }
                        : { type: 'MultiLineString', coordinates: yollar };
                }
                const halkalar = (g.rings || []).map(function (h) { return h.map(function (n) { return [n[0], n[1]]; }); });
                return { type: 'Polygon', coordinates: halkalar };
            }

            function halkaCikar(geom) {
                const gj = geoJsonaCevir(geom);
                if (gj.type === 'Polygon') return gj.coordinates[0] || [];
                if (gj.type === 'LineString') return gj.coordinates;
                if (gj.type === 'MultiLineString') return gj.coordinates[0] || [];
                return [];
            }

            function alanHesapla(geom) {
                try {
                    const m2 = Math.abs(geometryEngine.geodesicArea(geom, 'square-meters'));
                    return m2 > 0 ? Math.round(m2 * 100) / 100 : null;
                } catch (e) {
                    return null;
                }
            }

            /* ---- Seçim sonucu listesi ---- */

            function kalanRozet(k) {
                if (k === null || k === undefined) return '';
                const sinif = k < 0 ? 'is-gecmis' : (k <= 90 ? 'is-yakin' : '');
                const metin = k < 0 ? (Math.abs(k) + ' gün geçti') : (k + ' gün');
                return '<span class="hrm-kr-kalan ' + sinif + '">' + kacis(metin) + '</span>';
            }

            function sonucGoster(baslik, veri, ek) {
                sonucBaslik.textContent = baslik;
                const satirlar = (veri.kayitlar || []).map(function (k) {
                    const renk = k.dolu_bos === 'dolu' ? '#dc2626' : '#16a34a';
                    return '<div class="hrm-kr-satir" data-lat="' + kacis(k.lat) + '" data-lng="' + kacis(k.lng) + '">' +
                        '<div class="hrm-kr-satir-ust">' +
                            '<strong>' + kacis(k.dosya_no || '—') + '</strong>' +
                            '<span class="hrm-kr-nokta" style="background:' + renk + '"></span>' +
                            '<span class="hrm-kr-durum">' + kacis(k.dolu_bos_ad) + '</span>' +
                            kalanRozet(k.kalan_gun) +
                        '</div>' +
                        '<div class="hrm-kr-satir-orta">' + kacis(k.ad || '—') +
                            (k.kiraci ? ' · ' + kacis(k.kiraci) : '') + '</div>' +
                        '<div class="hrm-kr-satir-alt">' +
                            (k.ada_parsel ? '<span>' + kacis(k.ada_parsel) + '</span>' : '') +
                            (k.mahalle ? '<span>' + kacis(k.mahalle) + '</span>' : '') +
                            (k.alan ? '<span>' + sayiBicim(k.alan, ' m²') + '</span>' : '') +
                        '</div>' +
                        '<div class="hrm-kr-satir-eylem">' +
                            '<a href="' + kacis(k.detay) + '">Bilgi Kartı</a>' +
                            (KIRA.duzenlenir ? '<a href="' + kacis(k.duzenle) + '">Düzenle</a>' : '') +
                            '<button type="button" data-kr-git>Haritada Göster</button>' +
                        '</div>' +
                    '</div>';
                });

                sonucGovde.innerHTML =
                    '<div class="hrm-kr-sonuc-ust">' +
                        '<span><strong>' + (veri.toplam || 0).toLocaleString('tr-TR') + '</strong> kayıt' +
                        (veri.poligon_sayisi ? ' · ' + veri.poligon_sayisi + ' sınır' : '') + '</span>' +
                        (veri.kesilen ? '<em>ilk ' + (veri.kayitlar || []).length + ' gösteriliyor</em>' : '') +
                    '</div>' +
                    (ek || '') +
                    (satirlar.length ? satirlar.join('') : '<p class="hrm-panel-ipucu">Bu seçimde kira kaydı yok.</p>');

                sonucPanel.hidden = false;
            }

            sonucGovde.addEventListener('click', function (e) {
                const git = e.target.closest('[data-kr-git]');
                if (git) {
                    const satir = git.closest('.hrm-kr-satir');
                    const lat = parseFloat(satir.dataset.lat);
                    const lng = parseFloat(satir.dataset.lng);
                    if (!isNaN(lat) && !isNaN(lng)) {
                        view.goTo({ center: [lng, lat], zoom: Math.max(view.zoom, 18) }).catch(function () {});
                    }
                }
            });

            /* ---- Seçim araçları ---- */

            const SECIM_CIZIM = { nokta: 'point', alan: 'polygon', cizgi: 'polyline', pencere: 'rectangle' };
            const SECIM_AD = {
                nokta: 'Nokta Bilgisi', alan: 'Alan İçinde Kalanlar',
                cizgi: 'Çizgiyi Kesenler', pencere: 'Pencere Seçimi',
            };

            async function secimGonder(tur, geom) {
                const govde = { tur: tur, kesisen: kesisenKutu && kesisenKutu.checked ? 1 : 0 };
                if (tur === 'nokta') {
                    const gj = geoJsonaCevir(geom);
                    govde.nokta = gj.coordinates;
                } else if (tur === 'pencere') {
                    const halka = halkaCikar(geom);
                    const lng = halka.map(function (n) { return n[0]; });
                    const lat = halka.map(function (n) { return n[1]; });
                    govde.pencere = [Math.min.apply(null, lng), Math.min.apply(null, lat), Math.max.apply(null, lng), Math.max.apply(null, lat)];
                } else if (tur === 'alan') {
                    govde.halka = halkaCikar(geom);
                } else {
                    govde.cizgi = halkaCikar(geom);
                }

                sonucBaslik.textContent = SECIM_AD[tur];
                sonucGovde.innerHTML = popYukleniyor('Kayıtlar aranıyor…');
                sonucPanel.hidden = false;

                try {
                    const veri = await jsonGonder(KIRA.secim, 'POST', govde);
                    sonucGoster(SECIM_AD[tur], veri, '');
                } catch (e) {
                    sonucGovde.innerHTML = popUyari('Seçim yapılamadı: ' + e.message);
                }
            }

            document.querySelectorAll('[data-kr-secim]').forEach(function (dugme) {
                dugme.addEventListener('click', function () {
                    const tur = dugme.dataset.krSecim;
                    if (!aracSec(dugme, 'secim:' + tur)) {
                        cizimKatman.removeAll();
                        return;
                    }
                    cizimKatman.removeAll();
                    // Hepsi tıklayarak çizilir: pencerede iki köşeye tıklanır
                    svm.create(SECIM_CIZIM[tur], { mode: 'click' });
                });
            });

            const temizleBtn = document.getElementById('hrm-kr-temizle');
            if (temizleBtn) {
                temizleBtn.addEventListener('click', function () {
                    cizimKatman.removeAll();
                    araclariBirak();
                    sonucPanel.hidden = true;
                });
            }

            /* ---- Kayıt araçları ---- */

            async function yeniSinirKaydet(geom) {
                const gj = geoJsonaCevir(geom);
                mesajVer('Sınır kaydediliyor…', false);
                try {
                    const s = await jsonGonder(KIRA.poligon, 'POST', {
                        koordinat: gj,
                        kaynak: 'cizim',
                        alan: alanHesapla(geom),
                    });
                    mesajVer(s.mesaj, false);
                    // Form tam sayfadır: sınır kimliği taşınır, poligon hazır seçili açılır
                    window.location.href = s.form;
                } catch (e) {
                    mesajVer('Sınır kaydedilemedi: ' + e.message, true);
                }
            }

            async function sinirGuncelle(poligonId, geom) {
                mesajVer('Sınır güncelleniyor…', false);
                try {
                    const s = await jsonGonder(KIRA.poligon + '/' + poligonId, 'PUT', {
                        koordinat: geoJsonaCevir(geom),
                        alan: alanHesapla(geom),
                    });
                    mesajVer(s.mesaj + (s.kayit_sayisi > 1 ? ' Bu sınırı ' + s.kayit_sayisi + ' kayıt paylaşıyor.' : ''), false);
                    cizimKatman.removeAll();
                    guncellenenPoligon = null;
                    parselleriYukle();
                } catch (e) {
                    mesajVer('Sınır güncellenemedi: ' + e.message, true);
                }
            }

            async function sinirSil(poligonId) {
                try {
                    const s = await jsonGonder(KIRA.poligon + '/' + poligonId, 'DELETE', null);
                    mesajVer(s.mesaj, false);
                    sonucPanel.hidden = true;
                    parselleriYukle();
                } catch (e) {
                    // 409: sınır kullanımda — bağlı kayıtlar listelenir
                    if (e.durum === 409 && e.veri) {
                        sonucGoster('Sınır Kullanımda', e.veri,
                            '<p class="hrm-panel-ipucu is-uyari">' + kacis(e.veri.mesaj) + '</p>' +
                            (KIRA.duzenlenir
                                ? '<div class="hrm-kr-sonuc-eylem"><button type="button" data-kr-ayir="' + poligonId + '">Tüm kayıtların sınır bağını kaldır</button></div>'
                                : ''));
                        mesajVer('', false);
                        return;
                    }
                    mesajVer('Sınır silinemedi: ' + e.message, true);
                }
            }

            // "Sınır bağını kaldır" — poligonu paylaşan kayıtlardan tek tek çözülür
            sonucGovde.addEventListener('click', async function (e) {
                const ayir = e.target.closest('[data-kr-ayir]');
                if (!ayir) return;
                const poligonId = ayir.dataset.krAyir;
                ayir.disabled = true;
                ayir.textContent = 'Kaldırılıyor…';
                const satirlar = sonucGovde.querySelectorAll('.hrm-kr-satir');
                try {
                    for (const satir of satirlar) {
                        const baglanti = satir.querySelector('a[href*="/alanlar/"]');
                        const eslesme = baglanti && baglanti.getAttribute('href').match(/alanlar\/(\d+)/);
                        if (eslesme) {
                            await jsonGonder(KIRA.sinirAyir + '/' + eslesme[1] + '/sinir', 'DELETE', null);
                        }
                    }
                    await sinirSil(poligonId);
                } catch (hata) {
                    mesajVer('Sınır bağı kaldırılamadı: ' + hata.message, true);
                    ayir.disabled = false;
                    ayir.textContent = 'Tüm kayıtların sınır bağını kaldır';
                }
            });

            document.querySelectorAll('[data-kr-kayit]').forEach(function (dugme) {
                dugme.addEventListener('click', function () {
                    const islem = dugme.dataset.krKayit;
                    if (!aracSec(dugme, 'kayit:' + islem)) {
                        cizimKatman.removeAll();
                        mesajVer('', false);
                        return;
                    }
                    cizimKatman.removeAll();

                    if (islem === 'yeni') {
                        mesajVer('Haritada sınırı çizin; çift tıklayarak bitirin.', false);
                        svm.create('polygon');
                        return;
                    }
                    tiklamaBekleyen = islem;
                    mapEl.classList.add('hrm-cursor-help');
                    mesajVer(islem === 'guncelle'
                        ? 'Güncellemek istediğiniz sınıra tıklayın.'
                        : 'Silmek istediğiniz sınıra tıklayın.', false);
                });
            });

            /* ---- Çizim bitti ---- */
            svm.on('create', function (olay) {
                if (olay.state !== 'complete') return;
                const geom = olay.graphic.geometry;

                if (etkinArac === 'kayit:yeni') {
                    araclariBirak();
                    yeniSinirKaydet(geom);
                    return;
                }
                if (etkinArac && etkinArac.indexOf('secim:') === 0) {
                    const tur = etkinArac.slice(6);
                    araclariBirak();
                    secimGonder(tur, geom);
                }
            });

            svm.on('update', function (olay) {
                if (olay.state !== 'complete' || !guncellenenPoligon) return;
                const grafik = olay.graphics[0];
                if (grafik) sinirGuncelle(guncellenenPoligon, grafik.geometry);
            });

            /* =====================================================
             * BİLGİ PANELİ — sınıra tıklanınca popup yerine yan panel
             *
             * Aynı sınırda birden çok kira kaydı olabiliyor; panelde dosya
             * numarasına göre açılır liste çıkar, seçilen kaydın bilgileri
             * altta gösterilir.
             * ===================================================== */
            const bilgiPanel = document.getElementById('hrm-kr-bilgi');
            const bilgiGovde = document.getElementById('hrm-kr-bilgi-govde');
            const bilgiBaslik = document.getElementById('hrm-kr-bilgi-baslik');
            let bilgiVeri = null;
            let bilgiKontrol = null;

            function tarih(d) {
                return d ? new Date(d).toLocaleDateString('tr-TR') : null;
            }

            function bilgiSatirlari(ciftler) {
                return ciftler.filter(function (c) { return c[1] !== null && c[1] !== undefined && c[1] !== ''; })
                    .map(function (c) {
                        return '<div class="hrm-kr-bilgi-satir"><dt>' + kacis(c[0]) + '</dt><dd>' + kacis(c[1]) + '</dd></div>';
                    }).join('');
            }

            function kaydiBas(sira) {
                const k = bilgiVeri.kayitlar[sira];
                const kutu = bilgiGovde.querySelector('[data-kr-bilgi-kayit]');
                if (!k || !kutu) return;

                const kalan = k.kalan_gun === null || k.kalan_gun === undefined
                    ? null
                    : (k.kalan_gun < 0 ? Math.abs(k.kalan_gun) + ' gün geçti' : k.kalan_gun + ' gün kaldı');

                kutu.innerHTML =
                    '<div class="hrm-kr-bilgi-ust">' +
                        '<strong>' + kacis(k.ad || 'Adsız alan') + '</strong>' +
                        '<span class="hrm-kr-rozet" style="--kr-renk:' + (k.dolu_bos === 'dolu' ? '#dc2626' : '#16a34a') + '">' +
                            kacis(k.dolu_bos_ad) + '</span>' +
                        (k.sozlesme_aktif ? '' : '<span class="hrm-kr-rozet is-soluk">Yürürlükte değil</span>') +
                    '</div>' +
                    (k.uyari ? '<p class="hrm-panel-ipucu is-uyari">' + kacis(k.uyari) + '</p>' : '') +
                    '<dl class="hrm-kr-bilgi-liste">' +
                        bilgiSatirlari([
                            ['Dosya No', k.dosya_no],
                            ['Eski Dosya No', k.eski_dosya_no],
                            ['Kiracı', k.kiraci],
                            ['Alt Kiracı', k.alt_kiraci],
                            ['Telefon', k.telefon],
                            ['İş Kolu', k.is_kolu],
                            ['Faaliyet', k.faaliyet],
                            ['İhale', k.ihale],
                            ['Kira Dönemi', k.baslangic ? tarih(k.baslangic) + ' – ' + (tarih(k.bitis) || '—') : null],
                            ['Kalan Süre', kalan],
                            ['Yıllık Bedel', k.yillik_bedel ? sayiBicim(k.yillik_bedel, ' TL') : null],
                            ['Taksit', k.taksit],
                            ['Kiraya Verilen Alan', k.alan ? sayiBicim(k.alan, ' m²') : null],
                            ['İlçe / Mahalle', [k.ilce, k.mahalle].filter(Boolean).join(' · ') || null],
                            ['Ada / Parsel', k.ada_parsel],
                            ['İmar Durumu', k.imar],
                            ['Kiracılık Durumu', k.kiracilik_durum],
                            ['Hukuki Durum', k.hukuki_durum],
                            ['Adres', k.adres],
                        ]) +
                    '</dl>' +
                    '<div class="hrm-kr-bilgi-eylem">' +
                        '<a href="' + kacis(k.detay) + '">Alan Bilgi Kartı</a>' +
                        (k.dosya_detay ? '<a href="' + kacis(k.dosya_detay) + '">Dosya Detayı</a>' : '') +
                        (KIRA.duzenlenir ? '<a href="' + kacis(k.duzenle) + '">Düzenle</a>' : '') +
                    '</div>';
            }

            function bilgiBas(veri) {
                bilgiVeri = veri;
                const p = veri.poligon || {};
                const n = veri.kayitlar.length;
                bilgiBaslik.textContent = (p.ada_parsel || p.ad || 'Sınır #' + p.id);

                bilgiGovde.innerHTML =
                    '<div class="hrm-kr-bilgi-sinir">' +
                        '<span>Sınır #' + kacis(p.id) + (p.kaynak === 'tkgm' ? ' · TKGM parseli' : (p.kaynak === 'cizim' ? ' · haritada çizildi' : '')) + '</span>' +
                        (p.alan ? '<strong>' + sayiBicim(p.alan, ' m²') + '</strong>' : '') +
                    '</div>' +
                    (n === 0
                        ? '<p class="hrm-panel-ipucu">Bu sınıra bağlı kira kaydı yok.</p>'
                        : (n > 1
                            ? '<label class="hrm-kr-bilgi-secim">' +
                                  '<span>Bu sınırda <strong>' + n + '</strong> kayıt var</span>' +
                                  '<select data-kr-bilgi-secim>' +
                                      veri.kayitlar.map(function (k, i) {
                                          return '<option value="' + i + '">' + kacis(k.dosya_no || '—') +
                                              ' · ' + kacis(k.ad || 'adsız') + ' · ' + kacis(k.dolu_bos_ad) + '</option>';
                                      }).join('') +
                                  '</select>' +
                              '</label>'
                            : '')) +
                    '<div data-kr-bilgi-kayit></div>' +
                    (KIRA.ekleyebilir && veri.ekle
                        ? '<a class="hrm-kr-bilgi-ekle" href="' + kacis(veri.ekle) + '">+ Bu Sınıra Kayıt Ekle</a>'
                        : '');

                if (n) kaydiBas(0);

                const secim = bilgiGovde.querySelector('[data-kr-bilgi-secim]');
                if (secim) {
                    secim.addEventListener('change', function () { kaydiBas(parseInt(this.value, 10)); });
                }
                bilgiPanel.hidden = false;
            }

            async function bilgiAc(poligonId) {
                bilgiBaslik.textContent = 'Kira Bilgi Paneli';
                bilgiGovde.innerHTML = popYukleniyor('Kayıtlar getiriliyor…');
                bilgiPanel.hidden = false;

                if (bilgiKontrol) bilgiKontrol.abort();
                const kontrol = bilgiKontrol = new AbortController();
                try {
                    const yanit = await fetch(KIRA.poligonBilgi + '/' + poligonId + '/kayitlar',
                        { headers: { Accept: 'application/json' }, signal: kontrol.signal });
                    if (!yanit.ok) throw new Error('HTTP ' + yanit.status);
                    const veri = await yanit.json();
                    if (kontrol !== bilgiKontrol) return;
                    bilgiBas(veri);
                } catch (e) {
                    if (e.name === 'AbortError') return;
                    bilgiGovde.innerHTML = popUyari('Kayıtlar alınamadı: ' + e.message);
                }
            }

            /* ---- Sınıra tıklayarak güncelle / sil / bilgi ---- */
            view.on('click', async function (evt) {
                if (!tiklamaBekleyen) {
                    // Araç yoksa ve bilgi sorgu kipi kapalıysa: bilgi paneli
                    // Bilgi Sorgu açıksa tıklama TKGM/e-İmar sorgusunun işi
                    const sorguBtn = document.getElementById('hrm-bilgi-btn');
                    if (etkinArac || (sorguBtn && sorguBtn.classList.contains('is-aktif')) || !KIRA.poligonBilgi) return;
                    const sonuc = await view.hitTest(evt, { include: [parselKatman] });
                    const vurus = sonuc.results.find(function (r) {
                        return r.graphic && r.graphic.attributes && r.graphic.attributes.poligon_id;
                    });
                    if (vurus) bilgiAc(vurus.graphic.attributes.poligon_id);

                    return;
                }
                const islem = tiklamaBekleyen;
                const sonuc = await view.hitTest(evt, { include: [parselKatman] });
                const isabet = sonuc.results.find(function (r) {
                    return r.graphic && r.graphic.attributes && r.graphic.attributes.poligon_id;
                });
                if (!isabet) {
                    mesajVer('Bu noktada kira sınırı yok; sınırın üstüne tıklayın.', true);
                    return;
                }
                const poligonId = isabet.graphic.attributes.poligon_id;
                araclariBirak();

                if (islem === 'sil') {
                    sinirSil(poligonId);
                    return;
                }

                // Güncelleme: sınırın bir kopyası çizim katmanına alınır, köşeleri taşınır
                guncellenenPoligon = poligonId;
                cizimKatman.removeAll();
                const kopya = new Graphic({
                    geometry: isabet.graphic.geometry,
                    symbol: svm.polygonSymbol,
                });
                cizimKatman.add(kopya);
                mesajVer('Köşeleri sürükleyip bitirince kaydedilir.', false);
                svm.update([kopya], { tool: 'reshape' });
            });
        }

        /* ---- Değerleme kipi anahtarları ---- */
        if (MOD === 'degerleme') {
            let imarZamanlayici = null;
            function suzgecTazele() {
                clearTimeout(imarZamanlayici);
                imarZamanlayici = setTimeout(function () { parselleriYukle(); emsalleriYukle(); }, 200);
            }

            document.querySelectorAll('[data-imar]').forEach(function (k) {
                k.addEventListener('change', suzgecTazele);
            });
            document.querySelectorAll('[data-imar-hepsi]').forEach(function (d) {
                d.addEventListener('click', function () {
                    const ac = d.dataset.imarHepsi === '1';
                    document.querySelectorAll('[data-imar]').forEach(function (k) { k.checked = ac; });
                    suzgecTazele();
                });
            });

            const renkSecim = document.getElementById('hrm-deg-renk');
            if (renkSecim) renkSecim.addEventListener('change', function () { parselleriYukle(); emsalleriYukle(); });

            const emsalKutu = document.getElementById('hrm-ov-emsal');
            if (emsalKutu) emsalKutu.addEventListener('change', emsalleriYukle);
            document.querySelectorAll('[data-emsal-tur]').forEach(function (k) {
                k.addEventListener('change', emsalleriYukle);
            });
        }

        async function kapsamYukle() {
            try {
                const k = await jsonGetir(imarEkle(API.geojson + '?kapsam=1' + (kiraEkSorgu ? '&' + kiraEkSorgu : '')));
                if (k && k.birim_aralik) birimAralik = k.birim_aralik;
                if (k && k.bbox) {
                    const d = 0.0009; // tek nokta / küçük parsel: ~100 m pay
                    tumExtent = new Extent({ xmin: k.bbox[0] - d, ymin: k.bbox[1] - d, xmax: k.bbox[2] + d, ymax: k.bbox[3] + d, spatialReference: WGS84 });
                }
            } catch (e) { /* kapsam alınamazsa varsayılan görünüm kalır */ }
        }

        // Adres parametresi ?katman=imar,parselasyon,yapi: o ABB katmanlarını aç
        const ADRES_KATMAN = {
            imar: 'hrm-ov-imar', parselasyon: 'hrm-ov-parselasyon', belediye: 'hrm-ov-belediye',
            ilce: 'hrm-ov-abb-ilce', mahalle: 'hrm-ov-abb-mahalle', yapi: 'hrm-ov-abb-yapi', numarataj: 'hrm-ov-abb-numarataj',
        };
        (ADRES.get('katman') || '').split(',').forEach(function (k) {
            const kutu = ADRES_KATMAN[k] && document.getElementById(ADRES_KATMAN[k]);
            if (kutu && !kutu.checked) { kutu.checked = true; kutu.dispatchEvent(new Event('change')); }
        });

        view.when(async function () {
            if (SECILI_MOD) { parselleriYukle(); return; }
            await kapsamYukle();
            if (!ADRES_KONUM && tumExtent && parselKatman.visible) {
                await view.goTo(tumExtent.expand(1.25)).catch(function () {});
            }
            reactiveUtils.when(function () { return view.stationary; }, yuklemeyiZamanla, { initial: true });

            // Kümeleme eşiği de baloncuk çapı da zoom'a bağlı, yani zoom değişince
            // ekrandaki grafikler geçersiz hâle gelir. Yeni yanıtı beklerken eskiyi
            // ekranda tutmak "geç kapanıyor" hissi veriyordu; bir kademe
            // uzaklaşıldığında hemen bırakılır. Kaydırmada (pan) temizlenmez:
            // grafikler coğrafi olarak hâlâ doğru yerdedir.
            reactiveUtils.watch(function () { return view.zoom; }, function (z) {
                if (cizilenZoom !== null && Math.abs(z - cizilenZoom) >= 0.4) {
                    parselKatman.removeAll();
                    cizilenZoom = null;
                }
            });
        });

        /* ---- Rozetle etkileşim ----
         *
         * Rozete tıklamak eskiden hiçbir şey yapmıyordu (sonra bir ara
         * Esri'nin "Bu kümede N taşınmaz var" popup'ını açıyordu: yeni bir şey
         * söylemiyor, üstelik kapatmak gerekiyordu). Artık tıklamak kümeyi
         * açar — sunucu her küme için kapsayıcı dikdörtgeni de döndürdüğü için
         * ek sorgu gerekmez. Rozetin tıklanabilir olduğu imleç, vurgu ve küçük
         * bir ipucu balonuyla belli edilir.
         */
        function kumeRozeti(sonuc) {
            return sonuc.results.find(function (r) {
                return r.graphic && r.graphic.attributes && r.graphic.attributes.kume;
            });
        }

        function kumeyiAc(grafik) {
            const b = grafik.attributes.bbox;
            if (!b) return;
            ipucuGizle();
            const kapsam = new Extent({ xmin: b[0], ymin: b[1], xmax: b[2], ymax: b[3], spatialReference: WGS84 });
            // Tek noktada yığılmış kayıtlarda kapsam sıfır genişlikte gelir;
            // goTo böyle bir alana gidemez, kademeli yakınlaşmaya düşülür.
            if (kapsam.width > 0 && kapsam.height > 0) {
                view.goTo(kapsam.expand(1.3)).catch(function () {});
                return;
            }
            view.goTo({ target: grafik.geometry, zoom: view.zoom + 2.5 }).catch(function () {});
        }

        view.on('click', async function (evt) {
            const sonuc = await view.hitTest(evt, { include: [parselKatman] });
            const rozet = kumeRozeti(sonuc);
            if (rozet) kumeyiAc(rozet.graphic);
        });

        const ipucuEl = document.getElementById('hrm-kume-ipucu');
        let ipucuMesgul = false;
        let kumeVurgu = null;

        function ipucuGizle() {
            if (kumeVurgu) { kumeVurgu.remove(); kumeVurgu = null; }
            if (ipucuEl) ipucuEl.hidden = true;
            mapEl.classList.remove('hrm-cursor-kume');
        }

        function ipucuGoster(grafik, x, y) {
            mapEl.classList.add('hrm-cursor-kume');
            if (!ipucuEl) return;
            const adet = grafik.attributes.adet || 0;
            ipucuEl.innerHTML = '<strong>' + adet.toLocaleString('tr-TR') + '</strong> taşınmaz'
                + '<span>açmak için tıklayın</span>';
            ipucuEl.hidden = false;
            // Balon imlecin sağ üstünde durur; ekranın sağ kenarına
            // yaklaşınca sola geçer, yoksa görünümün dışına taşıyordu.
            const g = ipucuEl.getBoundingClientRect();
            const sol = x + 16 + g.width > window.innerWidth ? x - 16 - g.width : x + 16;
            ipucuEl.style.transform = 'translate(' + Math.round(sol) + 'px, ' + Math.round(y - g.height - 12) + 'px)';
        }

        // hitTest imleç hareketinde çalışır; önceki istek bitmeden yenisi
        // açılmaz, yoksa hızlı gezinmede istek kuyruğa biniyor.
        view.on('pointer-move', function (evt) {
            if (ipucuMesgul) return;
            ipucuMesgul = true;
            view.hitTest(evt, { include: [parselKatman] }).then(function (sonuc) {
                const rozet = kumeRozeti(sonuc);
                if (!rozet) { ipucuGizle(); return; }
                if (kumeVurgu) { kumeVurgu.remove(); kumeVurgu = null; }
                try { if (parselLV) kumeVurgu = parselLV.highlight(rozet.graphic); } catch (e) { /* vurgu şart değil */ }
                ipucuGoster(rozet.graphic, evt.x, evt.y);
            }).catch(function () {}).then(function () { ipucuMesgul = false; });
        });

        view.on('pointer-leave', ipucuGizle);

        // Harita imlecin altından kayarken balon eski kümeyi gösteriyordu;
        // pointer-move gelmediği için kendiliğinden kapanmıyor.
        reactiveUtils.watch(function () { return view.navigating; }, function (hareket) {
            if (hareket) ipucuGizle();
        });

        document.getElementById('hrm-fit-btn').addEventListener('click', function () {
            if (!tumExtent) return;
            view.goTo(tumExtent.expand(1.25)).catch(function () {});
        });

        /* =========================================================
         * 8) PANELLER — aç/kapat + sürükle
         * ========================================================= */
        // Paneller aynı köşede açılır — aynı anda tek panel açık kalsın, üst üste binmesin
        const PANELLER = [
            ['hrm-katman-btn', 'hrm-panel'],
            ['hrm-adaparsel-btn', 'hrm-adaparsel-panel'],
            ['hrm-arama-btn', 'hrm-arama-panel'],
            // Kira haritasındakiler; başka kiplerde bu kimlikler yok, panelKur geçer
            ['hrm-kr-secim-btn', 'hrm-kr-secim-panel'],
            ['hrm-kr-kayit-btn', 'hrm-kr-kayit-panel'],
        ];
        function panelKur(btnId, panelId) {
            const btn = document.getElementById(btnId);
            const panel = document.getElementById(panelId);
            if (!btn || !panel) return;
            btn.addEventListener('click', function () {
                const acilacak = panel.hidden;
                if (acilacak) {
                    PANELLER.forEach(function (p) { if (p[1] !== panelId) panelKapat(p[1], p[0]); });
                }
                panel.hidden = !acilacak;
                btn.setAttribute('aria-expanded', acilacak ? 'true' : 'false');
                btn.classList.toggle('is-aktif', acilacak);
            });
        }
        PANELLER.forEach(function (p) { panelKur(p[0], p[1]); });

        function panelKapat(panelId, btnId) {
            const panel = document.getElementById(panelId);
            const btn = btnId ? document.getElementById(btnId) : null;
            if (panel) panel.hidden = true;
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
                btn.classList.remove('is-aktif');
            }
        }
        document.getElementById('hrm-panel-kapat')
            .addEventListener('click', function () { panelKapat('hrm-panel', 'hrm-katman-btn'); });

        document.querySelectorAll('[data-kapat]').forEach(function (b) {
            const hedef = b.dataset.kapat;
            // data-kapat-btn verilmişse o düğme pasifleşir; verilmezse eski eşleme
            const btnId = b.dataset.kapatBtn || (hedef === 'hrm-adaparsel-panel' ? 'hrm-adaparsel-btn' : 'hrm-arama-btn');
            b.addEventListener('click', function () { panelKapat(hedef, btnId); });
        });

        // Sürüklenebilir paneller
        document.querySelectorAll('.hrm-float-panel [data-drag-handle]').forEach(function (tut) {
            const panel = tut.closest('.hrm-float-panel');
            let sx = 0, sy = 0, bl = 0, bt = 0, suruklu = false;

            tut.addEventListener('mousedown', function (e) {
                if (e.target.closest('.hrm-panel-kapat')) return;
                const kutu = panel.getBoundingClientRect();
                suruklu = true;
                sx = e.clientX; sy = e.clientY; bl = kutu.left; bt = kutu.top;
                panel.style.right = 'auto';
                panel.style.bottom = 'auto';
                document.body.style.userSelect = 'none';
                e.preventDefault();
            });

            window.addEventListener('mousemove', function (e) {
                if (!suruklu) return;
                const g = panel.getBoundingClientRect();
                panel.style.left = Math.min(Math.max(0, bl + e.clientX - sx), window.innerWidth - g.width) + 'px';
                panel.style.top = Math.min(Math.max(0, bt + e.clientY - sy), window.innerHeight - 40) + 'px';
            });

            window.addEventListener('mouseup', function () {
                if (!suruklu) return;
                suruklu = false;
                document.body.style.userSelect = '';
            });
        });

        /* =========================================================
         * 9) BİLGİ SORGU — TKGM parsel + Ankara e-İmar
         * ========================================================= */
        const bilgiBtn = document.getElementById('hrm-bilgi-btn');
        let bilgiModu = false;

        bilgiBtn.addEventListener('click', function () {
            bilgiModu = !bilgiModu;
            bilgiBtn.classList.toggle('is-aktif', bilgiModu);
            bilgiBtn.setAttribute('aria-pressed', bilgiModu ? 'true' : 'false');
            mapEl.classList.toggle('hrm-cursor-help', bilgiModu);
        });

        /**
         * Sorgulanan parselden kayıt açma bağlantıları (yalnızca değerleme
         * kipinde). Konum TKGM mahalle kimliğiyle taşınır; yerel il/ilçe/
         * mahalle sunucuda ondan çözülür. e-İmar sonucu geldiyse KAKS ve
         * Yençok da forma yazılır.
         */
        function kayitEylemleri(o, eimar) {
            // Kira kipinde kayıt tam sayfa formda açılır: form altı bölümlü,
            // modale sığmaz. Parsel bilgisi sorgu dizesiyle taşınır.
            if (MOD === 'kira') {
                if (!KIRA.ekleyebilir || !KIRA.yeni) return '';
                const kq = new URLSearchParams();
                if (o.mahalleId) kq.set('mahalle_tkgm', o.mahalleId);
                if (o.adaNo) kq.set('ada', o.adaNo);
                if (o.parselNo) kq.set('parsel', o.parselNo);

                return '<div class="hrm-pop-eylemler">' +
                    '<a class="hrm-pop-eylem" href="' + kacis(KIRA.yeni + '?' + kq) + '">+ Kira Alanı Ekle</a>' +
                '</div>';
            }
            if (MOD !== 'degerleme') return '';
            const q = new URLSearchParams();
            if (o.mahalleId) q.set('mahalle_tkgm', o.mahalleId);
            if (o.adaNo) q.set('ada', o.adaNo);
            if (o.parselNo) q.set('parsel', o.parselNo);
            if (o.alan) q.set('alan', o.alan);
            if (eimar && eimar.kaks) q.set('emsal', eimar.kaks);
            if (eimar && eimar.hmax) q.set('yenaz_yencok', eimar.hmax);
            if (eimar && eimar.kullanim) q.set('imar_ad', eimar.kullanim);

            // Kayıt modal içinde açılır; haritadan ayrılmamak için <a> değil
            // <button> kullanılır, parametreler data-sorgu'da taşınır.
            const baglar = [];
            if (EMSAL && EMSAL.ekleyebilir) {
                baglar.push('<button type="button" class="hrm-pop-eylem is-emsal" data-modal="emsal" data-sorgu="' + kacis(q.toString()) + '">+ Emsal Ekle</button>');
            }
            if (DEG.ekleyebilir) {
                baglar.push('<button type="button" class="hrm-pop-eylem" data-modal="degerleme" data-sorgu="' + kacis(q.toString()) + '">+ Değerleme Raporu</button>');
            }
            return baglar.length ? '<div class="hrm-pop-eylemler">' + baglar.join('') + '</div>' : '';
        }

        function tkgmPopupGovde(ozellik, eimarDurum, eimarVeri) {
            const o = ozellik || {};
            return '' +
                popUst('TKGM', 'is-tkgm', [o.ilAd, o.ilceAd, o.mahalleAd].filter(Boolean).join(' · ')) +
                // TKGM alanı zaten biçimlenmiş metindir — olduğu gibi gösterilir.
                popHero(o.adaNo, o.parselNo, tkgmAlan(o.alan)) +
                popListe([
                    ['Nitelik', o.nitelik],
                    ['Zemin Tipi', o.zeminKmdurum],
                    ['Pafta', o.pafta],
                    ['Mevkii', o.mevkii],
                ]) +
                '<div id="hrm-eimar-kutu">' + eimarDurum + '</div>' +
                '<div id="hrm-kayit-eylem">' + kayitEylemleri(o, eimarVeri) + '</div>';
        }

        function eimarGovde(d) {
            const olcu = doluCiftler([
                ['TAKS', d.taks],
                ['KAKS', d.kaks],
                ['Kat', d.kat_adedi],
                ['Hmax', d.hmax],
            ]);

            const olcuBlok = olcu.length
                ? '<div class="hrm-pop-olcu">' + olcu.map(function (c) {
                        return '<div class="hrm-pop-olcu-kutu">' +
                            '<span class="hrm-pop-olcu-etiket">' + kacis(c[0]) + '</span>' +
                            '<strong class="hrm-pop-olcu-deger">' + kacis(c[1]) + '</strong>' +
                        '</div>';
                    }).join('') + '</div>'
                : '';

            return '<div class="hrm-pop-bolum">' +
                popBolumBaslik('e-İmar · Plan Adası', 'is-eimar') +
                olcuBlok +
                popListe([
                    ['Kullanım', d.kullanim],
                    ['Alt Kullanım', d.alt_kullanim],
                    ['Yapı Düzeni', d.yapi_duzeni],
                    ['Emsal', d.emsal],
                    ['Çekme (Ön/Yan/Arka)', d.cekme],
                    ['Daire Sayısı', d.daire_sayisi],
                    ['Plan Adası', d.plan_adasi],
                ]) +
            '</div>';
        }

        /**
         * Parsel geometrisinden görünüm SR'sinde bir nokta üretir (popup konumu).
         * centroid yoksa extent merkezi, o da yoksa ilk halka noktası kullanılır.
         */
        function parselNoktasi(geom) {
            if (!geom) return null;
            if (geom.type === 'point') return geom;

            let p = geom.centroid || (geom.extent && geom.extent.center) || null;
            if (!p && geom.rings && geom.rings[0] && geom.rings[0][0]) {
                const xy = geom.rings[0][0];
                p = new Point({
                    x: xy[0],
                    y: xy[1],
                    spatialReference: geom.spatialReference || WGS84,
                });
            }
            if (!p) return null;

            const sr = p.spatialReference;
            const viewSr = view.spatialReference;
            if (viewSr && viewSr.isWebMercator && sr && !sr.isWebMercator && sr.wkid === 4326) {
                try { return webMercatorUtils.geographicToWebMercator(p); } catch (e) { return p; }
            }
            return p;
        }

        /**
         * Noktayı WGS84 lat/lng'ye çevirir (e-İmar identify).
         * Web Mercator veya SR'si belirsiz ama y>|90| olan noktalar coğrafi kabul edilmez.
         */
        function cografiLatLng(nokta) {
            if (!nokta) return null;
            const sr = nokta.spatialReference;
            const webMerc = !!(sr && (sr.isWebMercator || sr.wkid === 102100 || sr.wkid === 3857 || sr.wkid === 102113));
            const yHam = nokta.y != null ? nokta.y : nokta.latitude;
            let cografi = nokta;
            if (webMerc || (typeof yHam === 'number' && Math.abs(yHam) > 90)) {
                try { cografi = webMercatorUtils.webMercatorToGeographic(nokta); } catch (e) { cografi = nokta; }
            }
            const lat = cografi.latitude != null ? cografi.latitude : cografi.y;
            const lng = cografi.longitude != null ? cografi.longitude : cografi.x;
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
            return { lat: lat.toFixed(6), lng: lng.toFixed(6) };
        }

        /**
         * e-İmar isteğini hemen yola çıkarır ve bir söz döner. Çağıran taraf
         * bunu beklemeden TKGM sorgusuna veya kamera animasyonuna devam eder;
         * iki istek böylece sırayla değil birlikte gider.
         *
         * Hiçbir durumda reddetmez — hatayı da sonuç olarak taşır, yoksa
         * beklemeye alınmadığı anlarda sahipsiz ret uyarısı üretirdi.
         */
        function eimarGetir(lat, lng) {
            return jsonGetir(API.eimar + '?lat=' + lat + '&lng=' + lng).then(
                function (veri) { return { tamam: true, veri: veri }; },
                function (e) { return { tamam: false, mesaj: e.message }; }
            );
        }

        /**
         * Bekleyen e-İmar isteğini popup içindeki kutuya yazar ve plan adası
         * geometrisini haritaya işler. Hem "Bilgi Sorgu" hem "Ada/Parsel" yolu
         * kullanır. Kapsama alanı dışında veya planı olmayan yerde hata döner —
         * bu bir arıza değil, dipnot olarak gösterilir.
         */
        async function eimarYaz(kap, sozu, tkgmOzellik) {
            let kutu = kap && kap.querySelector ? kap.querySelector('#hrm-eimar-kutu') : null;
            if (!kutu) kutu = document.querySelector('#hrm-eimar-kutu');
            if (!kutu) return;

            const sonuc = await sozu;
            if (!sonuc.tamam) {
                kutu.innerHTML = '<div class="hrm-pop-dipnot">e-İmar: ' + kacis(sonuc.mesaj) + '</div>';
                return;
            }

            kutu.innerHTML = eimarGovde(sonuc.veri);
            // e-İmar geldikten sonra KAKS/Hmax/kullanım da form bağlantılarına
            // eklenir. Eski popup gövdeleri DOM'da kalabildiği için önce bu
            // popup'ın kendi kabında aranır.
            const eylem = (kap && kap.querySelector && kap.querySelector('#hrm-kayit-eylem'))
                || document.querySelector('#hrm-kayit-eylem');
            if (eylem && tkgmOzellik) eylem.innerHTML = kayitEylemleri(tkgmOzellik, sonuc.veri);
            const eGeom = geoJsonGeometri(sonuc.veri.geometry);
            if (eGeom) sorguKatman.add(new Graphic({ geometry: eGeom, symbol: SEM_EIMAR }));
        }

        view.on('click', async function (evt) {
            if (!bilgiModu) return;
            evt.stopPropagation();

            const cografi = webMercatorUtils.webMercatorToGeographic(evt.mapPoint);
            const lat = cografi.latitude.toFixed(6);
            const lng = cografi.longitude.toFixed(6);

            sorguKatman.removeAll();
            popupAc(evt.mapPoint, 'Parsel Bilgisi', popYukleniyor('TKGM sorgulanıyor…'));

            // İmar isteği TKGM'yi beklemesin; ikisi aynı anda yola çıkar.
            const eimarSozu = eimarGetir(lat, lng);

            let parsel;
            try {
                parsel = await jsonGetir(API.tkgmNokta + '/' + lat + '/' + lng);
            } catch (e) {
                popupAc(evt.mapPoint, 'Parsel Bilgisi', popUyari(e.message));
                return;
            }

            const geom = geoJsonGeometri(parsel.geometry);
            if (geom) sorguKatman.add(new Graphic({ geometry: geom, symbol: SEM_TKGM }));

            const kap = document.createElement('div');
            kap.className = 'hrm-pop';
            kap.innerHTML = tkgmPopupGovde(parsel.properties, popYukleniyor('e-İmar sorgulanıyor…'));
            popupAc(evt.mapPoint, 'Parsel Bilgisi', kap);

            await eimarYaz(kap, eimarSozu, parsel.properties);
        });

        /* =========================================================
         * 10) İL / İLÇE / MAHALLE KADEMELİ SEÇİM
         * ========================================================= */
        function secenekDoldur(select, kayitlar, bosEtiket) {
            select.innerHTML = '';
            const bos = document.createElement('option');
            bos.value = '';
            bos.textContent = bosEtiket;
            select.appendChild(bos);
            kayitlar.forEach(function (k) {
                const o = document.createElement('option');
                o.value = k.id;
                o.textContent = k.ad;
                if (k.tkgm_id !== undefined && k.tkgm_id !== null) o.dataset.tkgm = k.tkgm_id;
                select.appendChild(o);
            });
        }

        function kademeliKur(ilSel, ilceSel, mahalleSel, bosEtiket, degisimGeriCagri) {
            ilSel.addEventListener('change', async function () {
                secenekDoldur(ilceSel, [], bosEtiket);
                secenekDoldur(mahalleSel, [], bosEtiket);
                ilceSel.disabled = true;
                mahalleSel.disabled = true;
                if (degisimGeriCagri) degisimGeriCagri();
                if (!ilSel.value) return;
                try {
                    secenekDoldur(ilceSel, await jsonGetir(API.ilceler + '/' + ilSel.value), bosEtiket);
                    ilceSel.disabled = false;
                } catch (e) { /* sessiz */ }
            });

            ilceSel.addEventListener('change', async function () {
                secenekDoldur(mahalleSel, [], bosEtiket);
                mahalleSel.disabled = true;
                if (degisimGeriCagri) degisimGeriCagri();
                if (!ilceSel.value) return;
                try {
                    secenekDoldur(mahalleSel, await jsonGetir(API.mahalleler + '/' + ilceSel.value), bosEtiket);
                    mahalleSel.disabled = false;
                } catch (e) { /* sessiz */ }
            });
        }

        /* =========================================================
         * 11) ADA / PARSEL SORGU (TKGM)
         * ========================================================= */
        const apIl = document.getElementById('hrm-ap-il');
        const apIlce = document.getElementById('hrm-ap-ilce');
        const apMahalle = document.getElementById('hrm-ap-mahalle');
        const apAda = document.getElementById('hrm-ap-ada');
        const apParsel = document.getElementById('hrm-ap-parsel');
        const apBtn = document.getElementById('hrm-ap-sorgula');
        const apMesaj = document.getElementById('hrm-ap-mesaj');

        function apDurumGuncelle() {
            const secili = apMahalle.selectedOptions[0];
            apBtn.disabled = !(secili && secili.dataset.tkgm && apAda.value.trim() && apParsel.value.trim());
        }

        kademeliKur(apIl, apIlce, apMahalle, '— Seçin —', apDurumGuncelle);

        function apMesajVer(metin, tur) {
            apMesaj.hidden = !metin;
            apMesaj.textContent = metin || '';
            apMesaj.className = 'hrm-form-mesaj' + (tur ? ' is-' + tur : '');
        }

        [apMahalle, apAda, apParsel].forEach(function (el) {
            el.addEventListener('change', apDurumGuncelle);
            el.addEventListener('input', apDurumGuncelle);
        });

        apBtn.addEventListener('click', async function () {
            const secili = apMahalle.selectedOptions[0];
            const tkgmId = secili && secili.dataset.tkgm;
            const ada = apAda.value.trim();
            const parselNo = apParsel.value.trim();
            if (!tkgmId || !ada || !parselNo) return;

            apBtn.disabled = true;
            apMesajVer('TKGM sorgulanıyor…', 'info');

            try {
                const veri = await jsonGetir(
                    API.tkgmAdaParsel + '/' + tkgmId + '/' + encodeURIComponent(ada) + '/' + encodeURIComponent(parselNo)
                );
                const geom = geoJsonGeometri(veri.geometry);
                if (!geom) throw new Error('Parsel geometrisi okunamadı');

                sorguKatman.removeAll();
                sorguKatman.add(new Graphic({ geometry: geom, symbol: SEM_TKGM }));

                // Bilgi sorgu ile aynı: görünüm noktasından WGS84 lat/lng → e-İmar identify.
                // Nokta goTo'dan bağımsız hesaplanır, böylece imar isteği kamera
                // animasyonunu beklemeden yola çıkabilir.
                const nokta = parselNoktasi(geom);
                const ll = cografiLatLng(nokta);
                const eimarSozu = ll ? eimarGetir(ll.lat, ll.lng) : null;

                const e = geometriExtent(geom);
                if (e) await view.goTo(e.expand(1.6)).catch(function () {});

                const kap = document.createElement('div');
                kap.className = 'hrm-pop';
                kap.innerHTML = tkgmPopupGovde(veri.properties, popYukleniyor('e-İmar sorgulanıyor…'));
                popupAc(nokta || (e && e.center), 'Parsel Bilgisi', kap);

                apMesajVer('Parsel bulundu ve haritaya işlendi.', 'ok');

                if (eimarSozu) {
                    await eimarYaz(kap, eimarSozu, veri.properties);
                } else {
                    const kutu = kap.querySelector('#hrm-eimar-kutu');
                    if (kutu) kutu.innerHTML = '<div class="hrm-pop-dipnot">e-İmar: parsel merkezi hesaplanamadı</div>';
                }
            } catch (e) {
                apMesajVer(e.message, 'error');
            } finally {
                apDurumGuncelle();
            }
        });

        /* =========================================================
         * 12) SİSTEMDE ARAMA
         * ========================================================= */
        const arIl = document.getElementById('hrm-arama-il');
        const arIlce = document.getElementById('hrm-arama-ilce');
        const arMahalle = document.getElementById('hrm-arama-mahalle');
        const arQ = document.getElementById('hrm-arama-q');
        const arAda = document.getElementById('hrm-arama-ada');
        const arParsel = document.getElementById('hrm-arama-parsel');
        const arBtn = document.getElementById('hrm-arama-sorgula');
        const arMesaj = document.getElementById('hrm-arama-mesaj');
        const arListe = document.getElementById('hrm-arama-liste');
        const arSayi = document.getElementById('hrm-arama-sayi');
        const arSatirlar = document.getElementById('hrm-arama-satirlar');

        // Kira haritası kendi sorgu panelini kullanır; bu blok taşınmaz aramasıdır.
        if (arIl && arIlce && arMahalle && arBtn) {
        kademeliKur(arIl, arIlce, arMahalle, '— Tümü —', null);

        function arMesajVer(metin, tur) {
            arMesaj.hidden = !metin;
            arMesaj.textContent = metin || '';
            arMesaj.className = 'hrm-form-mesaj' + (tur ? ' is-' + tur : '');
        }

        function sonucGoster(kayit) {
            const geom = geoJsonGeometri(kayit.geometry) ||
                (kayit.lat !== null && kayit.lng !== null
                    ? new Point({ longitude: +kayit.lng, latitude: +kayit.lat, spatialReference: WGS84 })
                    : null);
            if (!geom) return;

            sorguKatman.removeAll();
            sorguKatman.add(new Graphic({
                geometry: geom,
                symbol: geom.type === 'point' ? SEM_NOKTA : SEM_VURGU,
            }));

            const e = geometriExtent(geom);
            if (e) view.goTo(e.expand(1.6)).catch(function () {});
        }

        arBtn.addEventListener('click', async function () {
            arBtn.disabled = true;
            arMesajVer('Aranıyor…', 'info');
            arListe.hidden = true;

            const params = new URLSearchParams();
            if (arQ.value.trim()) params.set('q', arQ.value.trim());
            if (arIl.value) params.set('il_id', arIl.value);
            if (arIlce.value) params.set('ilce_id', arIlce.value);
            if (arMahalle.value) params.set('mahalle_id', arMahalle.value);
            if (arAda.value.trim()) params.set('ada', arAda.value.trim());
            if (arParsel.value.trim()) params.set('parsel', arParsel.value.trim());

            try {
                const veri = await jsonGetir(API.ara + '?' + params.toString());
                const sonuclar = veri.sonuclar || [];

                arSatirlar.innerHTML = '';
                arSayi.textContent = String(veri.toplam || sonuclar.length);

                if (!sonuclar.length) {
                    arSatirlar.innerHTML = '<div class="hrm-arama-bos">Kayıt bulunamadı.</div>';
                } else {
                    sonuclar.forEach(function (k) {
                        const konum = [k.il, k.ilce, k.mahalle].filter(Boolean).join(' · ');
                        const alan = sayiBicim(k.alan, ' m²');
                        const haritada = !!(k.geometry || (k.lat !== null && k.lng !== null));

                        const satir = document.createElement('div');
                        satir.className = 'hrm-arama-satir';
                        satir.innerHTML =
                            '<div class="hrm-arama-satir-baslik">' +
                                '<span class="hrm-arama-tag">' + kacis((k.ada || '—') + '/' + (k.parsel || '—')) + '</span>' +
                                kacis(k.nitelik || '') +
                            '</div>' +
                            '<div class="hrm-arama-satir-alt">' + kacis(konum) + (alan ? ' · ' + kacis(alan) : '') + '</div>' +
                            '<div class="hrm-arama-satir-eylem">' +
                                '<button type="button" class="hrm-arama-goster"' + (haritada ? '' : ' disabled') + '>Haritada Göster</button>' +
                                '<a class="hrm-arama-duzen" href="' + kacis(k.duzenle) + '">Düzenle</a>' +
                            '</div>';

                        if (haritada) {
                            satir.querySelector('.hrm-arama-goster')
                                .addEventListener('click', function () { sonucGoster(k); });
                        }
                        arSatirlar.appendChild(satir);
                    });
                }

                arListe.hidden = false;
                arMesajVer('', null);
            } catch (e) {
                arMesajVer(e.message, 'error');
            } finally {
                arBtn.disabled = false;
            }
        });
        }

        /* =========================================================
         * 13) KLAVYE KISAYOLLARI
         * ========================================================= */
        if (arQ && arBtn) arQ.addEventListener('keydown', function (e) { if (e.key === 'Enter') arBtn.click(); });
        apParsel.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !apBtn.disabled) apBtn.click();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && bilgiModu) bilgiBtn.click();
        });
    });
})();
