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

    const ABB = 'https://planaski.ankara.bel.tr/webgis/rest/services/mobilServis';

    require([
        'esri/Map',
        'esri/Basemap',
        'esri/views/MapView',
        'esri/layers/WebTileLayer',
        'esri/layers/MapImageLayer',
        'esri/layers/GraphicsLayer',
        'esri/Graphic',
        'esri/geometry/Polygon',
        'esri/geometry/Point',
        'esri/geometry/Extent',
        'esri/geometry/geometryEngine',
        'esri/geometry/support/webMercatorUtils',
    ], function (EsriMap, Basemap, MapView, WebTileLayer, MapImageLayer, GraphicsLayer,
                 Graphic, Polygon, Point, Extent, geometryEngine, webMercatorUtils) {

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
        // Ankara BB · Uygulama İmar Planı — e-İmar sorgusu ile aynı kaynak (uipSade)
        const imarKatman = new MapImageLayer({
            url: ABB + '/uipSade/MapServer',
            opacity: 0.75,
            visible: false,
        });

        // Ankara BB · Aktif kadastro parselleri (parselAktif, alt katman 0)
        const parselasyonKatman = new MapImageLayer({
            url: ABB + '/parselAktif/MapServer',
            sublayers: [{ id: 0, visible: true }],
            opacity: 0.80,
            visible: false,
        });

        // Ankara BB · Belediye Taşınmazları — hisse_duru: TAM koyu, HİSSELİ açık
        const belediyeRenderer = {
            type: 'unique-value',
            field: 'hisse_duru',
            defaultSymbol: {
                type: 'simple-fill',
                color: [255, 0, 197, 0.22],
                outline: { color: [255, 0, 197, 0.85], width: 0.8 },
            },
            uniqueValueInfos: [
                {
                    value: 'TAM',
                    label: 'Tam',
                    symbol: {
                        type: 'simple-fill',
                        color: [140, 0, 98, 0.62],
                        outline: { color: [92, 0, 64, 1], width: 1.1 },
                    },
                },
                {
                    value: 'HİSSELİ',
                    label: 'Hisseli',
                    symbol: {
                        type: 'simple-fill',
                        color: [255, 170, 224, 0.28],
                        outline: { color: [232, 90, 186, 0.95], width: 1 },
                    },
                },
                {
                    value: 'HATALI',
                    label: 'Hatalı',
                    symbol: {
                        type: 'simple-fill',
                        color: [148, 163, 184, 0.22],
                        outline: { color: [100, 116, 139, 0.90], width: 0.8 },
                    },
                },
            ],
        };
        const belediyeKatman = new MapImageLayer({
            url: ABB + '/tasinmaz/MapServer',
            sublayers: [{ id: 0, visible: true, renderer: belediyeRenderer }],
            opacity: 0.80,
            visible: false,
        });

        const parselKatman = new GraphicsLayer({ title: 'Taşınmaz Parselleri', opacity: 0.70 });
        const sorguKatman = new GraphicsLayer({ title: 'Sorgu Sonucu' });

        const harita = new EsriMap({
            basemap: altlikBasemap(aktifAltlikId),
            layers: [imarKatman, parselasyonKatman, belediyeKatman, parselKatman, sorguKatman],
        });

        const view = new MapView({
            container: 'hrm-map',
            map: harita,
            center: [35.0, 39.0],
            zoom: 6,
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
         * ========================================================= */
        function katmanKontrolKur(kutuId, kaydiriciId, katman) {
            const kutu = document.getElementById(kutuId);
            const kaydirici = document.getElementById(kaydiriciId);
            const kart = kutu ? kutu.closest('.hrm-katman-kart') : null;
            const deger = document.querySelector('.hrm-opaklik-deger[data-hedef="' + kaydiriciId + '"]');

            if (kutu) {
                katman.visible = kutu.checked;
                if (kart) kart.dataset.aktif = kutu.checked ? '1' : '0';
                kutu.addEventListener('change', function () {
                    katman.visible = kutu.checked;
                    if (kart) kart.dataset.aktif = kutu.checked ? '1' : '0';
                });
            }
            if (kaydirici) {
                katman.opacity = kaydirici.value / 100;
                if (deger) deger.textContent = kaydirici.value + '%';
                kaydirici.addEventListener('input', function () {
                    katman.opacity = kaydirici.value / 100;
                    if (deger) deger.textContent = kaydirici.value + '%';
                });
            }
        }

        katmanKontrolKur('hrm-ov-parsel', 'hrm-ov-parsel-op', parselKatman);
        katmanKontrolKur('hrm-ov-imar', 'hrm-ov-imar-op', imarKatman);
        katmanKontrolKur('hrm-ov-parselasyon', 'hrm-ov-parselasyon-op', parselasyonKatman);
        katmanKontrolKur('hrm-ov-belediye', 'hrm-ov-belediye-op', belediyeKatman);

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

        async function parselleriYukle() {
            let fc;
            try {
                fc = await jsonGetir(API.geojson);
            } catch (e) {
                sayiEl.textContent = '0';
                return;
            }

            parselKatman.removeAll();
            tumExtent = null;

            (fc.features || []).forEach(function (f) {
                const geom = geoJsonGeometri(f.geometry);
                if (!geom) return;
                const p = f.properties || {};

                parselKatman.add(new Graphic({
                    geometry: geom,
                    symbol: geom.type === 'point' ? SEM_NOKTA : SEM_PARSEL,
                    attributes: p,
                    popupTemplate: {
                        title: (p.ada || '—') + ' / ' + (p.parsel || '—'),
                        content: function () { return tasinmazPopup(p); },
                    },
                }));

                const e = geometriExtent(geom);
                if (e) tumExtent = tumExtent ? tumExtent.union(e) : e;
            });

            sayiEl.textContent = String(parselKatman.graphics.length);
            if (tumExtent && parselKatman.graphics.length) {
                view.goTo(tumExtent.expand(1.25)).catch(function () {});
            }
        }

        view.when(function () { parselleriYukle(); });

        document.getElementById('hrm-fit-btn').addEventListener('click', function () {
            if (!tumExtent) return;
            view.goTo(tumExtent.expand(1.25)).catch(function () {});
        });

        /* =========================================================
         * 8) PANELLER — aç/kapat + sürükle
         * ========================================================= */
        function panelKur(btnId, panelId) {
            const btn = document.getElementById(btnId);
            const panel = document.getElementById(panelId);
            if (!btn || !panel) return;
            btn.addEventListener('click', function () {
                const acilacak = panel.hidden;
                panel.hidden = !acilacak;
                btn.setAttribute('aria-expanded', acilacak ? 'true' : 'false');
                btn.classList.toggle('is-aktif', acilacak);
            });
        }
        panelKur('hrm-katman-btn', 'hrm-panel');
        panelKur('hrm-adaparsel-btn', 'hrm-adaparsel-panel');
        panelKur('hrm-arama-btn', 'hrm-arama-panel');

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

        function tkgmPopupGovde(ozellik, eimarDurum) {
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
                '<div id="hrm-eimar-kutu">' + eimarDurum + '</div>';
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
                    ['Daire Sayısı', d.daire_sayisi],
                    ['Fonksiyon', d.fonksiyon],
                    ['Plan Notu', d.plan_notu],
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
         * e-İmar sorgusunu yapar, popup içindeki kutuyu doldurur ve plan adası
         * geometrisini haritaya işler. Hem "Bilgi Sorgu" hem "Ada/Parsel" yolu kullanır.
         * Ankara kapsama alanı dışında hata döner — bu bir arıza değil, dipnot olarak gösterilir.
         */
        async function eimarYukle(kap, lat, lng) {
            let kutu = kap && kap.querySelector ? kap.querySelector('#hrm-eimar-kutu') : null;
            if (!kutu) kutu = document.querySelector('#hrm-eimar-kutu');
            if (!kutu) return;
            kutu.innerHTML = popYukleniyor('e-İmar sorgulanıyor…');
            try {
                const eimar = await jsonGetir(API.eimar + '?lat=' + lat + '&lng=' + lng);
                kutu.innerHTML = eimarGovde(eimar);
                const eGeom = geoJsonGeometri(eimar.geometry);
                if (eGeom) sorguKatman.add(new Graphic({ geometry: eGeom, symbol: SEM_EIMAR }));
            } catch (e) {
                kutu.innerHTML = '<div class="hrm-pop-dipnot">e-İmar: ' + kacis(e.message) + '</div>';
            }
        }

        view.on('click', async function (evt) {
            if (!bilgiModu) return;
            evt.stopPropagation();

            const cografi = webMercatorUtils.webMercatorToGeographic(evt.mapPoint);
            const lat = cografi.latitude.toFixed(6);
            const lng = cografi.longitude.toFixed(6);

            sorguKatman.removeAll();
            popupAc(evt.mapPoint, 'Parsel Bilgisi', popYukleniyor('TKGM sorgulanıyor…'));

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

            await eimarYukle(kap, lat, lng);
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

                const e = geometriExtent(geom);
                if (e) await view.goTo(e.expand(1.6)).catch(function () {});

                // Bilgi sorgu ile aynı: görünüm noktasından WGS84 lat/lng → e-İmar identify
                const nokta = parselNoktasi(geom);
                const ll = cografiLatLng(nokta);

                const kap = document.createElement('div');
                kap.className = 'hrm-pop';
                kap.innerHTML = tkgmPopupGovde(veri.properties, popYukleniyor('e-İmar sorgulanıyor…'));
                popupAc(nokta || (e && e.center), 'Parsel Bilgisi', kap);

                apMesajVer('Parsel bulundu ve haritaya işlendi.', 'ok');

                if (ll) {
                    await eimarYukle(kap, ll.lat, ll.lng);
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
