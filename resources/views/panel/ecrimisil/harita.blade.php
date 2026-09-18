@extends('layouts.panel')

@section('title', 'Ecrimisil Haritası')
@section('contentClass', 'is-flush')
@section('shellClass', 'is-map-full')

@push('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.Default.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/themes/dark.css">
<style>
    .ech-shell { position: fixed; inset: 0; width: 100vw; height: 100vh; background: #0e1726; }
    .ech-map { position: absolute; inset: 0; z-index: 1; }
    .ech-shell .leaflet-control-zoom { display: none !important; }
    body.app-shell.is-map-full .app-content.is-flush { padding: 0; margin: 0; overflow: hidden; }

    /* Toolbar */
    .ech-toolbar { position: absolute; top: 12px; left: 12px; z-index: 20; display: flex; align-items: center; gap: 6px;
        background: rgba(14,23,38,.92); backdrop-filter: blur(6px); border: 1px solid rgba(255,255,255,.08);
        border-radius: 10px; padding: 6px; box-shadow: 0 6px 22px rgba(0,0,0,.35); }
    .ech-tool { display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; font-size: .78rem; font-weight: 600;
        background: transparent; color: #dfe3ea; border: 1px solid transparent; border-radius: 7px;
        cursor: pointer; text-decoration: none; }
    .ech-tool svg { width: 15px; height: 15px; }
    .ech-tool:hover { background: rgba(255,255,255,.06); color: #fff; }
    .ech-tool[aria-expanded="true"] { background: rgba(37,99,235,.18); border-color: rgba(37,99,235,.35); }
    .ech-tool-divider { width: 1px; align-self: stretch; background: rgba(255,255,255,.08); margin: 2px 4px; }
    .ech-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; font-size: .75rem;
        color: #9aa3b2; background: rgba(255,255,255,.04); border-radius: 6px; }
    .ech-chip strong { color: #fff; font-size: .82rem; }

    /* Paneller */
    .ech-panel { position: absolute; z-index: 15; background: #0e1726; color: #dfe3ea;
        border: 1px solid rgba(255,255,255,.08); border-radius: 12px;
        box-shadow: 0 22px 60px rgba(0,0,0,.55);
        display: flex; flex-direction: column; min-width: 320px; overflow: hidden; }
    .ech-panel[hidden] { display: none; }
    .ech-panel-head { display: flex; align-items: center; gap: 8px; padding: 12px 14px;
        background: linear-gradient(180deg,#16223c 0%,#0e1726 100%);
        border-bottom: 1px solid rgba(255,255,255,.06); cursor: move; }
    .ech-panel-head strong { color: #fff; font-size: .88rem; flex: 1; }
    .ech-panel-head small { color: #8a91a1; font-size: .74rem; display: block; }
    .ech-panel-kapat { background: transparent; border: none; color: #9aa3b2; padding: 4px; border-radius: 6px; cursor: pointer; }
    .ech-panel-kapat:hover { background: rgba(255,255,255,.08); color: #fff; }
    .ech-panel-scroll { flex: 1; overflow-y: auto; }
    .ech-panel-scroll::-webkit-scrollbar { width: 8px; }
    .ech-panel-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.16); border-radius: 4px; }

    /* Katman paneli */
    .ech-katman { top: 72px; left: 12px; width: 340px; max-height: calc(100vh - 100px); }
    .ech-katman-grup { border-bottom: 1px solid rgba(255,255,255,.05); }
    .ech-katman-baslik { padding: 10px 14px; font-size: .72rem; text-transform: uppercase; letter-spacing: .06em;
        color: #8a91a1; font-weight: 700; background: rgba(255,255,255,.02); }
    .ech-katman-kart { padding: 12px 14px; display: flex; align-items: center; gap: 12px; }
    .ech-katman-kart:hover { background: rgba(255,255,255,.03); }
    .ech-katman-ikon { width: 30px; height: 30px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; color: #fff; flex: 0 0 30px; }
    .ech-katman-ikon svg { width: 14px; height: 14px; }
    .ech-katman-meta { flex: 1; min-width: 0; }
    .ech-katman-ad { display: block; color: #fff; font-size: .84rem; font-weight: 600; }
    .ech-katman-alt { display: block; color: #8a91a1; font-size: .72rem; margin-top: 2px; }

    .ech-switch { position: relative; width: 34px; height: 20px; flex: 0 0 34px; }
    .ech-switch input { opacity: 0; width: 0; height: 0; }
    .ech-switch-slider { position: absolute; cursor: pointer; inset: 0; background: #2a3550; border-radius: 20px; transition: background .2s; }
    .ech-switch-slider::before { content: ''; position: absolute; width: 14px; height: 14px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: transform .2s; }
    .ech-switch input:checked + .ech-switch-slider { background: #2563eb; }
    .ech-switch input:checked + .ech-switch-slider::before { transform: translateX(14px); }

    .ech-radyo-grup { padding: 8px 6px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
    .ech-radyo { padding: 8px; border: 1px solid rgba(255,255,255,.08); border-radius: 8px; background: rgba(255,255,255,.02);
        cursor: pointer; text-align: center; font-size: .72rem; color: #dfe3ea; }
    .ech-radyo:hover { border-color: rgba(37,99,235,.4); color: #fff; }
    .ech-radyo.is-aktif { border-color: #2563eb; background: rgba(37,99,235,.15); color: #fff; font-weight: 600; }

    .ech-lejant { padding: 10px 14px; border-top: 1px solid rgba(255,255,255,.05); }
    .ech-lejant-baslik { font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; color: #8a91a1; font-weight: 700; margin-bottom: 8px; }
    .ech-lejant-oge { display: flex; align-items: center; gap: 8px; font-size: .76rem; color: #dfe3ea; padding: 4px 0; }
    .ech-lejant-nokta { width: 12px; height: 12px; border-radius: 3px; border: 1px solid rgba(255,255,255,.15); flex: 0 0 12px; }

    /* İşlemler paneli */
    .ech-islem { top: 12px; right: 12px; bottom: 12px; width: 420px; max-height: none; }
    .ech-islem-tabs { display: flex; background: #16223c; border-bottom: 1px solid rgba(255,255,255,.06); }
    .ech-tab { flex: 1; padding: 10px 8px; background: transparent; border: none; font-size: .76rem; font-weight: 600;
        color: #8a91a1; cursor: pointer; border-bottom: 2px solid transparent; }
    .ech-tab:hover { color: #dfe3ea; }
    .ech-tab.is-aktif { color: #fff; border-bottom-color: #2563eb; background: rgba(37,99,235,.08); }
    .ech-tab-panel { display: none; padding: 14px; }
    .ech-tab-panel.is-aktif { display: block; }

    .ech-ozet-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 14px; }
    .ech-alan .lbl { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: #8a91a1; font-weight: 700; margin-bottom: 3px; }
    .ech-alan .val { font-size: .84rem; color: #fff; word-break: break-word; }

    .ech-rozet { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px; font-size: .72rem; font-weight: 600; border: 1px solid; }
    .ech-rozet.is-yeni       { color: #94a3b8; border-color: rgba(148,163,184,.35); background: rgba(148,163,184,.08); }
    .ech-rozet.is-devam-eden { color: #f87171; border-color: rgba(248,113,113,.35); background: rgba(248,113,113,.08); }
    .ech-rozet.is-sonlanmis  { color: #34d399; border-color: rgba(52,211,153,.35); background: rgba(52,211,153,.08); }

    .ech-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 9px 14px;
        font-size: .82rem; font-weight: 600; background: #2563eb; color: #fff; border: none; border-radius: 7px;
        cursor: pointer; text-decoration: none; }
    .ech-btn:hover { background: #1d4ed8; }
    .ech-btn.is-ghost { background: transparent; color: #dfe3ea; border: 1px solid rgba(255,255,255,.14); }
    .ech-btn.is-yesil { background: #059669; } .ech-btn.is-yesil:hover { background: #047857; }
    .ech-btn.is-mini { padding: 5px 9px; font-size: .72rem; }
    .ech-btn svg { width: 14px; height: 14px; }
    .ech-btn-row { display: flex; gap: 8px; margin-top: 12px; }
    .ech-btn-row .ech-btn { flex: 1; }

    /* Tutanak listesi */
    .ech-tut-item { border: 1px solid rgba(255,255,255,.08); border-radius: 8px; padding: 10px 12px; background: rgba(255,255,255,.02); margin-bottom: 8px; }
    .ech-tut-ust { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
    .ech-tut-ust .num { color: #fff; font-weight: 700; font-size: .82rem; flex: 1; }
    .ech-tut-alt { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; font-size: .74rem; }
    .ech-tut-alt .lbl { color: #8a91a1; font-size: .66rem; text-transform: uppercase; font-weight: 700; }
    .ech-tut-alt .val { color: #dfe3ea; margin-top: 2px; }

    /* Form */
    .ech-form { display: flex; flex-direction: column; gap: 10px; }
    .ech-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .ech-form-satir label { display: block; font-size: .7rem; color: #8a91a1; text-transform: uppercase; letter-spacing: .05em; font-weight: 700; margin-bottom: 4px; }
    .ech-form-satir input, .ech-form-satir textarea, .ech-form-satir select {
        width: 100%; padding: 8px 10px; font-size: .82rem; background: rgba(0,0,0,.25); color: #fff;
        border: 1px solid rgba(255,255,255,.1); border-radius: 6px; }
    .ech-form-satir input:focus, .ech-form-satir textarea:focus, .ech-form-satir select:focus {
        outline: none; border-color: #2563eb; background: rgba(0,0,0,.35); }
    .ech-form-satir textarea { min-height: 60px; }

    /* Resim galeri */
    .ech-galeri { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin-bottom: 10px; }
    .ech-galeri img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 6px; cursor: pointer; border: 1px solid rgba(255,255,255,.1); }

    /* Ada/parsel etiketi */
    .ech-etiket { background: rgba(255,224,1,.95); color: #1f2937; padding: 2px 8px; border-radius: 4px;
        font-weight: 700; font-size: 11px; box-shadow: 0 1px 3px rgba(0,0,0,.5); white-space: nowrap; border: 1px solid rgba(0,0,0,.15); }

    /* İşaretçi (özel marker) — durum bazlı renk */
    .ech-marker {
        width: 26px; height: 26px; border-radius: 50% 50% 50% 0;
        transform: rotate(-45deg); border: 2.5px solid #fff;
        box-shadow: 0 2px 6px rgba(0,0,0,.45); position: relative;
        display: flex; align-items: center; justify-content: center;
    }
    .ech-marker::before {
        content: ''; position: absolute; inset: 5px; background: rgba(255,255,255,.35); border-radius: 50%;
    }
    .ech-marker-yeni { background: #64748b; }
    .ech-marker-devam-eden { background: #f43f5e; animation: ech-pulse-marker 1.8s infinite; }
    .ech-marker-sonlanmis { background: #10b981; }
    @keyframes ech-pulse-marker {
        0%,100% { box-shadow: 0 2px 6px rgba(0,0,0,.45), 0 0 0 0 rgba(244,63,94,.5); }
        50%     { box-shadow: 0 2px 6px rgba(0,0,0,.45), 0 0 0 10px rgba(244,63,94,0); }
    }

    /* Kümeleme (marker cluster) — durum bazlı renk */
    .ech-cluster {
        width: 40px !important; height: 40px !important;
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: 13px;
        border: 3px solid #fff; box-shadow: 0 3px 8px rgba(0,0,0,.4);
    }
    .ech-cluster-small { background: #64748b; }
    .ech-cluster-medium { background: #f59e0b; }
    .ech-cluster-large { background: #dc2626; }
    .ech-cluster-wrap { background: transparent !important; border: none !important; }
    .marker-cluster-small, .marker-cluster-medium, .marker-cluster-large { background: transparent !important; }
    .marker-cluster-small div, .marker-cluster-medium div, .marker-cluster-large div { display: none; }

    .ech-bos { padding: 24px; text-align: center; color: #8a91a1; font-size: .82rem; border: 1px dashed rgba(255,255,255,.08); border-radius: 8px; }
    .ech-yukleniyor { padding: 40px 20px; text-align: center; color: #8a91a1; }
    .ech-yukleniyor::before { content: ''; display: inline-block; width: 20px; height: 20px;
        border: 2.5px solid rgba(255,255,255,.15); border-top-color: #2563eb; border-radius: 50%; margin-right: 10px;
        vertical-align: middle; animation: ech-spin .8s linear infinite; }
    @keyframes ech-spin { to { transform: rotate(360deg); } }
    .ech-flash { padding: 8px 12px; border-radius: 6px; font-size: .78rem; margin-bottom: 10px; }
    .ech-flash.is-ok { background: rgba(52,211,153,.12); color: #6ee7b7; border: 1px solid rgba(52,211,153,.3); }
    .ech-flash.is-hata { background: rgba(248,113,113,.12); color: #fca5a5; border: 1px solid rgba(248,113,113,.3); }

    /* Arama paneli */
    .ech-arama { top: 72px; left: 368px; width: 340px; max-height: calc(100vh - 100px); }

    /* ---------- Şık date + file input (dark tema) ---------- */
    .ech-form-satir input[type="date"] {
        appearance: none; -webkit-appearance: none; cursor: pointer;
        padding: 9px 12px; font-size: .82rem; font-weight: 500;
        color: #fff; background: rgba(0,0,0,.25);
        border: 1px solid rgba(255,255,255,.1); border-radius: 6px;
        transition: all .15s ease; font-family: inherit;
    }
    .ech-form-satir input[type="date"]:hover { border-color: rgba(37,99,235,.4); background: rgba(0,0,0,.35); }
    .ech-form-satir input[type="date"]:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.2); }
    .ech-form-satir input[type="date"]::-webkit-calendar-picker-indicator {
        cursor: pointer; opacity: .7;
        filter: invert(80%) sepia(20%) saturate(400%) hue-rotate(190deg);
    }
    .ech-form-satir input[type="date"]:hover::-webkit-calendar-picker-indicator { opacity: 1; }

    /* Dark file drop */
    .ech-file-drop {
        position: relative; display: flex; align-items: center; gap: 12px;
        padding: 12px 14px;
        background: linear-gradient(135deg, rgba(255,255,255,.03) 0%, rgba(255,255,255,.06) 100%);
        border: 2px dashed rgba(255,255,255,.15); border-radius: 8px;
        cursor: pointer; transition: all .18s ease;
    }
    .ech-file-drop:hover {
        border-color: #38bdf8;
        background: linear-gradient(135deg, rgba(56,189,248,.08) 0%, rgba(56,189,248,.14) 100%);
        transform: translateY(-1px);
    }
    .ech-file-drop.is-dragover { border-color: #34d399; border-style: solid; background: rgba(52,211,153,.12); }
    .ech-file-drop.has-file {
        border-style: solid; border-color: #34d399;
        background: linear-gradient(135deg, rgba(52,211,153,.08) 0%, rgba(52,211,153,.15) 100%);
    }
    .ech-file-drop input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
    .ech-file-ikon {
        width: 38px; height: 38px; flex: 0 0 38px;
        display: flex; align-items: center; justify-content: center;
        background: rgba(255,255,255,.08); border-radius: 8px;
        color: #cbd5e1; transition: all .18s ease;
    }
    .ech-file-ikon svg { width: 18px; height: 18px; }
    .ech-file-drop:hover .ech-file-ikon { color: #38bdf8; background: rgba(56,189,248,.15); }
    .ech-file-drop.has-file .ech-file-ikon { color: #34d399; background: rgba(52,211,153,.18); }
    .ech-file-meta { flex: 1; min-width: 0; }
    .ech-file-baslik { font-size: .82rem; font-weight: 700; color: #fff; margin-bottom: 2px; }
    .ech-file-alt { font-size: .72rem; color: #94a3b8; }
    .ech-file-drop.has-file .ech-file-alt { color: #86efac; word-break: break-all; }

    /* ---------- Flatpickr dark özelleştirme ---------- */
    .flatpickr-calendar {
        background: #0e1726 !important; border: 1px solid rgba(255,255,255,.1) !important;
        box-shadow: 0 20px 50px rgba(0,0,0,.55) !important; border-radius: 12px !important;
    }
    .flatpickr-calendar.arrowTop::before,
    .flatpickr-calendar.arrowTop::after { border-bottom-color: #2563eb !important; }
    .flatpickr-months {
        background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%) !important;
        border-radius: 12px 12px 0 0 !important; padding: 8px 0 !important;
    }
    .flatpickr-months .flatpickr-month,
    .flatpickr-current-month,
    .flatpickr-current-month input.cur-year,
    .flatpickr-monthDropdown-months { color: #fff !important; }
    .flatpickr-monthDropdown-months option { background: #0e1726; color: #fff !important; }
    span.flatpickr-weekday { color: #8a91a1 !important; font-weight: 700 !important; text-transform: uppercase; font-size: .7rem !important; }
    .flatpickr-day {
        color: #dfe3ea !important; border-radius: 8px !important; font-weight: 500;
        transition: background .12s ease, color .12s ease;
    }
    .flatpickr-day:hover { background: rgba(37,99,235,.25) !important; color: #fff !important; border-color: transparent !important; }
    .flatpickr-day.today { border-color: #2563eb !important; font-weight: 700; color: #38bdf8 !important; }
    .flatpickr-day.selected,
    .flatpickr-day.selected:hover {
        background: #2563eb !important; color: #fff !important; border-color: #2563eb !important;
        box-shadow: 0 3px 8px rgba(37,99,235,.5);
    }
    .flatpickr-day.prevMonthDay, .flatpickr-day.nextMonthDay { color: #4b5563 !important; }
</style>
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
                    <span class="ech-katman-ikon" style="background:#8b5cf6;">
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
                <div class="ech-lejant-oge"><span class="ech-lejant-nokta" style="background:#94a3b8;"></span> Yeni kayıt (tutanak yok)</div>
                <div class="ech-lejant-oge"><span class="ech-lejant-nokta" style="background:#f43f5e;"></span> Devam eden işgal</div>
                <div class="ech-lejant-oge"><span class="ech-lejant-nokta" style="background:#10b981;"></span> Sonlanmış işgal</div>
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
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/tr.js"></script>
<script>
(function () {
    'use strict';
    const API = {
        geojson: @json(route('panel.ecrimisil.harita.geojson')),
        ozet: @json(url('/panel/ecrimisil/harita/kayit')),
        tutanakKaydet: @json(url('/panel/ecrimisil')),
        resimYukle: @json(url('/panel/ecrimisil')),
        ilceler: @json(url('/panel/ajax/ilceler')),
        mahalleler: @json(url('/panel/ajax/mahalleler')),
        csrf: @json(csrf_token()),
    };

    const DURUM_RENK = {
        'yeni':        { renk: '#94a3b8', etiket: 'Yeni kayıt' },
        'devam-eden':  { renk: '#f43f5e', etiket: 'Devam eden' },
        'sonlanmis':   { renk: '#10b981', etiket: 'Sonlanmış' },
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
            const cls = n < 10 ? 'ech-cluster-small' : (n < 50 ? 'ech-cluster-medium' : 'ech-cluster-large');
            return L.divIcon({
                html: `<div class="ech-cluster ${cls}">${n}</div>`,
                className: 'ech-cluster-wrap', iconSize: [40, 40],
            });
        },
    });
    const layerById = new Map();
    const markerById = new Map();
    let allBounds = null;
    let secilenLayer = null;
    let secilenKayitId = null;
    let mevcutFiltre = new URLSearchParams();

    function haritayiYukle(filtre) {
        parselKatman.clearLayers();
        etiketKatman.clearLayers();
        markerKatman.clearLayers();
        clusterKatman.clearLayers();
        layerById.clear();
        markerById.clear();
        const url = filtre?.toString() ? API.geojson + '?' + filtre.toString() : API.geojson;

        return fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(fc => {
                document.getElementById('ech-sayi').textContent = (fc.features || []).length;
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
                            icon: L.divIcon({ className: '', html: `<div class="ech-etiket">${p.ada ?? '—'}/${p.parsel ?? '—'}</div>`, iconSize: null }),
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
                        marker.bindTooltip(`<strong>${(p.ad_soyad_unvan || '').replace(/</g,'&lt;')}</strong><br>Ada ${p.ada ?? '—'}/Parsel ${p.parsel ?? '—'}`, { direction: 'top', offset: [0, -20] });
                        markerKatman.addLayer(marker);
                        markerById.set(p.id, marker);
                    } catch(e) {}
                });
                if (bounds.length) {
                    allBounds = bounds.reduce((acc, b) => acc ? acc.extend(b) : L.latLngBounds(b.getSouthWest ? [b.getSouthWest(), b.getNorthEast()] : [b, b]), null);
                    if (allBounds) map.fitBounds(allBounds, { padding: [30, 30] });
                }
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

    haritayiYukle(mevcutFiltre);

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
    document.getElementById('ech-fit-btn').addEventListener('click', () => { if (allBounds) map.fitBounds(allBounds, { padding: [30, 30] }); });
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
        haritayiYukle(mevcutFiltre);
        return false;
    };
    window.echFiltreTemizle = function () {
        document.getElementById('ech-arama-form').reset();
        document.getElementById('ech-f-ilce').innerHTML = '<option>— Önce il —</option>';
        document.getElementById('ech-f-ilce').disabled = true;
        document.getElementById('ech-f-mahalle').innerHTML = '<option>— Önce ilçe —</option>';
        document.getElementById('ech-f-mahalle').disabled = true;
        mevcutFiltre = new URLSearchParams();
        haritayiYukle(mevcutFiltre);
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
        document.getElementById('ech-islem-alt').textContent = props.ad_soyad_unvan + ' · Ada ' + (props.ada ?? '—') + '/' + (props.parsel ?? '—');
        ['ozet','tutanak','resim'].forEach(t => document.getElementById('ech-tab-' + t).innerHTML = '<div class="ech-yukleniyor">Yükleniyor</div>');

        fetch(API.ozet + '/' + props.id).then(r => r.json()).then(v => {
            renderOzet(v, props);
            renderTutanak(v);
            renderRapor(v);
            renderResim(v);
        }).catch(e => {
            document.getElementById('ech-tab-ozet').innerHTML = '<div class="ech-flash is-hata">Yükleme başarısız</div>';
        });
    }

    function renderRapor(v) {
        const canEdit = v.izinler.duzenle;
        const items = (v.raporlar || []).map(r => {
            const fiyat = r.fiyat !== null && r.fiyat !== undefined
                ? Number(r.fiyat).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺'
                : null;
            return `
            <div class="ech-tut-item">
                <div class="ech-tut-ust">
                    <span class="num">${r.rapor_no ? 'Rapor: ' + escapeHtml(r.rapor_no) : 'Rapor #' + r.id}</span>
                    <span style="font-size:.72rem;color:#8a91a1;">${r.rapor_tarihi ? tr(r.rapor_tarihi) : '—'}</span>
                    ${fiyat ? `<span style="background:rgba(52,211,153,.15);color:#6ee7b7;padding:2px 8px;border-radius:6px;font-weight:700;font-size:.74rem;">${fiyat}</span>` : ''}
                    ${r.dosya_url ? `<a class="ech-btn is-mini" href="${escapeAttr(r.dosya_url)}" target="_blank">Dosya</a>` : ''}
                </div>
                ${r.aciklama ? `<div style="font-size:.76rem;color:#cbd5e1;line-height:1.5;">${escapeHtml(r.aciklama)}</div>` : ''}
            </div>`;
        }).join('');

        const form = canEdit ? `
            <details style="margin-top:12px;">
                <summary style="cursor:pointer;color:#dfe3ea;font-weight:600;font-size:.84rem;padding:8px 0;">➕ Yeni Rapor Ekle</summary>
                <form class="ech-form" style="margin-top:10px;" enctype="multipart/form-data" onsubmit="return echRaporKaydet(event, ${v.kayit.id})">
                    <div class="ech-form-row">
                        <div class="ech-form-satir"><label>Rapor No</label><input type="text" name="rapor_no" maxlength="100"></div>
                        <div class="ech-form-satir"><label>Rapor Tarihi</label><input type="date" name="rapor_tarihi"></div>
                    </div>
                    <div class="ech-form-satir"><label>Fiyat (₺)</label><input type="number" name="fiyat" step="0.01" min="0" placeholder="0,00"></div>
                    <div class="ech-form-satir"><label>Dosya Eki</label>
                        <label class="ech-file-drop" data-file-drop>
                            <input type="file" name="dosya" accept=".pdf,.doc,.docx">
                            <span class="ech-file-ikon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l3-3 3 3M12 12v6"/></svg></span>
                            <span class="ech-file-meta">
                                <span class="ech-file-baslik" data-file-baslik>Dosyayı sürükleyin veya tıklayın</span>
                                <span class="ech-file-alt" data-file-alt>PDF veya Word · max 20 MB</span>
                            </span>
                        </label>
                    </div>
                    <div class="ech-form-satir"><label>Açıklama</label><textarea name="aciklama" maxlength="5000"></textarea></div>
                    <div id="ech-rapor-flash"></div>
                    <button type="submit" class="ech-btn is-yesil">Raporu Kaydet</button>
                </form>
            </details>` : '';

        document.getElementById('ech-tab-rapor').innerHTML =
            ((v.raporlar || []).length === 0 ? '<div class="ech-bos">Henüz rapor yok.</div>' : items) + form;
    }

    window.echRaporKaydet = function (e, id) {
        e.preventDefault();
        const form = e.target, btn = form.querySelector('button[type=submit]');
        btn.disabled = true;
        const fd = new FormData(form);
        const flash = form.querySelector('#ech-rapor-flash');
        fetch(@json(url('/panel/ecrimisil')) + '/' + id + '/rapor', {
            method: 'POST', headers: { 'X-CSRF-TOKEN': API.csrf, 'Accept': 'application/json' }, body: fd,
        }).then(r => {
            if (r.ok) {
                flash.innerHTML = '<div class="ech-flash is-ok">Rapor kaydedildi.</div>';
                fetch(API.ozet + '/' + id).then(r => r.json()).then(v => renderRapor(v));
            } else flash.innerHTML = '<div class="ech-flash is-hata">Hata oluştu.</div>';
        }).finally(() => btn.disabled = false);
        return false;
    };

    function renderOzet(v, props) {
        const k = v.kayit;
        const durumEt = DURUM_RENK[props.durum]?.etiket ?? 'Yeni kayıt';
        document.getElementById('ech-tab-ozet').innerHTML = `
            <div style="margin-bottom:12px;"><span class="ech-rozet ${durumSinifi(props.durum)}">${escapeHtml(durumEt)}</span></div>
            <div class="ech-ozet-grid">
                <div class="ech-alan" style="grid-column:1/-1;"><div class="lbl">Ad Soyad / Ünvan</div><div class="val">${escapeHtml(k.ad_soyad_unvan)}</div></div>
                <div class="ech-alan"><div class="lbl">TC / Vergi</div><div class="val">${escapeHtml(k.tc_vergi_no ?? '—')}</div></div>
                <div class="ech-alan"><div class="lbl">Nitelik</div><div class="val">${escapeHtml(k.nitelik ?? '—')}</div></div>
                <div class="ech-alan"><div class="lbl">Sorumlu</div><div class="val">${escapeHtml(k.kullanici?.ad_soyad ?? '—')}</div></div>
                <div class="ech-alan" style="grid-column:1/-1;"><div class="lbl">Cadde/Sokak & Adres</div><div class="val">${escapeHtml((k.cadde_sokak ? k.cadde_sokak + ' · ' : '') + (k.adres ?? '—'))}</div></div>
                <div class="ech-alan"><div class="lbl">Ada / Parsel</div><div class="val">${escapeHtml(k.ada ?? '—')} / ${escapeHtml(k.parsel ?? '—')}</div></div>
                <div class="ech-alan"><div class="lbl">Konum</div><div class="val">${escapeHtml(k.ilce ?? '')} · ${escapeHtml(k.mahalle ?? '')}</div></div>
                ${k.aciklama ? `<div class="ech-alan" style="grid-column:1/-1;"><div class="lbl">Açıklama</div><div class="val">${escapeHtml(k.aciklama)}</div></div>` : ''}
            </div>
            <div class="ech-btn-row">
                <a class="ech-btn" href="${escapeAttr(v.detay_url)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>Kayıt Detayına Git</a>
            </div>`;
    }

    function renderTutanak(v) {
        const canEdit = v.izinler.duzenle;
        const items = v.tutanaklar.map(t => `
            <div class="ech-tut-item">
                <div class="ech-tut-ust">
                    <span class="num">Seri: ${escapeHtml(t.seri_no ?? '—')}</span>
                    ${t.gun_sayisi !== null ? `<span class="ech-rozet ${t.devam_ediyor ? 'is-devam-eden' : 'is-sonlanmis'}">${t.gun_sayisi} gün${t.devam_ediyor ? ' (devam)' : ''}</span>` : ''}
                </div>
                <div class="ech-tut-alt">
                    <div><div class="lbl">Tutanak</div><div class="val">${t.tutanak_tarihi ? tr(t.tutanak_tarihi) : '—'}</div></div>
                    <div><div class="lbl">Başlangıç</div><div class="val">${t.isgal_baslangic_tarihi ? tr(t.isgal_baslangic_tarihi) : '—'}</div></div>
                    <div><div class="lbl">Bitiş</div><div class="val">${t.isgal_bitis_tarihi ? tr(t.isgal_bitis_tarihi) : 'Devam'}</div></div>
                </div>
                ${t.aciklama ? `<div style="margin-top:8px;font-size:.74rem;color:#dfe3ea;">${escapeHtml(t.aciklama)}</div>` : ''}
            </div>`).join('');

        const form = canEdit ? `
            <details style="margin-top:12px;">
                <summary style="cursor:pointer;color:#dfe3ea;font-weight:600;font-size:.84rem;padding:8px 0;">➕ Yeni Tutanak Ekle</summary>
                <form class="ech-form" style="margin-top:10px;" onsubmit="return echTutanakKaydet(event, ${v.kayit.id})">
                    <div class="ech-form-row">
                        <div class="ech-form-satir"><label>Seri No</label><input type="text" name="seri_no" maxlength="100"></div>
                        <div class="ech-form-satir"><label>Tutanak Tarihi</label><input type="date" name="tutanak_tarihi"></div>
                    </div>
                    <div class="ech-form-row">
                        <div class="ech-form-satir"><label>İşgal Başlangıç</label><input type="date" name="isgal_baslangic_tarihi"></div>
                        <div class="ech-form-satir"><label>İşgal Bitiş</label><input type="date" name="isgal_bitis_tarihi"></div>
                    </div>
                    <div class="ech-form-satir"><label>Açıklama</label><textarea name="aciklama" maxlength="2000"></textarea></div>
                    <div id="ech-tutanak-flash"></div>
                    <button type="submit" class="ech-btn is-yesil">Tutanağı Kaydet</button>
                </form>
            </details>` : '';

        document.getElementById('ech-tab-tutanak').innerHTML =
            (v.tutanaklar.length === 0 ? '<div class="ech-bos">Henüz tutanak yok.</div>' : items) + form;
    }

    function renderResim(v) {
        const canEdit = v.izinler.duzenle;
        const galeri = v.resimler.length > 0 ? `
            <div class="ech-galeri">${v.resimler.map(r => `<img src="${escapeAttr(r.url)}" alt="${escapeAttr(r.aciklama ?? '')}" onclick="window.open('${escapeAttr(r.url)}','_blank')">`).join('')}</div>`
            : '<div class="ech-bos">Henüz resim yok.</div>';
        const form = canEdit ? `
            <details style="margin-top:12px;">
                <summary style="cursor:pointer;color:#dfe3ea;font-weight:600;font-size:.84rem;padding:8px 0;">➕ Resim Yükle</summary>
                <form class="ech-form" style="margin-top:10px;" onsubmit="return echResimYukle(event, ${v.kayit.id})" enctype="multipart/form-data">
                    <div class="ech-form-satir"><label>Resim(ler)</label>
                        <label class="ech-file-drop" data-file-drop>
                            <input type="file" name="resimler[]" accept="image/*" multiple required>
                            <span class="ech-file-ikon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg></span>
                            <span class="ech-file-meta">
                                <span class="ech-file-baslik" data-file-baslik>Resimleri sürükleyin veya tıklayın</span>
                                <span class="ech-file-alt" data-file-alt>JPG, PNG, WEBP · çoklu · max 8 MB</span>
                            </span>
                        </label>
                    </div>
                    <div class="ech-form-satir"><label>Açıklama</label><input type="text" name="aciklama" maxlength="300"></div>
                    <div id="ech-resim-flash"></div>
                    <button type="submit" class="ech-btn is-yesil">Yükle</button>
                </form>
            </details>` : '';
        document.getElementById('ech-tab-resim').innerHTML = galeri + form;
    }

    window.echTutanakKaydet = function (e, id) {
        e.preventDefault();
        const form = e.target, btn = form.querySelector('button[type=submit]');
        btn.disabled = true;
        const fd = new FormData(form);
        const flash = form.querySelector('#ech-tutanak-flash');
        fetch(API.tutanakKaydet + '/' + id + '/tutanak', {
            method: 'POST', headers: { 'X-CSRF-TOKEN': API.csrf, 'Accept': 'application/json' }, body: fd,
        }).then(r => {
            if (r.ok) {
                flash.innerHTML = '<div class="ech-flash is-ok">Tutanak kaydedildi.</div>';
                fetch(API.ozet + '/' + id).then(r => r.json()).then(v => renderTutanak(v));
                // Parselin durumunu güncelle: layer'ı yeniden çiz
                haritayiYukle(mevcutFiltre);
            } else {
                flash.innerHTML = '<div class="ech-flash is-hata">Hata oluştu.</div>';
            }
        }).finally(() => btn.disabled = false);
        return false;
    };

    window.echResimYukle = function (e, id) {
        e.preventDefault();
        const form = e.target, btn = form.querySelector('button[type=submit]');
        btn.disabled = true;
        const fd = new FormData(form);
        const flash = form.querySelector('#ech-resim-flash');
        fetch(API.resimYukle + '/' + id + '/resim', {
            method: 'POST', headers: { 'X-CSRF-TOKEN': API.csrf, 'Accept': 'application/json' }, body: fd,
        }).then(r => {
            if (r.ok) {
                flash.innerHTML = '<div class="ech-flash is-ok">Resim yüklendi.</div>';
                fetch(API.ozet + '/' + id).then(r => r.json()).then(v => renderResim(v));
            } else {
                flash.innerHTML = '<div class="ech-flash is-hata">Hata oluştu.</div>';
            }
        }).finally(() => btn.disabled = false);
        return false;
    };

    function tr(iso) { try { return new Date(iso).toLocaleDateString('tr-TR'); } catch(e) { return iso; } }
    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }
    function escapeAttr(s) { return escapeHtml(s); }

    // Flatpickr — dinamik formlarda da otomatik uygulanır
    function fpBaslat() {
        if (typeof flatpickr === 'undefined') return;
        document.querySelectorAll('input[type="date"]:not(.fp-inited)').forEach(el => {
            el.classList.add('fp-inited');
            flatpickr(el, {
                locale: (typeof flatpickr.l10ns !== 'undefined' && flatpickr.l10ns.tr) ? flatpickr.l10ns.tr : 'default',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd F Y, l',
                allowInput: true,
                monthSelectorType: 'static',
            });
        });
    }
    fpBaslat();
    new MutationObserver(fpBaslat).observe(document.body, { childList: true, subtree: true });

    // Şık dosya yükleme davranışı — dinamik formlarda çalışır (event delegation)
    document.addEventListener('change', function (e) {
        if (e.target.matches('.ech-file-drop input[type="file"]')) {
            const wrap = e.target.closest('.ech-file-drop');
            const baslik = wrap.querySelector('[data-file-baslik]');
            const alt = wrap.querySelector('[data-file-alt]');
            const files = e.target.files;
            if (files && files.length > 0) {
                wrap.classList.add('has-file');
                if (files.length === 1) {
                    baslik.textContent = files[0].name;
                    alt.textContent = (files[0].size / 1024 / 1024).toFixed(2) + ' MB · seçildi';
                } else {
                    baslik.textContent = files.length + ' dosya seçildi';
                    alt.textContent = Array.from(files).map(f => f.name).slice(0, 3).join(', ') + (files.length > 3 ? '...' : '');
                }
            } else {
                wrap.classList.remove('has-file');
            }
        }
    });
    document.addEventListener('dragover', function (e) {
        const wrap = e.target.closest?.('.ech-file-drop');
        if (wrap) { e.preventDefault(); wrap.classList.add('is-dragover'); }
    });
    document.addEventListener('dragleave', function (e) {
        const wrap = e.target.closest?.('.ech-file-drop');
        if (wrap) wrap.classList.remove('is-dragover');
    });
    document.addEventListener('drop', function (e) {
        const wrap = e.target.closest?.('.ech-file-drop');
        if (wrap) wrap.classList.remove('is-dragover');
    });
})();
</script>
@endpush
