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
    ], function (EsriMap, Basemap, MapView, WebTileLayer, BaseTileLayer, BaseDynamicLayer, TileInfo, GraphicsLayer,
                 Graphic, Polygon, Point, Extent, geometryEngine, webMercatorUtils, reactiveUtils) {

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

        const parselKatman = new GraphicsLayer({ title: 'Taşınmaz Parselleri', opacity: 0.70 });
        const emsalKatman = new GraphicsLayer({ title: 'Emsaller', opacity: 0.70, visible: true });
        const sorguKatman = new GraphicsLayer({ title: 'Sorgu Sonucu' });

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

        /* =========================================================
         * 3) SEMBOLLER
         * ========================================================= */
        const SEM_PARSEL = { type: 'simple-fill', color: [245, 158, 11, 0.25], outline: { color: [245, 158, 11, 0.95], width: 1.6 } };
        const SEM_NOKTA  = { type: 'simple-marker', style: 'circle', size: 9, color: [245, 158, 11, 0.90], outline: { color: [14, 23, 38, 0.90], width: 1.4 } };

        /* ---- Değerleme kipi ----
         * Aynı modül iki haritayı çalıştırır. Altlıklar, ABB katmanları,
         * TKGM/e-İmar sorguları ve panel davranışı ortaktır; ayrışan yerler
         * yalnızca veri katmanı, sembol rengi ve parsel popup'ının içeriğidir.
         */
        const MOD = API.mod || 'tasinmaz';
        const DEG = API.degerleme || {};
        const EMSAL = API.emsal || null;
        const IMAR_RENK = DEG.imarRenk || {};
        const IMAR_YOK_RENK = '#94a3b8';
        const DEG_TUR_RENK = { arsa: '#0d9488', bb: '#7c3aed', ikisi: '#f59e0b' };

        // '#rrggbb' / 'hsl(...)' → Esri'nin kabul ettiği renk dizesi
        function imarRengi(id) {
            return (id === null || id === undefined) ? IMAR_YOK_RENK : (IMAR_RENK[id] || IMAR_YOK_RENK);
        }

        function semDoldur(renk, opaklik) {
            return { type: 'simple-fill', color: renkAlfa(renk, opaklik === undefined ? 0.3 : opaklik), outline: { color: renk, width: 1.8 } };
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

        // GeoJSON dış halkaları CCW, ArcGIS CW bekler — simplify sarımı düzeltir.
        function halkalariDuzelt(polygon) {
            try {
                return geometryEngine.simplify(polygon) || polygon;
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
                return halkalariDuzelt(new Polygon({ rings: g.coordinates, spatialReference: WGS84 }));
            }
            if (g.type === 'MultiPolygon') {
                const rings = [];
                g.coordinates.forEach(function (poly) { poly.forEach(function (r) { rings.push(r); }); });
                return rings.length ? halkalariDuzelt(new Polygon({ rings: rings, spatialReference: WGS84 })) : null;
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
        const KATMAN_ONBELLEK_ANAHTAR = 'etys-harita-katman-v1';
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

        function kumeGrafikleri(f) {
            const p = f.properties;
            const konum = new Point({ longitude: f.geometry.coordinates[0], latitude: f.geometry.coordinates[1], spatialReference: WGS84 });
            const boy = Math.min(54, 26 + Math.log10(p.adet) * 9);
            const ozellik = { kume: true, adet: p.adet, bbox: p.bbox };
            return [
                new Graphic({
                    geometry: konum,
                    attributes: ozellik,
                    symbol: { type: 'simple-marker', style: 'circle', size: boy, color: [245, 158, 11, 0.85], outline: { color: [255, 255, 255, 0.95], width: 2 } },
                }),
                new Graphic({
                    geometry: konum,
                    attributes: ozellik,
                    symbol: {
                        type: 'text', text: p.adet >= 1000 ? (p.adet / 1000).toFixed(p.adet >= 10000 ? 0 : 1) + 'B' : String(p.adet),
                        color: [14, 23, 38, 1], yoffset: -4, font: { size: 11, weight: 'bold', family: 'sans-serif' },
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
            if (!SECILI_MOD) {
                const g = webMercatorUtils.webMercatorToGeographic(view.extent);
                // Kenardaki parseller kesilmesin diye alan biraz genişletilir
                const px = (g.xmax - g.xmin) * 0.1, py = (g.ymax - g.ymin) * 0.1;
                const bbox = [g.xmin - px, g.ymin - py, g.xmax + px, g.ymax + py].map(function (v) { return v.toFixed(6); });
                url += (url.indexOf('?') === -1 ? '?' : '&') + 'bbox=' + bbox.join(',') + '&zoom=' + Math.round(view.zoom);
            }
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

            const grafikler = [];
            (fc.features || []).forEach(function (f) {
                if (f.properties && f.properties.kume) {
                    kumeGrafikleri(f).forEach(function (g) { grafikler.push(g); });
                    return;
                }
                const g = parselGrafigi(f);
                if (!g) return;
                grafikler.push(g);
                if (SECILI_MOD) {
                    const e = geometriExtent(g.geometry);
                    if (e) tumExtent = tumExtent ? tumExtent.union(e) : e;
                }
            });
            parselKatman.removeAll();
            parselKatman.addMany(grafikler);

            sayiEl.textContent = (fc.toplam !== undefined ? fc.toplam : grafikler.length).toLocaleString('tr-TR');
            if (SECILI_MOD && tumExtent && grafikler.length && parselKatman.visible) {
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

        function yuklemeyiZamanla() {
            clearTimeout(yuklemeZamanlayici);
            yuklemeZamanlayici = setTimeout(parselleriYukle, 250);
            if (EMSAL) {
                clearTimeout(emsalZamanlayici);
                emsalZamanlayici = setTimeout(emsalleriYukle, 250);
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
            ayak.innerHTML = '<button type="button" class="hrm-form-btn is-ghost" data-vazgec>Vazgeç</button>' +
                '<button type="submit" class="hrm-form-btn">Kaydet</button>';
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
                const k = await jsonGetir(imarEkle(API.geojson + '?kapsam=1'));
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
        });

        // Kümeye tıklayınca o alana yakınlaş
        view.on('click', async function (evt) {
            const sonuc = await view.hitTest(evt, { include: [parselKatman] });
            const isabet = sonuc.results.find(function (r) { return r.graphic && r.graphic.attributes && r.graphic.attributes.kume; });
            if (!isabet) return;
            const b = isabet.graphic.attributes.bbox;
            view.goTo(new Extent({ xmin: b[0], ymin: b[1], xmax: b[2], ymax: b[3], spatialReference: WGS84 }).expand(1.2)).catch(function () {});
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
            const btnId = hedef === 'hrm-adaparsel-panel' ? 'hrm-adaparsel-btn' : 'hrm-arama-btn';
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

            const yazilar = Array.isArray(d.plan_yazilari)
                ? d.plan_yazilari.filter(function (t) { return t !== null && String(t).trim() !== ''; })
                : [];
            const yaziBlok = yazilar.length
                ? '<div class="hrm-pop-not-baslik">Plan Yazıları</div><div class="hrm-pop-notlar">' +
                    yazilar.map(function (t) { return '<p>' + kacis(t) + '</p>'; }).join('') +
                  '</div>'
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
                yaziBlok +
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

        /* =========================================================
         * 13) KLAVYE KISAYOLLARI
         * ========================================================= */
        arQ.addEventListener('keydown', function (e) { if (e.key === 'Enter') arBtn.click(); });
        apParsel.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !apBtn.disabled) apBtn.click();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && bilgiModu) bilgiBtn.click();
        });
    });
})();
