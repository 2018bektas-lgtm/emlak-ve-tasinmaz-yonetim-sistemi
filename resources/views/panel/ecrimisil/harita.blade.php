@extends('layouts.panel')

@section('title', 'Ecrimisil Haritası')
@section('contentClass', 'is-flush')
@section('shellClass', 'is-map-full')

@push('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.Default.css">
<link rel="stylesheet" href="{{ asset('css/ecrimisil-harita.css') }}?v=1">
@endpush

@section('content')
<div class="ech-shell" id="ech-shell">
    <div id="ech-map" class="ech-map"></div>

    {{-- ==== TOOLBAR ==== --}}
    <div class="ech-toolbar">
        <a class="ech-tool" href="{{ route('panel.ecrimisil.index') }}" title="Listeye dön">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4h3a2 2 0 012 2v12a2 2 0 01-2 2h-3"/><path d="M10 16l-4-4 4-4M14 12H6"/></svg>
            Liste
        </a>
        <div class="ech-tool-divider"></div>
        <button type="button" class="ech-tool" id="ech-katman-btn" aria-expanded="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 8l10 6 10-6z"/><path d="M2 16l10 6 10-6M2 12l10 6 10-6"/></svg>
            Katmanlar
        </button>
        <button type="button" class="ech-tool" id="ech-arama-btn" aria-expanded="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            Ara
        </button>
        <button type="button" class="ech-tool" id="ech-fit-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
            Sığdır
        </button>
        <div class="ech-tool-divider"></div>
        <span class="ech-chip"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8L12 3l8 5L18 20H6z"/></svg><strong id="ech-sayi">0</strong> işgal</span>
    </div>

    {{-- ==== KATMAN PANELİ ==== --}}
    <div class="ech-panel ech-katman" id="ech-katman-panel">
        <div class="ech-panel-head" data-drag>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 8l10 6 10-6z"/><path d="M2 12l10 6 10-6"/></svg>
            <div style="flex:1;"><strong>Katmanlar</strong><small>Altlık ve ecrimisil katmanları</small></div>
            <button type="button" class="ech-panel-kapat" data-panel-kapat="ech-katman-panel">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="ech-panel-scroll">
            <div class="ech-katman-grup">
                <div class="ech-katman-baslik">Altlık</div>
                <div class="ech-radyo-grup" id="ech-altlik-grup">
                    <div class="ech-radyo is-aktif" data-altlik="uydu-etiket">Uydu+Etiket</div>
                    <div class="ech-radyo" data-altlik="uydu">Uydu</div>
                    <div class="ech-radyo" data-altlik="yol">Yol</div>
                    <div class="ech-radyo" data-altlik="arazi">Arazi</div>
                    <div class="ech-radyo" data-altlik="osm">OSM</div>
                </div>
            </div>
            <div class="ech-katman-grup">
                <div class="ech-katman-baslik">Ecrimisil</div>
                <div class="ech-katman-kart">
                    <span class="ech-katman-ikon" style="background:#f43f5e;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="12" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M9 11h6M9 15h4"/></svg>
                    </span>
                    <div class="ech-katman-meta">
                        <span class="ech-katman-ad">Ecrimisil Parselleri</span>
                        <span class="ech-katman-alt">İşgal kayıtlarının olduğu taşınmazlar</span>
                    </div>
                    <label class="ech-switch"><input type="checkbox" id="ech-lyr-parseller" checked><span class="ech-switch-slider"></span></label>
                </div>
                <div class="ech-katman-kart">
                    <span class="ech-katman-ikon" style="background:#facc15;color:#0e1726;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8L12 3l8 5L18 20H6z"/></svg>
                    </span>
                    <div class="ech-katman-meta">
                        <span class="ech-katman-ad">Ada/Parsel Etiketi</span>
                        <span class="ech-katman-alt">Parsel merkezinde ada/parsel yazısı</span>
                    </div>
                    <label class="ech-switch"><input type="checkbox" id="ech-lyr-etiket" checked><span class="ech-switch-slider"></span></label>
                </div>
                <div class="ech-katman-kart">
                    <span class="ech-katman-ikon" style="background:#f43f5e;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-7-7-7-13a7 7 0 0114 0c0 6-7 13-7 13z"/><circle cx="12" cy="9" r="2.6"/></svg>
                    </span>
                    <div class="ech-katman-meta">
                        <span class="ech-katman-ad">İşaretçi (Marker)</span>
                        <span class="ech-katman-alt">Durum rengiyle konum işaretçileri</span>
                    </div>
                    <label class="ech-switch"><input type="checkbox" id="ech-lyr-marker" checked><span class="ech-switch-slider"></span></label>
                </div>
                <div class="ech-katman-kart">
                    <span class="ech-katman-ikon is-kume">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="4"/><circle cx="17" cy="7" r="4"/><circle cx="12" cy="17" r="4"/></svg>
                    </span>
                    <div class="ech-katman-meta">
                        <span class="ech-katman-ad">Kümeleme</span>
                        <span class="ech-katman-alt">Yakın işaretçileri gruplandır</span>
                    </div>
                    <label class="ech-switch"><input type="checkbox" id="ech-lyr-cluster"><span class="ech-switch-slider"></span></label>
                </div>
            </div>
            <div class="ech-lejant">
                <div class="ech-lejant-baslik">Ecrimisil Durumu</div>
                <div class="ech-lejant-oge"><span class="ech-lejant-nokta is-yeni"></span> Yeni kayıt (tutanak yok)</div>
                <div class="ech-lejant-oge"><span class="ech-lejant-nokta is-devam-eden"></span> Devam eden işgal</div>
                <div class="ech-lejant-oge"><span class="ech-lejant-nokta is-sonlanmis"></span> Sonlanmış işgal</div>
            </div>
        </div>
    </div>

    {{-- ==== ARAMA PANELİ ==== --}}
    <div class="ech-panel ech-arama" id="ech-arama-panel" hidden>
        <div class="ech-panel-head" data-drag>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <div style="flex:1;"><strong>Ara / Filtrele</strong><small>Konum, kişi, sorumlu ile daralt</small></div>
            <button type="button" class="ech-panel-kapat" data-panel-kapat="ech-arama-panel">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="ech-panel-scroll" style="padding:14px;">
            <form class="ech-form" id="ech-arama-form" onsubmit="return echFiltreUygula(event)">
                <div class="ech-form-satir">
                    <label>Serbest Arama</label>
                    <input type="search" name="q" placeholder="Ad, TC/Vergi, nitelik…" autocomplete="off">
                </div>
                <div class="ech-form-satir">
                    <label>İl</label>
                    <select name="il_id" id="ech-f-il"><option value="">— Tümü —</option>@foreach ($iller as $il)<option value="{{ $il->id }}">{{ $il->ad }}</option>@endforeach</select>
                </div>
                <div class="ech-form-satir"><label>İlçe</label><select name="ilce_id" id="ech-f-ilce" disabled><option>— Önce il —</option></select></div>
                <div class="ech-form-satir"><label>Mahalle</label><select name="mahalle_id" id="ech-f-mahalle" disabled><option>— Önce ilçe —</option></select></div>
                <div class="ech-form-row">
                    <div class="ech-form-satir"><label>Ada</label><input type="text" name="ada"></div>
                    <div class="ech-form-satir"><label>Parsel</label><input type="text" name="parsel"></div>
                </div>
                <div class="ech-form-satir">
                    <label>Sorumlu Kullanıcı</label>
                    <select name="kullanici_id"><option value="">— Tümü —</option>@foreach ($kullanicilar as $k)<option value="{{ $k->id }}">{{ $k->ad }} {{ $k->soyad }}</option>@endforeach</select>
                </div>
                <div class="ech-form-satir">
                    <label>Durum</label>
                    <select name="durum">
                        <option value="">— Tümü —</option>
                        <option value="yeni">Yeni kayıt</option>
                        <option value="devam-eden">Devam eden işgal</option>
                        <option value="sonlanmis">Sonlanmış işgal</option>
                    </select>
                </div>
                <div class="ech-btn-row">
                    <button type="submit" class="ech-btn">Uygula</button>
                    <button type="button" class="ech-btn is-ghost" onclick="echFiltreTemizle()">Temizle</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ==== İŞLEMLER PANELİ ==== --}}
    <div class="ech-panel ech-islem" id="ech-islem-panel" hidden>
        <div class="ech-panel-head" data-drag>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="12" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M9 11h6M9 15h4"/></svg>
            <div style="flex:1;"><strong>İşlemler</strong><small id="ech-islem-alt">Kayıt seçilmedi</small></div>
            <button type="button" class="ech-panel-kapat" data-panel-kapat="ech-islem-panel">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="ech-islem-tabs">
            <button type="button" class="ech-tab is-aktif" data-tab="ozet">Özet</button>
            <button type="button" class="ech-tab" data-tab="tutanak">Tutanaklar</button>
            <button type="button" class="ech-tab" data-tab="rapor">Raporlar</button>
            <button type="button" class="ech-tab" data-tab="resim">Resimler</button>
        </div>
        <div class="ech-panel-scroll">
            <div class="ech-tab-panel is-aktif" data-tab-panel="ozet" id="ech-tab-ozet"><div class="ech-yukleniyor">Yükleniyor</div></div>
            <div class="ech-tab-panel" data-tab-panel="tutanak" id="ech-tab-tutanak"><div class="ech-yukleniyor">Yükleniyor</div></div>
            <div class="ech-tab-panel" data-tab-panel="rapor" id="ech-tab-rapor"><div class="ech-yukleniyor">Yükleniyor</div></div>
            <div class="ech-tab-panel" data-tab-panel="resim" id="ech-tab-resim"><div class="ech-yukleniyor">Yükleniyor</div></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/leaflet.markercluster.js"></script>
