@extends('layouts.panel')

@section('title', 'Hisse Satışı Haritası')
@section('contentClass', 'is-flush')
@section('shellClass', 'is-map-full')

@push('head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.Default.css">
    <style>
        /* Tam ekran leaflet + kurumsal koyu popup */
        .hsh-shell {
            position: fixed; inset: 0;
            width: 100vw; height: 100vh; height: 100dvh;
            background: #0e1726;
        }
        .hsh-map { position: absolute; inset: 0; width: 100%; height: 100%; z-index: 1; }
        /* is-map-full shell'i altında ana içerik padding/margin varsa sıfırla */
        body.app-shell.is-map-full .app-content.is-flush { padding: 0; margin: 0; overflow: hidden; }

        /* Leaflet varsayılan +/- kontrolünü sakla — kendi toolbar butonlarımız var */
        .hsh-shell .leaflet-control-zoom { display: none !important; }
        .hsh-shell .leaflet-container { background: #0e1726; }

        /* ---- TOOLBAR ---- */
        .hsh-toolbar {
            position: absolute; top: 12px; left: 12px; z-index: 20;
            display: flex; align-items: center; gap: 6px;
            background: rgba(14,23,38,0.92); backdrop-filter: blur(6px);
            border: 1px solid rgba(255,255,255,0.08); border-radius: 10px;
            padding: 6px; box-shadow: 0 6px 22px rgba(0,0,0,.35);
        }
        .hsh-tool {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 12px; font-size: .78rem; font-weight: 600;
            background: transparent; color: #dfe3ea; border: 1px solid transparent;
            border-radius: 7px; cursor: pointer; text-decoration: none;
            transition: background .15s ease, border-color .15s ease, color .15s ease;
        }
        .hsh-tool svg { width: 15px; height: 15px; flex: 0 0 15px; }
        .hsh-tool:hover { background: rgba(255,255,255,0.06); color: #fff; }
        .hsh-tool.is-aktif { background: #2563eb; color: #fff; border-color: rgba(255,255,255,.12); }
        .hsh-tool[aria-expanded="true"] { background: rgba(37,99,235,.18); color: #fff; border-color: rgba(37,99,235,.35); }
        .hsh-tool-divider { width: 1px; align-self: stretch; background: rgba(255,255,255,.08); margin: 2px 4px; }
        .hsh-chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 10px; font-size: .75rem; color: #9aa3b2;
            background: rgba(255,255,255,.04); border-radius: 6px;
        }
        .hsh-chip[hidden] { display: none !important; }
        .hsh-chip strong { color: #fff; font-size: .82rem; }

        /* ---- FLOAT PANEL (Katman & İşlemler) ---- */
        .hsh-panel {
            position: absolute; z-index: 15;
            background: #0e1726; color: #dfe3ea;
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 12px;
            box-shadow: 0 22px 60px rgba(0,0,0,.55);
            display: flex; flex-direction: column;
            min-width: 320px; max-height: calc(100vh - 160px);
            overflow: hidden;
        }
        .hsh-panel[hidden] { display: none; }
        .hsh-panel-head {
            display: flex; align-items: center; gap: 8px;
            padding: 12px 14px;
            background: linear-gradient(180deg, #16223c 0%, #0e1726 100%);
            border-bottom: 1px solid rgba(255,255,255,.06);
            cursor: move; user-select: none;
        }
        .hsh-panel-head strong { color: #fff; font-size: .88rem; font-weight: 700; letter-spacing: .01em; flex: 1; }
        .hsh-panel-head small { color: #8a91a1; font-size: .74rem; display: block; }
        .hsh-panel-kapat {
            background: transparent; border: none; color: #9aa3b2;
            padding: 4px; border-radius: 6px; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .hsh-panel-kapat:hover { background: rgba(255,255,255,.08); color: #fff; }
        .hsh-panel-scroll { flex: 1; overflow-y: auto; }
        .hsh-panel-scroll::-webkit-scrollbar { width: 8px; }
        .hsh-panel-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.16); border-radius: 4px; }

        /* Katman paneli */
        .hsh-katman { position: absolute; top: 72px; left: 12px; width: 360px; }
        .hsh-katman-grup { border-bottom: 1px solid rgba(255,255,255,.05); }
        .hsh-katman-grup:last-child { border-bottom: none; }
        .hsh-katman-baslik {
            padding: 10px 14px; font-size: .72rem; text-transform: uppercase; letter-spacing: .06em;
            color: #8a91a1; font-weight: 700; background: rgba(255,255,255,.02);
        }
        .hsh-katman-kart {
            padding: 12px 14px; display: flex; align-items: center; gap: 12px;
            transition: background .15s ease;
        }
        .hsh-katman-kart:hover { background: rgba(255,255,255,.03); }
        .hsh-katman-ikon {
            width: 30px; height: 30px; border-radius: 8px; flex: 0 0 30px;
            display: inline-flex; align-items: center; justify-content: center; color: #fff;
        }
        .hsh-katman-meta { flex: 1; min-width: 0; }
        .hsh-katman-ad { display: block; color: #fff; font-size: .84rem; font-weight: 600; }
        .hsh-katman-alt { display: block; color: #8a91a1; font-size: .72rem; margin-top: 2px; }

        /* Switch */
        .hsh-switch { position: relative; display: inline-block; width: 34px; height: 20px; flex: 0 0 34px; }
        .hsh-switch input { opacity: 0; width: 0; height: 0; }
        .hsh-switch-slider {
            position: absolute; cursor: pointer; inset: 0;
            background: #2a3550; border-radius: 20px; transition: background .2s ease;
        }
        .hsh-switch-slider::before {
            content: ''; position: absolute; height: 14px; width: 14px;
            left: 3px; top: 3px; background: #fff; border-radius: 50%;
            transition: transform .2s ease;
        }
        .hsh-switch input:checked + .hsh-switch-slider { background: #2563eb; }
        .hsh-switch input:checked + .hsh-switch-slider::before { transform: translateX(14px); }

        /* Radyo listesi (altlık seçici) */
        .hsh-radyo-grup { padding: 8px 6px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
        .hsh-radyo {
            padding: 8px; border: 1px solid rgba(255,255,255,.08); border-radius: 8px;
            background: rgba(255,255,255,.02); cursor: pointer; text-align: center;
            font-size: .72rem; color: #dfe3ea; transition: all .15s ease;
        }
        .hsh-radyo:hover { border-color: rgba(37,99,235,.4); color: #fff; }
        .hsh-radyo.is-aktif { border-color: #2563eb; background: rgba(37,99,235,.15); color: #fff; font-weight: 600; }

        /* Lejant */
        .hsh-lejant { padding: 10px 14px; border-top: 1px solid rgba(255,255,255,.05); }
        .hsh-lejant-baslik { font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; color: #8a91a1; font-weight: 700; margin-bottom: 8px; }
        .hsh-lejant-oge { display: flex; align-items: center; gap: 8px; font-size: .76rem; color: #dfe3ea; padding: 4px 0; }
        .hsh-lejant-nokta { width: 12px; height: 12px; border-radius: 3px; border: 1px solid rgba(255,255,255,.15); flex: 0 0 12px; }

        /* İşlemler paneli (sağ) */
        .hsh-islem { position: absolute; top: 12px; right: 12px; bottom: 12px; width: 420px; max-height: none; }
        .hsh-islem-baslik-alt { font-size: .74rem; color: #8a91a1; margin-top: 3px; }

        .hsh-islem-tabs { display: flex; background: #16223c; border-bottom: 1px solid rgba(255,255,255,.06); }
        .hsh-tab {
            flex: 1; padding: 10px 8px; background: transparent; border: none;
            font-size: .76rem; font-weight: 600; color: #8a91a1; cursor: pointer;
            border-bottom: 2px solid transparent; transition: all .15s ease;
        }
        .hsh-tab:hover { color: #dfe3ea; }
        .hsh-tab.is-aktif { color: #fff; border-bottom-color: #2563eb; background: rgba(37,99,235,.08); }

        .hsh-tab-panel { display: none; padding: 14px; }
        .hsh-tab-panel.is-aktif { display: block; }

        .hsh-ozet-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 14px; }
        .hsh-ozet-alan { min-width: 0; }
        .hsh-ozet-alan .lbl { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: #8a91a1; font-weight: 700; margin-bottom: 3px; }
        .hsh-ozet-alan .val { font-size: .84rem; color: #fff; word-break: break-word; }

        .hsh-durum-rozet {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 9px; border-radius: 999px; font-size: .72rem; font-weight: 600;
            border: 1px solid;
        }
        .hsh-durum-rozet.is-yeni       { color: #38bdf8; border-color: rgba(56,189,248,.35); background: rgba(56,189,248,.08); }
        .hsh-durum-rozet.is-encumen    { color: #facc15; border-color: rgba(250,204,21,.4);  background: rgba(250,204,21,.08); }
        .hsh-durum-rozet.is-satis      { color: #fb923c; border-color: rgba(251,146,60,.4);  background: rgba(251,146,60,.08); }
        .hsh-durum-rozet.is-tamamlandi { color: #34d399; border-color: rgba(52,211,153,.4);  background: rgba(52,211,153,.08); }

        .hsh-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            padding: 9px 14px; font-size: .82rem; font-weight: 600;
            background: #2563eb; color: #fff; border: none; border-radius: 7px; cursor: pointer;
            transition: background .15s ease; text-decoration: none;
        }
        .hsh-btn:hover { background: #1d4ed8; }
        .hsh-btn.is-ghost { background: transparent; color: #dfe3ea; border: 1px solid rgba(255,255,255,.14); }
        .hsh-btn.is-ghost:hover { background: rgba(255,255,255,.06); color: #fff; }
        .hsh-btn.is-yesil { background: #059669; }
        .hsh-btn.is-yesil:hover { background: #047857; }
        .hsh-btn.is-kirmizi { background: #dc2626; }
        .hsh-btn.is-kirmizi:hover { background: #b91c1c; }
        .hsh-btn.is-mini { padding: 5px 9px; font-size: .72rem; border-radius: 5px; gap: 4px; }
        .hsh-btn svg { width: 14px; height: 14px; }
        .hsh-btn:disabled { opacity: .55; cursor: not-allowed; }

        .hsh-btn-row { display: flex; gap: 8px; margin-top: 12px; }
        .hsh-btn-row .hsh-btn { flex: 1; }

        /* Encümen listesi */
        .hsh-encumen-liste { display: flex; flex-direction: column; gap: 8px; }
        .hsh-encumen-item {
            border: 1px solid rgba(255,255,255,.08); border-radius: 8px;
            background: rgba(255,255,255,.02); overflow: hidden;
        }
        .hsh-encumen-head {
            padding: 10px 12px; display: flex; align-items: center; gap: 10px;
            cursor: pointer; transition: background .15s ease;
        }
        .hsh-encumen-head:hover { background: rgba(255,255,255,.04); }
        .hsh-encumen-head .num { color: #fff; font-weight: 700; font-size: .84rem; }
        .hsh-encumen-head .sub { color: #8a91a1; font-size: .72rem; margin-top: 2px; }
        .hsh-encumen-head .caret { margin-left: auto; color: #8a91a1; transition: transform .2s ease; }
        .hsh-encumen-item.is-acik .hsh-encumen-head .caret { transform: rotate(180deg); }
        .hsh-encumen-body {
            display: none; padding: 12px; border-top: 1px solid rgba(255,255,255,.06);
            background: rgba(0,0,0,.15);
        }
        .hsh-encumen-item.is-acik .hsh-encumen-body { display: block; }
        .hsh-encumen-body .hsh-ozet-grid { grid-template-columns: 1fr 1fr; gap: 8px 12px; }
        .hsh-encumen-body .lbl { color: #8a91a1; font-size: .68rem; }
        .hsh-encumen-body .val { color: #dfe3ea; font-size: .78rem; }

        /* Form */
        .hsh-form { display: flex; flex-direction: column; gap: 10px; }
        .hsh-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .hsh-form-satir label { display: block; font-size: .7rem; color: #8a91a1; text-transform: uppercase; letter-spacing: .05em; font-weight: 700; margin-bottom: 4px; }
        .hsh-form-satir input, .hsh-form-satir textarea, .hsh-form-satir select {
            width: 100%; padding: 8px 10px; font-size: .82rem;
            background: rgba(0,0,0,.25); color: #fff;
            border: 1px solid rgba(255,255,255,.1); border-radius: 6px;
            transition: border-color .15s ease, background .15s ease;
        }
        .hsh-form-satir input:focus, .hsh-form-satir textarea:focus, .hsh-form-satir select:focus {
            outline: none; border-color: #2563eb; background: rgba(0,0,0,.35);
        }
        .hsh-form-satir textarea { min-height: 60px; resize: vertical; }

        .hsh-form-toggle {
            border-top: 1px solid rgba(255,255,255,.06); padding-top: 12px; margin-top: 12px;
        }

        .hsh-bos {
            padding: 24px; text-align: center; color: #8a91a1; font-size: .82rem;
            border: 1px dashed rgba(255,255,255,.08); border-radius: 8px;
        }

        .hsh-yukleniyor {
            padding: 40px 20px; text-align: center; color: #8a91a1; font-size: .82rem;
        }
        .hsh-yukleniyor::before {
            content: ''; display: inline-block; width: 22px; height: 22px;
            border: 2.5px solid rgba(255,255,255,.15); border-top-color: #2563eb;
            border-radius: 50%; margin-right: 10px; vertical-align: middle;
            animation: hsh-spin .8s linear infinite;
        }
        @keyframes hsh-spin { to { transform: rotate(360deg); } }

        .hsh-flash {
            padding: 8px 12px; border-radius: 6px; font-size: .78rem; margin-bottom: 10px;
        }
        .hsh-flash.is-ok { background: rgba(52,211,153,.12); color: #6ee7b7; border: 1px solid rgba(52,211,153,.3); }
        .hsh-flash.is-hata { background: rgba(248,113,113,.12); color: #fca5a5; border: 1px solid rgba(248,113,113,.3); }

        /* Ada/Parsel etiketi */
        .hsh-etiket {
            background: rgba(255,224,1,.95); color: #1f2937; padding: 2px 8px;
            border-radius: 4px; font-weight: 700; font-size: 11px;
            box-shadow: 0 1px 3px rgba(0,0,0,.5); white-space: nowrap;
            border: 1px solid rgba(0,0,0,.15);
        }
        .hsh-cluster {
            background: #2563eb; color: #fff; width: 36px; height: 36px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 13px; border: 2px solid #fff;
            box-shadow: 0 2px 6px rgba(0,0,0,.4);
        }
        .hsh-cluster-wrap { background: transparent !important; border: none !important; }

        /* Arama paneli */
        .hsh-arama { position: absolute; top: 72px; left: 388px; width: 340px; }

        /* İndir dropdown */
        .hsh-indir-wrap { position: relative; }
        .hsh-indir-menu {
            position: absolute; top: calc(100% + 6px); left: 0;
            min-width: 260px; background: #0e1726;
            border: 1px solid rgba(255,255,255,.08); border-radius: 10px;
            box-shadow: 0 22px 60px rgba(0,0,0,.55);
            padding: 6px; z-index: 30; overflow: hidden;
        }
        .hsh-indir-item {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 10px 12px; width: 100%; text-align: left;
            background: transparent; border: none; border-radius: 6px;
            color: #dfe3ea; cursor: pointer; transition: background .15s ease;
        }
        .hsh-indir-item:hover { background: rgba(37,99,235,.15); color: #fff; }
        .hsh-indir-item svg { color: #38bdf8; margin-top: 2px; flex: 0 0 15px; }
        .hsh-indir-item .ttl { font-size: .84rem; font-weight: 600; }
        .hsh-indir-item .sub { font-size: .72rem; color: #8a91a1; margin-top: 2px; }

        /* Seçim modu göstergesi + chip x */
        .hsh-tool[aria-pressed="true"] { background: #16a34a; color: #fff; border-color: rgba(255,255,255,.14); }
        .hsh-shell.is-secim-modu { cursor: crosshair; }
        .hsh-shell.is-secim-modu .hsh-map { cursor: crosshair; }
        .hsh-chip-x {
            background: transparent; border: none; color: #8a91a1; cursor: pointer;
            padding: 2px; border-radius: 4px; display: inline-flex; align-items: center;
        }
        .hsh-chip-x:hover { background: rgba(255,255,255,.08); color: #fff; }

        /* Küçük ekranlar için panel genişliğini daralt */
        @media (max-width: 900px) {
            .hsh-katman { width: calc(100vw - 24px); left: 12px; }
            .hsh-islem { width: calc(100vw - 24px); right: 12px; }
            .hsh-arama { width: calc(100vw - 24px); left: 12px; top: 130px; }
        }
    </style>
@endpush

@section('content')
<div class="hsh-shell" id="hsh-shell">
    <div id="hsh-map" class="hsh-map"></div>

    {{-- ==== TOOLBAR ==== --}}
    <div class="hsh-toolbar" role="toolbar" aria-label="Harita araç çubuğu">
        <a class="hsh-tool" href="{{ route('panel.hisse-satisi.liste') }}" title="Başvuru listesine dön">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4h3a2 2 0 012 2v12a2 2 0 01-2 2h-3"/><path d="M10 16l-4-4 4-4M14 12H6"/></svg>
            <span>Liste</span>
        </a>
        <div class="hsh-tool-divider"></div>
        <button type="button" class="hsh-tool" id="hsh-katman-btn" aria-expanded="true" title="Katmanlar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 8l10 6 10-6z"/><path d="M2 16l10 6 10-6M2 12l10 6 10-6"/></svg>
            <span>Katmanlar</span>
        </button>
        <button type="button" class="hsh-tool" id="hsh-arama-btn" aria-expanded="false" title="Ara / Filtrele">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <span>Ara</span>
        </button>
        <button type="button" class="hsh-tool" id="hsh-secim-btn" aria-pressed="false" title="Seçim modu — parsellere tıklayarak seçim yapın">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            <span>Seçim</span>
        </button>
        <div class="hsh-indir-wrap">
            <button type="button" class="hsh-tool" id="hsh-indir-btn" aria-expanded="false" title="İndir">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0 0l-4-4m4 4l4-4"/></svg>
                <span>İndir</span>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="hsh-indir-menu" id="hsh-indir-menu" hidden>
                <button type="button" class="hsh-indir-item" data-indir="kml">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M2 12h20"/></svg>
                    <div>
                        <div class="ttl">KML olarak indir</div>
                        <div class="sub">Google Earth / GIS uygulamaları için</div>
                    </div>
                </button>
                <button type="button" class="hsh-indir-item" data-indir="excel">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                    <div>
                        <div class="ttl">Excel olarak indir</div>
                        <div class="sub">Ada/Parsel + iş akışı özet tablosu</div>
                    </div>
                </button>
            </div>
        </div>
        <button type="button" class="hsh-tool" id="hsh-fit-btn" title="Tüm hisse satışı parsellerine sığdır">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
            <span>Sığdır</span>
        </button>
        <div class="hsh-tool-divider"></div>
        <span class="hsh-chip" title="Haritadaki parsel sayısı">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8L12 3l8 5L18 20H6z"/></svg>
            <strong id="hsh-sayi">0</strong>
            <span>parsel</span>
        </span>
        <span class="hsh-chip" id="hsh-secili-chip" title="Seçili parsel sayısı" hidden>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/></svg>
            <strong id="hsh-secili-sayi">0</strong>
            <span>seçili</span>
            <button type="button" class="hsh-chip-x" id="hsh-secim-temizle" title="Seçimi temizle">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </span>
    </div>

    {{-- ==== ARAMA / FİLTRE PANELİ ==== --}}
    <div class="hsh-panel hsh-arama" id="hsh-arama-panel" hidden>
        <div class="hsh-panel-head" data-drag>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <div style="flex:1;min-width:0;">
                <strong>Ara / Filtrele</strong>
                <small>Konum, ada/parsel veya durum ile daralt</small>
            </div>
            <button type="button" class="hsh-panel-kapat" data-panel-kapat="hsh-arama-panel" aria-label="Kapat">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="hsh-panel-scroll" style="padding:14px;">
            <form class="hsh-form" id="hsh-arama-form" onsubmit="return hshFiltreUygula(event)">
                <div class="hsh-form-satir">
                    <label>Serbest Arama</label>
                    <input type="search" name="q" placeholder="Ada / parsel / nitelik…" autocomplete="off">
                </div>
                <div class="hsh-form-satir">
                    <label>İl</label>
                    <select name="il_id" id="hsh-f-il">
                        <option value="">— Tümü —</option>
                        @foreach ($iller as $il)
                            <option value="{{ $il->id }}">{{ $il->ad }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="hsh-form-satir">
                    <label>İlçe</label>
                    <select name="ilce_id" id="hsh-f-ilce" disabled><option value="">— Önce il —</option></select>
                </div>
                <div class="hsh-form-satir">
                    <label>Mahalle</label>
                    <select name="mahalle_id" id="hsh-f-mahalle" disabled><option value="">— Önce ilçe —</option></select>
                </div>
                <div class="hsh-form-row">
                    <div class="hsh-form-satir"><label>Ada</label><input type="text" name="ada" autocomplete="off"></div>
                    <div class="hsh-form-satir"><label>Parsel</label><input type="text" name="parsel" autocomplete="off"></div>
                </div>
                <div class="hsh-form-satir">
                    <label>Durum</label>
                    <select name="durum">
                        <option value="">— Tümü —</option>
                        <option value="yeni">Yeni başvuru</option>
                        <option value="encumen-var">Encümen var</option>
                        <option value="satis-tebligat">Satış tebligatı</option>
                        <option value="tamamlandi">Tamamlandı</option>
                    </select>
                </div>
                <div class="hsh-btn-row">
                    <button type="submit" class="hsh-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54z"/></svg>Uygula</button>
                    <button type="button" class="hsh-btn is-ghost" onclick="hshFiltreTemizle()">Temizle</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ==== KATMAN PANELİ ==== --}}
    <div class="hsh-panel hsh-katman" id="hsh-katman-panel">
        <div class="hsh-panel-head" data-drag>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 8l10 6 10-6z"/><path d="M2 12l10 6 10-6"/></svg>
            <div style="flex:1;min-width:0;">
                <strong>Katmanlar</strong>
                <small>Altlık ve hisse satışı katmanları</small>
            </div>
            <button type="button" class="hsh-panel-kapat" data-panel-kapat="hsh-katman-panel" aria-label="Kapat">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="hsh-panel-scroll">
            {{-- Altlık --}}
            <div class="hsh-katman-grup">
                <div class="hsh-katman-baslik">Altlık</div>
                <div class="hsh-radyo-grup" id="hsh-altlik-grup">
                    <div class="hsh-radyo is-aktif" data-altlik="uydu-etiket">Uydu + Etiket</div>
                    <div class="hsh-radyo" data-altlik="uydu">Uydu</div>
                    <div class="hsh-radyo" data-altlik="yol">Yol Haritası</div>
                    <div class="hsh-radyo" data-altlik="arazi">Arazi</div>
                    <div class="hsh-radyo" data-altlik="osm">OSM</div>
                </div>
            </div>

            {{-- Ana veri katmanı --}}
            <div class="hsh-katman-grup">
                <div class="hsh-katman-baslik">Hisse Satışı</div>
                <div class="hsh-katman-kart">
                    <span class="hsh-katman-ikon" style="background:#facc15;color:#0e1726;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8L12 3l8 5L18 20H6z"/></svg>
                    </span>
                    <div class="hsh-katman-meta">
                        <span class="hsh-katman-ad">Hisse Satışı Parselleri</span>
                        <span class="hsh-katman-alt">Başvurusu olan parseller — durum rengiyle</span>
                    </div>
                    <label class="hsh-switch"><input type="checkbox" id="hsh-lyr-parseller" checked><span class="hsh-switch-slider"></span></label>
                </div>
                <div class="hsh-katman-kart">
                    <span class="hsh-katman-ikon" style="background:#f43f5e;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-7-7-7-13a7 7 0 0114 0c0 6-7 13-7 13z"/><circle cx="12" cy="9" r="2.6"/></svg>
                    </span>
                    <div class="hsh-katman-meta">
                        <span class="hsh-katman-ad">Ada/Parsel Etiketi</span>
                        <span class="hsh-katman-alt">Her parselin merkezinde ada/parsel yazısı</span>
                    </div>
                    <label class="hsh-switch"><input type="checkbox" id="hsh-lyr-etiket" checked><span class="hsh-switch-slider"></span></label>
                </div>
                <div class="hsh-katman-kart">
                    <span class="hsh-katman-ikon" style="background:#8b5cf6;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 3v18M3 12h18"/></svg>
                    </span>
                    <div class="hsh-katman-meta">
                        <span class="hsh-katman-ad">Kümeleme</span>
                        <span class="hsh-katman-alt">Yakın parselleri gruplandır</span>
                    </div>
                    <label class="hsh-switch"><input type="checkbox" id="hsh-lyr-cluster"><span class="hsh-switch-slider"></span></label>
                </div>
            </div>

            {{-- Ankara BB imar & parselasyon (referans) --}}
            <div class="hsh-katman-grup">
                <div class="hsh-katman-baslik">Ankara BB (Referans)</div>
                <div class="hsh-katman-kart">
                    <span class="hsh-katman-ikon" style="background:#ec4899;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4v-4h4v4h4v-4h4v4h2M3 12v6h18v-6"/></svg>
                    </span>
                    <div class="hsh-katman-meta">
                        <span class="hsh-katman-ad">İmar Planı (UIP)</span>
                        <span class="hsh-katman-alt">Ankara BB · uipSade</span>
                    </div>
                    <label class="hsh-switch"><input type="checkbox" id="hsh-lyr-imar"><span class="hsh-switch-slider"></span></label>
                </div>
                <div class="hsh-katman-kart">
                    <span class="hsh-katman-ikon" style="background:#0ea5e9;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16M9 3v18M15 3v18"/></svg>
                    </span>
                    <div class="hsh-katman-meta">
                        <span class="hsh-katman-ad">Parselasyon</span>
                        <span class="hsh-katman-alt">Ankara BB · parselAktif</span>
                    </div>
                    <label class="hsh-switch"><input type="checkbox" id="hsh-lyr-parselasyon"><span class="hsh-switch-slider"></span></label>
                </div>
            </div>

            {{-- Lejant --}}
            <div class="hsh-lejant">
                <div class="hsh-lejant-baslik">Parsel Durumu</div>
                <div class="hsh-lejant-oge"><span class="hsh-lejant-nokta" style="background:#38bdf8;"></span> Yeni başvuru (henüz encümen yok)</div>
                <div class="hsh-lejant-oge"><span class="hsh-lejant-nokta" style="background:#facc15;"></span> Encümen kararı var</div>
                <div class="hsh-lejant-oge"><span class="hsh-lejant-nokta" style="background:#fb923c;"></span> Satış tebligatı çıkarıldı</div>
                <div class="hsh-lejant-oge"><span class="hsh-lejant-nokta" style="background:#34d399;"></span> Tapu tescili tamamlandı</div>
            </div>
        </div>
    </div>

    {{-- ==== İŞLEMLER PANELİ (sağ) — parsel tıklanınca açılır ==== --}}
    <div class="hsh-panel hsh-islem" id="hsh-islem-panel" hidden>
        <div class="hsh-panel-head" data-drag>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/><circle cx="12" cy="12" r="4"/></svg>
            <div style="flex:1;min-width:0;">
                <strong>İşlemler</strong>
                <small class="hsh-islem-baslik-alt" id="hsh-islem-alt">Parsel seçilmedi</small>
            </div>
            <button type="button" class="hsh-panel-kapat" data-panel-kapat="hsh-islem-panel" aria-label="Kapat">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="hsh-islem-tabs">
            <button type="button" class="hsh-tab is-aktif" data-tab="ozet">Özet</button>
            <button type="button" class="hsh-tab" data-tab="encumen">Encümen</button>
            <button type="button" class="hsh-tab" data-tab="basvuru">Başvurular</button>
        </div>

        <div class="hsh-panel-scroll">
            <div class="hsh-tab-panel is-aktif" data-tab-panel="ozet" id="hsh-tab-ozet">
                <div class="hsh-yukleniyor">Yükleniyor</div>
            </div>
            <div class="hsh-tab-panel" data-tab-panel="encumen" id="hsh-tab-encumen">
                <div class="hsh-yukleniyor">Yükleniyor</div>
            </div>
            <div class="hsh-tab-panel" data-tab-panel="basvuru" id="hsh-tab-basvuru">
                <div class="hsh-yukleniyor">Yükleniyor</div>
            </div>
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
        geojson: @json(route('panel.hisse-satisi.harita.geojson')),
        ozet: @json(url('/panel/hisse-satisi/harita/tasinmaz')),
        excel: @json(route('panel.hisse-satisi.harita.excel')),
        kml: @json(route('panel.hisse-satisi.harita.kml')),
        encumenKaydet: @json(url('/panel/hisse-satisi/grup')),
        detayBase: @json(url('/panel/hisse-satisi/grup')),
        ilceler: @json(url('/panel/ajax/ilceler')),
        mahalleler: @json(url('/panel/ajax/mahalleler')),
        csrf: @json(csrf_token()),
    };

    // === 1) Harita kurulumu ===
    const map = L.map('hsh-map', {
        zoomSnap: 0.5, zoomDelta: 0.5, zoomControl: false, attributionControl: false,
    }).setView([39.9334, 32.8597], 10);

    // Altlıklar
    const altliklar = {
        'uydu-etiket': L.tileLayer('https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { subdomains: ['0','1','2','3'], maxZoom: 20 }),
        'uydu':        L.tileLayer('https://mt{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', { subdomains: ['0','1','2','3'], maxZoom: 20 }),
        'yol':         L.tileLayer('https://mt{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', { subdomains: ['0','1','2','3'], maxZoom: 20 }),
        'arazi':       L.tileLayer('https://mt{s}.google.com/vt/lyrs=p&x={x}&y={y}&z={z}', { subdomains: ['0','1','2','3'], maxZoom: 20 }),
        'osm':         L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { subdomains: ['a','b','c'], maxZoom: 19 }),
    };
    let aktifAltlik = 'uydu-etiket';
    altliklar[aktifAltlik].addTo(map);

    // Ankara BB katmanları (WMS)
    const abbImar = L.tileLayer.wms('https://planaski.ankara.bel.tr/webgis/services/mobilServis/uipSade/MapServer/WMSServer', {
        layers: '0', format: 'image/png', transparent: true, opacity: .75, maxZoom: 20,
    });
    const abbParselasyon = L.tileLayer.wms('https://planaski.ankara.bel.tr/webgis/services/mobilServis/parselAktif/MapServer/WMSServer', {
        layers: '0', format: 'image/png', transparent: true, opacity: .8, maxZoom: 20,
    });

    // Ana katmanlar
    const parselKatman = L.featureGroup();
    const etiketKatman = L.featureGroup();
    const clusterKatman = L.markerClusterGroup({
        disableClusteringAtZoom: 16,
        maxClusterRadius: 55,
        iconCreateFunction: c => L.divIcon({
            html: `<div class="hsh-cluster">${c.getChildCount()}</div>`,
            className: 'hsh-cluster-wrap',
            iconSize: [36, 36],
        }),
    });
    parselKatman.addTo(map);
    etiketKatman.addTo(map);

    // Durum → renk
    const DURUM_RENK = {
        'yeni':           { renk: '#38bdf8', dolgu: 'rgba(56,189,248,0.28)', etiket: 'Yeni başvuru' },
        'encumen-var':    { renk: '#facc15', dolgu: 'rgba(250,204,21,0.30)', etiket: 'Encümen var' },
        'satis-tebligat': { renk: '#fb923c', dolgu: 'rgba(251,146,60,0.32)', etiket: 'Satış tebligatı' },
        'tamamlandi':     { renk: '#34d399', dolgu: 'rgba(52,211,153,0.32)', etiket: 'Tamamlandı' },
    };
    const durumSinifi = d => 'is-' + ({ 'yeni':'yeni','encumen-var':'encumen','satis-tebligat':'satis','tamamlandi':'tamamlandi' }[d] || 'yeni');

    let allBounds = null;
    let secilenLayer = null;
    let secilenTasinmazId = null;
    let secimModu = false;
    const secili = new Set(); // seçili tasinmaz_id'leri
    const layerById = new Map(); // tasinmaz_id -> L.Layer
    let mevcutFiltre = new URLSearchParams();

    // === 2) GeoJSON yükleme fonksiyonu (filtre destekli) ===
    function haritayiYukle(filtre) {
        parselKatman.clearLayers();
        etiketKatman.clearLayers();
        clusterKatman.clearLayers();
        layerById.clear();

        const url = filtre && filtre.toString() ? API.geojson + '?' + filtre.toString() : API.geojson;

        return fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(fc => {
                document.getElementById('hsh-sayi').textContent = (fc.features || []).length;
                const bounds = [];
                (fc.features || []).forEach(f => {
                    const props = f.properties || {};
                    const stil = DURUM_RENK[props.durum] || DURUM_RENK.yeni;
                    const layer = L.geoJSON(f, {
                        style: () => ({ color: stil.renk, fillColor: stil.renk, fillOpacity: .30, weight: 2.4 }),
                        pointToLayer: (feat, latlng) => L.circleMarker(latlng, {
                            color: stil.renk, fillColor: stil.renk, fillOpacity: .6, radius: 8, weight: 2,
                        }),
                    });
                    layer.eachLayer(l => {
                        l._props = props;
                        l._durumStil = stil;
                        l.on('click', () => secParsel(l, props));
                        l.on('mouseover', () => { if (l !== secilenLayer && !secili.has(props.id) && l.setStyle) l.setStyle({ fillOpacity: .5, weight: 3 }); });
                        l.on('mouseout',  () => stiliniUygula(l));
                        parselKatman.addLayer(l);
                        layerById.set(props.id, l);
                        try { bounds.push(l.getBounds ? l.getBounds() : L.latLngBounds([l.getLatLng(), l.getLatLng()])); } catch(e) {}
                    });

                    // Ada/parsel etiketi (poligon merkezinde)
                    try {
                        const c = layer.getBounds().getCenter();
                        const et = L.marker(c, {
                            icon: L.divIcon({ className: '', html: `<div class="hsh-etiket">${props.ada ?? '—'}/${props.parsel ?? '—'}</div>`, iconSize: null }),
                            interactive: true,
                        });
                        et.on('click', () => secParsel(layer.getLayers()[0], props));
                        etiketKatman.addLayer(et);
                    } catch(e) {}
                });

                if (bounds.length) {
                    allBounds = bounds.reduce((acc, b) => acc ? acc.extend(b) : L.latLngBounds(b.getSouthWest ? [b.getSouthWest(), b.getNorthEast()] : [b, b]), null);
                    if (allBounds) map.fitBounds(allBounds, { padding: [30, 30] });
                }

                // Filtre sonrası eski seçimden bu görünümde olmayanlar temizlensin
                Array.from(secili).forEach(id => { if (!layerById.has(id)) secili.delete(id); });
                seciliChipGuncelle();
            })
            .catch(e => console.error('GeoJSON yükleme hatası', e));
    }

    // Layerın rengini seçim/hover durumuna göre yeniden uygula
    function stiliniUygula(l) {
        if (!l.setStyle) return;
        const stil = l._durumStil || DURUM_RENK.yeni;
        const seciliMi = secili.has(l._props?.id);
        const aktifMi = l === secilenLayer;
        if (seciliMi) {
            l.setStyle({ color: '#22c55e', fillColor: stil.renk, weight: 4, fillOpacity: .55, dashArray: '6 4' });
        } else if (aktifMi) {
            l.setStyle({ color: stil.renk, fillColor: stil.renk, weight: 4, fillOpacity: .55, dashArray: null });
        } else {
            l.setStyle({ color: stil.renk, fillColor: stil.renk, weight: 2.4, fillOpacity: .30, dashArray: null });
        }
    }

    // İlk yükleme
    haritayiYukle(mevcutFiltre);

    // === 3) Katman switch'leri ===
    function toggleKatman(input, katman) {
        if (input.checked) { if (!map.hasLayer(katman)) map.addLayer(katman); }
        else { if (map.hasLayer(katman)) map.removeLayer(katman); }
    }
    document.getElementById('hsh-lyr-parseller').addEventListener('change', e => toggleKatman(e.target, parselKatman));
    document.getElementById('hsh-lyr-etiket').addEventListener('change', e => toggleKatman(e.target, etiketKatman));
    document.getElementById('hsh-lyr-cluster').addEventListener('change', e => {
        if (e.target.checked) {
            // Parsel katmanını kümeleyerek göster: parselKatman'ı çıkar, işaretçileri clustera ekle
            parselKatman.eachLayer(l => {
                if (l.getBounds) {
                    const c = l.getBounds().getCenter();
                    const m = L.marker(c);
                    m._src = l;
                    m.on('click', () => secParsel(l, l._props));
                    clusterKatman.addLayer(m);
                }
            });
            map.removeLayer(parselKatman);
            map.addLayer(clusterKatman);
        } else {
            map.removeLayer(clusterKatman);
            clusterKatman.clearLayers();
            if (document.getElementById('hsh-lyr-parseller').checked) map.addLayer(parselKatman);
        }
    });
    document.getElementById('hsh-lyr-imar').addEventListener('change', e => toggleKatman(e.target, abbImar));
    document.getElementById('hsh-lyr-parselasyon').addEventListener('change', e => toggleKatman(e.target, abbParselasyon));

    // === 4) Altlık seçici ===
    document.querySelectorAll('#hsh-altlik-grup .hsh-radyo').forEach(el => {
        el.addEventListener('click', () => {
            const id = el.dataset.altlik;
            if (id === aktifAltlik) return;
            map.removeLayer(altliklar[aktifAltlik]);
            aktifAltlik = id;
            altliklar[id].addTo(map);
            document.querySelectorAll('#hsh-altlik-grup .hsh-radyo').forEach(x => x.classList.remove('is-aktif'));
            el.classList.add('is-aktif');
        });
    });

    // === 5) Toolbar butonları ===
    document.getElementById('hsh-katman-btn').addEventListener('click', () => {
        const p = document.getElementById('hsh-katman-panel');
        const gizli = p.hidden;
        p.hidden = !gizli;
        document.getElementById('hsh-katman-btn').setAttribute('aria-expanded', gizli ? 'true' : 'false');
    });
    document.getElementById('hsh-fit-btn').addEventListener('click', () => {
        if (allBounds) map.fitBounds(allBounds, { padding: [30, 30] });
    });
    document.querySelectorAll('[data-panel-kapat]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.panelKapat;
            document.getElementById(id).hidden = true;
            if (id === 'hsh-katman-panel') document.getElementById('hsh-katman-btn').setAttribute('aria-expanded', 'false');
            if (id === 'hsh-arama-panel') document.getElementById('hsh-arama-btn').setAttribute('aria-expanded', 'false');
        });
    });

    // Arama panel toggle
    document.getElementById('hsh-arama-btn').addEventListener('click', () => {
        const p = document.getElementById('hsh-arama-panel');
        const gizli = p.hidden;
        p.hidden = !gizli;
        document.getElementById('hsh-arama-btn').setAttribute('aria-expanded', gizli ? 'true' : 'false');
    });

    // Seçim modu toggle
    document.getElementById('hsh-secim-btn').addEventListener('click', () => {
        secimModu = !secimModu;
        const btn = document.getElementById('hsh-secim-btn');
        btn.setAttribute('aria-pressed', secimModu ? 'true' : 'false');
        document.getElementById('hsh-shell').classList.toggle('is-secim-modu', secimModu);
    });

    // Seçim temizle
    document.getElementById('hsh-secim-temizle').addEventListener('click', () => {
        const idler = Array.from(secili);
        secili.clear();
        idler.forEach(id => { const l = layerById.get(id); if (l) stiliniUygula(l); });
        seciliChipGuncelle();
    });

    // İndir menü toggle
    const indirBtn = document.getElementById('hsh-indir-btn');
    const indirMenu = document.getElementById('hsh-indir-menu');
    indirBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const gizli = indirMenu.hidden;
        indirMenu.hidden = !gizli;
        indirBtn.setAttribute('aria-expanded', gizli ? 'true' : 'false');
    });
    document.addEventListener('click', (e) => {
        if (!indirMenu.contains(e.target) && e.target !== indirBtn) {
            indirMenu.hidden = true;
            indirBtn.setAttribute('aria-expanded', 'false');
        }
    });
    document.querySelectorAll('.hsh-indir-item').forEach(item => {
        item.addEventListener('click', () => {
            const tip = item.dataset.indir;
            const url = tip === 'excel' ? API.excel : API.kml;
            const idler = Array.from(secili);
            const params = new URLSearchParams();
            if (idler.length > 0) params.set('ids', idler.join(','));
            const tam = url + (params.toString() ? '?' + params.toString() : '');
            window.location.href = tam;
            indirMenu.hidden = true;
            indirBtn.setAttribute('aria-expanded', 'false');
        });
    });

    // İlçe/Mahalle chain
    document.getElementById('hsh-f-il').addEventListener('change', async (e) => {
        const ilceSelect = document.getElementById('hsh-f-ilce');
        const mahSelect = document.getElementById('hsh-f-mahalle');
        ilceSelect.innerHTML = '<option value="">— Tümü —</option>';
        mahSelect.innerHTML = '<option value="">— Önce ilçe —</option>';
        mahSelect.disabled = true;
        if (!e.target.value) { ilceSelect.disabled = true; return; }
        ilceSelect.disabled = false;
        try {
            const r = await fetch(API.ilceler + '/' + e.target.value);
            const veri = await r.json();
            (veri || []).forEach(ilce => {
                const opt = new Option(ilce.ad, ilce.id);
                ilceSelect.appendChild(opt);
            });
        } catch(err) { console.error(err); }
    });
    document.getElementById('hsh-f-ilce').addEventListener('change', async (e) => {
        const mahSelect = document.getElementById('hsh-f-mahalle');
        mahSelect.innerHTML = '<option value="">— Tümü —</option>';
        if (!e.target.value) { mahSelect.disabled = true; return; }
        mahSelect.disabled = false;
        try {
            const r = await fetch(API.mahalleler + '/' + e.target.value);
            const veri = await r.json();
            (veri || []).forEach(m => mahSelect.appendChild(new Option(m.ad, m.id)));
        } catch(err) { console.error(err); }
    });

    // Filtre uygulama
    window.hshFiltreUygula = function (e) {
        e.preventDefault();
        const form = e.target;
        const fd = new FormData(form);
        mevcutFiltre = new URLSearchParams();
        for (const [k, v] of fd.entries()) {
            if (v && v !== '') mevcutFiltre.set(k, v);
        }
        haritayiYukle(mevcutFiltre);
        return false;
    };
    window.hshFiltreTemizle = function () {
        const form = document.getElementById('hsh-arama-form');
        form.reset();
        document.getElementById('hsh-f-ilce').innerHTML = '<option value="">— Önce il —</option>';
        document.getElementById('hsh-f-ilce').disabled = true;
        document.getElementById('hsh-f-mahalle').innerHTML = '<option value="">— Önce ilçe —</option>';
        document.getElementById('hsh-f-mahalle').disabled = true;
        mevcutFiltre = new URLSearchParams();
        haritayiYukle(mevcutFiltre);
    };

    function seciliChipGuncelle() {
        const chip = document.getElementById('hsh-secili-chip');
        const sayi = document.getElementById('hsh-secili-sayi');
        sayi.textContent = secili.size;
        chip.hidden = secili.size === 0;
    }

    // === 6) Panel sürükleme ===
    document.querySelectorAll('.hsh-panel-head[data-drag]').forEach(head => {
        let baslangicX = 0, baslangicY = 0, sX = 0, sY = 0;
        const panel = head.closest('.hsh-panel');
        head.addEventListener('mousedown', e => {
            if (e.target.closest('button')) return;
            baslangicX = e.clientX; baslangicY = e.clientY;
            const rect = panel.getBoundingClientRect();
            sX = rect.left; sY = rect.top;
            panel.style.right = 'auto'; panel.style.bottom = 'auto';
            panel.style.left = sX + 'px'; panel.style.top = sY + 'px';
            document.addEventListener('mousemove', sur);
            document.addEventListener('mouseup', bit, { once: true });
        });
        function sur(e) { panel.style.left = (sX + e.clientX - baslangicX) + 'px'; panel.style.top = (sY + e.clientY - baslangicY) + 'px'; }
        function bit() { document.removeEventListener('mousemove', sur); }
    });

    // === 7) Parsel seçimi + İşlemler paneli ===
    function secParsel(layer, props) {
        // Seçim modu ise: sadece seçim setine ekle/çıkar, işlemler panelini açma
        if (secimModu) {
            if (secili.has(props.id)) secili.delete(props.id);
            else secili.add(props.id);
            stiliniUygula(layer);
            seciliChipGuncelle();
            return;
        }

        const oncekiLayer = secilenLayer;
        secilenLayer = layer;
        secilenTasinmazId = props.id;
        if (oncekiLayer) stiliniUygula(oncekiLayer);
        stiliniUygula(layer);

        const panel = document.getElementById('hsh-islem-panel');
        panel.hidden = false;
        document.getElementById('hsh-islem-alt').textContent = `Ada ${props.ada ?? '—'} · Parsel ${props.parsel ?? '—'}`;

        // Yükleniyor göster
        ['ozet','encumen','basvuru'].forEach(t => {
            document.getElementById('hsh-tab-' + t).innerHTML = '<div class="hsh-yukleniyor">Yükleniyor</div>';
        });

        fetch(API.ozet + '/' + props.id, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(veri => {
                renderOzet(veri, props);
                renderEncumen(veri);
                renderBasvurular(veri);
            })
            .catch(e => {
                document.getElementById('hsh-tab-ozet').innerHTML = '<div class="hsh-flash is-hata">Yükleme başarısız: ' + e.message + '</div>';
            });
    }

    function renderOzet(veri, props) {
        const t = veri.tasinmaz;
        const durum = props.durum || 'yeni';
        const durumEtiket = DURUM_RENK[durum]?.etiket || 'Yeni başvuru';
        const alan = t.alan ? Number(t.alan).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' m²' : '—';

        document.getElementById('hsh-tab-ozet').innerHTML = `
            <div style="margin-bottom:14px;">
                <span class="hsh-durum-rozet ${durumSinifi(durum)}">${escapeHtml(durumEtiket)}</span>
            </div>
            <div class="hsh-ozet-grid">
                <div class="hsh-ozet-alan"><div class="lbl">Ada</div><div class="val">${escapeHtml(t.ada ?? '—')}</div></div>
                <div class="hsh-ozet-alan"><div class="lbl">Parsel</div><div class="val">${escapeHtml(t.parsel ?? '—')}</div></div>
                <div class="hsh-ozet-alan"><div class="lbl">İl</div><div class="val">${escapeHtml(t.il ?? '—')}</div></div>
                <div class="hsh-ozet-alan"><div class="lbl">İlçe</div><div class="val">${escapeHtml(t.ilce ?? '—')}</div></div>
                <div class="hsh-ozet-alan" style="grid-column:1/-1;"><div class="lbl">Mahalle</div><div class="val">${escapeHtml(t.mahalle ?? '—')}</div></div>
                <div class="hsh-ozet-alan"><div class="lbl">Alan</div><div class="val">${alan}</div></div>
                <div class="hsh-ozet-alan"><div class="lbl">Nitelik</div><div class="val">${escapeHtml(t.nitelik ?? '—')}</div></div>
                <div class="hsh-ozet-alan"><div class="lbl">Grup No</div><div class="val">${veri.grup_no ?? '—'}</div></div>
                <div class="hsh-ozet-alan"><div class="lbl">Başvuru Sayısı</div><div class="val">${veri.basvurular.length}</div></div>
                <div class="hsh-ozet-alan"><div class="lbl">Encümen Kararı</div><div class="val">${veri.encumenler.length}</div></div>
                <div class="hsh-ozet-alan"><div class="lbl">Satış Tebligatı</div><div class="val">${props.satis_tebligat_sayisi ?? 0}</div></div>
            </div>
            <div class="hsh-btn-row">
                ${veri.detay_url ? `<a class="hsh-btn" href="${veri.detay_url}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>Başvuru Detayına Git</a>` : ''}
                <button type="button" class="hsh-btn is-ghost" onclick="hshKmlIndir(${t.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0 0l-4-4m4 4l4-4"/></svg>KML</button>
            </div>
        `;
    }

    function renderEncumen(veri) {
        const grupNo = veri.grup_no;
        const izinDuzenle = veri.izinler.duzenle;
        const items = veri.encumenler.map((k, i) => {
            const gelen = k.gelen_tarih ? tr(k.gelen_tarih) : '—';
            const giden = k.giden_tarih ? tr(k.giden_tarih) : '—';
            const fiyat = k.birim_fiyat ? Number(k.birim_fiyat).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' ₺/m²' : '—';
            return `
                <div class="hsh-encumen-item" data-idx="${i}">
                    <div class="hsh-encumen-head" onclick="hshToggleEncumen(this)">
                        <div style="flex:1;min-width:0;">
                            <div class="num">Karar No: ${escapeHtml(k.karar_no ?? '—')}</div>
                            <div class="sub">Gelen: ${gelen} · Giden: ${giden}</div>
                        </div>
                        <span class="hsh-durum-rozet is-encumen" style="margin-right:6px;">${fiyat}</span>
                        <svg class="caret" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                    </div>
                    <div class="hsh-encumen-body">
                        <div class="hsh-ozet-grid">
                            <div><div class="lbl">Kayıt No</div><div class="val">${escapeHtml(k.kayit_no ?? '—')}</div></div>
                            <div><div class="lbl">Birim Fiyat</div><div class="val">${fiyat}</div></div>
                            <div><div class="lbl">Grup No</div><div class="val">${k.grup_no ?? '—'}</div></div>
                            <div><div class="lbl">Gelen Tarih</div><div class="val">${gelen}</div></div>
                            <div><div class="lbl">Giden Tarih</div><div class="val">${giden}</div></div>
                            <div style="grid-column:1/-1;"><div class="lbl">Açıklama</div><div class="val">${escapeHtml(k.aciklama ?? '—')}</div></div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        const formHtml = (izinDuzenle && grupNo) ? `
            <details class="hsh-form-toggle" ${veri.encumenler.length === 0 ? 'open' : ''}>
                <summary style="cursor:pointer;color:#dfe3ea;font-weight:600;font-size:.84rem;padding:8px 0;">➕ Yeni Encümen Kararı Ekle</summary>
                <form class="hsh-form" style="margin-top:10px;" onsubmit="return hshEncumenKaydet(event, ${grupNo})">
                    <div class="hsh-form-row">
                        <div class="hsh-form-satir"><label>Karar No</label><input type="text" name="karar_no" maxlength="100"></div>
                        <div class="hsh-form-satir"><label>Kayıt No</label><input type="text" name="kayit_no" maxlength="100"></div>
                    </div>
                    <div class="hsh-form-row">
                        <div class="hsh-form-satir"><label>Gelen Tarih</label><input type="date" name="gelen_tarih"></div>
                        <div class="hsh-form-satir"><label>Giden Tarih</label><input type="date" name="giden_tarih"></div>
                    </div>
                    <div class="hsh-form-satir"><label>Birim Fiyat (₺/m²)</label><input type="number" name="birim_fiyat" step="0.01" min="0"></div>
                    <div class="hsh-form-satir"><label>Açıklama</label><textarea name="aciklama" maxlength="2000"></textarea></div>
                    <div id="hsh-encumen-flash"></div>
                    <button type="submit" class="hsh-btn is-yesil"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Kararı Kaydet</button>
                </form>
            </details>
        ` : (grupNo ? '' : '<div class="hsh-flash is-hata" style="margin-top:12px;">Bu taşınmaz için başvuru grubu yok — önce başvuru oluşturun.</div>');

        document.getElementById('hsh-tab-encumen').innerHTML = `
            ${veri.encumenler.length === 0 ? '<div class="hsh-bos">Bu parsel için henüz encümen kararı yok.</div>' : `<div class="hsh-encumen-liste">${items}</div>`}
            ${formHtml}
        `;
    }

    function renderBasvurular(veri) {
        if (veri.basvurular.length === 0) {
            document.getElementById('hsh-tab-basvuru').innerHTML = '<div class="hsh-bos">Bu parsel için başvuru bulunmuyor.</div>';
            return;
        }
        const rows = veri.basvurular.map(b => `
            <div style="padding:10px 12px;border:1px solid rgba(255,255,255,.08);border-radius:8px;background:rgba(255,255,255,.02);margin-bottom:8px;">
                <div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline;">
                    <div style="color:#fff;font-weight:600;font-size:.86rem;">${escapeHtml(b.ad_soyad)}</div>
                    <div style="color:#8a91a1;font-size:.72rem;">${b.basvuru_tarihi ? tr(b.basvuru_tarihi) : '—'}</div>
                </div>
                <div style="color:#8a91a1;font-size:.74rem;margin-top:3px;">TC: ${escapeHtml(b.tc_kimlik)} · Durum: ${escapeHtml(b.durum ?? '—')}</div>
            </div>
        `).join('');
        document.getElementById('hsh-tab-basvuru').innerHTML = rows + (veri.detay_url ? `<a class="hsh-btn" style="width:100%;margin-top:6px;" href="${veri.detay_url}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>Grup Detayına Git</a>` : '');
    }

    // === 8) Sekme geçişi ===
    document.querySelectorAll('.hsh-tab').forEach(t => {
        t.addEventListener('click', () => {
            document.querySelectorAll('.hsh-tab').forEach(x => x.classList.remove('is-aktif'));
            document.querySelectorAll('.hsh-tab-panel').forEach(x => x.classList.remove('is-aktif'));
            t.classList.add('is-aktif');
            document.querySelector(`[data-tab-panel="${t.dataset.tab}"]`).classList.add('is-aktif');
        });
    });

    // === 9) Yardımcı fonksiyonlar (global — inline onclick'ler için) ===
    window.hshToggleEncumen = function (head) {
        head.closest('.hsh-encumen-item').classList.toggle('is-acik');
    };

    window.hshEncumenKaydet = function (e, grupNo) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('button[type=submit]');
        btn.disabled = true;
        const fd = new FormData(form);
        const flash = form.querySelector('#hsh-encumen-flash');

        fetch(API.encumenKaydet + '/' + grupNo + '/encumen', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': API.csrf, 'Accept': 'application/json' },
            body: fd,
        })
        .then(r => r.ok ? r.json() : r.json().then(x => Promise.reject(x)))
        .then(() => {
            flash.innerHTML = '<div class="hsh-flash is-ok">Karar kaydedildi.</div>';
            // Özet & encümen sekmelerini yeniden yükle
            if (secilenTasinmazId) {
                fetch(API.ozet + '/' + secilenTasinmazId).then(r => r.json()).then(veri => {
                    renderEncumen(veri);
                    // Parsel rengini de güncellemek için katmanı yeniden yükle olur — basit: sayfayı zorlamayalım, sadece meta güncelle
                });
            }
        })
        .catch(err => {
            flash.innerHTML = '<div class="hsh-flash is-hata">Hata: ' + (err.message ?? 'Kayıt başarısız') + '</div>';
        })
        .finally(() => { btn.disabled = false; });
        return false;
    };

    window.hshKmlIndir = function (tasinmazId) {
        if (!secilenLayer || !secilenLayer.toGeoJSON) return;
        const gj = secilenLayer.toGeoJSON();
        const p = secilenLayer._props || {};
        let coords = [];
        if (gj.geometry.type === 'Polygon') coords = gj.geometry.coordinates[0];
        else if (gj.geometry.type === 'Point') coords = [gj.geometry.coordinates];

        const kmlCoords = coords.map(c => `${c[0]},${c[1]},0`).join(' ');
        const kml = `<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2"><Document>
<name>Ada ${p.ada}/Parsel ${p.parsel}</name>
<Style id="s"><LineStyle><color>ffff00ff</color><width>3</width></LineStyle><PolyStyle><color>7dff8800</color></PolyStyle></Style>
<Placemark><name>Ada ${p.ada}/Parsel ${p.parsel}</name><styleUrl>#s</styleUrl>
<Polygon><outerBoundaryIs><LinearRing><coordinates>${kmlCoords}</coordinates></LinearRing></outerBoundaryIs></Polygon>
</Placemark></Document></kml>`;
        const blob = new Blob([kml], { type: 'application/vnd.google-earth.kml+xml' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = `Ada${p.ada}_Parsel${p.parsel}.kml`;
        document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
    };

    function tr(iso) { try { return new Date(iso).toLocaleDateString('tr-TR'); } catch(e) { return iso; } }
    function escapeHtml(s) {
        if (s === null || s === undefined) return '—';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }
})();
</script>
@endpush
