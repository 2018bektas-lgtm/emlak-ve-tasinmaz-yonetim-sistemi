/* ------------------------------------------------------------------
 * KİRA HARİTA KUTUSU — form ve bilgi kartlarına gömülen küçük harita
 *
 * Kira haritasının (harita-esri.js) küçük kardeşi: aynı ArcGIS 4.30
 * yığınını ve aynı Google altlığını kullanır, ama tek bir kaydın sınırını
 * gösterir / çizdirir.
 *
 * Kullanım: sayfaya <div class="kmh" data-kmh='{...}'></div> koymak yeterli.
 * Yapılandırma anahtarları:
 *   duzenlenir  : çizim araçları açılsın mı
 *   hedef       : GeoJSON'un yazılacağı gizli input seçicisi (düzenlenirse)
 *   geometri    : açılışta çizilecek GeoJSON
 *   ekler       : [{geometri, renk, ad}] — komşu kayıtlar, soluk çizilir
 *   tkgm        : /panel/ajax/tkgm-parsel-adaparsel (ada/parselden getir)
 *   tkgmNokta   : /panel/ajax/tkgm-parsel (haritada tıklayarak parsel al)
 *   alanlar     : {mahalle, ada, parsel, yuzolcum} form alanı seçicileri
 *   buyukHarita : "Büyük haritada aç" bağlantısı
 * ------------------------------------------------------------------ */