<script>
(function () {
    'use strict';
    const API = {
        geojson: @json(route('panel.ecrimisil.harita.geojson')),
        ozet: @json(url('/panel/ecrimisil/harita/kayit')),
        kayit: @json(url('/panel/ecrimisil')),
        ilceler: @json(url('/panel/ajax/ilceler')),
        mahalleler: @json(url('/panel/ajax/mahalleler')),
        csrf: @json(csrf_token()),
    };

    // Durum renkleri corporate.css ':root' içinde tek yerde durur; lejant
    // noktaları, harita işaretçileri ve kayıt formundaki çizim aynı değeri okur.
    const kokStil = getComputedStyle(document.documentElement);
    const durumRengi = d => kokStil.getPropertyValue('--durum-' + d).trim();
    const DURUM_RENK = {
        'yeni':        { renk: durumRengi('yeni'),       etiket: 'Tutanak yok' },
        'devam-eden':  { renk: durumRengi('devam-eden'), etiket: 'İşgal sürüyor' },
        'sonlanmis':   { renk: durumRengi('sonlanmis'),  etiket: 'İşgal sonlandı' },
    };
    const durumSinifi = d => 'is-' + ({ 'yeni':'yeni','devam-eden':'devam-eden','sonlanmis':'sonlanmis' }[d] || 'yeni');

    const map = L.map('ech-map', { zoomSnap: 0.5, zoomDelta: 0.5, zoomControl: false, attributionControl: false })
        .setView([39.9334, 32.8597], 10);

    const altliklar = {
        'uydu-etiket': L.tileLayer('https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { subdomains: ['0','1','2','3'], maxZoom: 20 }),
        'uydu':        L.tileLayer('https://mt{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', { subdomains: ['0','1','2','3'], maxZoom: 20 }),
        'yol':         L.tileLayer('https://mt{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', { subdomains: ['0','1','2','3'], maxZoom: 20 }),
        'arazi':       L.tileLayer('https://mt{s}.google.com/vt/lyrs=p&x={x}&y={y}&z={z}', { subdomains: ['0','1','2','3'], maxZoom: 20 }),
        'osm':         L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { subdomains: ['a','b','c'], maxZoom: 19 }),
    };
    let aktifAltlik = 'uydu-etiket';
    altliklar[aktifAltlik].addTo(map);

    const parselKatman = L.featureGroup().addTo(map);
    const etiketKatman = L.featureGroup().addTo(map);
    const markerKatman = L.featureGroup().addTo(map);
    const clusterKatman = L.markerClusterGroup({
        showCoverageOnHover: false,
        maxClusterRadius: 55,
        disableClusteringAtZoom: 17,
        iconCreateFunction: c => {
            const n = c.getChildCount();
            // Kutu capi sinifin capiyla ayni olmali, yoksa baloncuk kayar.
            const [cls, boy] = n < 10 ? ['ech-cluster-small', 32]
                : (n < 50 ? ['ech-cluster-medium', 38] : ['ech-cluster-large', 46]);
            return L.divIcon({
                html: `<div class="ech-cluster ${cls}" style="--cap:${boy}px">${n}</div>`,
                className: 'ech-cluster-wrap', iconSize: [boy, boy],
            });
        },
    });
    const layerById = new Map();
    const markerById = new Map();
    let allBounds = null;
    let secilenLayer = null;
    let secilenKayitId = null;
    let mevcutFiltre = new URLSearchParams();
    let istekNo = 0;
    let kaydirma;

    function haritaSorgu(filtre) {
        const p = new URLSearchParams(filtre ? filtre.toString() : '');
        const b = map.getBounds();
        p.set('bbox', [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].map(v => (+v).toFixed(5)).join(','));
        p.set('zoom', String(Math.round(map.getZoom())));
        return p;
    }

    function ulkeyeSigdir(filtre) {
        const once = map.getBounds().toBBoxString();
        const p = new URLSearchParams(filtre ? filtre.toString() : '');
        p.set('kapsam', '1');
        return fetch(API.geojson + '?' + p.toString(), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(k => {
                if (k.bbox && k.bbox.length === 4) {
                    map.fitBounds([[k.bbox[1], k.bbox[0]], [k.bbox[3], k.bbox[2]]], { padding: [24, 24], maxZoom: 8 });
                    if (map.getBounds().toBBoxString() === once) haritayiYukle(filtre, false);
                } else {
                    haritayiYukle(filtre, false);
                }
            })
            .catch(e => console.error('Kapsam hatası', e));
    }

    function haritayiYukle(filtre, sigdir = false) {
        const no = ++istekNo;
        parselKatman.clearLayers();
        etiketKatman.clearLayers();
        markerKatman.clearLayers();
        clusterKatman.clearLayers();
        layerById.clear();
        markerById.clear();
        const url = API.geojson + '?' + haritaSorgu(filtre).toString();

        return fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(fc => {
                if (no !== istekNo) return;
                const toplam = fc.toplam != null ? fc.toplam : (fc.features || []).length;
                document.getElementById('ech-sayi').textContent = Number(toplam).toLocaleString('tr-TR');
                if (fc.kumeli) {
                    // Çap: alan ∝ adet olacak şekilde karekök — göz daireyi alanıyla
                    // okur, çapıyla değil. Ölçek görünümdeki en kalabalık kümeye göre
                    // normalize edilir. Eskiden çap mutlak bir log10 formülündeydi ve
                    // 26px tavanına dayandığı için 100 bin kayıtta kümelerin %88'i
                    // aynı boyda çıkıyordu; boyut hiçbir şey anlatmıyordu.
                    const adetler = (fc.features || []).map(f => (f.properties || {}).adet || 0);
                    const enCok = Math.max(1, ...adetler);
                    const kumeCapi = n => Math.round(24 + 32 * Math.sqrt(n / enCok));
                    const kumeKademe = n => n < enCok / 8 ? 'ech-cluster-small'
                        : (n < enCok / 2 ? 'ech-cluster-medium' : 'ech-cluster-large');
                    // 5152 -> "5,2B"; tam sayı baloncuğa sığmıyor, hover'da veriliyor.
                    const kisaAdet = n => n < 1000 ? String(n)
                        : (n / 1000).toLocaleString('tr-TR', { maximumFractionDigits: 1 }) + 'B';

                    (fc.features || []).forEach(f => {
                        const p = f.properties || {};
                        const c = (f.geometry && f.geometry.coordinates) || [32.85, 39.93];
                        const adet = p.adet || 0;
                        const boy = kumeCapi(adet);
                        // Sayı baloncuğun içinde. Eskiden permanent bir Leaflet
                        // tooltip'iydi ve .ech-kume-tip'in hiç stili olmadığı için her
                        // kümenin üstünde Leaflet'in varsayılan beyaz kutusu duruyordu.
                        const m = L.marker([c[1], c[0]], {
                            icon: L.divIcon({
                                html: `<div class="ech-cluster ${kumeKademe(adet)}" style="--cap:${boy}px">${kisaAdet(adet)}</div>`,
                                className: 'ech-cluster-wrap', iconSize: [boy, boy],
                            }),
                        });
                        m.bindTooltip(Number(adet).toLocaleString('tr-TR') + ' kayıt', { direction: 'top' });
                        m.on('click', () => {
                            if (p.bbox && p.bbox.length === 4) {
                                map.fitBounds([[p.bbox[1], p.bbox[0]], [p.bbox[3], p.bbox[2]]], { padding: [20, 20], maxZoom: 17 });
                            }
                        });
                        parselKatman.addLayer(m);
                    });
                    markerKatmanGuncelle();
                    return;
                }
                const bounds = [];
                (fc.features || []).forEach(f => {
                    const p = f.properties || {};
                    const stil = DURUM_RENK[p.durum] || DURUM_RENK.yeni;
                    const layer = L.geoJSON(f, {
                        style: () => ({ color: stil.renk, fillColor: stil.renk, fillOpacity: .30, weight: 2.4 }),
                        pointToLayer: (feat, latlng) => L.circleMarker(latlng, { color: stil.renk, fillColor: stil.renk, fillOpacity: .6, radius: 8, weight: 2 }),
                    });
                    layer.eachLayer(l => {
                        l._props = p;
                        l._durumStil = stil;
                        l.on('click', () => secKayit(l, p));
                        l.on('mouseover', () => { if (l !== secilenLayer && l.setStyle) l.setStyle({ fillOpacity: .5, weight: 3 }); });
                        l.on('mouseout', () => stiliniUygula(l));
                        parselKatman.addLayer(l);
                        layerById.set(p.id, l);
                        try { bounds.push(l.getBounds ? l.getBounds() : L.latLngBounds([l.getLatLng(), l.getLatLng()])); } catch(e) {}
                    });
                    try {
                        const c = layer.getBounds().getCenter();
                        // Ada/parsel etiketi
                        const et = L.marker(c, {
                            icon: L.divIcon({ className: '', html: `<div class="ech-etiket">${p.ada ? escapeHtml(p.ada) + '/' + escapeHtml(p.parsel) : escapeHtml(p.nitelik || 'Bağlı değil')}</div>`, iconSize: null }),
                        });
                        et.on('click', () => secKayit(layer.getLayers()[0], p));
                        etiketKatman.addLayer(et);

                        // İşaretçi (durum bazlı özel marker)
                        const mIcon = L.divIcon({
                            className: 'ech-marker-wrap',
                            html: `<div class="ech-marker ech-marker-${p.durum || 'yeni'}"></div>`,
                            iconSize: [26, 26], iconAnchor: [13, 24],
                        });
                        const marker = L.marker(c, { icon: mIcon });
                        marker.on('click', () => secKayit(layer.getLayers()[0], p));
                        marker.bindTooltip(`<strong>${escapeHtml(p.ad_soyad_unvan)}</strong><br>${p.ada ? 'Ada ' + escapeHtml(p.ada) + ' / Parsel ' + escapeHtml(p.parsel) : 'Taşınmaz bağlı değil'}`, { direction: 'top', offset: [0, -20] });
                        markerKatman.addLayer(marker);
                        markerById.set(p.id, marker);
                    } catch(e) {}
                });
                if (bounds.length) {
                    allBounds = bounds.reduce((acc, b) => acc ? acc.extend(b) : L.latLngBounds(b.getSouthWest ? [b.getSouthWest(), b.getNorthEast()] : [b, b]), null);
                    if (allBounds && sigdir) map.fitBounds(allBounds, { padding: [30, 30] });
                }
                // Seçili kayıt yeniden çizildiyse vurgusunu koru
                if (secilenKayitId && layerById.has(secilenKayitId)) { secilenLayer = layerById.get(secilenKayitId); stiliniUygula(secilenLayer); }
                // Marker/cluster durumunu uygula (veri yüklendikten sonra)
                markerKatmanGuncelle();
            });
    }

    function stiliniUygula(l) {
        if (!l.setStyle) return;
        const s = l._durumStil || DURUM_RENK.yeni;
        const aktif = l === secilenLayer;
        l.setStyle({ color: s.renk, fillColor: s.renk, weight: aktif ? 4 : 2.4, fillOpacity: aktif ? .55 : .30 });
    }

    map.on('moveend', () => {
        clearTimeout(kaydirma);
        kaydirma = setTimeout(() => haritayiYukle(mevcutFiltre, false), 160);
    });
    ulkeyeSigdir(mevcutFiltre);

    // Katman switch
    document.getElementById('ech-lyr-parseller').addEventListener('change', e => {
        if (e.target.checked) { if (!map.hasLayer(parselKatman)) map.addLayer(parselKatman); }
        else if (map.hasLayer(parselKatman)) map.removeLayer(parselKatman);
    });
    document.getElementById('ech-lyr-etiket').addEventListener('change', e => {
        if (e.target.checked) { if (!map.hasLayer(etiketKatman)) map.addLayer(etiketKatman); }
        else if (map.hasLayer(etiketKatman)) map.removeLayer(etiketKatman);
    });
    document.getElementById('ech-lyr-marker').addEventListener('change', markerKatmanGuncelle);
    document.getElementById('ech-lyr-cluster').addEventListener('change', markerKatmanGuncelle);

    function markerKatmanGuncelle() {
        const markerOn = document.getElementById('ech-lyr-marker').checked;
        const clusterOn = document.getElementById('ech-lyr-cluster').checked;
        // Önce her ikisini de çıkar
        if (map.hasLayer(markerKatman)) map.removeLayer(markerKatman);
        if (map.hasLayer(clusterKatman)) map.removeLayer(clusterKatman);
        clusterKatman.clearLayers();

        if (!markerOn) return; // İşaretçi kapalıysa hiçbir şey gösterme
        if (clusterOn) {
            // Marker'ları cluster'a ekle
            markerKatman.eachLayer(m => clusterKatman.addLayer(m));
            map.addLayer(clusterKatman);
        } else {
            map.addLayer(markerKatman);
        }
    }

    // Altlık
    document.querySelectorAll('#ech-altlik-grup .ech-radyo').forEach(el => {
        el.addEventListener('click', () => {
            const id = el.dataset.altlik;
            if (id === aktifAltlik) return;
            map.removeLayer(altliklar[aktifAltlik]);
            aktifAltlik = id;
            altliklar[id].addTo(map);
            document.querySelectorAll('#ech-altlik-grup .ech-radyo').forEach(x => x.classList.remove('is-aktif'));
            el.classList.add('is-aktif');
        });
    });

    // Toolbar
    document.getElementById('ech-katman-btn').addEventListener('click', () => {
        const p = document.getElementById('ech-katman-panel');
        p.hidden = !p.hidden;
        document.getElementById('ech-katman-btn').setAttribute('aria-expanded', p.hidden ? 'false' : 'true');
    });
    document.getElementById('ech-arama-btn').addEventListener('click', () => {
        const p = document.getElementById('ech-arama-panel');
        p.hidden = !p.hidden;
        document.getElementById('ech-arama-btn').setAttribute('aria-expanded', p.hidden ? 'false' : 'true');
    });
    document.getElementById('ech-fit-btn').addEventListener('click', () => ulkeyeSigdir(mevcutFiltre));
    document.querySelectorAll('[data-panel-kapat]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.panelKapat;
            document.getElementById(id).hidden = true;
            const bId = { 'ech-katman-panel': 'ech-katman-btn', 'ech-arama-panel': 'ech-arama-btn' }[id];
            if (bId) document.getElementById(bId).setAttribute('aria-expanded', 'false');
        });
    });

    // Sürükleme
    document.querySelectorAll('.ech-panel-head[data-drag]').forEach(head => {
        let sX = 0, sY = 0, bX = 0, bY = 0;
        const panel = head.closest('.ech-panel');
        head.addEventListener('mousedown', e => {
            if (e.target.closest('button')) return;
            bX = e.clientX; bY = e.clientY;
            const r = panel.getBoundingClientRect();
            sX = r.left; sY = r.top;
            panel.style.right = 'auto'; panel.style.bottom = 'auto';
            panel.style.left = sX + 'px'; panel.style.top = sY + 'px';
            document.addEventListener('mousemove', mv);
            document.addEventListener('mouseup', () => document.removeEventListener('mousemove', mv), { once: true });
        });
        function mv(e) { panel.style.left = (sX + e.clientX - bX) + 'px'; panel.style.top = (sY + e.clientY - bY) + 'px'; }
    });

    // Sekme
    document.querySelectorAll('.ech-tab').forEach(t => {
        t.addEventListener('click', () => {
            document.querySelectorAll('.ech-tab').forEach(x => x.classList.remove('is-aktif'));
            document.querySelectorAll('.ech-tab-panel').forEach(x => x.classList.remove('is-aktif'));
            t.classList.add('is-aktif');
            document.querySelector(`[data-tab-panel="${t.dataset.tab}"]`).classList.add('is-aktif');
        });
    });

    // İl/İlçe chain
    document.getElementById('ech-f-il').addEventListener('change', async e => {
        const i = document.getElementById('ech-f-ilce'), m = document.getElementById('ech-f-mahalle');
        i.innerHTML = '<option value="">— Tümü —</option>'; m.innerHTML = '<option value="">— Önce ilçe —</option>'; m.disabled = true;
        if (!e.target.value) { i.disabled = true; return; }
        i.disabled = false;
        const r = await fetch(API.ilceler + '/' + e.target.value); (await r.json() || []).forEach(x => i.appendChild(new Option(x.ad, x.id)));
    });
    document.getElementById('ech-f-ilce').addEventListener('change', async e => {
        const m = document.getElementById('ech-f-mahalle');
        m.innerHTML = '<option value="">— Tümü —</option>';
        if (!e.target.value) { m.disabled = true; return; }
        m.disabled = false;
        const r = await fetch(API.mahalleler + '/' + e.target.value); (await r.json() || []).forEach(x => m.appendChild(new Option(x.ad, x.id)));
    });

    window.echFiltreUygula = function (e) {
        e.preventDefault();
        mevcutFiltre = new URLSearchParams();
        for (const [k, v] of new FormData(e.target)) if (v) mevcutFiltre.set(k, v);
        ulkeyeSigdir(mevcutFiltre);
        return false;
    };
    window.echFiltreTemizle = function () {
        document.getElementById('ech-arama-form').reset();
        document.getElementById('ech-f-ilce').innerHTML = '<option>— Önce il —</option>';
        document.getElementById('ech-f-ilce').disabled = true;
        document.getElementById('ech-f-mahalle').innerHTML = '<option>— Önce ilçe —</option>';
        document.getElementById('ech-f-mahalle').disabled = true;
        mevcutFiltre = new URLSearchParams();
        ulkeyeSigdir(mevcutFiltre);
    };

    // Kayıt seçimi
    function secKayit(layer, props) {
        const eski = secilenLayer;
        secilenLayer = layer;
        secilenKayitId = props.id;
        if (eski) stiliniUygula(eski);
        stiliniUygula(layer);

        const panel = document.getElementById('ech-islem-panel');
        panel.hidden = false;
        document.getElementById('ech-islem-alt').textContent = props.ad_soyad_unvan + (props.ada ? ' · Ada ' + props.ada + '/' + props.parsel : '');
        ['ozet', 'tutanak', 'rapor', 'resim'].forEach(t => document.getElementById('ech-tab-' + t).innerHTML = '<div class="ech-yukleniyor">Yükleniyor</div>');
        ozetYukle(props.id);
    }

    function ozetYukle(id, sadece) {
        return fetch(API.ozet + '/' + id, { headers: { 'Accept': 'application/json' } })
            .then(r => { if (!r.ok) throw new Error(r.status === 403 ? 'Bu kayda erişim yetkiniz yok.' : 'Yükleme başarısız (' + r.status + ').'); return r.json(); })
            .then(v => {
                if (id !== secilenKayitId) return;
                if (!sadece || sadece === 'ozet') renderOzet(v);
                if (!sadece || sadece === 'tutanak') renderTutanak(v);
                if (!sadece || sadece === 'rapor') renderRapor(v);
                if (!sadece || sadece === 'resim') renderResim(v);
            })
            .catch(e => { document.getElementById('ech-tab-ozet').innerHTML = '<div class="ech-flash is-hata">' + escapeHtml(e.message) + '</div>'; });
    }

    const tarihAlani = (ad, etiket, ekstra = '') => `<div class="ech-form-satir"><label>${etiket}</label><input type="date" name="${ad}" ${ekstra}></div>`;
    const BUGUN = new Date().toISOString().slice(0, 10);
    const ekleKutusu = (baslik, govde) => `<details class="ech-form-toggle"><summary>+ ${baslik}</summary>${govde}</details>`;

    function renderOzet(v) {
        const k = v.kayit;
        const durumEt = DURUM_RENK[k.durum]?.etiket ?? 'Yeni kayıt';
        document.getElementById('ech-tab-ozet').innerHTML = `
            <div class="ech-ozet-ust">
                <span class="ech-rozet ${durumSinifi(k.durum)}">${escapeHtml(durumEt)}</span>
                ${k.toplam_gun ? `<small>${Number(k.toplam_gun).toLocaleString('tr-TR')} gün işgal</small>` : ''}
            </div>
            <div class="ech-ozet-grid">
                <div class="ech-alan is-genis"><div class="lbl">Ad Soyad / Ünvan</div><div class="val">${escapeHtml(k.ad_soyad_unvan)}</div></div>
                <div class="ech-alan"><div class="lbl">TC / Vergi No</div><div class="val">${escapeHtml(k.tc_vergi_no ?? '—')}</div></div>
                <div class="ech-alan"><div class="lbl">Nitelik</div><div class="val">${escapeHtml(k.nitelik ?? '—')}</div></div>
                <div class="ech-alan"><div class="lbl">Sorumlu</div><div class="val">${escapeHtml(k.kullanici?.ad_soyad ?? '—')}</div></div>
                <div class="ech-alan"><div class="lbl">Ada / Parsel</div><div class="val">${k.ada ? escapeHtml(k.ada) + ' / ' + escapeHtml(k.parsel) : 'Bağlı değil'}</div></div>
                <div class="ech-alan is-genis"><div class="lbl">Konum</div><div class="val">${escapeHtml([k.ilce, k.mahalle, k.cadde_sokak].filter(Boolean).join(' · ') || '—')}</div></div>
                ${k.adres ? `<div class="ech-alan is-genis"><div class="lbl">Adres</div><div class="val">${escapeHtml(k.adres)}</div></div>` : ''}
                ${k.aciklama ? `<div class="ech-alan is-genis"><div class="lbl">Açıklama</div><div class="val">${escapeHtml(k.aciklama)}</div></div>` : ''}
            </div>
            <div class="ech-btn-row">
                <a class="ech-btn" href="${escapeAttr(v.detay_url)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>Kaydı Aç</a>
            </div>`;
    }

    function renderTutanak(v) {
        const items = v.tutanaklar.map(t => `
            <div class="ech-tut-item ${t.devam_ediyor ? 'is-acik' : ''}">
                <div class="ech-tut-ust">
                    <span class="num">${t.seri_no ? 'Seri ' + escapeHtml(t.seri_no) : 'Tutanak #' + t.id}</span>
                    ${t.gun_sayisi !== null ? `<span class="ech-rozet ${t.devam_ediyor ? 'is-devam-eden' : 'is-sonlanmis'}">${Number(t.gun_sayisi).toLocaleString('tr-TR')} gün${t.devam_ediyor ? ' · sürüyor' : ''}</span>` : ''}
                </div>
                <div class="ech-tut-alt">
                    <div><div class="lbl">Tutanak</div><div class="val">${t.tutanak_tarihi ? tr(t.tutanak_tarihi) : '—'}</div></div>
                    <div><div class="lbl">Başlangıç</div><div class="val">${t.isgal_baslangic_tarihi ? tr(t.isgal_baslangic_tarihi) : '—'}</div></div>
                    <div><div class="lbl">Bitiş</div><div class="val">${t.isgal_bitis_tarihi ? tr(t.isgal_bitis_tarihi) : 'Devam'}</div></div>
                </div>
                ${t.aciklama ? `<p class="ech-tut-not">${escapeHtml(t.aciklama)}</p>` : ''}
            </div>`).join('');

        const form = v.izinler.duzenle ? ekleKutusu('Yeni Tutanak', `
            <form class="ech-form" data-gonder="tutanak" data-id="${v.kayit.id}">
                <div class="ech-form-row">
                    <div class="ech-form-satir"><label>Seri No</label><input type="text" name="seri_no" maxlength="100"></div>
                    ${tarihAlani('tutanak_tarihi', 'Tutanak Tarihi', `max="${BUGUN}" value="${BUGUN}"`)}
                </div>
                <div class="ech-form-row">
                    ${tarihAlani('isgal_baslangic_tarihi', 'İşgal Başlangıcı', `max="${BUGUN}"`)}
                    ${tarihAlani('isgal_bitis_tarihi', 'İşgal Bitişi')}
                </div>
                <p class="ech-not">İşgal sürüyorsa bitişi boş bırakın. Düzenleme ve silme kayıt ekranından yapılır.</p>
                <div class="ech-form-satir"><label>Açıklama</label><textarea name="aciklama" maxlength="2000"></textarea></div>
                <div class="ech-flash-yer"></div>
                <button type="submit" class="ech-btn is-yesil">Tutanağı Kaydet</button>
            </form>`) : '';

        document.getElementById('ech-tab-tutanak').innerHTML =
            (v.tutanaklar.length === 0 ? '<div class="ech-bos">Henüz tutanak yok.</div>' : items) + form;
    }

    function renderRapor(v) {
        const items = (v.raporlar || []).map(r => {
            const fiyat = r.fiyat !== null && r.fiyat !== undefined
                ? Number(r.fiyat).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺' : null;
            return `
            <div class="ech-tut-item">
                <div class="ech-tut-ust">
                    <span class="num">${r.rapor_no ? 'Rapor ' + escapeHtml(r.rapor_no) : 'Rapor #' + r.id}</span>
                    <span class="tarih">${r.rapor_tarihi ? tr(r.rapor_tarihi) : '—'}</span>
                    ${fiyat ? `<span class="ech-tutar">${fiyat}</span>` : ''}
                    ${r.dosya_url ? `<a class="ech-btn is-mini is-ghost" href="${escapeAttr(r.dosya_url)}" target="_blank" rel="noopener">Dosya</a>` : ''}
                </div>
                ${r.aciklama ? `<p class="ech-tut-not">${escapeHtml(r.aciklama)}</p>` : ''}
            </div>`;
        }).join('');

        const form = v.izinler.duzenle ? ekleKutusu('Yeni Rapor', `
            <form class="ech-form" data-gonder="rapor" data-id="${v.kayit.id}">
                <div class="ech-form-row">
                    <div class="ech-form-satir"><label>Rapor No</label><input type="text" name="rapor_no" maxlength="100"></div>
                    ${tarihAlani('rapor_tarihi', 'Rapor Tarihi', `max="${BUGUN}" value="${BUGUN}"`)}
                </div>
                <div class="ech-form-satir"><label>Bedel (₺)</label><input type="text" name="fiyat" inputmode="decimal" placeholder="12.500,00"></div>
                <div class="ech-form-satir"><label>Ek (PDF / Word)</label><input type="file" name="dosya" accept=".pdf,.doc,.docx"><span class="ipucu">En fazla 20 MB.</span></div>
                <div class="ech-form-satir"><label>Açıklama</label><textarea name="aciklama" maxlength="5000"></textarea></div>
                <div class="ech-flash-yer"></div>
                <button type="submit" class="ech-btn is-yesil">Raporu Kaydet</button>
            </form>`) : '';

        document.getElementById('ech-tab-rapor').innerHTML =
            ((v.raporlar || []).length === 0 ? '<div class="ech-bos">Henüz rapor yok.</div>' : items) + form;
    }

    function renderResim(v) {
        const galeri = v.resimler.length > 0
            ? `<div class="ech-galeri">${v.resimler.map(r => `<a href="${escapeAttr(r.url)}" target="_blank" rel="noopener" title="${escapeAttr(r.aciklama ?? '')}"><img src="${escapeAttr(r.url)}" alt="${escapeAttr(r.aciklama ?? 'İşgal resmi')}" loading="lazy"></a>`).join('')}</div>`
            : '<div class="ech-bos">Henüz resim yok.</div>';
        const form = v.izinler.duzenle ? ekleKutusu('Resim Yükle', `
            <form class="ech-form" data-gonder="resim" data-id="${v.kayit.id}">
                <div class="ech-form-satir"><label>Resimler</label><input type="file" name="resimler[]" accept="image/jpeg,image/png,image/webp" multiple required><span class="ipucu">JPG, PNG, WEBP · her biri en fazla 8 MB · en çok 20 adet</span></div>
                <div class="ech-form-satir"><label>Açıklama</label><input type="text" name="aciklama" maxlength="300"></div>
                <div class="ech-flash-yer"></div>
                <button type="submit" class="ech-btn is-yesil">Yükle</button>
            </form>`) : '';
        document.getElementById('ech-tab-resim').innerHTML = galeri + form;
    }

    // Panel formları — sunucu JSON döner; doğrulama hataları alan alan gösterilir
    const GONDER = {
        tutanak: { yol: '/tutanak', sekme: 'tutanak', haritaYenile: true },
        rapor: { yol: '/rapor', sekme: 'rapor' },
        resim: { yol: '/resim', sekme: 'resim' },
    };
    document.addEventListener('submit', async e => {
        const form = e.target.closest('form[data-gonder]');
        if (!form) return;
        e.preventDefault();
        const ayar = GONDER[form.dataset.gonder];
        const id = +form.dataset.id;
        const btn = form.querySelector('button[type=submit]');
        const flash = form.querySelector('.ech-flash-yer');
        btn.disabled = true;
        try {
            const r = await fetch(API.kayit + '/' + id + ayar.yol, {
                method: 'POST', headers: { 'X-CSRF-TOKEN': API.csrf, 'Accept': 'application/json' }, body: new FormData(form),
            });
            const veri = await r.json().catch(() => ({}));
            if (!r.ok) {
                const hatalar = veri.errors ? Object.values(veri.errors).flat() : [veri.message || 'Kayıt başarısız (' + r.status + ').'];
                flash.innerHTML = '<div class="ech-flash is-hata">' + hatalar.map(escapeHtml).join('<br>') + '</div>';
                return;
            }
            bildir(veri.mesaj || 'Kaydedildi.');
            await ozetYukle(id);
            if (ayar.haritaYenile) haritayiYukle(mevcutFiltre, false);
        } catch (err) {
            flash.innerHTML = '<div class="ech-flash is-hata">Bağlantı hatası: ' + escapeHtml(err.message) + '</div>';
        } finally {
            btn.disabled = false;
        }
    });

    function bildir(mesaj) {
        let kutu = document.getElementById('ech-bildirim');
        if (!kutu) {
            kutu = document.createElement('div');
            kutu.id = 'ech-bildirim';
            kutu.className = 'ech-bildirim';
            kutu.setAttribute('role', 'status');
            document.body.appendChild(kutu);
        }
        kutu.textContent = mesaj;
        kutu.classList.add('is-acik');
        clearTimeout(kutu._t);
        kutu._t = setTimeout(() => kutu.classList.remove('is-acik'), 3200);
    }

    function tr(iso) { try { return new Date(iso).toLocaleDateString('tr-TR'); } catch (e) { return iso; } }
    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function escapeAttr(s) { return escapeHtml(s); }
})();
</script>
@endpush
