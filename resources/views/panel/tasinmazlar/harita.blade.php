@extends('layouts.panel')

@section('title', 'Taşınmaz Haritası')
@section('contentClass', 'is-flush')
@section('shellClass', 'is-map-full')

@push('head')
    <link rel="stylesheet" href="https://js.arcgis.com/4.30/esri/themes/light/main.css">
    {{-- Esri temasından SONRA gelmeli: kurumsal koyu popup kabuğu --}}
    <style>
        /* Esri kabuğunu kurumsal koyu palete çevir */
        .hrm-map .esri-view-surface { outline: none; }
        .hrm-cursor-help .esri-view-surface { cursor: help !important; }

        .hrm-shell .esri-popup__main-container {
            background: #0e1726;
            color: #dfe3ea;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            box-shadow: 0 12px 34px rgba(6, 11, 22, 0.45);
            overflow: hidden;
            min-width: 288px;
            max-width: 340px;
        }
        .hrm-shell .esri-popup__header {
            background: linear-gradient(180deg, #16223c 0%, #0e1726 100%);
            border-bottom: 1px solid rgba(255,255,255,0.07);
            padding: 2px 4px;
        }
        .hrm-shell .esri-popup__header-title {
            color: #fff;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.01em;
            padding: 10px 12px;
        }
        .hrm-shell .esri-popup__header-title:hover { background: rgba(255,255,255,0.05); }
        .hrm-shell .esri-popup__content { margin: 0; padding: 0; color: #dfe3ea; }

        /* 4.30 popup gövdesini Calcite'ın beyaz zeminlerinden arındır.
           .esri-features ve .esri-features__content-feature koyu kabuğun üstünü boyuyor. */
        .hrm-shell .esri-popup { color-scheme: dark; }
        .hrm-shell .esri-features,
        .hrm-shell .esri-features__container,
        .hrm-shell .esri-features__content-feature,
        .hrm-shell .esri-features__content-container,
        .hrm-shell .esri-feature,
        .hrm-shell .esri-feature__main-container,
        .hrm-shell .esri-feature__size-container,
        .hrm-shell .esri-feature-content {
            background: transparent !important;
            color: #dfe3ea !important;
        }
        /* 4.30'da başlık h2.esri-features__heading — varsayılanı koyu gri kalıyor */
        .hrm-shell .esri-features__container-header,
        .hrm-shell .esri-features__heading,
        .hrm-shell .esri-widget__heading,
        .hrm-shell .esri-popup__header-title {
            color: #fff !important;
            font-size: 0.82rem !important;
            font-weight: 700;
        }

        /* Varsayılan 340px sınırı içeriği erken kırpıyor */
        .hrm-shell .esri-popup__main-container { max-height: min(72vh, 560px) !important; }
        .hrm-shell .esri-features__content-feature {
            max-height: none !important;
            overflow-y: auto !important;
        }

        /* Calcite değişkenleri — gelecekteki iç bileşenler de koyu kalsın */
        .hrm-shell .esri-popup,
        .hrm-shell .esri-popup__main-container {
            --calcite-color-foreground-1: #0e1726;
            --calcite-color-foreground-2: #16223c;
            --calcite-color-foreground-3: #1e2c47;
            --calcite-color-text-1: #ffffff;
            --calcite-color-text-2: #dfe3ea;
            --calcite-color-text-3: #8a91a1;
            --calcite-color-border-1: rgba(255,255,255,0.10);
            --calcite-color-border-2: rgba(255,255,255,0.08);
            --calcite-color-border-3: rgba(255,255,255,0.06);
        }

        /* İçerik alanı kaydırma çubuğu */
        .hrm-shell .esri-popup__content::-webkit-scrollbar,
        .hrm-shell .esri-features__content-feature::-webkit-scrollbar { width: 8px; }
        .hrm-shell .esri-popup__content::-webkit-scrollbar-thumb,
        .hrm-shell .esri-features__content-feature::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.16);
            border-radius: 4px;
        }
        .hrm-shell .esri-popup__pointer-direction {
            background: #0e1726;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .hrm-shell .esri-popup__button,
        .hrm-shell .esri-popup__icon { color: #9aa3b2; }
        .hrm-shell .esri-popup__button:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .hrm-shell .esri-popup__footer,
        .hrm-shell .esri-popup__navigation {
            background: #0e1726;
            border-color: rgba(255,255,255,0.08);
        }

        /* Zoom (+/−) ve alttaki Google / Powered by Esri çubuğu gizlenir */
        .hrm-shell .esri-ui-zoom,
        .hrm-shell .esri-zoom,
        .hrm-shell .esri-attribution {
            display: none !important;
        }
    </style>
@endpush

@section('content')
@if (! empty($secilenIdler))
    <div class="hrm-filtre-bar" role="status">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54z"/></svg>
        <span><strong>{{ count($secilenIdler) }}</strong> taşınmaz haritada filtreli gösteriliyor.</span>
        <a href="{{ route('panel.tasinmazlar.harita') }}" class="hrm-filtre-temizle">Tümünü göster</a>
    </div>
@endif

<div class="hrm-shell" id="hrm-shell">
    <div class="hrm-toolbar" role="toolbar" aria-label="Harita araç çubuğu">
        <a class="hrm-tool" href="{{ route('panel.tasinmazlar.index') }}" title="Listeye dön">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4 h3 a2 2 0 0 1 2 2 v12 a2 2 0 0 1-2 2 h-3"/><path d="M10 16 l-4-4 4-4 M14 12 H6"/></svg>
            <span>Liste</span>
        </a>

        <button type="button" class="hrm-tool" id="hrm-katman-btn" aria-expanded="false" title="Katmanlar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 L2 8 l10 6 l10-6z"/><path d="M2 16 l10 6 l10-6"/><path d="M2 12 l10 6 l10-6"/></svg>
            <span>Katmanlar</span>
        </button>

        <button type="button" class="hrm-tool" id="hrm-bilgi-btn" aria-pressed="false" title="Haritaya tıklayarak parsel bilgisi al">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>
            <span>Bilgi Sorgu</span>
        </button>

        <button type="button" class="hrm-tool" id="hrm-adaparsel-btn" aria-expanded="false" title="Ada / Parsel ile TKGM'de sorgula">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg>
            <span>Ada/Parsel</span>
        </button>

        <button type="button" class="hrm-tool" id="hrm-arama-btn" aria-expanded="false" title="Sistemdeki taşınmazlar arasında ara">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <span>Arama</span>
        </button>

        <div class="hrm-float-panel" id="hrm-panel" aria-label="Katman paneli" hidden>
        <div class="hrm-panel-head" data-drag-handle>
            <span class="hrm-panel-tut" title="Taşımak için sürükleyin">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="6" r="1"/><circle cx="8" cy="12" r="1"/><circle cx="8" cy="18" r="1"/><circle cx="16" cy="6" r="1"/><circle cx="16" cy="12" r="1"/><circle cx="16" cy="18" r="1"/></svg>
            </span>
            <div class="hrm-panel-head-baslik">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 L2 8 l10 6 l10-6z"/><path d="M2 12 l10 6 l10-6"/></svg>
                <strong>Katmanlar</strong>
            </div>
            <button type="button" class="hrm-panel-kapat" id="hrm-panel-kapat" aria-label="Paneli kapat">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="hrm-katman-eylem-satir">
            <div class="hrm-katman-ara-wrap">
                <svg class="hrm-katman-ara-ikon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="search" id="hrm-katman-ara" class="hrm-katman-ara" placeholder="Katman ara..." autocomplete="off">
                <button type="button" class="hrm-katman-ara-temizle" id="hrm-katman-ara-temizle" aria-label="Aramayı temizle" hidden>
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <button type="button" class="hrm-katman-hepsi-kapa" id="hrm-katman-hepsi-kapa" title="Tüm veri katmanlarını kapat">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h16"/></svg>
                <span>Hepsi</span>
            </button>
        </div>

        <div class="hrm-panel-scroll">

        <details class="hrm-details" open>
            <summary class="hrm-summary">
                <svg class="hrm-summary-caret" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                <span>Altlık</span>
            </summary>
            <div class="hrm-details-icerik">
                <div class="hrt-layers-grid" id="hrm-altlik-grid"></div>
            </div>
        </details>

        <details class="hrm-details hrm-grup" open data-grup="sistem">
            <summary class="hrm-grup-summary">
                <svg class="hrm-summary-caret" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                <span class="hrm-grup-ad">Sistem</span>
                <span class="hrm-grup-sayac" data-grup-sayac="sistem" title="Aktif / Toplam">0/0</span>
                <label class="hrm-switch hrm-switch-mini hrm-grup-master-wrap" title="Grubu aç/kapat">
                    <input type="checkbox" class="hrm-grup-master" data-grup="sistem">
                    <span class="hrm-switch-slider"></span>
                </label>
            </summary>
            <div class="hrm-details-icerik" data-grup-icerik="sistem">

            <div class="hrm-katman-kart" data-katman-kart="parsel" data-grup="sistem" data-ad="taşınmaz parselleri sistem parsel">
                <div class="hrm-katman-ust">
                    <span class="hrm-katman-ikon" style="background:#f59e0b;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8 L12 3 L20 8 L18 20 L6 20 z"/></svg>
                    </span>
                    <div class="hrm-katman-meta">
                        <span class="hrm-katman-ad">Taşınmaz Parselleri</span>
                        <span class="hrm-katman-alt">Sistemdeki tüm parsel geometrileri</span>
                    </div>
                    <label class="hrm-switch" title="Katmanı aç/kapat">
                        <input type="checkbox" id="hrm-ov-parsel" checked>
                        <span class="hrm-switch-slider"></span>
                    </label>
                </div>
                <div class="hrm-katman-alt-satir">
                    <span class="hrm-opaklik-label">Opaklık</span>
                    <input type="range" id="hrm-ov-parsel-op" min="10" max="100" value="70" class="hrm-range">
                    <span class="hrm-opaklik-deger" data-hedef="hrm-ov-parsel-op">70%</span>
                    <button type="button" class="hrm-opaklik-sifirla" data-varsayilan="70" data-hedef="hrm-ov-parsel-op" title="Varsayılana döndür">↺</button>
                </div>
            </div>

            </div>
        </details>

        <details class="hrm-details hrm-grup" open data-grup="abb-imar">
            <summary class="hrm-grup-summary">
                <svg class="hrm-summary-caret" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                <span class="hrm-grup-ad">Ankara BB · İmar & Kadastro</span>
                <span class="hrm-grup-sayac" data-grup-sayac="abb-imar" title="Aktif / Toplam">0/0</span>
                <label class="hrm-switch hrm-switch-mini hrm-grup-master-wrap" title="Grubu aç/kapat">
                    <input type="checkbox" class="hrm-grup-master" data-grup="abb-imar">
                    <span class="hrm-switch-slider"></span>
                </label>
            </summary>
            <div class="hrm-details-icerik" data-grup-icerik="abb-imar">

            <div class="hrm-katman-kart" data-katman-kart="imar" data-grup="abb-imar" data-ad="imar planı uip ankara bb uipsade">
                <div class="hrm-katman-ust">
                    <span class="hrm-katman-ikon" style="background:#ec4899;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12 h4 v-4 h4 v4 h4 v-4 h4 v4 h2 M3 12 v6 h18 v-6"/></svg>
                    </span>
                    <div class="hrm-katman-meta">
                        <span class="hrm-katman-ad">İmar Planı (UIP)</span>
                        <span class="hrm-katman-alt">Ankara BB · uipSade MapServer</span>
                    </div>
                    <label class="hrm-switch" title="Katmanı aç/kapat">
                        <input type="checkbox" id="hrm-ov-imar">
                        <span class="hrm-switch-slider"></span>
                    </label>
                </div>
                <div class="hrm-katman-alt-satir">
                    <span class="hrm-opaklik-label">Opaklık</span>
                    <input type="range" id="hrm-ov-imar-op" min="10" max="100" value="75" class="hrm-range">
                    <span class="hrm-opaklik-deger" data-hedef="hrm-ov-imar-op">75%</span>
                    <button type="button" class="hrm-opaklik-sifirla" data-varsayilan="75" data-hedef="hrm-ov-imar-op" title="Varsayılana döndür">↺</button>
                </div>
            </div>

            <div class="hrm-katman-kart" data-katman-kart="parselasyon" data-grup="abb-imar" data-ad="parselasyon kadastro parselaktif">
                <div class="hrm-katman-ust">
                    <span class="hrm-katman-ikon" style="background:#8b5cf6;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16 M4 12h16 M4 18h16 M9 3v18 M15 3v18"/></svg>
                    </span>
                    <div class="hrm-katman-meta">
                        <span class="hrm-katman-ad">Parselasyon</span>
                        <span class="hrm-katman-alt">Ankara BB · parselAktif (aktif kadastro)</span>
                    </div>
                    <label class="hrm-switch" title="Katmanı aç/kapat">
                        <input type="checkbox" id="hrm-ov-parselasyon">
                        <span class="hrm-switch-slider"></span>
                    </label>
                </div>
                <div class="hrm-katman-alt-satir">
                    <span class="hrm-opaklik-label">Opaklık</span>
                    <input type="range" id="hrm-ov-parselasyon-op" min="10" max="100" value="80" class="hrm-range">
                    <span class="hrm-opaklik-deger" data-hedef="hrm-ov-parselasyon-op">80%</span>
                    <button type="button" class="hrm-opaklik-sifirla" data-varsayilan="80" data-hedef="hrm-ov-parselasyon-op" title="Varsayılana döndür">↺</button>
                </div>
            </div>

            <div class="hrm-katman-kart" data-katman-kart="belediye" data-grup="abb-imar" data-ad="belediye taşınmazları hisseli tam">
                <div class="hrm-katman-ust">
                    <span class="hrm-katman-ikon" style="background:#16a34a;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21 h18 M5 21 V9 l7-5 7 5 v12 M9 21 v-6 h6 v6"/></svg>
                    </span>
                    <div class="hrm-katman-meta">
                        <span class="hrm-katman-ad">Belediye Taşınmazları</span>
                        <span class="hrm-katman-alt">Tam koyu · Hisseli açık</span>
                    </div>
                    <label class="hrm-switch" title="Katmanı aç/kapat">
                        <input type="checkbox" id="hrm-ov-belediye">
                        <span class="hrm-switch-slider"></span>
                    </label>
                </div>
                <div class="hrm-katman-alt-satir">
                    <span class="hrm-opaklik-label">Opaklık</span>
                    <input type="range" id="hrm-ov-belediye-op" min="10" max="100" value="80" class="hrm-range">
                    <span class="hrm-opaklik-deger" data-hedef="hrm-ov-belediye-op">80%</span>
                    <button type="button" class="hrm-opaklik-sifirla" data-varsayilan="80" data-hedef="hrm-ov-belediye-op" title="Varsayılana döndür">↺</button>
                </div>
                <div class="hrm-katman-lejant" aria-label="Hisse durumu lejantı">
                    <span class="hrm-lejant-oge">
                        <span class="hrm-lejant-swatch" style="background:rgba(140,0,98,.72);border-color:#5c0040;"></span>
                        Tam
                    </span>
                    <span class="hrm-lejant-oge">
                        <span class="hrm-lejant-swatch" style="background:rgba(255,170,224,.45);border-color:#e85aba;"></span>
                        Hisseli
                    </span>
                    <span class="hrm-lejant-oge">
                        <span class="hrm-lejant-swatch" style="background:rgba(148,163,184,.40);border-color:#64748b;"></span>
                        Hatalı
                    </span>
                </div>
            </div>

            </div>
        </details>

        <details class="hrm-details hrm-grup" open data-grup="abb-detay">
            <summary class="hrm-grup-summary">
                <svg class="hrm-summary-caret" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                <span class="hrm-grup-ad">Ankara BB · Sınır & Detay</span>
                <span class="hrm-grup-sayac" data-grup-sayac="abb-detay" title="Aktif / Toplam">0/0</span>
                <label class="hrm-switch hrm-switch-mini hrm-grup-master-wrap" title="Grubu aç/kapat">
                    <input type="checkbox" class="hrm-grup-master" data-grup="abb-detay">
                    <span class="hrm-switch-slider"></span>
                </label>
            </summary>
            <div class="hrm-details-icerik" data-grup-icerik="abb-detay">

            <div class="hrm-katman-kart" data-katman-kart="abb-ilce" data-grup="abb-detay" data-ad="ilçe sınırı ankara">
                <div class="hrm-katman-ust">
                    <span class="hrm-katman-ikon" style="background:#0ea5e9;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h6v6H4z M14 4h6v6h-6z M4 14h6v6H4z M14 14h6v6h-6z"/></svg>
                    </span>
                    <div class="hrm-katman-meta">
                        <span class="hrm-katman-ad">İlçe Sınırı</span>
                        <span class="hrm-katman-alt">Ankara BB · abbcbs proxy (layer 4)</span>
                    </div>
                    <label class="hrm-switch" title="Katmanı aç/kapat">
                        <input type="checkbox" id="hrm-ov-abb-ilce">
                        <span class="hrm-switch-slider"></span>
                    </label>
                </div>
                <div class="hrm-katman-alt-satir">
                    <span class="hrm-opaklik-label">Opaklık</span>
                    <input type="range" id="hrm-ov-abb-ilce-op" min="10" max="100" value="80" class="hrm-range">
                    <span class="hrm-opaklik-deger" data-hedef="hrm-ov-abb-ilce-op">80%</span>
                    <button type="button" class="hrm-opaklik-sifirla" data-varsayilan="80" data-hedef="hrm-ov-abb-ilce-op" title="Varsayılana döndür">↺</button>
                </div>
            </div>

            <div class="hrm-katman-kart" data-katman-kart="abb-mahalle" data-grup="abb-detay" data-ad="mahalle sınırı ankara">
                <div class="hrm-katman-ust">
                    <span class="hrm-katman-ikon" style="background:#14b8a6;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12 L12 3 L21 12 M5 10 v10 h14 V10"/></svg>
                    </span>
                    <div class="hrm-katman-meta">
                        <span class="hrm-katman-ad">Mahalle Sınırı</span>
                        <span class="hrm-katman-alt">Ankara BB · abbcbs proxy (layer 3)</span>
                    </div>
                    <label class="hrm-switch" title="Katmanı aç/kapat">
                        <input type="checkbox" id="hrm-ov-abb-mahalle">
                        <span class="hrm-switch-slider"></span>
                    </label>
                </div>
                <div class="hrm-katman-alt-satir">
                    <span class="hrm-opaklik-label">Opaklık</span>
                    <input type="range" id="hrm-ov-abb-mahalle-op" min="10" max="100" value="80" class="hrm-range">
                    <span class="hrm-opaklik-deger" data-hedef="hrm-ov-abb-mahalle-op">80%</span>
                    <button type="button" class="hrm-opaklik-sifirla" data-varsayilan="80" data-hedef="hrm-ov-abb-mahalle-op" title="Varsayılana döndür">↺</button>
                </div>
            </div>

            <div class="hrm-katman-kart" data-katman-kart="abb-yapi" data-grup="abb-detay" data-ad="yapı bina ankara">
                <div class="hrm-katman-ust">
                    <span class="hrm-katman-ikon" style="background:#f97316;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21 V7 h6 V3 h10 v18 M9 11h2 M13 11h2 M9 15h2 M13 15h2 M9 19h2 M13 19h2"/></svg>
                    </span>
                    <div class="hrm-katman-meta">
                        <span class="hrm-katman-ad">Yapı</span>
                        <span class="hrm-katman-alt">Ankara BB · abbcbs proxy (layer 2)</span>
                    </div>
                    <label class="hrm-switch" title="Katmanı aç/kapat">
                        <input type="checkbox" id="hrm-ov-abb-yapi">
                        <span class="hrm-switch-slider"></span>
                    </label>
                </div>
                <div class="hrm-katman-alt-satir">
                    <span class="hrm-opaklik-label">Opaklık</span>
                    <input type="range" id="hrm-ov-abb-yapi-op" min="10" max="100" value="85" class="hrm-range">
                    <span class="hrm-opaklik-deger" data-hedef="hrm-ov-abb-yapi-op">85%</span>
                    <button type="button" class="hrm-opaklik-sifirla" data-varsayilan="85" data-hedef="hrm-ov-abb-yapi-op" title="Varsayılana döndür">↺</button>
                </div>
            </div>

            <div class="hrm-katman-kart" data-katman-kart="abb-numarataj" data-grup="abb-detay" data-ad="numarataj adres kapı ankara">
                <div class="hrm-katman-ust">
                    <span class="hrm-katman-ikon" style="background:#ef4444;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22 s-7-7-7-13 a7 7 0 0 1 14 0 c0 6-7 13-7 13z"/><circle cx="12" cy="9" r="2.6"/></svg>
                    </span>
                    <div class="hrm-katman-meta">
                        <span class="hrm-katman-ad">Numarataj</span>
                        <span class="hrm-katman-alt">Ankara BB · abbcbs proxy (layer 0)</span>
                    </div>
                    <label class="hrm-switch" title="Katmanı aç/kapat">
                        <input type="checkbox" id="hrm-ov-abb-numarataj">
                        <span class="hrm-switch-slider"></span>
                    </label>
                </div>
                <div class="hrm-katman-alt-satir">
                    <span class="hrm-opaklik-label">Opaklık</span>
                    <input type="range" id="hrm-ov-abb-numarataj-op" min="10" max="100" value="90" class="hrm-range">
                    <span class="hrm-opaklik-deger" data-hedef="hrm-ov-abb-numarataj-op">90%</span>
                    <button type="button" class="hrm-opaklik-sifirla" data-varsayilan="90" data-hedef="hrm-ov-abb-numarataj-op" title="Varsayılana döndür">↺</button>
                </div>
            </div>

            </div>
        </details>

        <div class="hrm-katman-bos" id="hrm-katman-bos" hidden>Aramanıza uyan katman bulunamadı.</div>
        </div>

        <div class="hrm-panel-alt">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>
            Parsel tıklandığında e-imar bilgileri otomatik sorgulanır.
        </div>
        </div>

        <button type="button" class="hrm-tool" id="hrm-fit-btn" title="Tüm parsellere sığdır">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
            <span>Sığdır</span>
        </button>

        <div class="hrm-toolbar-meta">
            <span class="hrm-chip">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8 L12 3 L20 8 L18 20 L6 20 z"/></svg>
                <strong id="hrm-sayi">0</strong>
                <span>parsel</span>
            </span>
        </div>
    </div>

    {{-- Ada / Parsel Sorgu paneli --}}
    <div class="hrm-float-panel" id="hrm-adaparsel-panel" hidden>
        <div class="hrm-panel-head" data-drag-handle>
            <span class="hrm-panel-tut" title="Taşımak için sürükleyin">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="6" r="1"/><circle cx="8" cy="12" r="1"/><circle cx="8" cy="18" r="1"/><circle cx="16" cy="6" r="1"/><circle cx="16" cy="12" r="1"/><circle cx="16" cy="18" r="1"/></svg>
            </span>
            <div class="hrm-panel-head-baslik">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg>
                <strong>Ada / Parsel Sorgu</strong>
            </div>
            <button type="button" class="hrm-panel-kapat" data-kapat="hrm-adaparsel-panel" aria-label="Kapat">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="hrm-panel-icerik">
            <div class="hrm-form-satir">
                <label>İl</label>
                <select id="hrm-ap-il" class="hrm-form-select">
                    <option value="">— İl seçin —</option>
                    @foreach ($iller as $il)
                        <option value="{{ $il->id }}">{{ $il->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="hrm-form-satir">
                <label>İlçe</label>
                <select id="hrm-ap-ilce" class="hrm-form-select" disabled>
                    <option value="">— Önce il —</option>
                </select>
            </div>
            <div class="hrm-form-satir">
                <label>Mahalle</label>
                <select id="hrm-ap-mahalle" class="hrm-form-select" disabled>
                    <option value="">— Önce ilçe —</option>
                </select>
            </div>
            <div class="hrm-form-2li">
                <div class="hrm-form-satir">
                    <label>Ada</label>
                    <input type="text" id="hrm-ap-ada" class="hrm-form-input" placeholder="123" autocomplete="off">
                </div>
                <div class="hrm-form-satir">
                    <label>Parsel</label>
                    <input type="text" id="hrm-ap-parsel" class="hrm-form-input" placeholder="45" autocomplete="off">
                </div>
            </div>
            <button type="button" class="hrm-form-btn" id="hrm-ap-sorgula" disabled>Sorgula</button>
            <div class="hrm-form-mesaj" id="hrm-ap-mesaj" hidden></div>
        </div>
    </div>

    {{-- Arama paneli (sistemdeki tasinmazlarda) --}}
    <div class="hrm-float-panel hrm-panel-wide" id="hrm-arama-panel" hidden>
        <div class="hrm-panel-head" data-drag-handle>
            <span class="hrm-panel-tut" title="Taşımak için sürükleyin">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="6" r="1"/><circle cx="8" cy="12" r="1"/><circle cx="8" cy="18" r="1"/><circle cx="16" cy="6" r="1"/><circle cx="16" cy="12" r="1"/><circle cx="16" cy="18" r="1"/></svg>
            </span>
            <div class="hrm-panel-head-baslik">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
                <strong>Sistemde Arama</strong>
            </div>
            <button type="button" class="hrm-panel-kapat" data-kapat="hrm-arama-panel" aria-label="Kapat">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="hrm-panel-icerik">
            <div class="hrm-form-satir">
                <label>Serbest arama</label>
                <input type="text" id="hrm-arama-q" class="hrm-form-input" placeholder="Ada / parsel / nitelik / kullanım ara..." autocomplete="off">
            </div>
            <div class="hrm-form-satir">
                <label>İl</label>
                <select id="hrm-arama-il" class="hrm-form-select">
                    <option value="">— Tümü —</option>
                    @foreach ($iller as $il)
                        <option value="{{ $il->id }}">{{ $il->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="hrm-form-satir">
                <label>İlçe</label>
                <select id="hrm-arama-ilce" class="hrm-form-select" disabled>
                    <option value="">— Tümü —</option>
                </select>
            </div>
            <div class="hrm-form-satir">
                <label>Mahalle</label>
                <select id="hrm-arama-mahalle" class="hrm-form-select" disabled>
                    <option value="">— Tümü —</option>
                </select>
            </div>
            <div class="hrm-form-2li">
                <div class="hrm-form-satir">
                    <label>Ada</label>
                    <input type="text" id="hrm-arama-ada" class="hrm-form-input" placeholder="123" autocomplete="off">
                </div>
                <div class="hrm-form-satir">
                    <label>Parsel</label>
                    <input type="text" id="hrm-arama-parsel" class="hrm-form-input" placeholder="45" autocomplete="off">
                </div>
            </div>
            <button type="button" class="hrm-form-btn" id="hrm-arama-sorgula">Ara</button>
            <div class="hrm-form-mesaj" id="hrm-arama-mesaj" hidden></div>

            <div class="hrm-arama-liste" id="hrm-arama-liste" hidden>
                <div class="hrm-arama-basli">
                    <span>Sonuç: <strong id="hrm-arama-sayi">0</strong></span>
                </div>
                <div class="hrm-arama-satirlar" id="hrm-arama-satirlar"></div>
            </div>
        </div>
    </div>

    <div id="hrm-map" class="hrm-map"></div>
</div>
@endsection


@push('scripts')
<script src="https://js.arcgis.com/4.30/"></script>
<script>
    @php
        $geojsonUrl = route('panel.ajax.tasinmaz-geojson');
        if (! empty($secilenIdler)) {
            $geojsonUrl .= '?ids='.implode(',', $secilenIdler);
        }
    @endphp
    window.ETYS_HARITA = {
        geojson:       @json($geojsonUrl),
        ara:           @json(route('panel.ajax.tasinmaz-ara')),
        eimar:         @json(route('panel.ajax.eimar-identify')),
        abbProxy:      @json(route('panel.ajax.abb-proxy')),
        ilceler:       @json(url('/panel/ajax/ilceler')),
        mahalleler:    @json(url('/panel/ajax/mahalleler')),
        tkgmNokta:     @json(url('/panel/ajax/tkgm-parsel')),
        tkgmAdaParsel: @json(url('/panel/ajax/tkgm-parsel-adaparsel')),
        secilenSayi:   @json(is_array($secilenIdler) ? count($secilenIdler) : null),
    };
</script>
<script src="{{ asset('js/harita-esri.js') }}?v=15"></script>
@endpush
