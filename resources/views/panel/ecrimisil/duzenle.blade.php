@extends('layouts.panel')

@section('title', 'İşgal Kaydı Düzenle')

@push('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
<style>
    .ec-harita-wrap { background: #0e1726; border-radius: 12px; padding: 12px; margin-bottom: 16px; }
    .ec-harita-baslik { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
    .ec-harita-baslik h3 { color: #fff; margin: 0; font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
    .ec-harita-baslik h3 svg { color: #38bdf8; width: 18px; height: 18px; }
    .ec-harita-baslik small { color: #8a91a1; font-size: .78rem; }
    .ec-harita-toolbar { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; align-items: center; }
    .ec-mt-btn { display: inline-flex; align-items: center; gap: 5px; padding: 7px 12px; background: rgba(255,255,255,.06); color: #dfe3ea; border: 1px solid rgba(255,255,255,.08); border-radius: 7px; font-size: .76rem; font-weight: 600; cursor: pointer; transition: all .15s ease; }
    .ec-mt-btn svg { width: 14px; height: 14px; }
    .ec-mt-btn:hover { background: rgba(37,99,235,.2); color: #fff; border-color: rgba(37,99,235,.4); }
    .ec-mt-btn.is-aktif { background: #2563eb; color: #fff; border-color: #2563eb; box-shadow: 0 4px 12px rgba(37,99,235,.35); }
    .ec-mt-btn.is-tehlike:hover { background: rgba(220,38,38,.25); border-color: rgba(220,38,38,.5); color: #fca5a5; }
    .ec-mt-btn[hidden] { display: none; }
    .ec-mt-durum { margin-left: auto; padding: 5px 12px; background: rgba(255,255,255,.04); border-radius: 6px; font-size: .74rem; color: #9aa3b2; display: inline-flex; gap: 8px; }
    .ec-mt-durum b { color: #fff; font-weight: 700; }
    .ec-mt-durum.is-var { background: rgba(52,211,153,.12); color: #a7f3d0; }
    .ec-mt-durum.is-var b { color: #6ee7b7; }
    .ec-harita { height: 500px; border-radius: 10px; overflow: hidden; background: #16223c; position: relative; }
    .ec-harita .leaflet-container { background: #16223c; }
    .ec-harita.is-tam { position: fixed; inset: 12px; height: auto; z-index: 9999; border: 2px solid #2563eb; }

    .ec-vertex-wrap { background: none !important; border: none !important; }
    .ec-vertex { width: 12px; height: 12px; background: #fff; border: 2px solid #f43f5e; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,.4); }
    .ec-vertex-first { width: 16px; height: 16px; background: #f43f5e; border-color: #fff; box-shadow: 0 0 0 2px #f43f5e, 0 1px 4px rgba(0,0,0,.4); cursor: pointer; animation: ec-pulse 1.6s ease-in-out infinite; }
    @keyframes ec-pulse { 0%,100%{transform:scale(1);} 50%{transform:scale(1.18);} }
    .ec-vertex-edit { width: 14px; height: 14px; background: #0e1726; border-color: #f43f5e; cursor: move; box-shadow: 0 1px 4px rgba(0,0,0,.5); }
    .ec-vertex-edge { width: 10px; height: 10px; background: rgba(244,63,94,.85); border: 1.5px solid #fff; border-radius: 50%; cursor: cell; opacity: .6; transition: opacity .15s ease, transform .15s ease; }
    .ec-vertex-edge:hover { opacity: 1; transform: scale(1.3); }
    .ec-mod-cizim, .ec-mod-cizim .leaflet-container { cursor: crosshair !important; }
    .ec-mod-duzenle .leaflet-container { cursor: grab; }
    .ec-ipucu { margin-top: 8px; padding: 8px 14px; background: rgba(56,189,248,.08); color: #7dd3fc; border-left: 3px solid #38bdf8; border-radius: 4px; font-size: .78rem; min-height: 36px; display: flex; align-items: center; transition: background .2s ease; }
    .ec-ipucu.is-aktif { background: rgba(245,158,11,.12); color: #fcd34d; border-left-color: #f59e0b; }
    .ec-tsn-bilgi { margin-top: 4px; }
    .ec-tsn-durum { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; font-size: .82rem; transition: background .2s ease, border-color .2s ease; }
    .ec-tsn-durum svg { flex: 0 0 14px; }
    .ec-tsn-durum strong { display: block; font-weight: 700; }
    .ec-tsn-durum small { display: block; color: #6b7280; font-size: .74rem; margin-top: 2px; }
    .ec-tsn-durum.is-bos { background: #f9fafb; border: 1px dashed #d1d5db; color: #6b7280; }
    .ec-tsn-durum.is-arama { background: #fef3c7; border: 1px solid #fde68a; color: #92400e; }
    .ec-tsn-durum.is-bulundu { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
    .ec-tsn-durum.is-bulundu strong { color: #064e3b; }
    .ec-tsn-durum.is-bulundu small { color: #059669; }
    .ec-tsn-durum.is-bulunamadi { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }

    .ec-mvt-tooltip { background: rgba(15,23,42,.95) !important; color: #fff !important; border: none !important; padding: 6px 10px !important; border-radius: 6px !important; font-size: .78rem !important; box-shadow: 0 4px 12px rgba(0,0,0,.35) !important; }
    .ec-mvt-tooltip::before { border-top-color: rgba(15,23,42,.95) !important; }
    .ec-mvt-tooltip strong { display: block; color: #fbbf24; font-weight: 700; font-size: .82rem; }
    .ec-mvt-tooltip .sub { color: #cbd5e1; font-size: .7rem; margin-top: 2px; }
    .ec-mvt-tooltip .aksiyon { display: block; margin-top: 4px; color: #38bdf8; font-weight: 700; font-size: .72rem; }
</style>
@endpush

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <h2>İşgal Kaydını Düzenle</h2>
        <small>#{{ $kayit->id }} · {{ $kayit->ad_soyad_unvan }}</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.ecrimisil.detay', $kayit->id) }}" class="btn-cancel">Detaya Dön</a>
    </div>
</section>

@if ($errors->any())
    <div class="flash-error"><ul style="margin:0;padding-left:20px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('panel.ecrimisil.guncelle', $kayit->id) }}" id="ec-form">
    @csrf @method('PUT')

    {{-- ==== HARİTA ==== --}}
    <div class="ec-harita-wrap">
        <div class="ec-harita-baslik">
            <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-7-7-7-13a7 7 0 0114 0c0 6-7 13-7 13z"/><circle cx="12" cy="9" r="2.6"/></svg> İşgal Alanı — Harita</h3>
            <small>Poligon üzerine tıklayınca düzenleme aktifleşir</small>
        </div>
        <div class="ec-harita-toolbar">
            <button type="button" class="ec-mt-btn" id="ec-btn-poligon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 8l3 12h14l3-12z"/></svg>Poligon Çiz</button>
            <button type="button" class="ec-mt-btn is-tehlike" id="ec-btn-sil" hidden><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M6 6l1 14a2 2 0 002 2h6a2 2 0 002-2l1-14"/></svg>Sil</button>
            <button type="button" class="ec-mt-btn" id="ec-btn-konum"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>Konumuma Git</button>
            <label class="ec-mt-btn" for="ec-kml-input"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>KML Yükle</label>
            <input type="file" id="ec-kml-input" accept=".kml" style="display:none;">
            <button type="button" class="ec-mt-btn" id="ec-btn-tam-ekran"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>Tam Ekran</button>
            <span class="ec-mt-durum" id="ec-mt-durum">Alan: <b>—</b></span>
        </div>
        <div id="ec-harita" class="ec-harita"></div>
        <div class="ec-ipucu" id="ec-ipucu">Poligon üzerine tıklayarak düzenleme moduna girin, vertex noktalarını sürükleyin.</div>
        <input type="hidden" name="koordinat" id="ec-koordinat">
        <input type="hidden" name="lat" id="ec-lat">
        <input type="hidden" name="lng" id="ec-lng">
    </div>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">1</span>
            <div class="form-section-head-text"><h3>Şirket / Kişi Bilgileri</h3></div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field"><label>Ad Soyad / Şirket Ünvanı *</label><input type="text" name="ad_soyad_unvan" class="form-input" value="{{ old('ad_soyad_unvan', $kayit->ad_soyad_unvan) }}" required maxlength="200"></div>
            <div class="form-field"><label>TC Kimlik / Vergi No</label><input type="text" name="tc_vergi_no" class="form-input" value="{{ old('tc_vergi_no', $kayit->tc_vergi_no) }}" maxlength="20"></div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field"><label>İşgal Niteliği</label><input type="text" name="nitelik" class="form-input" value="{{ old('nitelik', $kayit->nitelik) }}" maxlength="150" placeholder="Bakkal, büfe, depo vb."></div>
            <div class="form-field"><label>Cadde / Sokak</label><input type="text" name="cadde_sokak" class="form-input" value="{{ old('cadde_sokak', $kayit->cadde_sokak) }}" maxlength="200"></div>
        </div>
        <div class="form-field"><label>Açık Adres</label><input type="text" name="adres" class="form-input" value="{{ old('adres', $kayit->adres) }}" maxlength="500"></div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">2</span>
            <div class="form-section-head-text"><h3>Taşınmaz & Sorumlu</h3></div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field">
                <label>Bağlanan Taşınmaz</label>
                <input type="hidden" name="tasinmaz_id" id="tasinmaz_id" value="{{ old('tasinmaz_id', $kayit->tasinmaz_id) }}">
                <div class="ec-tsn-bilgi" id="ec-tsn-bilgi">
                    @if ($kayit->tasinmaz)
                        <div class="ec-tsn-durum is-bulundu">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                            <div>
                                <strong>Ada {{ $kayit->tasinmaz->ada }} / Parsel {{ $kayit->tasinmaz->parsel }}</strong>
                                <small>{{ $kayit->tasinmaz->il?->ad }} · {{ $kayit->tasinmaz->ilce?->ad }} · {{ $kayit->tasinmaz->mahalle?->ad }}</small>
                            </div>
                        </div>
                    @else
                        <div class="ec-tsn-durum is-bos">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 8l10 6 10-6z"/><path d="M2 12l10 6 10-6"/></svg>
                            Haritada bir alan çizin; içinde bir taşınmaz varsa otomatik bağlanacak.
                        </div>
                    @endif
                </div>
                <small class="hint">Aynı taşınmaza birden fazla ecrimisil kaydı serbesttir.</small>
            </div>
            <div class="form-field">
                <label>Sorumlu Kullanıcı</label>
                <select name="kullanici_id" class="form-select">
                    <option value="">— Atanmadı —</option>
                    @foreach ($kullanicilar as $k)
                        <option value="{{ $k->id }}" @selected(old('kullanici_id', $kayit->kullanici_id) == $k->id)>{{ $k->ad }} {{ $k->soyad }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-field"><label>Açıklama</label><textarea name="aciklama" class="form-input" rows="3" maxlength="5000">{{ old('aciklama', $kayit->aciklama) }}</textarea></div>
    </section>

    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
        <a href="{{ route('panel.ecrimisil.detay', $kayit->id) }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">Güncelle</button>
    </div>
</form>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script>
/* Ecrimisil düzenle — olustur.blade.php ile aynı motor */
(function () {
    'use strict';
    const haritaEl = document.getElementById('ec-harita');
    if (!haritaEl) return;

    const eskiKoordinat = @json(old('koordinat', $kayit->koordinat ? json_encode($kayit->koordinat) : null));
    const API_TASINMAZ_BUL = @json(route('panel.ecrimisil.tasinmaz-bul'));
    const API_MEVCUT = @json(route('panel.ecrimisil.mevcut-ecrimisiller'));
    const HARIC_ID = @json($kayit->id);
    const CSRF = @json(csrf_token());

    @php
        $refKoord = null;
        if ($tasinmaz && $tasinmaz->koordinat) {
            $refKoord = ['koordinat' => $tasinmaz->koordinat->koordinat, 'lat' => $tasinmaz->koordinat->lat, 'lng' => $tasinmaz->koordinat->lng];
        }
    @endphp
    const refKoordinat = @json($refKoord);

    const harita = L.map('ec-harita', { zoomControl: false, attributionControl: false, doubleClickZoom: false })
        .setView([39.9334, 32.8597], 12);
    L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { subdomains: ['mt0','mt1','mt2','mt3'], maxZoom: 20 }).addTo(harita);

    if (refKoordinat) {
        try {
            const g = typeof refKoordinat.koordinat === 'string' ? JSON.parse(refKoordinat.koordinat) : refKoordinat.koordinat;
            if (g && g.type === 'Polygon') {
                const l = L.geoJSON({ type: 'Feature', geometry: g }, { style: { color: '#facc15', fillColor: '#facc15', fillOpacity: .1, weight: 1.8, dashArray: '5 5' }, interactive: false }).addTo(harita);
                harita.fitBounds(l.getBounds(), { padding: [30, 30] });
            } else if (Array.isArray(g) && Array.isArray(g[0])) {
                const l = L.polygon(g.map(p => [p[1], p[0]]), { color: '#facc15', fillColor: '#facc15', fillOpacity: .1, weight: 1.8, dashArray: '5 5', interactive: false }).addTo(harita);
                harita.fitBounds(l.getBounds(), { padding: [30, 30] });
            }
        } catch(e) {}
    }

    const cizim = { modu: 'idle', noktalar: [], polygon: null, tempLine: null, tempMarkerlar: [], vertexMarkerlar: [], edgeMarkerlar: [], farePos: null };
    const koordinatInput = document.getElementById('ec-koordinat');
    const latInput = document.getElementById('ec-lat');
    const lngInput = document.getElementById('ec-lng');
    const durumEl = document.getElementById('ec-mt-durum');
    const ipucuEl = document.getElementById('ec-ipucu');
    const btnPoligon = document.getElementById('ec-btn-poligon');
    const btnSil = document.getElementById('ec-btn-sil');
    const btnKonum = document.getElementById('ec-btn-konum');
    const btnTam = document.getElementById('ec-btn-tam-ekran');

    function ipucu(m, aktif = false) { ipucuEl.textContent = m; ipucuEl.classList.toggle('is-aktif', aktif); }

    function modaGir(m) {
        temizleTemp(); temizleVertex();
        cizim.modu = m;
        btnPoligon.classList.toggle('is-aktif', m === 'cizim');
        haritaEl.classList.toggle('ec-mod-cizim', m === 'cizim');
        haritaEl.classList.toggle('ec-mod-duzenle', m === 'duzenle');
        if (m === 'cizim') {
            ipucu('Boş alana tıklayın · İlk noktaya tıklayarak veya çift tıklayarak kapatın · ESC ile iptal', true);
            if (cizim.polygon) { harita.removeLayer(cizim.polygon); cizim.polygon = null; btnSil.hidden = true; }
            cizim.noktalar = [];
        } else if (m === 'duzenle') {
            if (!cizim.polygon) { modaGir('idle'); return; }
            ipucu('Vertex noktalarını sürükleyerek düzenleyin · Ara noktaları sürükleyerek yeni vertex ekleyin · Çift tık ile vertex silin', true);
            vertexCiz();
        } else {
            ipucu(cizim.polygon ? 'Poligon hazır — düzenlemek için üzerine tıklayın' : 'Poligon Çiz butonuna basarak başlayın');
        }
    }

    function temizleTemp() {
        if (cizim.tempLine) { harita.removeLayer(cizim.tempLine); cizim.tempLine = null; }
        cizim.tempMarkerlar.forEach(m => harita.removeLayer(m));
        cizim.tempMarkerlar = [];
    }
    function temizleVertex() {
        cizim.vertexMarkerlar.forEach(m => harita.removeLayer(m));
        cizim.vertexMarkerlar = [];
        cizim.edgeMarkerlar.forEach(m => harita.removeLayer(m));
        cizim.edgeMarkerlar = [];
    }

    function noktaEkle(latlng) {
        cizim.noktalar.push(latlng);
        const idx = cizim.noktalar.length - 1;
        const m = L.marker(latlng, {
            icon: L.divIcon({ className: 'ec-vertex-wrap', html: idx === 0 ? '<div class="ec-vertex ec-vertex-first"></div>' : '<div class="ec-vertex"></div>', iconSize: [16, 16], iconAnchor: [8, 8] }),
            keyboard: false,
        }).addTo(harita);
        m.on('click', function (e) { L.DomEvent.stopPropagation(e); if (idx === 0 && cizim.noktalar.length >= 3) polygonKapat(); });
        cizim.tempMarkerlar.push(m);
        onizleCiz();
    }
    function onizleCiz() {
        if (cizim.tempLine) harita.removeLayer(cizim.tempLine);
        const pts = cizim.noktalar.slice();
        if (cizim.farePos && cizim.modu === 'cizim') pts.push(cizim.farePos);
        if (pts.length < 2) return;
        cizim.tempLine = L.polyline(pts, { color: '#f43f5e', weight: 2, dashArray: '4 4', opacity: .9, interactive: false }).addTo(harita);
    }
    function polygonKapat() {
        if (cizim.noktalar.length < 3) return;
        const pts = cizim.noktalar.slice();
        temizleTemp();
        cizim.noktalar = [];
        cizim.polygon = L.polygon(pts, { color: '#f43f5e', weight: 2, fillColor: '#f43f5e', fillOpacity: .28, interactive: true }).addTo(harita);
        cizim.polygon.on('click', e => { L.DomEvent.stopPropagation(e); if (cizim.modu !== 'duzenle') modaGir('duzenle'); });
        cizim.polygon.on('dblclick', e => { L.DomEvent.stopPropagation(e); if (confirm('Poligonu silmek istediğinize emin misiniz?')) polygonSil(); });
        btnSil.hidden = false;
        formuGuncelle();
        modaGir('duzenle');
    }
    function polygonSil() {
        if (cizim.polygon) { harita.removeLayer(cizim.polygon); cizim.polygon = null; }
        temizleTemp(); temizleVertex();
        cizim.noktalar = [];
        btnSil.hidden = true;
        koordinatInput.value = ''; latInput.value = ''; lngInput.value = '';
        durumEl.innerHTML = 'Alan: <b>—</b>'; durumEl.classList.remove('is-var');
        if (typeof tsnInput !== 'undefined' && tsnInput) tsnInput.value = '';
        if (typeof tsnBilgiGuncelle === 'function') tsnBilgiGuncelle('bos');
        modaGir('idle');
    }
    function vertexCiz() {
        temizleVertex();
        if (!cizim.polygon) return;
        const halka = cizim.polygon.getLatLngs()[0];
        halka.forEach((ll, i) => {
            const m = L.marker(ll, {
                draggable: true,
                icon: L.divIcon({ className: 'ec-vertex-wrap', html: '<div class="ec-vertex ec-vertex-edit"></div>', iconSize: [14, 14], iconAnchor: [7, 7] }),
            }).addTo(harita);
            m.on('drag', e => { halka[i] = e.target.getLatLng(); cizim.polygon.setLatLngs([halka]); edgeGuncelle(); formuGuncelle(); });
            m.on('dblclick', e => {
                L.DomEvent.stopPropagation(e);
                if (halka.length <= 3) { ipucu('En az 3 vertex olmalı', true); return; }
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
            const orta = L.latLng((a.lat + b.lat) / 2, (a.lng + b.lng) / 2);
            const idx = i + 1;
            const m = L.marker(orta, {
                draggable: true,
                icon: L.divIcon({ className: 'ec-vertex-wrap', html: '<div class="ec-vertex ec-vertex-edge"></div>', iconSize: [10, 10], iconAnchor: [5, 5] }),
            }).addTo(harita);
            m.on('dragstart', function () { halka.splice(idx, 0, m.getLatLng()); cizim.polygon.setLatLngs([halka]); });
            m.on('drag', e => { halka[idx] = e.target.getLatLng(); cizim.polygon.setLatLngs([halka]); formuGuncelle(); });
            m.on('dragend', () => { vertexCiz(); formuGuncelle(); });
            cizim.edgeMarkerlar.push(m);
        }
    }
    function formuGuncelle() {
        if (!cizim.polygon) return;
        const halka = cizim.polygon.getLatLngs()[0];
        const coords = halka.map(p => [p.lng, p.lat]);
        if (coords.length > 0) coords.push([coords[0][0], coords[0][1]]);
        koordinatInput.value = JSON.stringify({ type: 'Polygon', coordinates: [coords] });
        const merkez = { lat: halka.reduce((s, p) => s + p.lat, 0) / halka.length, lng: halka.reduce((s, p) => s + p.lng, 0) / halka.length };
        latInput.value = merkez.lat.toFixed(7); lngInput.value = merkez.lng.toFixed(7);
        const m2 = geodesikAlan(halka);
        durumEl.innerHTML = 'Alan: <b>' + alanBiciminde(m2) + '</b>';
        durumEl.classList.add('is-var');
        tasinmazEsleDebounced(coords);
    }

    const tsnInput = document.getElementById('tasinmaz_id');
    const tsnBilgi = document.getElementById('ec-tsn-bilgi');
    let esleTimer = null;
    function tasinmazEsleDebounced(coords) { clearTimeout(esleTimer); esleTimer = setTimeout(() => tasinmazEsle(coords), 450); }
    async function tasinmazEsle(coords) {
        if (!coords || coords.length < 3) return;
        const noktalar = coords.slice(0, -1);
        tsnBilgiGuncelle('arama', 'Taşınmaz aranıyor...');
        try {
            const r = await fetch(API_TASINMAZ_BUL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ koordinatlar: noktalar }),
            });
            const veri = await r.json();
            if (veri.bulundu && veri.tasinmaz) {
                tsnInput.value = veri.tasinmaz.id;
                tsnBilgiGuncelle('bulundu', veri.tasinmaz);
            } else {
                tsnInput.value = '';
                tsnBilgiGuncelle('bulunamadi', 'Bu alanla eşleşen bir taşınmaz bulunamadı — bağlantısız kaydedilecek.');
            }
        } catch (e) { tsnBilgiGuncelle('bulunamadi', 'Eşleme sırasında hata oluştu.'); }
    }
    function tsnBilgiGuncelle(tip, veri) {
        const html = {
            arama: `<div class="ec-tsn-durum is-arama"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/></svg><div>${veri}</div></div>`,
            bulundu: `<div class="ec-tsn-durum is-bulundu"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg><div><strong>Ada ${esc(veri.ada ?? '—')} / Parsel ${esc(veri.parsel ?? '—')}</strong><small>${esc(veri.il ?? '')} · ${esc(veri.ilce ?? '')} · ${esc(veri.mahalle ?? '')}</small></div></div>`,
            bulunamadi: `<div class="ec-tsn-durum is-bulunamadi"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg><div>${veri}</div></div>`,
            bos: `<div class="ec-tsn-durum is-bos"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 8l10 6 10-6z"/></svg>Haritada bir alan çizin; içinde bir taşınmaz varsa otomatik bağlanacak.</div>`,
        }[tip];
        tsnBilgi.innerHTML = html;
    }
    function esc(s) { if (s == null) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
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
        if (m2 >= 10000) return (m2 / 10000).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ha';
        return m2.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' m²';
    }

    harita.on('click', e => { if (cizim.modu === 'cizim') noktaEkle(e.latlng); });
    harita.on('mousemove', e => { if (cizim.modu === 'cizim' && cizim.noktalar.length > 0) { cizim.farePos = e.latlng; onizleCiz(); } });
    harita.on('dblclick', () => { if (cizim.modu === 'cizim' && cizim.noktalar.length >= 3) polygonKapat(); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            if (cizim.modu === 'cizim') { cizim.noktalar = []; modaGir('idle'); }
            if (haritaEl.classList.contains('is-tam')) { haritaEl.classList.remove('is-tam'); btnTam.classList.remove('is-aktif'); setTimeout(() => harita.invalidateSize(), 200); }
        }
    });

    btnPoligon.addEventListener('click', () => modaGir(cizim.modu === 'cizim' ? 'idle' : 'cizim'));
    btnSil.addEventListener('click', () => { if (confirm('Poligonu silmek istediğinize emin misiniz?')) polygonSil(); });
    btnKonum.addEventListener('click', function () {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(
            pos => harita.setView([pos.coords.latitude, pos.coords.longitude], 18),
            err => alert('Konum alınamadı: ' + err.message)
        );
    });
    btnTam.addEventListener('click', function () {
        haritaEl.classList.toggle('is-tam');
        this.classList.toggle('is-aktif');
        setTimeout(() => harita.invalidateSize(), 200);
    });
    document.getElementById('ec-kml-input').addEventListener('change', function (e) {
        const file = e.target.files[0]; if (!file) return;
        const reader = new FileReader();
        reader.onload = ev => {
            try {
                const kml = new DOMParser().parseFromString(ev.target.result, 'text/xml');
                const cText = kml.querySelector('coordinates')?.textContent?.trim();
                if (!cText) throw new Error('KML içinde koordinat bulunamadı.');
                const points = [];
                cText.split(/\s+/).forEach(p => {
                    const parts = p.split(',').map(parseFloat);
                    if (parts.length >= 2 && !isNaN(parts[0]) && !isNaN(parts[1])) points.push(L.latLng(parts[1], parts[0]));
                });
                if (points.length < 3) throw new Error('Geçerli poligon oluşturulamadı.');
                if (cizim.polygon) { harita.removeLayer(cizim.polygon); cizim.polygon = null; }
                cizim.noktalar = points;
                polygonKapat();
                harita.fitBounds(cizim.polygon.getBounds(), { padding: [30, 30] });
            } catch(err) { alert('KML yüklenemedi: ' + err.message); }
        };
        reader.readAsText(file);
        e.target.value = '';
    });

    // Mevcut koordinatı yükle
    if (eskiKoordinat) {
        try {
            const g = typeof eskiKoordinat === 'string' ? JSON.parse(eskiKoordinat) : eskiKoordinat;
            let pts = [];
            if (g && g.type === 'Polygon' && g.coordinates?.[0]) {
                pts = g.coordinates[0].slice(0, -1).map(p => L.latLng(p[1], p[0]));
            } else if (Array.isArray(g) && Array.isArray(g[0])) {
                pts = g.map(p => L.latLng(p[1], p[0]));
            }
            if (pts.length >= 3) {
                cizim.noktalar = pts;
                polygonKapat();
                harita.fitBounds(cizim.polygon.getBounds(), { padding: [30, 30] });
            }
        } catch(e) { console.error(e); }
    }

    // ---------- Mevcut ecrimisil poligonları (kendisi hariç) ----------
    const mevcutKatman = L.featureGroup().addTo(harita);
    const MEVCUT_RENK = { 'yeni': '#94a3b8', 'devam-eden': '#f43f5e', 'sonlanmis': '#10b981' };

    async function mevcutlariYukle() {
        try {
            const url = API_MEVCUT + (HARIC_ID ? '?haric=' + HARIC_ID : '');
            const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const fc = await r.json();
            (fc.features || []).forEach(f => {
                const p = f.properties;
                const renk = MEVCUT_RENK[p.durum] || '#94a3b8';
                const layer = L.geoJSON(f, {
                    style: { color: renk, fillColor: renk, fillOpacity: .10, weight: 1.8, dashArray: '4 4' },
                }).addTo(mevcutKatman);
                layer.eachLayer(l => {
                    l.bindTooltip(
                        `<strong>${esc(p.ad_soyad_unvan ?? '—')}</strong>
                        <div class="sub">Ada ${esc(p.ada ?? '—')} / Parsel ${esc(p.parsel ?? '—')} · ${esc(p.nitelik ?? '')}</div>
                        <span class="aksiyon">▸ Tıklayın: bu alanı bu kayda ata</span>`,
                        { className: 'ec-mvt-tooltip', sticky: true }
                    );
                    l.on('click', e => {
                        L.DomEvent.stopPropagation(e);
                        if (!confirm(`Bu ecrimisil'in poligonunu bu kayda kopyalamak istiyor musunuz? Mevcut çizim üzerine yazılacak.`)) return;
                        mevcuttanKopyala(f);
                    });
                    l.on('mouseover', () => l.setStyle({ fillOpacity: .28, weight: 2.5 }));
                    l.on('mouseout',  () => l.setStyle({ fillOpacity: .10, weight: 1.8 }));
                });
            });
        } catch(e) { console.warn(e); }
    }

    function mevcuttanKopyala(feature) {
        if (cizim.polygon) { harita.removeLayer(cizim.polygon); cizim.polygon = null; }
        const g = feature.geometry;
        let pts = [];
        if (g.type === 'Polygon' && g.coordinates?.[0]) {
            pts = g.coordinates[0].slice(0, -1).map(p => L.latLng(p[1], p[0]));
        }
        if (pts.length < 3) return;
        cizim.noktalar = pts;
        polygonKapat();
        harita.fitBounds(cizim.polygon.getBounds(), { padding: [40, 40] });
        if (feature.properties.tasinmaz_id) {
            tsnInput.value = feature.properties.tasinmaz_id;
            tsnBilgiGuncelle('bulundu', { ada: feature.properties.ada, parsel: feature.properties.parsel, il: '', ilce: '', mahalle: '' });
        }
    }

    mevcutlariYukle();

    modaGir('idle');
    setTimeout(() => harita.invalidateSize(), 100);
})();
</script>
@endpush