(function () {
    const kutular = document.querySelectorAll('[data-kmh]');
    if (!kutular.length || typeof require !== 'function') return;

    require([
        'esri/Map',
        'esri/Basemap',
        'esri/views/MapView',
        'esri/layers/WebTileLayer',
        'esri/layers/GraphicsLayer',
        'esri/Graphic',
        'esri/geometry/Extent',
        'esri/geometry/geometryEngine',
        'esri/geometry/support/webMercatorUtils',
        'esri/widgets/Sketch/SketchViewModel',
    ], function (EsriMap, Basemap, MapView, WebTileLayer, GraphicsLayer, Graphic,
                 Extent, geometryEngine, webMercatorUtils, SketchViewModel) {

        const WGS84 = { wkid: 4326 };
        const ALTLIK = {
            uydu: { ad: 'Uydu', lyrs: 'y' },
            yol: { ad: 'Yol', lyrs: 'm' },
        };

        function googleAltlik(lyrs) {
            return new Basemap({
                baseLayers: [new WebTileLayer({
                    urlTemplate: 'https://{subDomain}.google.com/vt/lyrs=' + lyrs + '&x={col}&y={row}&z={level}',
                    subDomains: ['mt0', 'mt1', 'mt2', 'mt3'],
                    copyright: 'Google',
                })],
            });
        }

        function kacis(d) {
            return String(d === null || d === undefined ? '' : d).replace(/[&<>"']/g, function (k) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[k];
            });
        }

        /** GeoJSON → Esri geometri. Düz halka dizisi de kabul edilir. */
        function esriGeometri(g) {
            if (!g) return null;
            if (typeof g === 'string') {
                try { g = JSON.parse(g); } catch (e) { return null; }
            }
            if (Array.isArray(g) && Array.isArray(g[0])) {
                g = { type: 'Polygon', coordinates: [g] };
            }
            if (!g || !g.type) return null;

            if (g.type === 'Point') {
                return { type: 'point', longitude: g.coordinates[0], latitude: g.coordinates[1], spatialReference: WGS84 };
            }
            if (g.type === 'Polygon') {
                return { type: 'polygon', rings: g.coordinates, spatialReference: WGS84 };
            }
            if (g.type === 'MultiPolygon') {
                const halkalar = [];
                g.coordinates.forEach(function (p) { p.forEach(function (h) { halkalar.push(h); }); });
                return { type: 'polygon', rings: halkalar, spatialReference: WGS84 };
            }
            if (g.type === 'LineString') {
                return { type: 'polyline', paths: [g.coordinates], spatialReference: WGS84 };
            }
            return null;
        }

        /** Esri geometri → GeoJSON ([lng, lat]) */
        function geoJson(geom) {
            if (!geom) return null;
            const g = geom.spatialReference && geom.spatialReference.isWGS84
                ? geom
                : webMercatorUtils.webMercatorToGeographic(geom);
            if (g.type === 'point') return { type: 'Point', coordinates: [g.longitude, g.latitude] };
            if (g.type === 'polyline') return { type: 'LineString', coordinates: (g.paths || [[]])[0] };
            return { type: 'Polygon', coordinates: (g.rings || []).map(function (h) {
                return h.map(function (n) { return [n[0], n[1]]; });
            }) };
        }

        function dolgu(renk) {
            return { type: 'simple-fill', color: renk + '2e', outline: { color: renk, width: 2 } };
        }

        function nokta(renk) {
            return { type: 'simple-marker', style: 'circle', size: 11, color: renk, outline: { color: '#fff', width: 2 } };
        }

        function sembol(geom, renk) {
            return geom.type === 'point' ? nokta(renk) : dolgu(renk);
        }

        function m2(geom) {
            try {
                const d = Math.abs(geometryEngine.geodesicArea(geom, 'square-meters'));
                return d > 0 ? d : null;
            } catch (e) {
                return null;
            }
        }

        function sayi(d, ondalik) {
            return Number(d).toLocaleString('tr-TR', {
                minimumFractionDigits: ondalik === undefined ? 2 : ondalik,
                maximumFractionDigits: ondalik === undefined ? 2 : ondalik,
            });
        }

        /**
         * Kutuyu kurar. Gizli bir sekmedeyse (genişlik 0) ArcGIS görünümü
         * boyutsuz doğar ve boş kalır; bu yüzden kurulum görünür olmaya
         * ertelenir.
         */
        function kurulumuZamanla(kutu) {
            if (kutu.offsetWidth > 0) {
                kur(kutu);

                return;
            }
            const gozcu = new IntersectionObserver(function (girisler) {
                if (girisler.some(function (g) { return g.isIntersecting; })) {
                    gozcu.disconnect();
                    kur(kutu);
                }
            });
            gozcu.observe(kutu);
        }

        function kur(kutu) {
            let ayar = {};
            try { ayar = JSON.parse(kutu.dataset.kmh || '{}'); } catch (e) { ayar = {}; }

            const duzenlenir = !!ayar.duzenlenir;
            const hedef = ayar.hedef ? document.querySelector(ayar.hedef) : null;
            const alanlar = ayar.alanlar || {};
            const el = function (secici) { return secici ? document.querySelector(secici) : null; };

            /* ---- İskelet ---- */
            kutu.innerHTML =
                '<div class="kmh-arac">' +
                    (duzenlenir
                        ? '<button type="button" class="kmh-btn" data-kmh-ciz>' +
                              '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7l8-4 8 4-2 12-6 2-6-2z"/></svg>' +
                              'Sınır Çiz</button>' +
                          '<button type="button" class="kmh-btn" data-kmh-duzenle>' +
                              '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>' +
                              'Köşeleri Taşı</button>' +
                          (ayar.tkgm
                              ? '<button type="button" class="kmh-btn" data-kmh-tkgm>' +
                                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg>' +
                                    'Ada/Parselden Getir</button>'
                              : '') +
                          (ayar.tkgmNokta
                              ? '<button type="button" class="kmh-btn" data-kmh-sec>' +
                                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>' +
                                    'Haritadan Parsel Seç</button>'
                              : '') +
                          '<button type="button" class="kmh-btn" data-kmh-geri>' +
                              '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/></svg>' +
                              'Geri Al</button>' +
                          '<button type="button" class="kmh-btn is-tehlike" data-kmh-temizle>' +
                              '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>' +
                              'Temizle</button>'
                        : '') +
                    '<span class="kmh-ara"></span>' +
                    '<button type="button" class="kmh-btn" data-kmh-sigdir>' +
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>' +
                        'Sığdır</button>' +
                    '<button type="button" class="kmh-btn" data-kmh-altlik>Yol</button>' +
                    (ayar.buyukHarita
                        ? '<a class="kmh-btn" href="' + kacis(ayar.buyukHarita) + '" target="_blank" rel="noopener">Büyük Harita ↗</a>'
                        : '') +
                '</div>' +
                '<div class="kmh-tuval"></div>' +
                '<div class="kmh-ayak"><span data-kmh-durum>' +
                    (duzenlenir ? 'Sınır çizebilir ya da ada/parselden getirebilirsiniz.' : 'Kayıtlı sınır') +
                '</span><strong data-kmh-alan></strong></div>';

            const tuval = kutu.querySelector('.kmh-tuval');
            const durumEl = kutu.querySelector('[data-kmh-durum]');
            const alanEl = kutu.querySelector('[data-kmh-alan]');

            function durum(metin, tur) {
                durumEl.textContent = metin;
                durumEl.className = 'kmh-durum' + (tur ? ' is-' + tur : '');
            }

            /* ---- Harita ---- */
            let altlikAdi = 'uydu';
            const harita = new EsriMap({ basemap: googleAltlik(ALTLIK.uydu.lyrs) });
            const ekKatman = new GraphicsLayer();      // komşu kayıtlar
            const anaKatman = new GraphicsLayer();     // bu kaydın sınırı
            harita.addMany([ekKatman, anaKatman]);

            const view = new MapView({
                container: tuval,
                map: harita,
                center: ayar.merkez || [32.8597, 39.9334],
                zoom: ayar.zoom || 13,
                constraints: { rotationEnabled: false },
                ui: { components: ['zoom'] },
                popup: { dockEnabled: false, autoOpenEnabled: false },
            });

            /* ---- Komşu kayıtlar ---- */
            (ayar.ekler || []).forEach(function (e) {
                const g = esriGeometri(e.geometri);
                if (!g) return;
                ekKatman.add(new Graphic({
                    geometry: g,
                    symbol: g.type === 'point'
                        ? nokta(e.renk || '#94a3b8')
                        : { type: 'simple-fill', color: (e.renk || '#94a3b8') + '1f', outline: { color: e.renk || '#94a3b8', width: 1.4, style: 'short-dash' } },
                }));
            });

            /* ---- Ana sınır ---- */
            const RENK = '#0ea5e9';
            let gecmis = [];

            function mevcutGeometri() {
                return anaKatman.graphics.length ? anaKatman.graphics.getItemAt(0).geometry : null;
            }

            function hedefeYaz(geom) {
                if (!hedef) return;
                hedef.value = geom ? JSON.stringify(geoJson(geom)) : '';
            }

            function alaniYaz(geom) {
                const d = geom && geom.type !== 'point' ? m2(geom) : null;
                alanEl.textContent = d ? sayi(d) + ' m²' : '';
                return d;
            }

            function ciz(geom, gecmiseEkle) {
                if (gecmiseEkle !== false) {
                    const onceki = mevcutGeometri();
                    gecmis.push(onceki ? onceki.clone() : null);
                    if (gecmis.length > 20) gecmis.shift();
                }
                anaKatman.removeAll();
                if (geom) {
                    anaKatman.add(new Graphic({ geometry: geom, symbol: sembol(geom, RENK) }));
                }
                hedefeYaz(geom);
                alaniYaz(geom);
            }

            function sigdir() {
                const hepsi = anaKatman.graphics.concat(ekKatman.graphics);
                let kapsam = null;
                hepsi.forEach(function (g) {
                    const e = g.geometry.extent
                        || new Extent({ xmin: g.geometry.longitude, ymin: g.geometry.latitude, xmax: g.geometry.longitude, ymax: g.geometry.latitude, spatialReference: WGS84 });
                    kapsam = kapsam ? kapsam.union(e) : e.clone();
                });
                if (!kapsam) return;
                if (kapsam.width === 0 && kapsam.height === 0) {
                    view.goTo({ center: [kapsam.xmin, kapsam.ymin], zoom: 18 }).catch(function () {});
                    return;
                }
                view.goTo(kapsam.expand(1.5)).catch(function () {});
            }

            const baslangic = esriGeometri(ayar.geometri);
            if (baslangic) {
                ciz(baslangic, false);
                view.when(function () { sigdir(); });
            } else if (ayar.ekler && ayar.ekler.length) {
                view.when(function () { sigdir(); });
            } else if (!duzenlenir) {
                durum('Bu kayda bağlı sınır yok.', 'bos');
            }

            kutu.querySelector('[data-kmh-sigdir]').addEventListener('click', sigdir);

            const altlikBtn = kutu.querySelector('[data-kmh-altlik]');
            altlikBtn.addEventListener('click', function () {
                altlikAdi = altlikAdi === 'uydu' ? 'yol' : 'uydu';
                harita.basemap = googleAltlik(ALTLIK[altlikAdi].lyrs);
                altlikBtn.textContent = altlikAdi === 'uydu' ? 'Yol' : 'Uydu';
            });

            if (!duzenlenir) return;

            /* ---- Çizim ---- */
            const svm = new SketchViewModel({
                view: view,
                layer: anaKatman,
                updateOnGraphicClick: false,
                defaultCreateOptions: { mode: 'click' },
                polygonSymbol: dolgu(RENK),
                pointSymbol: nokta(RENK),
            });

            let parselSecme = false;

            function araclariBirak() {
                svm.cancel();
                parselSecme = false;
                kutu.querySelectorAll('.kmh-btn').forEach(function (b) { b.classList.remove('is-aktif'); });
                tuval.classList.remove('kmh-imlec');
            }

            svm.on('create', function (olay) {
                if (olay.state === 'complete') {
                    araclariBirak();
                    // Sketch grafiği katmana kendi ekliyor; geçmiş ve input için yeniden kur
                    const geom = olay.graphic.geometry;
                    anaKatman.remove(olay.graphic);
                    ciz(geom);
                    durum('Sınır çizildi.', 'tamam');
                }
            });

            svm.on('update', function (olay) {
                if (olay.state !== 'complete') return;
                const g = olay.graphics[0];
                if (g) {
                    hedefeYaz(g.geometry);
                    alaniYaz(g.geometry);
                    durum('Sınır güncellendi.', 'tamam');
                }
                araclariBirak();
            });

            kutu.querySelector('[data-kmh-ciz]').addEventListener('click', function () {
                const aktifti = this.classList.contains('is-aktif');
                araclariBirak();
                if (aktifti) return;
                this.classList.add('is-aktif');
                ciz(null);
                svm.create('polygon');
                durum('Köşelere tıklayın, bitirmek için çift tıklayın.');
            });

            kutu.querySelector('[data-kmh-duzenle]').addEventListener('click', function () {
                const g = anaKatman.graphics.getItemAt(0);
                if (!g) { durum('Önce bir sınır çizin ya da getirin.', 'hata'); return; }
                const aktifti = this.classList.contains('is-aktif');
                araclariBirak();
                if (aktifti) return;
                this.classList.add('is-aktif');
                gecmis.push(g.geometry.clone());
                svm.update([g], { tool: 'reshape' });
                durum('Köşeleri sürükleyin; boş alana tıklayınca kaydedilir.');
            });

            kutu.querySelector('[data-kmh-geri]').addEventListener('click', function () {
                if (!gecmis.length) { durum('Geri alınacak işlem yok.', 'hata'); return; }
                araclariBirak();
                ciz(gecmis.pop(), false);
                durum('Son işlem geri alındı.');
            });

            kutu.querySelector('[data-kmh-temizle]').addEventListener('click', function () {
                araclariBirak();
                ciz(null);
                durum('Sınır kaldırıldı.', 'bos');
            });

            /* ---- TKGM: ada/parselden getir ---- */
            const tkgmBtn = kutu.querySelector('[data-kmh-tkgm]');
            if (tkgmBtn) {
                tkgmBtn.addEventListener('click', async function () {
                    araclariBirak();
                    const mahalle = el(alanlar.mahalle);
                    const ada = el(alanlar.ada);
                    const parsel = el(alanlar.parsel);
                    const secili = mahalle && mahalle.selectedOptions[0];
                    const tkgmId = secili ? (secili.dataset.tkgm || '') : '';

                    if (!tkgmId) { durum('Önce mahalle seçin (mahallenin TKGM kaydı gerekli).', 'hata'); return; }
                    if (!ada || !parsel || !ada.value.trim() || !parsel.value.trim()) {
                        durum('Ada ve parsel girilmeli.', 'hata');
                        return;
                    }

                    durum('TKGM sorgulanıyor…');
                    try {
                        const url = ayar.tkgm + '/' + encodeURIComponent(tkgmId) + '/' + encodeURIComponent(ada.value.trim()) + '/' + encodeURIComponent(parsel.value.trim());
                        const yanit = await fetch(url, { headers: { Accept: 'application/json' } });
                        const veri = await yanit.json();
                        if (!yanit.ok || !veri.geometry) {
                            durum(veri.hata || 'Parsel bulunamadı.', 'hata');
                            return;
                        }
                        const geom = esriGeometri(veri.geometry);
                        if (!geom) { durum('Parsel geometrisi okunamadı.', 'hata'); return; }
                        ciz(geom);
                        sigdir();

                        const yuzolcum = el(alanlar.yuzolcum);
                        const olculen = alaniYaz(geom);
                        if (yuzolcum && !yuzolcum.value && olculen) yuzolcum.value = olculen.toFixed(2);
                        durum('Parsel TKGM’den getirildi.', 'tamam');
                    } catch (e) {
                        durum('TKGM sorgusu başarısız: ' + e.message, 'hata');
                    }
                });
            }

            /* ---- TKGM: haritadan parsel seç ---- */
            const secBtn = kutu.querySelector('[data-kmh-sec]');
            if (secBtn) {
                secBtn.addEventListener('click', function () {
                    const aktifti = this.classList.contains('is-aktif');
                    araclariBirak();
                    if (aktifti) return;
                    this.classList.add('is-aktif');
                    parselSecme = true;
                    tuval.classList.add('kmh-imlec');
                    durum('Haritada parselin üstüne tıklayın.');
                });

                view.on('click', async function (olay) {
                    if (!parselSecme) return;
                    const n = olay.mapPoint;
                    const cg = n.spatialReference.isWGS84 ? n : webMercatorUtils.webMercatorToGeographic(n);
                    araclariBirak();
                    durum('TKGM sorgulanıyor…');
                    try {
                        const url = ayar.tkgmNokta + '/' + cg.latitude.toFixed(7) + '/' + cg.longitude.toFixed(7);
                        const yanit = await fetch(url, { headers: { Accept: 'application/json' } });
                        const veri = await yanit.json();
                        if (!yanit.ok || !veri.geometry) {
                            durum(veri.hata || 'Bu noktada parsel yok.', 'hata');
                            return;
                        }
                        const geom = esriGeometri(veri.geometry);
                        if (!geom) { durum('Parsel geometrisi okunamadı.', 'hata'); return; }
                        ciz(geom);
                        sigdir();

                        // Ada / parsel / yüzölçüm alanları boşsa TKGM değerleriyle doldurulur
                        const o = veri.properties || {};
                        const ada = el(alanlar.ada);
                        const parsel = el(alanlar.parsel);
                        const yuzolcum = el(alanlar.yuzolcum);
                        if (ada && !ada.value && o.adaNo) ada.value = o.adaNo;
                        if (parsel && !parsel.value && o.parselNo) parsel.value = o.parselNo;
                        const olculen = alaniYaz(geom);
                        if (yuzolcum && !yuzolcum.value && olculen) yuzolcum.value = olculen.toFixed(2);

                        durum('Parsel seçildi: ' + (o.adaNo || '—') + '/' + (o.parselNo || '—'), 'tamam');
                    } catch (e) {
                        durum('TKGM sorgusu başarısız: ' + e.message, 'hata');
                    }
                });
            }
        }

        kutular.forEach(kurulumuZamanla);
    });
})();
