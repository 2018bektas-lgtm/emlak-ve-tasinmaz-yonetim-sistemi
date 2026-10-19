@extends('layouts.panel')

@section('title', 'Değerleme Haritası')
@section('contentClass', 'is-flush')
@section('shellClass', 'is-map-full')

@push('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.Default.css">
<link rel="stylesheet" href="{{ asset('css/ecrimisil-harita.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=3">
@endpush

@php
    // Ankara BB · baskentcbs (dışa açık sunucu)
    // Önceden bu katmanlar iç ağdaki abbcbs proxy'si ve gisserver WMS'i
    // üzerinden geliyordu; ikisi de yalnızca belediye ağından erişilebiliyor.
    // baskentcbs dışarıdan da açık ve aynı verileri yayınlıyor.
    //   tip = tile → önbellekli servis, /tile/{z}/{y}/{x}
    //   tip = export → dinamik servis, /export?bbox=...&layers=show:<katman>
    $BCBS = 'https://baskentcbs.ankara.bel.tr/server/rest/services';
    $abbKatmanlar = [
        'imar' => ['ad' => 'İmar Planı', 'alt' => 'UİP · uip_esri3', 'renk' => '#8b5cf6',
            'tip' => 'tile', 'url' => $BCBS.'/plan/uip_esri3/MapServer', 'katman' => 0],
        'parselasyon' => ['ad' => 'Parselasyon', 'alt' => 'Kadastro · ParselAktifCached', 'renk' => '#22c55e',
            'tip' => 'tile', 'url' => $BCBS.'/ortak/ParselAktifCached/MapServer', 'katman' => 0],
        'belediye' => ['ad' => 'Belediye Taşınmazları', 'alt' => 'ABB · tasinmazservice', 'renk' => '#ef4444',
            'tip' => 'export', 'url' => $BCBS.'/tasinmaz/tasinmazservice/MapServer', 'katman' => 0],
        'numarataj' => ['ad' => 'Numarataj', 'alt' => 'Kapı numaraları · ortak/adres', 'renk' => '#f97316',
            'tip' => 'export', 'url' => $BCBS.'/ortak/adres/MapServer', 'katman' => 0],
        'yapi' => ['ad' => 'Yapılar', 'alt' => 'Bina izleri · ortak/adres', 'renk' => '#06b6d4',
            'tip' => 'export', 'url' => $BCBS.'/ortak/adres/MapServer', 'katman' => 2],
    ];
@endphp

@section('content')
<div class="ech-shell dgh-shell" id="dgh-shell">
    <div id="dgh-map" class="ech-map"></div>

    {{-- ==== ARAÇ ÇUBUĞU ==== --}}
    <div class="ech-toolbar">
        <a class="ech-tool" href="{{ route('panel.degerleme.index') }}" title="Listeye dön">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4h3a2 2 0 012 2v12a2 2 0 01-2 2h-3"/><path d="M10 16l-4-4 4-4M14 12H6"/></svg>
            Liste
        </a>
        <div class="ech-tool-divider"></div>
        <button type="button" class="ech-tool" id="dgh-katman-btn" aria-expanded="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 8l10 6 10-6z"/><path d="M2 16l10 6 10-6M2 12l10 6 10-6"/></svg>
            Katmanlar
        </button>
        <button type="button" class="ech-tool" id="dgh-arama-btn" aria-expanded="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            Ara
        </button>
        <button type="button" class="ech-tool" id="dgh-fit-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
            Sığdır
        </button>
        <form class="dgh-hizli" id="dgh-hizli" autocomplete="off">
            <input type="text" name="ada" placeholder="Ada" aria-label="Ada">
            <input type="text" name="parsel" placeholder="Parsel" aria-label="Parsel">
            <button type="submit" title="Ada/parsel bul">Bul</button>
        </form>
        <div class="ech-tool-divider"></div>
        <span class="ech-chip"><strong id="dgh-sayi">0</strong> taşınmaz · <strong id="dgh-rapor">0</strong> rapor</span>
    </div>

    {{-- ==== KATMAN PANELİ ==== --}}
    <div class="ech-panel ech-katman" id="dgh-katman-panel">
        <div class="ech-panel-head" data-drag>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 8l10 6 10-6z"/><path d="M2 12l10 6 10-6"/></svg>
            <div style="flex:1;"><strong>Katmanlar</strong><small>Altlık, değerleme ve ABB katmanları</small></div>
            <button type="button" class="ech-panel-kapat" data-panel-kapat="dgh-katman-panel" aria-label="Kapat">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="ech-panel-scroll">
            <div class="ech-katman-grup">
                <div class="ech-katman-baslik">Altlık</div>
                <div class="ech-radyo-grup" id="dgh-altlik-grup">
                    <div class="ech-radyo is-aktif" data-altlik="uydu-etiket">Hibrit</div>
                    <div class="ech-radyo" data-altlik="uydu">Uydu</div>
                    <div class="ech-radyo" data-altlik="yol">Yol</div>
                    <div class="ech-radyo" data-altlik="osm">OSM</div>
                </div>
            </div>

            <div class="ech-katman-grup">
                <div class="ech-katman-baslik">Değerleme Raporları</div>
                <div class="ech-katman-kart">
                    <span class="ech-katman-ikon" style="background:#0d9488;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8L12 3l8 5L18 20H6z"/></svg></span>
                    <div class="ech-katman-meta"><span class="ech-katman-ad">Arsa Değerlemeleri</span><span class="ech-katman-alt">Parsel / kurum hissesi raporları</span></div>
                    <label class="ech-switch"><input type="checkbox" data-tur-katman="arsa" checked><span class="ech-switch-slider"></span></label>
                </div>
                <div class="ech-katman-kart">
                    <span class="ech-katman-ikon" style="background:#7c3aed;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="1.5"/><path d="M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1"/></svg></span>
                    <div class="ech-katman-meta"><span class="ech-katman-ad">Bağımsız Bölüm Değerlemeleri</span><span class="ech-katman-alt">Daire, dükkan vb. raporları</span></div>
                    <label class="ech-switch"><input type="checkbox" data-tur-katman="bb" checked><span class="ech-switch-slider"></span></label>
                </div>
                <div class="ech-katman-kart">
                    <span class="ech-katman-ikon" style="background:#facc15;color:#0e1726;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h10M4 17h7"/></svg></span>
                    <div class="ech-katman-meta"><span class="ech-katman-ad">Ada/Parsel Etiketi</span><span class="ech-katman-alt">Yakınlaşınca parsel merkezinde</span></div>
                    <label class="ech-switch"><input type="checkbox" id="dgh-lyr-etiket" checked><span class="ech-switch-slider"></span></label>
                </div>
                <div class="ech-katman-kart">
                    <span class="ech-katman-ikon" style="background:#8b5cf6;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="4"/><circle cx="17" cy="7" r="4"/><circle cx="12" cy="17" r="4"/></svg></span>
                    <div class="ech-katman-meta"><span class="ech-katman-ad">Kümeleme</span><span class="ech-katman-alt">Uzakta işaretçileri grupla</span></div>
                    <label class="ech-switch"><input type="checkbox" id="dgh-lyr-cluster" checked><span class="ech-switch-slider"></span></label>
                </div>
                <div class="dgh-renk-mod">
                    <span>Renk:</span>
                    <div class="ech-radyo-grup" id="dgh-renk-grup">
                        <div class="ech-radyo is-aktif" data-renk="tur">Türe göre</div>
                        <div class="ech-radyo" data-renk="birim">Arsa m² değeri</div>
                    </div>
                </div>
            </div>

            <div class="ech-katman-grup">
                <div class="ech-katman-baslik">Ankara BB · İmar ve Kadastro</div>
                @foreach ($abbKatmanlar as $kod => $k)
                    <div class="ech-katman-kart dgh-ov" data-ov="{{ $kod }}" data-tip="{{ $k['tip'] }}" data-url="{{ $k['url'] }}" data-katman="{{ $k['katman'] }}">
                        <span class="ech-katman-ikon" style="background:{{ $k['renk'] }};"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4L3 6v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14M15 6v14"/></svg></span>
                        <div class="ech-katman-meta"><span class="ech-katman-ad">{{ $k['ad'] }}</span><span class="ech-katman-alt">{{ $k['alt'] }}</span>
                            <input type="range" min="10" max="100" value="70" class="dgh-opak" aria-label="{{ $k['ad'] }} saydamlığı" hidden></div>
                        <label class="ech-switch"><input type="checkbox"><span class="ech-switch-slider"></span></label>
                    </div>
                @endforeach
                <p class="ech-not dgh-not">ABB katmanları 15. yakınlaştırma düzeyinden itibaren görünür.</p>
            </div>

            <div class="ech-lejant" id="dgh-lejant-tur">
                <div class="ech-lejant-baslik">Taşınmaz Rengi</div>
                <div class="ech-lejant-oge"><span class="ech-lejant-nokta" style="background:#0d9488;"></span> Arsa değerlemesi</div>
                <div class="ech-lejant-oge"><span class="ech-lejant-nokta" style="background:#7c3aed;"></span> Bağımsız bölüm değerlemesi</div>
                <div class="ech-lejant-oge"><span class="ech-lejant-nokta" style="background:#f59e0b;"></span> İkisi de var</div>
            </div>
            <div class="ech-lejant" id="dgh-lejant-birim" hidden>
                <div class="ech-lejant-baslik">Arsa birim değeri (TL/m²)</div>
                <div class="dgh-skala"><span></span></div>
                <div class="dgh-skala-uclar"><small id="dgh-skala-min">—</small><small id="dgh-skala-max">—</small></div>
                <div class="ech-lejant-oge"><span class="ech-lejant-nokta" style="background:#94a3b8;"></span> Arsa raporu yok</div>
            </div>
        </div>
    </div>

    {{-- ==== ARAMA PANELİ ==== --}}
    <div class="ech-panel ech-arama" id="dgh-arama-panel" hidden>
        <div class="ech-panel-head" data-drag>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <div style="flex:1;"><strong>Ara / Filtrele</strong><small>Konum, tür, yıl, rapor no</small></div>
            <button type="button" class="ech-panel-kapat" data-panel-kapat="dgh-arama-panel" aria-label="Kapat">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="ech-panel-scroll" style="padding:14px;">
            <form class="ech-form" id="dgh-arama-form">
                <div class="ech-form-satir"><label>İl</label>
                    <select name="il_id" id="dgh-f-il"><option value="">— Tümü —</option>@foreach ($iller as $il)<option value="{{ $il->id }}">{{ $il->ad }}</option>@endforeach</select>
                </div>
                <div class="ech-form-satir"><label>İlçe</label><select name="ilce_id" id="dgh-f-ilce" disabled><option value="">— Önce il —</option></select></div>
                <div class="ech-form-satir"><label>Mahalle</label><select name="mahalle_id" id="dgh-f-mahalle" disabled><option value="">— Önce ilçe —</option></select></div>
                <div class="ech-form-row">
                    <div class="ech-form-satir"><label>Ada</label><input type="text" name="ada"></div>
                    <div class="ech-form-satir"><label>Parsel</label><input type="text" name="parsel"></div>
                </div>
                <div class="ech-form-row">
                    <div class="ech-form-satir"><label>Rapor Yılı</label>
                        <select name="yil"><option value="">— Tümü —</option>@foreach ($yillar as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</select>
                    </div>
                    <div class="ech-form-satir"><label>Rapor / BB No</label><input type="text" name="q" placeholder="2026/15"></div>
                </div>
                <div class="ech-btn-row">
                    <button type="submit" class="ech-btn">Uygula</button>
                    <button type="button" class="ech-btn is-ghost" id="dgh-temizle">Temizle</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ==== TAŞINMAZ PANELİ ==== --}}
    <div class="ech-panel ech-islem" id="dgh-islem-panel" hidden>
        <div class="ech-panel-head" data-drag>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M7 21h10M4 7h16"/><path d="M6 7l-3 7a3 3 0 0 0 6 0zM18 7l-3 7a3 3 0 0 0 6 0z"/></svg>
            <div style="flex:1;"><strong id="dgh-islem-baslik">Taşınmaz</strong><small id="dgh-islem-alt">Seçilmedi</small></div>
            <button type="button" class="ech-panel-kapat" data-panel-kapat="dgh-islem-panel" aria-label="Kapat">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="ech-panel-scroll" style="padding:14px;" id="dgh-islem-icerik"></div>
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
        geojson: {{ \Illuminate\Support\Js::from(route('panel.degerleme.harita.geojson')) }},
        tasinmaz: {{ \Illuminate\Support\Js::from(url('/panel/degerleme/harita/tasinmaz')) }},
        ilceler: {{ \Illuminate\Support\Js::from(url('/panel/ajax/ilceler')) }},
        mahalleler: {{ \Illuminate\Support\Js::from(url('/panel/ajax/mahalleler')) }},
    };
    const TUR = {
        arsa:  { renk: '#0d9488', etiket: 'Arsa' },
        bb:    { renk: '#7c3aed', etiket: 'Bağımsız Bölüm' },
        ikisi: { renk: '#f59e0b', etiket: 'Arsa + BB' },
    };
    const ETIKET_ZOOM = 16;
    const $ = id => document.getElementById(id);
    const para = n => Number(n || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const kisaPara = n => { n = Number(n || 0); return n >= 1e9 ? (n / 1e9).toLocaleString('tr-TR', { maximumFractionDigits: 1 }) + ' mr' : n >= 1e6 ? (n / 1e6).toLocaleString('tr-TR', { maximumFractionDigits: 1 }) + ' mn' : n.toLocaleString('tr-TR', { maximumFractionDigits: 0 }); };
    const tarih = iso => iso ? new Date(iso).toLocaleDateString('tr-TR') : '—';
    const esc = s => s === null || s === undefined ? '' : String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));

    const map = L.map('dgh-map', { zoomSnap: 0.5, zoomDelta: 0.5, zoomControl: false, attributionControl: false }).setView([39.9334, 32.8597], 11);
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    // ---- Altlık ----
    const g = l => L.tileLayer('https://mt{s}.google.com/vt/lyrs=' + l + '&x={x}&y={y}&z={z}', { subdomains: ['0', '1', '2', '3'], maxZoom: 21 });
    const altliklar = { 'uydu-etiket': g('y'), 'uydu': g('s'), 'yol': g('m'), 'osm': L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }) };
    let aktifAltlik = 'uydu-etiket';
    altliklar[aktifAltlik].addTo(map);
    document.querySelectorAll('#dgh-altlik-grup .ech-radyo').forEach(el => el.addEventListener('click', () => {
        if (el.dataset.altlik === aktifAltlik) return;
        map.removeLayer(altliklar[aktifAltlik]);
        aktifAltlik = el.dataset.altlik;
        altliklar[aktifAltlik].addTo(map).bringToBack();
        document.querySelectorAll('#dgh-altlik-grup .ech-radyo').forEach(x => x.classList.toggle('is-aktif', x === el));
    }));

    // ---- ABB katmanları (baskentcbs — doğrudan, proxy yok) ----
    const ovKatman = {};
    function ovOlustur(kart) {
        const opak = kart.querySelector('.dgh-opak').value / 100;
        const url = kart.dataset.url;
        const ayar = { opacity: opak, maxZoom: 22, minZoom: 15, tileSize: 256 };

        // Önbellekli servis: ArcGIS tile şeması /tile/{z}/{y}/{x}
        if (kart.dataset.tip === 'tile') {
            return L.tileLayer(url + '/tile/{z}/{y}/{x}', Object.assign({ maxNativeZoom: 19 }, ayar));
        }

        // Dinamik servis: her kare için bbox hesaplanıp /export çağrılır
        const katman = L.tileLayer('', ayar);
        katman.getTileUrl = function (c) {
            const nwP = c.multiplyBy(256), seP = nwP.add(L.point(256, 256));
            const nw = L.CRS.EPSG3857.project(this._map.unproject(nwP, c.z));
            const se = L.CRS.EPSG3857.project(this._map.unproject(seP, c.z));
            return url + '/export?' + new URLSearchParams({
                bbox: [nw.x, se.y, se.x, nw.y].join(','),
                bboxSR: '3857',
                imageSR: '3857',
                size: '256,256',
                dpi: '96',
                format: 'png32',
                transparent: 'true',
                layers: 'show:' + kart.dataset.katman,
                f: 'image',
            });
        };
        return katman;
    }
    document.querySelectorAll('.dgh-ov').forEach(kart => {
        const kutu = kart.querySelector('input[type=checkbox]');
        const aralik = kart.querySelector('.dgh-opak');
        kutu.addEventListener('change', () => {
            const kod = kart.dataset.ov;
            aralik.hidden = !kutu.checked;
            if (kutu.checked) {
                ovKatman[kod] = ovOlustur(kart).addTo(map);
                if (map.getZoom() < 15) map.setZoom(15);
            } else if (ovKatman[kod]) {
                map.removeLayer(ovKatman[kod]);
                delete ovKatman[kod];
            }
        });
        aralik.addEventListener('input', () => ovKatman[kart.dataset.ov]?.setOpacity(aralik.value / 100));
    });

    // ---- Değerleme katmanları ----
    const parselKatman = L.featureGroup().addTo(map);
    const etiketKatman = L.featureGroup();
    const markerKatman = L.featureGroup();
    const clusterKatman = L.markerClusterGroup({
        showCoverageOnHover: false, maxClusterRadius: 50, disableClusteringAtZoom: 16,
        iconCreateFunction: c => {
            const n = c.getChildCount();
            return L.divIcon({ html: `<div class="ech-cluster dgh-cluster">${n}</div>`, className: 'ech-cluster-wrap', iconSize: [40, 40] });
        },
    });
    let ogeler = [];       // { p, layer, marker, etiket }
    let renkModu = 'tur';
    let birimAralik = [0, 0];
    let secili = null;
    let filtre = new URLSearchParams();
    let tumSinir = null;

    function renk(p) {
        if (renkModu === 'tur') return TUR[p.tur].renk;
        if (p.birim === null || p.birim === undefined) return '#94a3b8';
        const [mn, mx] = birimAralik;
        const t = mx > mn ? (Math.log(p.birim + 1) - Math.log(mn + 1)) / (Math.log(mx + 1) - Math.log(mn + 1)) : 0.5;
        // yeşil → sarı → kırmızı
        const h = Math.round(140 - Math.max(0, Math.min(1, t)) * 140);
        return `hsl(${h}, 78%, 45%)`;
    }
    function turGorunur(p) {
        const arsa = document.querySelector('[data-tur-katman="arsa"]').checked;
        const bb = document.querySelector('[data-tur-katman="bb"]').checked;
        return (p.arsa_sayisi > 0 && arsa) || (p.bb_sayisi > 0 && bb);
    }
    function stil(o) {
        const r = renk(o.p), aktif = secili === o;
        return { color: aktif ? '#0f172a' : r, fillColor: r, weight: aktif ? 3.5 : 2, fillOpacity: aktif ? 0.55 : 0.32 };
    }
    function markerIkon(o) {
        return L.divIcon({ className: 'ech-marker-wrap', html: `<div class="ech-marker dgh-marker" style="background:${renk(o.p)}"></div>`, iconSize: [26, 26], iconAnchor: [13, 24] });
    }

    function ciz() {
        parselKatman.clearLayers(); etiketKatman.clearLayers(); markerKatman.clearLayers(); clusterKatman.clearLayers();
        let ts = 0, rs = 0;
        ogeler.forEach(o => {
            if (!turGorunur(o.p)) return;
            ts++; rs += o.p.arsa_sayisi + o.p.bb_sayisi;
            if (o.layer.setStyle) o.layer.setStyle(stil(o));
            parselKatman.addLayer(o.layer);
            o.marker.setIcon(markerIkon(o));
            markerKatman.addLayer(o.marker);
            etiketKatman.addLayer(o.etiket);
        });
        $('dgh-sayi').textContent = ts.toLocaleString('tr-TR');
        $('dgh-rapor').textContent = rs.toLocaleString('tr-TR');
        isaretciGuncelle();
        etiketGuncelle();
    }
    function isaretciGuncelle() {
        [markerKatman, clusterKatman].forEach(k => map.hasLayer(k) && map.removeLayer(k));
        if ($('dgh-lyr-cluster').checked) { clusterKatman.clearLayers(); markerKatman.eachLayer(m => clusterKatman.addLayer(m)); map.addLayer(clusterKatman); }
        else map.addLayer(markerKatman);
    }
    function etiketGuncelle() {
        const goster = $('dgh-lyr-etiket').checked && map.getZoom() >= ETIKET_ZOOM;
        if (goster && !map.hasLayer(etiketKatman)) map.addLayer(etiketKatman);
        if (!goster && map.hasLayer(etiketKatman)) map.removeLayer(etiketKatman);
    }
    map.on('zoomend', etiketGuncelle);

    async function yukle(sigdir = true) {
        const r = await fetch(API.geojson + (filtre.toString() ? '?' + filtre : ''), { headers: { Accept: 'application/json' } });
        const fc = await r.json();
        ogeler = (fc.features || []).map(f => {
            const p = f.properties;
            const layer = L.geoJSON(f, { pointToLayer: (ft, ll) => L.circleMarker(ll, { radius: 7 }) }).getLayers()[0];
            const merkez = layer.getBounds ? layer.getBounds().getCenter() : layer.getLatLng();
            const o = { p, layer, merkez };
            o.marker = L.marker(merkez);
            o.marker.bindTooltip(`<strong>${esc(p.ada)}/${esc(p.parsel)}</strong> · ${esc(p.mahalle || '')}<br>${kisaPara(p.son_toplam)} TL · ${esc(TUR[p.tur].etiket)}`, { direction: 'top', offset: [0, -20] });
            o.etiket = L.marker(merkez, { interactive: false, icon: L.divIcon({ className: '', html: `<div class="ech-etiket">${esc(p.ada)}/${esc(p.parsel)}</div>`, iconSize: null }) });
            [layer, o.marker].forEach(x => x.on('click', () => sec(o)));
            layer.on('mouseover', () => { if (secili !== o && layer.setStyle) layer.setStyle({ fillOpacity: 0.5, weight: 3 }); });
            layer.on('mouseout', () => layer.setStyle && layer.setStyle(stil(o)));
            return o;
        });
        const birimler = ogeler.map(o => o.p.birim).filter(b => b !== null && b !== undefined && b > 0);
        birimAralik = birimler.length ? [Math.min(...birimler), Math.max(...birimler)] : [0, 0];
        $('dgh-skala-min').textContent = birimler.length ? kisaPara(birimAralik[0]) : '—';
        $('dgh-skala-max').textContent = birimler.length ? kisaPara(birimAralik[1]) : '—';
        if (secili) secili = ogeler.find(o => o.p.id === secili.p.id) || null;
        ciz();
        tumSinir = ogeler.length ? L.featureGroup(ogeler.map(o => o.layer)).getBounds() : null;
        if (sigdir && tumSinir && tumSinir.isValid()) map.fitBounds(tumSinir, { padding: [40, 40], maxZoom: 17 });
        return ogeler;
    }

    // ---- Seçim / yan panel ----
    async function sec(o) {
        const eski = secili;
        secili = o;
        if (eski && eski.layer.setStyle) eski.layer.setStyle(stil(eski));
        if (o.layer.setStyle) o.layer.setStyle(stil(o));
        const panel = $('dgh-islem-panel');
        panel.hidden = false;
        $('dgh-islem-baslik').textContent = `${o.p.ada}/${o.p.parsel}`;
        $('dgh-islem-alt').textContent = [o.p.ilce, o.p.mahalle].filter(Boolean).join(' · ');
        $('dgh-islem-icerik').innerHTML = '<div class="ech-yukleniyor">Yükleniyor</div>';
        try {
            const r = await fetch(API.tasinmaz + '/' + o.p.id, { headers: { Accept: 'application/json' } });
            if (!r.ok) throw new Error('Yüklenemedi (' + r.status + ')');
            const v = await r.json();
            if (secili !== o) return;
            panelCiz(v);
        } catch (e) {
            $('dgh-islem-icerik').innerHTML = `<div class="ech-flash is-hata">${esc(e.message)}</div>`;
        }
    }

    function panelCiz(v) {
        const t = v.tasinmaz;
        const alanlar = [
            ['Kayıt Tipi', t.kayit_tipi], ['Parsel Alanı', t.alan !== null ? para(t.alan) + ' m²' : null],
            ['Kurum Hissesi', t.kurum_hisse !== null ? para(t.kurum_hisse) + ' m²' : null], ['Nitelik', t.nitelik],
            ['İmar', t.imar], ['Emsal', t.emsal !== null ? para(t.emsal) : null], ['Yençok', t.yencok],
        ].filter(a => a[1]);
        const raporlar = v.degerlemeler.map(d => `
            <div class="ech-tut-item dgh-rapor is-${d.tip}">
                <div class="ech-tut-ust">
                    <span class="num">${d.rapor_no ? 'Rapor ' + esc(d.rapor_no) : 'Değerleme #' + d.id}</span>
                    <span class="dg-tur is-${d.tip}">${d.tip === 'bb' ? 'BB' : 'Arsa'}</span>
                </div>
                <div class="dgh-tutar">${para(d.toplam)} <small>TL</small></div>
                <div class="dgh-rapor-alt">
                    ${d.bolum ? `<span>${esc(d.bolum)}</span>` : ''}
                    ${d.alan ? `<span>${para(d.alan)} m²</span>` : ''}
                    ${d.birim_fiyat !== null ? `<span>${para(d.birim_fiyat)} TL/m²</span>` : ''}
                    <span>${tarih(d.rapor_tarihi)}</span>
                    ${d.hazirlayan ? `<span>${esc(d.hazirlayan)}</span>` : ''}
                </div>
                <div class="ech-btn-row">
                    <a class="ech-btn is-mini" href="${esc(d.detay_url)}">Detay</a>
                    <a class="ech-btn is-mini is-ghost" href="${esc(d.rapor_url)}" target="_blank">Rapor çıktısı</a>
                    ${d.pdf_url ? `<a class="ech-btn is-mini is-ghost" href="${esc(d.pdf_url)}" target="_blank" rel="noopener">PDF</a>` : ''}
                </div>
            </div>`).join('');
        const yeni = Object.entries(v.yeni || {}).map(([tip, url]) => `<a class="ech-btn is-mini" href="${esc(url)}">+ ${tip === 'bb' ? 'Bağımsız bölüm' : 'Arsa'} değerlemesi</a>`).join('');
        $('dgh-islem-icerik').innerHTML = `
            <div class="ech-ozet-grid">${alanlar.map(a => `<div class="ech-alan"><div class="lbl">${a[0]}</div><div class="val">${esc(a[1])}</div></div>`).join('')}</div>
            <div class="ech-btn-row">${t.detay_url ? `<a class="ech-btn is-ghost is-mini" href="${esc(t.detay_url)}">Taşınmaz kaydı →</a>` : ''}${yeni}</div>
            <div class="dgh-baslik">Değerleme raporları (${v.degerlemeler.length})</div>
            ${raporlar || '<div class="ech-bos">Rapor yok.</div>'}`;
    }

    // ---- Katman anahtarları ----
    document.querySelectorAll('[data-tur-katman]').forEach(k => k.addEventListener('change', ciz));
    $('dgh-lyr-etiket').addEventListener('change', etiketGuncelle);
    $('dgh-lyr-cluster').addEventListener('change', isaretciGuncelle);
    document.querySelectorAll('#dgh-renk-grup .ech-radyo').forEach(el => el.addEventListener('click', () => {
        renkModu = el.dataset.renk;
        document.querySelectorAll('#dgh-renk-grup .ech-radyo').forEach(x => x.classList.toggle('is-aktif', x === el));
        $('dgh-lejant-tur').hidden = renkModu !== 'tur';
        $('dgh-lejant-birim').hidden = renkModu !== 'birim';
        ciz();
    }));

    // ---- Araç çubuğu / paneller ----
    const ac = (panelId, btnId) => { const p = $(panelId); p.hidden = !p.hidden; $(btnId).setAttribute('aria-expanded', String(!p.hidden)); };
    $('dgh-katman-btn').addEventListener('click', () => ac('dgh-katman-panel', 'dgh-katman-btn'));
    $('dgh-arama-btn').addEventListener('click', () => ac('dgh-arama-panel', 'dgh-arama-btn'));
    $('dgh-fit-btn').addEventListener('click', () => tumSinir && tumSinir.isValid() && map.fitBounds(tumSinir, { padding: [40, 40], maxZoom: 17 }));
    document.querySelectorAll('[data-panel-kapat]').forEach(b => b.addEventListener('click', () => {
        $(b.dataset.panelKapat).hidden = true;
        const btn = { 'dgh-katman-panel': 'dgh-katman-btn', 'dgh-arama-panel': 'dgh-arama-btn' }[b.dataset.panelKapat];
        if (btn) $(btn).setAttribute('aria-expanded', 'false');
        if (b.dataset.panelKapat === 'dgh-islem-panel' && secili) { const o = secili; secili = null; o.layer.setStyle && o.layer.setStyle(stil(o)); }
    }));
    document.querySelectorAll('.ech-panel-head[data-drag]').forEach(head => {
        const panel = head.closest('.ech-panel');
        head.addEventListener('mousedown', e => {
            if (e.target.closest('button')) return;
            const r = panel.getBoundingClientRect(), bx = e.clientX, by = e.clientY;
            Object.assign(panel.style, { right: 'auto', bottom: 'auto', left: r.left + 'px', top: r.top + 'px' });
            const mv = ev => Object.assign(panel.style, { left: (r.left + ev.clientX - bx) + 'px', top: (r.top + ev.clientY - by) + 'px' });
            document.addEventListener('mousemove', mv);
            document.addEventListener('mouseup', () => document.removeEventListener('mousemove', mv), { once: true });
        });
    });

    // Hızlı ada/parsel: önce haritadakiler arasında, yoksa sunucuda ara
    $('dgh-hizli').addEventListener('submit', async e => {
        e.preventDefault();
        const ada = e.target.ada.value.trim(), parsel = e.target.parsel.value.trim();
        if (!ada) return;
        let o = ogeler.find(x => String(x.p.ada) === ada && (!parsel || String(x.p.parsel) === parsel));
        if (!o) {
            filtre = new URLSearchParams({ ada, ...(parsel ? { parsel } : {}) });
            await yukle(true);
            o = ogeler[0];
        }
        if (!o) { bildir('Bu ada/parselde değerlenmiş taşınmaz yok.'); return; }
        map.fitBounds(o.layer.getBounds ? o.layer.getBounds() : L.latLngBounds([o.merkez, o.merkez]), { maxZoom: 18, padding: [60, 60] });
        sec(o);
    });

    function bildir(m) {
        let k = $('dgh-bildirim');
        if (!k) { k = document.createElement('div'); k.id = 'dgh-bildirim'; k.className = 'ech-bildirim'; k.setAttribute('role', 'status'); document.body.appendChild(k); }
        k.textContent = m; k.classList.add('is-acik');
        clearTimeout(k._t); k._t = setTimeout(() => k.classList.remove('is-acik'), 3200);
    }

    // Filtre formu
    $('dgh-f-il').addEventListener('change', async e => {
        const i = $('dgh-f-ilce'), m = $('dgh-f-mahalle');
        i.innerHTML = '<option value="">— Tümü —</option>'; m.innerHTML = '<option value="">— Önce ilçe —</option>'; m.disabled = true;
        i.disabled = !e.target.value;
        if (e.target.value) (await (await fetch(API.ilceler + '/' + e.target.value)).json() || []).forEach(x => i.add(new Option(x.ad, x.id)));
    });
    $('dgh-f-ilce').addEventListener('change', async e => {
        const m = $('dgh-f-mahalle');
        m.innerHTML = '<option value="">— Tümü —</option>';
        m.disabled = !e.target.value;
        if (e.target.value) (await (await fetch(API.mahalleler + '/' + e.target.value)).json() || []).forEach(x => m.add(new Option(x.ad, x.id)));
    });
    $('dgh-arama-form').addEventListener('submit', e => {
        e.preventDefault();
        filtre = new URLSearchParams();
        for (const [k, v] of new FormData(e.target)) if (v) filtre.set(k, v);
        yukle(true);
    });
    $('dgh-temizle').addEventListener('click', () => {
        $('dgh-arama-form').reset();
        $('dgh-f-ilce').disabled = $('dgh-f-mahalle').disabled = true;
        filtre = new URLSearchParams();
        yukle(true);
    });

    // ?tasinmaz=ID ile açılırsa o taşınmazı seç
    const acilis = new URLSearchParams(location.search).get('tasinmaz');
    yukle(!acilis).then(() => {
        const o = acilis && ogeler.find(x => String(x.p.id) === acilis);
        if (o) { map.fitBounds(o.layer.getBounds ? o.layer.getBounds() : L.latLngBounds([o.merkez, o.merkez]), { maxZoom: 18 }); sec(o); }
    });
})();
</script>
@endpush
