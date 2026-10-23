/* ==========================================================================
   ETYS · Ecrimisil işgal alanı çizimi (oluştur + düzenle ortak)
   Leaflet üzerinde kendi poligon motoru (leaflet.draw kullanmaz).
   Ayarlar #ec-harita üzerindeki data-* özniteliklerinden okunur.

   Taşınmaz bağlama kuralı:
   - Alan çizilince/değişince, alanla örtüşen taşınmaz aranır ve bağlanır.
   - Önceden bağlı bir taşınmaz varsa ve yeni alanla örtüşen taşınmaz
     bulunamazsa bağlantı KORUNUR, yalnızca uyarı verilir (önceden sessizce
     siliniyordu).
   - Sayfa açılışında kayıtlı alan yüklenirken arama yapılmaz.
   - "Bağlantıyı kaldır" ile bilerek boş bırakılırsa sunucu da yeniden
     eşlemez (tasinmaz_elle=1).
   ========================================================================== */
(function () {
    'use strict';

    const haritaEl = document.getElementById('ec-harita');
    if (!haritaEl || typeof L === 'undefined') return;

    const ds = haritaEl.dataset;
    const API_BUL = ds.apiBul;
    const API_MEVCUT = ds.apiMevcut;
    const HARIC_ID = ds.haric || null;
    const CSRF = ds.csrf;
    const json = s => { try { return s ? JSON.parse(s) : null; } catch (e) { return null; } };
    const eskiKoordinat = json(ds.eski);
    const refKoordinat = json(ds.ref);

    const kart = document.getElementById('ec-harita-kart');
    const koordinatInput = document.getElementById('ec-koordinat');
    const latInput = document.getElementById('ec-lat');
    const lngInput = document.getElementById('ec-lng');
    const durumEl = document.getElementById('ec-mt-durum');
    const ipucuEl = document.getElementById('ec-ipucu');
    const btnPoligon = document.getElementById('ec-btn-poligon');
    const btnSil = document.getElementById('ec-btn-sil');
    const btnKonum = document.getElementById('ec-btn-konum');
    const btnTam = document.getElementById('ec-btn-tam-ekran');
    const tsnInput = document.getElementById('tasinmaz_id');
    const tsnElle = document.getElementById('tasinmaz_elle');
    const tsnBilgi = document.getElementById('ec-tsn-bilgi');
    const tsnKaldir = document.getElementById('ec-tsn-kaldir');

    const RENK = { cizim: '#ff6a3d', ref: '#facc15' };
    // Durum renkleri corporate.css ':root' içinde tek yerde durur; harita
    // lejantı ve işaretçileri de aynı değeri okur.
    const kokStil = getComputedStyle(document.documentElement);
    const durumRengi = d => kokStil.getPropertyValue('--durum-' + d).trim();
    const MEVCUT_RENK = { 'yeni': durumRengi('yeni'), 'devam-eden': durumRengi('devam-eden'), 'sonlanmis': durumRengi('sonlanmis') };

    // ---------- Harita ----------
    const harita = L.map(haritaEl, { zoomControl: false, attributionControl: false, doubleClickZoom: false })
        .setView([39.9334, 32.8597], 12);
    L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { subdomains: ['mt0', 'mt1', 'mt2', 'mt3'], maxZoom: 20 }).addTo(harita);
    L.control.zoom({ position: 'bottomright' }).addTo(harita);

    // Bağlı taşınmazın sınırı (referans)
    let refKatman = null;
    function refCiz(g, lat, lng) {
        if (refKatman) { harita.removeLayer(refKatman); refKatman = null; }
        const stil = { color: RENK.ref, fillColor: RENK.ref, fillOpacity: .08, weight: 2, dashArray: '6 5', interactive: false };
        if (typeof g === 'string') g = json(g);
        if (g && g.type === 'Polygon') refKatman = L.geoJSON({ type: 'Feature', geometry: g }, { style: stil }).addTo(harita);
        else if (Array.isArray(g) && Array.isArray(g[0])) refKatman = L.polygon(g.map(p => [p[1], p[0]]), stil).addTo(harita);
        else if (lat && lng) harita.setView([lat, lng], 17);
        return refKatman;
    }
    if (refKoordinat) {
        const l = refCiz(refKoordinat.koordinat, refKoordinat.lat, refKoordinat.lng);
        if (l) harita.fitBounds(l.getBounds(), { padding: [30, 30] });
    }

    // ---------- Çizim durumu ----------
    const cizim = { modu: 'idle', noktalar: [], polygon: null, tempLine: null, tempMarkerlar: [], vertexMarkerlar: [], edgeMarkerlar: [], farePos: null };
    let yukleniyor = false;

    function ipucu(m, aktif = false) { ipucuEl.textContent = m; ipucuEl.classList.toggle('is-aktif', aktif); }

    function modaGir(m) {
        temizleTemp(); temizleVertex();
        cizim.modu = m;
        btnPoligon.classList.toggle('is-aktif', m === 'cizim');
        haritaEl.classList.toggle('ec-mod-cizim', m === 'cizim');
        if (m === 'cizim') {
            ipucu('Haritaya tıklayarak köşe ekleyin · ilk noktaya ya da çift tıklayarak kapatın · Esc ile vazgeçin', true);
            if (cizim.polygon) { harita.removeLayer(cizim.polygon); cizim.polygon = null; btnSil.hidden = true; }
            cizim.noktalar = [];
        } else if (m === 'duzenle') {
            if (!cizim.polygon) { modaGir('idle'); return; }
            ipucu('Köşeleri sürükleyin · kenar ortasındaki noktayı sürükleyerek köşe ekleyin · köşeye çift tıklayarak silin', true);
            vertexCiz();
        } else {
            ipucu(cizim.polygon ? 'Alan hazır — düzenlemek için üzerine tıklayın.' : '“Alan Çiz”e basıp haritaya tıklayarak köşe ekleyin; ilk noktaya ya da çift tıklayarak kapatın.');
        }
    }

    function temizleTemp() {
        if (cizim.tempLine) { harita.removeLayer(cizim.tempLine); cizim.tempLine = null; }
        cizim.tempMarkerlar.forEach(m => harita.removeLayer(m));
        cizim.tempMarkerlar = [];
    }
    function temizleVertex() {
        cizim.vertexMarkerlar.concat(cizim.edgeMarkerlar).forEach(m => harita.removeLayer(m));
        cizim.vertexMarkerlar = [];
        cizim.edgeMarkerlar = [];
    }
    const ikon = (cls, boy) => L.divIcon({ className: 'ec-vertex-wrap', html: `<div class="ec-vertex ${cls}"></div>`, iconSize: [boy, boy], iconAnchor: [boy / 2, boy / 2] });

    function noktaEkle(latlng) {
        cizim.noktalar.push(latlng);
        const idx = cizim.noktalar.length - 1;
        const m = L.marker(latlng, { icon: idx === 0 ? ikon('ec-vertex-first', 16) : ikon('', 12), keyboard: false }).addTo(harita);
        m.on('click', e => { L.DomEvent.stopPropagation(e); if (idx === 0 && cizim.noktalar.length >= 3) polygonKapat(); });
        cizim.tempMarkerlar.push(m);
        onizleCiz();
    }

    function onizleCiz() {
        if (cizim.tempLine) harita.removeLayer(cizim.tempLine);
        const pts = cizim.noktalar.slice();
        if (cizim.farePos && cizim.modu === 'cizim') pts.push(cizim.farePos);
        if (pts.length < 2) return;
        cizim.tempLine = L.polyline(pts, { color: RENK.cizim, weight: 2.5, dashArray: '5 5', interactive: false }).addTo(harita);
    }

    function polygonKapat() {
        if (cizim.noktalar.length < 3) return;
        const pts = cizim.noktalar.slice();
        temizleTemp();
        cizim.noktalar = [];
        cizim.polygon = L.polygon(pts, { color: RENK.cizim, weight: 2.5, fillColor: RENK.cizim, fillOpacity: .26 }).addTo(harita);
        cizim.polygon.on('click', e => { L.DomEvent.stopPropagation(e); if (cizim.modu !== 'duzenle') modaGir('duzenle'); });
        btnSil.hidden = false;
        formuGuncelle();
        modaGir(yukleniyor ? 'idle' : 'duzenle');
    }

    function polygonSil() {
        if (cizim.polygon) { harita.removeLayer(cizim.polygon); cizim.polygon = null; }
        temizleTemp(); temizleVertex();
        cizim.noktalar = [];
        btnSil.hidden = true;
        koordinatInput.value = ''; latInput.value = ''; lngInput.value = '';
        durumEl.innerHTML = 'Alan: <b>—</b>';
        durumEl.classList.remove('is-var');
        // Taşınmaz bağlantısı korunur — alan silmek parselden kopmak demek değil
        modaGir('idle');
    }

    function vertexCiz() {
        temizleVertex();
        if (!cizim.polygon) return;
        const halka = cizim.polygon.getLatLngs()[0];
        halka.forEach((ll, i) => {
            const m = L.marker(ll, { draggable: true, icon: ikon('ec-vertex-edit', 14) }).addTo(harita);
            m.on('drag', e => { halka[i] = e.target.getLatLng(); cizim.polygon.setLatLngs([halka]); edgeGuncelle(); });
            m.on('dragend', formuGuncelle);
            m.on('dblclick', e => {
                L.DomEvent.stopPropagation(e);
                if (halka.length <= 3) { ipucu('Bir alanın en az 3 köşesi olmalı.', true); return; }
                halka.splice(i, 1); cizim.polygon.setLatLngs([halka]); vertexCiz(); formuGuncelle();
            });
            cizim.vertexMarkerlar.push(m);
        });
        edgeGuncelle();
    }

    function edgeGuncelle() {
        cizim.edgeMarkerlar.forEach(m => harita.removeLayer(m));
        cizim.edgeMarkerlar = [];
        if (!cizim.polygon) return;
        const halka = cizim.polygon.getLatLngs()[0];
        for (let i = 0; i < halka.length; i++) {
            const a = halka[i], b = halka[(i + 1) % halka.length];
            const idx = i + 1;
            const m = L.marker(L.latLng((a.lat + b.lat) / 2, (a.lng + b.lng) / 2), { draggable: true, icon: ikon('ec-vertex-edge', 10) }).addTo(harita);
            m.on('dragstart', () => { halka.splice(idx, 0, m.getLatLng()); cizim.polygon.setLatLngs([halka]); });
            m.on('drag', e => { halka[idx] = e.target.getLatLng(); cizim.polygon.setLatLngs([halka]); });
            m.on('dragend', () => { vertexCiz(); formuGuncelle(); });
            cizim.edgeMarkerlar.push(m);
        }
    }

    // ---------- Form alanları + alan hesabı ----------
    function formuGuncelle() {
        if (!cizim.polygon) return;
        const halka = cizim.polygon.getLatLngs()[0];
        const coords = halka.map(p => [+p.lng.toFixed(8), +p.lat.toFixed(8)]);
        coords.push([coords[0][0], coords[0][1]]);
        koordinatInput.value = JSON.stringify({ type: 'Polygon', coordinates: [coords] });
        const merkez = cizim.polygon.getBounds().getCenter();
        latInput.value = merkez.lat.toFixed(7);
        lngInput.value = merkez.lng.toFixed(7);
        durumEl.innerHTML = 'Alan: <b>' + alanBiciminde(geodesikAlan(halka)) + '</b>';
        durumEl.classList.add('is-var');
        if (!yukleniyor) tasinmazEsleGecikmeli(coords.slice(0, -1));
    }

    // ---------- Taşınmaz eşleme ----------
    let esleZamanlayici = null;
    let esleSira = 0;
    function tasinmazEsleGecikmeli(noktalar) {
        clearTimeout(esleZamanlayici);
        esleZamanlayici = setTimeout(() => tasinmazEsle(noktalar), 450);
    }

    async function tasinmazEsle(noktalar) {
        if (!noktalar || noktalar.length < 3) return;
        const sira = ++esleSira;
        const oncekiId = tsnInput.value;
        // Bağlı taşınmaz varsa kartı silme; arama notu altına eklenir
        if (oncekiId) tsnBilgiSakla('arama', 'Alanla örtüşen taşınmaz aranıyor…');
        else tsnBilgiGuncelle('arama', 'Alanla örtüşen taşınmaz aranıyor…');
        try {
            const r = await fetch(API_BUL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ koordinatlar: noktalar }),
            });
            if (!r.ok) throw new Error(r.status);
            const veri = await r.json();
            if (sira !== esleSira) return; // daha yeni bir arama başladı
            if (veri.bulundu && veri.tasinmaz) {
                tsnInput.value = veri.tasinmaz.id;
                tsnElle.value = '0';
                tsnBilgiGuncelle('bulundu', veri.tasinmaz);
            } else if (oncekiId) {
                tsnBilgiSakla('uyari', 'Çizilen alan bağlı taşınmazla örtüşmüyor — bağlantı korunuyor. Gerekirse kaldırın.');
            } else {
                tsnBilgiGuncelle('bulunamadi', 'Bu alanla örtüşen kayıtlı bir taşınmaz yok — kayıt taşınmazsız açılacak.');
            }
        } catch (e) {
            if (sira === esleSira) tsnBilgiSakla('uyari', 'Taşınmaz eşlemesi yapılamadı; kaydederken yeniden denenecek.');
        }
    }

    const esc = s => (s == null ? '' : String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'));
    const SVG = {
        tamam: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>',
        bilgi: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>',
        arama: '<span class="ec-donen"></span>',
        katman: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 8l10 6 10-6z"/><path d="M2 12l10 6 10-6"/></svg>',
    };

    function tsnBilgiGuncelle(tip, veri) {
        let html;
        if (tip === 'bulundu') {
            html = `<div class="ec-tsn-durum is-bulundu">${SVG.tamam}<div><strong>Ada ${esc(veri.ada ?? '—')} / Parsel ${esc(veri.parsel ?? '—')}</strong><small>${[veri.il, veri.ilce, veri.mahalle].filter(Boolean).map(esc).join(' · ')}</small></div></div>`;
        } else if (tip === 'bos') {
            html = `<div class="ec-tsn-durum is-bos">${SVG.katman}<div>Taşınmaz bağlı değil. Haritada alan çizerseniz örtüşen taşınmaz aranır.</div></div>`;
        } else {
            html = `<div class="ec-tsn-durum is-${tip}">${tip === 'arama' ? SVG.arama : SVG.bilgi}<div>${esc(veri)}</div></div>`;
        }
        tsnBilgi.innerHTML = html;
        tsnKaldir.hidden = !tsnInput.value;
    }
    // Bağlı taşınmaz kartını bırakıp altına not ekler
    function tsnBilgiSakla(tip, mesaj) {
        tsnBilgi.querySelectorAll('.ec-tsn-not').forEach(x => x.remove());
        if (!tsnBilgi.querySelector('.ec-tsn-durum')) tsnBilgiGuncelle('bos');
        tsnBilgi.insertAdjacentHTML('beforeend', `<div class="ec-tsn-not is-${tip}">${tip === 'arama' ? SVG.arama : SVG.bilgi}<span>${esc(mesaj)}</span></div>`);
        tsnKaldir.hidden = !tsnInput.value;
    }

    tsnKaldir?.addEventListener('click', () => {
        tsnInput.value = '';
        tsnElle.value = '1';
        if (refKatman) { harita.removeLayer(refKatman); refKatman = null; }
        tsnBilgiGuncelle('bos');
    });

    function geodesikAlan(pts) {
        const R = 6378137, n = pts.length;
        if (n < 3) return 0;
        let t = 0;
        for (let i = 0; i < n; i++) {
            const p1 = pts[i], p2 = pts[(i + 1) % n];
            t += (p2.lng - p1.lng) * Math.PI / 180 * (2 + Math.sin(p1.lat * Math.PI / 180) + Math.sin(p2.lat * Math.PI / 180));
        }
        return Math.abs(t * R * R / 2);
    }
    function alanBiciminde(m2) {
        const f = (v) => v.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return m2 >= 10000 ? f(m2 / 10000) + ' ha' : f(m2) + ' m²';
    }

    // ---------- Harita olayları ----------
    harita.on('click', e => { if (cizim.modu === 'cizim') noktaEkle(e.latlng); else if (cizim.modu === 'duzenle') modaGir('idle'); });
    harita.on('mousemove', e => {
        if (cizim.modu !== 'cizim' || cizim.noktalar.length === 0) return;
        cizim.farePos = e.latlng;
        onizleCiz();
    });
    harita.on('dblclick', () => { if (cizim.modu === 'cizim' && cizim.noktalar.length >= 3) polygonKapat(); });

    function tamEkran(ac) {
        kart.classList.toggle('is-tam', ac);
        btnTam.classList.toggle('is-aktif', ac);
        document.documentElement.classList.toggle('ec-tam-ekran-acik', ac);
        setTimeout(() => harita.invalidateSize(), 220);
    }
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        if (cizim.modu === 'cizim') { cizim.noktalar = []; modaGir('idle'); return; }
        if (kart.classList.contains('is-tam')) tamEkran(false);
    });

    btnPoligon.addEventListener('click', () => modaGir(cizim.modu === 'cizim' ? 'idle' : 'cizim'));
    btnSil.addEventListener('click', () => { if (confirm('Çizilen işgal alanı silinsin mi?')) polygonSil(); });
    btnTam.addEventListener('click', () => tamEkran(!kart.classList.contains('is-tam')));
    btnKonum.addEventListener('click', () => {
        if (!navigator.geolocation) { ipucu('Tarayıcı konum desteği sunmuyor.', true); return; }
        ipucu('Konum alınıyor…');
        navigator.geolocation.getCurrentPosition(
            pos => { harita.setView([pos.coords.latitude, pos.coords.longitude], 18); modaGir(cizim.modu); },
            err => ipucu('Konum alınamadı: ' + err.message, true)
        );
    });

    // KML yükleme — ilk <coordinates> bloğu poligon olarak alınır
    document.getElementById('ec-kml-input').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = ev => {
            try {
                const kml = new DOMParser().parseFromString(ev.target.result, 'text/xml');
                const metin = kml.querySelector('coordinates')?.textContent?.trim();
                if (!metin) throw new Error('KML içinde koordinat bulunamadı.');
                const pts = [];
                metin.split(/\s+/).forEach(p => {
                    const [lng, lat] = p.split(',').map(parseFloat);
                    if (!isNaN(lng) && !isNaN(lat)) pts.push(L.latLng(lat, lng));
                });
                if (pts.length > 3 && pts[0].equals(pts[pts.length - 1])) pts.pop();
                if (pts.length < 3) throw new Error('Geçerli bir alan oluşturulamadı.');
                if (cizim.polygon) { harita.removeLayer(cizim.polygon); cizim.polygon = null; }
                cizim.noktalar = pts;
                polygonKapat();
                harita.fitBounds(cizim.polygon.getBounds(), { padding: [30, 30] });
            } catch (err) {
                ipucu('KML yüklenemedi: ' + err.message, true);
            }
        };
        reader.readAsText(file);
        e.target.value = '';
    });

    // ---------- Kayıtlı / formdan dönen alanı yükle (eşleme yapmadan) ----------
    function halkaNoktalari(g) {
        if (g && g.type === 'Polygon' && g.coordinates?.[0]) {
            const h = g.coordinates[0].map(p => L.latLng(p[1], p[0]));
            if (h.length > 3 && h[0].equals(h[h.length - 1])) h.pop();
            return h;
        }
        if (Array.isArray(g) && Array.isArray(g[0])) return g.map(p => L.latLng(p[1], p[0]));
        return [];
    }
    if (eskiKoordinat) {
        const pts = halkaNoktalari(eskiKoordinat);
        if (pts.length >= 3) {
            yukleniyor = true;
            cizim.noktalar = pts;
            polygonKapat();
            yukleniyor = false;
            harita.fitBounds(cizim.polygon.getBounds(), { padding: [40, 40] });
        }
    }

    // ---------- Mevcut ecrimisil alanları (tıkla → aynı alana yeni kayıt) ----------
    const mevcutKatman = L.featureGroup().addTo(harita);
    let mevcutIstek = 0;
    let mevcutKaydirma;
    function mevcutYukle() {
        const no = ++mevcutIstek;
        const b = harita.getBounds();
        const p = new URLSearchParams();
        if (HARIC_ID) p.set('haric', HARIC_ID);
        p.set('bbox', [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].map(v => (+v).toFixed(5)).join(','));
        p.set('zoom', String(Math.round(harita.getZoom())));
        mevcutKatman.clearLayers();
        fetch(API_MEVCUT + '?' + p.toString(), { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(fc => {
            if (no !== mevcutIstek || fc.kumeli) return;
            (fc.features || []).forEach(f => {
            const p = f.properties || {};
            const renk = MEVCUT_RENK[p.durum] || MEVCUT_RENK.yeni;
            L.geoJSON(f, { style: { color: renk, fillColor: renk, fillOpacity: .1, weight: 1.8, dashArray: '4 4' } })
                .addTo(mevcutKatman)
                .eachLayer(l => {
                    l.bindTooltip(
                        `<strong>${esc(p.ad_soyad_unvan ?? '—')}</strong>
                         <div class="sub">${p.ada ? 'Ada ' + esc(p.ada) + ' / Parsel ' + esc(p.parsel) : 'Taşınmaz bağlı değil'}${p.nitelik ? ' · ' + esc(p.nitelik) : ''}</div>
                         <span class="aksiyon">Tıklayın: aynı alanı bu kayda kopyalayın</span>`,
                        { className: 'ec-mvt-tooltip', sticky: true }
                    );
                    l.on('click', e => {
                        L.DomEvent.stopPropagation(e);
                        if (cizim.modu === 'cizim') { noktaEkle(e.latlng); return; }
                        if (!confirm(`“${p.ad_soyad_unvan}” kaydının alanı bu kayda kopyalansın mı?`)) return;
                        mevcuttanKopyala(f);
                    });
                    l.on('mouseover', () => l.setStyle({ fillOpacity: .26, weight: 2.5 }));
                    l.on('mouseout', () => l.setStyle({ fillOpacity: .1, weight: 1.8 }));
                });
            });
        })
        .catch(() => { /* mevcut kayıtlar yalnızca yardımcı katman */ });
    }
    harita.on('moveend', () => {
        clearTimeout(mevcutKaydirma);
        mevcutKaydirma = setTimeout(mevcutYukle, 200);
    });
    mevcutYukle();

    function mevcuttanKopyala(feature) {
        const pts = halkaNoktalari(feature.geometry);
        if (pts.length < 3) { ipucu('Bu kaydın kendi alanı yok (taşınmaz sınırı gösteriliyor); kopyalanamaz.', true); return; }
        if (cizim.polygon) { harita.removeLayer(cizim.polygon); cizim.polygon = null; }
        cizim.noktalar = pts;
        polygonKapat();
        harita.fitBounds(cizim.polygon.getBounds(), { padding: [40, 40] });
    }

    modaGir('idle');
    setTimeout(() => harita.invalidateSize(), 120);
})();
