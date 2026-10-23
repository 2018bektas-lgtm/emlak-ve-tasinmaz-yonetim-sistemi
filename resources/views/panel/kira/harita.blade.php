@extends('layouts.panel')

@section('title', 'Kira Haritası')
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
    <link rel="stylesheet" href="{{ asset('css/tasinmaz-harita.css') }}?v=9">
@endpush

@php
    // İmar durumu katman rengi id'den türetilir: tanım eklense de mevcut
    // katmanların rengi kaymaz, altın oran adımı tonları birbirinden ayırır.
    $imarRenk = function (int $id): string {
        return 'hsl('.((int) fmod($id * 137.508, 360)).', 65%, 52%)';
    };
@endphp

@section('content')

<div class="hrm-shell" id="hrm-shell">
    <div class="hrm-toolbar" role="toolbar" aria-label="Harita araç çubuğu">
        <a class="hrm-tool" href="{{ route('panel.kira.index') }}" title="Kira listesine dön">
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

        <button type="button" class="hrm-tool" id="hrm-arama-btn" aria-expanded="false" title="Kira dosyalarında ara">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <span>Arama</span>
        </button>

        <button type="button" class="hrm-tool" id="hrm-kr-secim-btn" aria-expanded="false" title="Haritadan kayıt seçme araçları">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h5M4 4v5M20 4h-5M20 4v5M4 20h5M4 20v-5M20 20h-5M20 20v-5"/><circle cx="12" cy="12" r="2.5"/></svg>
            <span>Seçim</span>
        </button>

        @can('kira.olustur')
            <button type="button" class="hrm-tool" id="hrm-kr-kayit-btn" aria-expanded="false" title="Haritada kayıt ekle / sınır güncelle / sınır sil">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l5-9 5 5 4-7 4 11z"/><path d="M17 3v6M14 6h6"/></svg>
                <span>Kayıt</span>
            </button>
        @endcan

        <button type="button" class="hrm-tool" id="hrm-fit-btn" title="Tüm parsellere sığdır">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
            <span>Sığdır</span>
        </button>

        <div class="hrm-toolbar-meta">
            <span class="hrm-chip" id="hrm-kr-filtre-rozet" hidden>Süzgeç açık</span>
            <span class="hrm-chip">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8 L12 3 L20 8 L18 20 L6 20 z"/></svg>
                <strong id="hrm-sayi">0</strong>
                <span>kira dosyası</span>
            </span>
        </div>
    </div>

    {{-- Katmanlar paneli — araç çubuğunun dışında (çubuk cam/backdrop-filter) --}}
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
                    <span class="hrm-katman-ad">Kira Dosyaları</span>
                    <span class="hrm-katman-alt">Kiraya verilen parseller</span>
                </div>
                <label class="hrm-switch" title="Katmanı aç/kapat">
                    <input type="checkbox" id="hrm-ov-parsel" checked>
                    <span class="hrm-switch-slider"></span>
                </label>
            </div>
            <div class="hrm-katman-alt-satir">
                <span class="hrm-opaklik-label">Opaklık</span>
                <input type="range" id="hrm-ov-parsel-op" min="10" max="100" value="100" class="hrm-range">
                <span class="hrm-opaklik-deger" data-hedef="hrm-ov-parsel-op">100%</span>
                <button type="button" class="hrm-opaklik-sifirla" data-varsayilan="100" data-hedef="hrm-ov-parsel-op" title="Varsayılana döndür">↺</button>
            </div>
            <div class="hrm-katman-alt-satir">
                <span class="hrm-opaklik-label">Renk</span>
                <select id="hrm-deg-renk" class="hrm-form-select is-mini">
                    <option value="durum" selected>Dolu / Boş</option>
                    <option value="imar">İmar durumu</option>
                    <option value="bitis">Sözleşme bitişi</option>
                </select>
            </div>
        </div>

        @if ($imarDurumlari->isNotEmpty())
        <div class="hrm-katman-kart" data-katman-kart="imar-durumu" data-grup="sistem" data-ad="imar durumu katman süzgeç">
            <div class="hrm-katman-ust">
                <span class="hrm-katman-ikon" style="background:#14b8a6;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v18H3z"/><path d="M3 9h18M9 3v18"/></svg>
                </span>
                <div class="hrm-katman-meta">
                    <span class="hrm-katman-ad">İmar Durumu</span>
                    <span class="hrm-katman-alt">Kira dosyalarını imar durumuna göre süzer</span>
                </div>
                <span class="hrm-imar-kisa">
                    <button type="button" data-imar-hepsi="1">Tümü</button>
                    <button type="button" data-imar-hepsi="0">Hiçbiri</button>
                </span>
            </div>
            <div class="hrm-imar-liste">
                @foreach ($imarDurumlari as $id)
                    <label class="hrm-imar-oge">
                        <input type="checkbox" data-imar="{{ $id->id }}" checked>
                        <span class="hrm-imar-nokta" style="background:{{ $imarRenk($id->id) }}"></span>
                        <span class="hrm-imar-ad">{{ $id->ad }}</span>
                    </label>
                @endforeach
                <label class="hrm-imar-oge">
                    <input type="checkbox" data-imar="yok" checked>
                    <span class="hrm-imar-nokta" style="background:#94a3b8"></span>
                    <span class="hrm-imar-ad">Belirtilmemiş</span>
                </label>
            </div>
        </div>
        @endif

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

        <div class="hrm-katman-kart" data-katman-kart="imar" data-grup="abb-imar" data-ad="imar planı uip ankara bb baskentcbs uip_esri3">
            <div class="hrm-katman-ust">
                <span class="hrm-katman-ikon" style="background:#ec4899;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12 h4 v-4 h4 v4 h4 v-4 h4 v4 h2 M3 12 v6 h18 v-6"/></svg>
                </span>
                <div class="hrm-katman-meta">
                    <span class="hrm-katman-ad">İmar Planı (UIP)</span>
                    <span class="hrm-katman-alt">Ankara BB · baskentcbs uip_esri3 · 15+ zoom</span>
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

        <div class="hrm-katman-kart" data-katman-kart="parselasyon" data-grup="abb-imar" data-ad="parselasyon kadastro parselaktif baskentcbs cached">
            <div class="hrm-katman-ust">
                <span class="hrm-katman-ikon" style="background:#8b5cf6;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16 M4 12h16 M4 18h16 M9 3v18 M15 3v18"/></svg>
                </span>
                <div class="hrm-katman-meta">
                    <span class="hrm-katman-ad">Parselasyon</span>
                    <span class="hrm-katman-alt">Ankara BB · ParselAktifCached · 16+ zoom</span>
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
                    <span class="hrm-katman-alt">Ankara BB · tasinmazservice · Tam / Hisseli</span>
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
                    <span class="hrm-katman-alt">Ankara BB · ortak/adres · İlçe</span>
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
                    <span class="hrm-katman-alt">Ankara BB · ortak/adres · Mahalle</span>
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
                    <span class="hrm-katman-alt">Ankara BB · ortak/adres · Yapı · 17+ zoom</span>
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
                    <span class="hrm-katman-alt">Ankara BB · ortak/adres · Numarataj · 19+ zoom</span>
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

    {{-- Kira sorgusu — kira listesindeki süzgeçlerin aynısı --}}
    <div class="hrm-float-panel hrm-panel-wide hrm-kr-sorgu-panel" id="hrm-arama-panel" hidden>
        <div class="hrm-panel-head" data-drag-handle>
            <span class="hrm-panel-tut" title="Taşımak için sürükleyin">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="6" r="1"/><circle cx="8" cy="12" r="1"/><circle cx="8" cy="18" r="1"/><circle cx="16" cy="6" r="1"/><circle cx="16" cy="12" r="1"/><circle cx="16" cy="18" r="1"/></svg>
            </span>
            <div class="hrm-panel-head-baslik">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
                <strong>Kira Sorgusu</strong>
            </div>
            <button type="button" class="hrm-panel-kapat" data-kapat="hrm-arama-panel" aria-label="Kapat">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="hrm-panel-icerik">
            <form id="hrm-kr-sorgu" class="hrm-kr-sorgu">
                <div class="hrm-kr-dolu" role="radiogroup" aria-label="Doluluk durumu">
                    <label><input type="radio" name="dolu_bos" value="" checked><span>Tümü</span></label>
                    <label><input type="radio" name="dolu_bos" value="dolu"><span>Dolu</span></label>
                    <label><input type="radio" name="dolu_bos" value="bos"><span>Boş</span></label>
                </div>
                <div class="hrm-form-satir">
                    <label for="hrm-kr-q">Serbest arama</label>
                    <input type="search" id="hrm-kr-q" name="q" class="hrm-form-input" placeholder="Dosya no, kiracı, işyeri, ada/parsel, ilçe…" autocomplete="off">
                </div>
                <button type="button" class="hrm-form-btn is-ghost" id="hrm-kr-detay-ac" aria-expanded="false" aria-controls="hrm-kr-detay">Detaylı sorgu</button>
                <div id="hrm-kr-detay" hidden>
                    <div class="hrm-form-satir">
                        <label for="hrm-kr-dosya">Dosya No</label>
                        <input type="text" id="hrm-kr-dosya" name="dosya_no" class="hrm-form-input" placeholder="Eski dosya no dahil" autocomplete="off">
                    </div>
                    <div class="hrm-form-satir">
                        <label for="hrm-kr-kiraci">Kiracı</label>
                        <input type="text" id="hrm-kr-kiraci" name="kiraci" class="hrm-form-input" placeholder="Ad/unvan ya da TC/vergi no" autocomplete="off">
                    </div>
                    <div class="hrm-form-satir">
                        <label for="hrm-kr-isyeri">İşyeri Adı</label>
                        <input type="text" id="hrm-kr-isyeri" name="isyeri" class="hrm-form-input" autocomplete="off">
                    </div>
                    <div class="hrm-form-satir">
                        <label for="hrm-kr-iskol">İş Kolu</label>
                        <select id="hrm-kr-iskol" name="is_kolu_id" class="hrm-form-select">
                            <option value="">Tümü</option>
                            @foreach ($isKollari as $i)
                                <option value="{{ $i->id }}">{{ $i->ad }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="hrm-form-2li">
                        <div class="hrm-form-satir">
                            <label for="hrm-kr-ihale">İhale Durumu</label>
                            <select id="hrm-kr-ihale" name="ihale_turu" class="hrm-form-select">
                                <option value="">Tümü</option>
                                <option value="ihaleli">İhaleli</option>
                                <option value="ihalesiz">İhalesiz</option>
                            </select>
                        </div>
                        <div class="hrm-form-satir">
                            <label for="hrm-kr-durum">Sözleşme</label>
                            <select id="hrm-kr-durum" name="durum" class="hrm-form-select">
                                <option value="">Tümü</option>
                                <option value="aktif">Yürürlükte</option>
                                <option value="pasif">Yürürlükte değil</option>
                                <option value="dolmus">Süresi dolmuş</option>
                            </select>
                        </div>
                    </div>
                    <div class="hrm-form-satir">
                        <label for="hrm-kr-ilce">İlçe</label>
                        <select id="hrm-kr-ilce" name="ilce_id" class="hrm-form-select">
                            <option value="">Tümü</option>
                            @foreach ($ilceler as $i)
                                <option value="{{ $i->id }}">{{ $i->ad }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="hrm-form-satir">
                        <label for="hrm-kr-mahalle">Mahalle</label>
                        <select id="hrm-kr-mahalle" name="mahalle_id" class="hrm-form-select" disabled>
                            <option value="">Önce ilçe seçin</option>
                        </select>
                    </div>
                    <div class="hrm-form-2li">
                        <div class="hrm-form-satir">
                            <label for="hrm-kr-ada">Ada</label>
                            <input type="text" id="hrm-kr-ada" name="ada" class="hrm-form-input" placeholder="Ada" autocomplete="off">
                        </div>
                        <div class="hrm-form-satir">
                            <label for="hrm-kr-parsel">Parsel</label>
                            <input type="text" id="hrm-kr-parsel" name="parsel" class="hrm-form-input" placeholder="Parsel" autocomplete="off">
                        </div>
                    </div>
                    <div class="hrm-form-satir">
                        <label for="hrm-kr-hukuki">Hukuki Durum</label>
                        <select id="hrm-kr-hukuki" name="hukuki_durum_id" class="hrm-form-select">
                            <option value="">Tümü</option>
                            @foreach ($hukukiDurumlar as $h)
                                <option value="{{ $h->id }}">{{ $h->ad }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="hrm-form-satir">
                        <label for="hrm-kr-kiracilik">Kiracılık Durumu</label>
                        <select id="hrm-kr-kiracilik" name="kiracilik_durum_id" class="hrm-form-select">
                            <option value="">Tümü</option>
                            @foreach ($kiracilikDurumlar as $k)
                                <option value="{{ $k->id }}">{{ $k->ad }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="hrm-form-2li">
                        <div class="hrm-form-satir">
                            <label for="hrm-kr-baslangic">Kira başlangıç</label>
                            <input type="date" id="hrm-kr-baslangic" name="baslangic" class="hrm-form-input" aria-label="Başlangıç tarihinden sonra">
                        </div>
                        <div class="hrm-form-satir">
                            <label for="hrm-kr-bitis">Kira bitiş</label>
                            <input type="date" id="hrm-kr-bitis" name="bitis" class="hrm-form-input" aria-label="Bitiş tarihinden önce">
                        </div>
                    </div>
                    <label class="hrm-kr-onay">
                        <input type="checkbox" name="tahliye" value="1">
                        <span>Yalnız tahliye ihbarnamesi çekilenler</span>
                    </label>
                    <label class="hrm-kr-onay">
                        <input type="checkbox" name="ana_dosya" value="1">
                        <span>Yalnız ana dosyalar <small>altında dosya bulunanlar</small></span>
                    </label>
                </div>
                <div class="hrm-kr-sorgu-ayak">
                    <button type="submit" class="hrm-form-btn" id="hrm-kr-sorgula">Sorgula</button>
                    <button type="button" class="hrm-form-btn is-ghost" id="hrm-kr-temizle">Temizle</button>
                </div>
            </form>
            <div class="hrm-form-mesaj" id="hrm-kr-arama-mesaj" hidden></div>

            <div class="hrm-arama-liste" id="hrm-kr-liste" hidden>
                <div class="hrm-kr-liste-ust">
                    <label class="hrm-kr-onay">
                        <input type="checkbox" id="hrm-kr-hepsi">
                        <span>Tümünü seç · <strong id="hrm-kr-sayi">0</strong> kayıt</span>
                    </label>
                    <p class="hrm-kr-liste-not" id="hrm-kr-liste-not"></p>
                    <button type="button" class="hrm-form-btn" id="hrm-kr-secilen-goster" disabled>Seçilenleri haritada göster</button>
                    <div class="hrm-kr-indir" aria-label="İndir">
                        <a id="hrm-kr-excel" href="{{ route('panel.kira.excel') }}">Excel</a>
                        <a id="hrm-kr-kml" href="{{ route('panel.kira.harita.kml') }}">KML</a>
                        <a id="hrm-kr-geojson" href="{{ route('panel.kira.harita.geojson-indir') }}">GeoJSON</a>
                    </div>
                    <p class="hrm-kr-liste-not" id="hrm-kr-indir-not">Satır seçilmezse süzgecin tamamı iner. Seçilirse yalnız o dosyalar.</p>
                </div>
                <div class="hrm-arama-satirlar" id="hrm-kr-satirlar"></div>
            </div>
        </div>
    </div>

    {{-- Seçim araçları — referanstaki Nokta Bilgisi / Alan İçinde / Çizgiyi Kesenler / Pencere --}}
    <div class="hrm-float-panel" id="hrm-kr-secim-panel" hidden>
        <div class="hrm-panel-head" data-drag-handle>
            <span class="hrm-panel-tut" title="Taşımak için sürükleyin">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="6" r="1"/><circle cx="8" cy="12" r="1"/><circle cx="8" cy="18" r="1"/><circle cx="16" cy="6" r="1"/><circle cx="16" cy="12" r="1"/><circle cx="16" cy="18" r="1"/></svg>
            </span>
            <div class="hrm-panel-head-baslik">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="2.5"/><path d="M4 4h5M20 4h-5M4 20h5M20 20h-5"/></svg>
                <strong>Seçim Araçları</strong>
            </div>
            <button type="button" class="hrm-panel-kapat" data-kapat="hrm-kr-secim-panel" data-kapat-btn="hrm-kr-secim-btn" aria-label="Kapat">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="hrm-panel-icerik">
            <p class="hrm-panel-ipucu">Bir araç seçin, sonra haritada çizin. Çizim bitince o sınırdaki kira kayıtları listelenir.</p>
            <div class="hrm-kr-arac-liste">
                <button type="button" class="hrm-kr-arac" data-kr-secim="nokta">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.4"/></svg>
                    <span><strong>Nokta Bilgisi</strong><small>Tıkladığınız noktadaki kayıtlar</small></span>
                </button>
                <button type="button" class="hrm-kr-arac" data-kr-secim="alan">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7l8-4 8 4-2 12-6 2-6-2z"/></svg>
                    <span><strong>Alan İçinde Kalanlar</strong><small>Çizdiğiniz alanın içindekiler</small></span>
                </button>
                <button type="button" class="hrm-kr-arac" data-kr-secim="cizgi">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 20L10 9l4 5 7-10"/></svg>
                    <span><strong>Çizgiyi Kesenler</strong><small>Çizginin geçtiği kayıtlar</small></span>
                </button>
                <button type="button" class="hrm-kr-arac" data-kr-secim="pencere">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="14" rx="1.5"/><path d="M4 10h16"/></svg>
                    <span><strong>Pencere</strong><small>İki köşesine tıklayın</small></span>
                </button>
            </div>
            <label class="hrm-kr-onay">
                <input type="checkbox" id="hrm-kr-kesisen">
                <span>Kısmen kesişenleri de al <small>(kapalıysa yalnız tamamen içinde kalanlar)</small></span>
            </label>
            <button type="button" class="hrm-form-btn is-ghost" id="hrm-kr-temizle">Çizimi Temizle</button>
        </div>
    </div>

    {{-- Kayıt işlemleri — referanstaki Yeni Kayıt / Kayıt Güncelle / Kayıt Sil --}}
    @can('kira.olustur')
    <div class="hrm-float-panel" id="hrm-kr-kayit-panel" hidden>
        <div class="hrm-panel-head" data-drag-handle>
            <span class="hrm-panel-tut" title="Taşımak için sürükleyin">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="6" r="1"/><circle cx="8" cy="12" r="1"/><circle cx="8" cy="18" r="1"/><circle cx="16" cy="6" r="1"/><circle cx="16" cy="12" r="1"/><circle cx="16" cy="18" r="1"/></svg>
            </span>
            <div class="hrm-panel-head-baslik">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l5-9 5 5 4-7 4 11z"/></svg>
                <strong>Kayıt İşlemleri</strong>
            </div>
            <button type="button" class="hrm-panel-kapat" data-kapat="hrm-kr-kayit-panel" data-kapat-btn="hrm-kr-kayit-btn" aria-label="Kapat">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="hrm-panel-icerik">
            <p class="hrm-panel-ipucu">Aynı sınırda birden çok kira kaydı olabilir. Var olan bir sınıra kayıt eklemek için sınıra tıklayıp <strong>Bu Sınıra Kayıt Ekle</strong>'yi kullanın.</p>
            <div class="hrm-kr-arac-liste">
                <button type="button" class="hrm-kr-arac" data-kr-kayit="yeni">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7l8-4 8 4-2 12-6 2-6-2z"/><path d="M12 9v6M9 12h6"/></svg>
                    <span><strong>Yeni Kayıt</strong><small>Sınırı çizip kayıt formunu aç</small></span>
                </button>
                @can('kira.duzenle')
                    <button type="button" class="hrm-kr-arac" data-kr-kayit="guncelle">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                        <span><strong>Kayıt Güncelle</strong><small>Sınıra tıkla, köşeleri taşı</small></span>
                    </button>
                @endcan
                @can('kira.sil')
                    <button type="button" class="hrm-kr-arac is-tehlike" data-kr-kayit="sil">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
                        <span><strong>Kayıt Sil</strong><small>Sınıra tıkla, bağlı kayıtları gör</small></span>
                    </button>
                @endcan
            </div>
            <div class="hrm-form-mesaj" id="hrm-kr-kayit-mesaj" hidden></div>
        </div>
    </div>
    @endcan

    {{-- Sınıra tıklanınca açılan bilgi paneli (popup yerine) --}}
    <div class="hrm-float-panel hrm-panel-wide" id="hrm-kr-bilgi" hidden>
        <div class="hrm-panel-head" data-drag-handle>
            <span class="hrm-panel-tut" title="Taşımak için sürükleyin">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="6" r="1"/><circle cx="8" cy="12" r="1"/><circle cx="8" cy="18" r="1"/><circle cx="16" cy="6" r="1"/><circle cx="16" cy="12" r="1"/><circle cx="16" cy="18" r="1"/></svg>
            </span>
            <div class="hrm-panel-head-baslik">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8 L12 3 L20 8 L18 20 L6 20 z"/></svg>
                <strong id="hrm-kr-bilgi-baslik">Kira Bilgi Paneli</strong>
            </div>
            <button type="button" class="hrm-panel-kapat" data-kapat="hrm-kr-bilgi" data-kapat-btn="hrm-kr-bilgi-dugmesi-yok" aria-label="Kapat">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="hrm-panel-icerik" id="hrm-kr-bilgi-govde"></div>
    </div>

    {{-- Seçim sonucu --}}
    <div class="hrm-float-panel hrm-panel-wide" id="hrm-kr-sonuc" hidden>
        <div class="hrm-panel-head" data-drag-handle>
            <span class="hrm-panel-tut" title="Taşımak için sürükleyin">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="6" r="1"/><circle cx="8" cy="12" r="1"/><circle cx="8" cy="18" r="1"/><circle cx="16" cy="6" r="1"/><circle cx="16" cy="12" r="1"/><circle cx="16" cy="18" r="1"/></svg>
            </span>
            <div class="hrm-panel-head-baslik">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
                <strong id="hrm-kr-sonuc-baslik">Seçilen Kayıtlar</strong>
            </div>
            {{-- Sonuç panelinin araç çubuğunda düğmesi yok; data-kapat-btn bilerek yok sayılır --}}
            <button type="button" class="hrm-panel-kapat" data-kapat="hrm-kr-sonuc" data-kapat-btn="hrm-kr-sonuc-dugmesi-yok" aria-label="Kapat">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="hrm-panel-icerik" id="hrm-kr-sonuc-govde"></div>
    </div>

    <div id="hrm-map" class="hrm-map"></div>

    {{-- Kayıt modali: haritadan ayrılmadan emsal / değerleme eklenir --}}
    <div class="hrm-modal-katman" id="hrm-modal" hidden>
        <div class="hrm-modal" role="dialog" aria-modal="true" aria-labelledby="hrm-modal-baslik">
            <div class="hrm-modal-head">
                <strong id="hrm-modal-baslik">Kayıt Ekle</strong>
                <small id="hrm-modal-alt"></small>
                <button type="button" class="hrm-panel-kapat" id="hrm-modal-kapat" aria-label="Kapat">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="hrm-modal-govde" id="hrm-modal-govde"></div>
        </div>
    </div>

</div>
@endsection


@push('scripts')
<script src="https://js.arcgis.com/4.30/"></script>
<script>
    window.ETYS_HARITA = {
        // Kira kipi: parsel katmanı kira dosyalarının konumundan gelir.
        // Altlıklar, ABB katmanları, TKGM parsel sorgusu ve e-İmar popup'ı
        // taşınmaz/değerleme haritalarıyla ortaktır.
        mod:           'kira',
        geojson:       @json(route('panel.kira.harita.geojson')),
        ara:           @json(route('panel.ajax.tasinmaz-ara')),
        eimar:         @json(route('panel.ajax.eimar-identify')),
        ilceler:       @json(url('/panel/ajax/ilceler')),
        mahalleler:    @json(url('/panel/ajax/mahalleler')),
        tkgmNokta:     @json(url('/panel/ajax/tkgm-parsel')),
        tkgmAdaParsel: @json(url('/panel/ajax/tkgm-parsel-adaparsel')),
        secilenSayi:   null,
        csrf:          @json(csrf_token()),
        kira: {
            dosya:       @json(url('/panel/kira/harita/dosya')),
            yeni:        @json(route('panel.kira.alanlar.olustur')),
            ekleyebilir: @json($ekleyebilir),
            // Seçim ve kayıt araçları
            secim:       @json(route('panel.kira.harita.secim')),
            // Sınıra tıklanınca yan panele basılan kayıtlar
            poligonBilgi: @json(url('/panel/kira/harita/poligon')),
            alanYeni:    @json(route('panel.kira.alanlar.olustur')),
            poligon:     @json(url('/panel/kira/harita/poligon')),
            sinirAyir:   @json(url('/panel/kira/harita/alan')),
            duzenlenir:  @json(auth()->user()?->can('kira.duzenle') ?? false),
            silinebilir: @json(auth()->user()?->can('kira.sil') ?? false),
            imarRenk:    @json($imarDurumlari->mapWithKeys(fn ($i) => [$i->id => $imarRenk($i->id)])->all()),
            ara:         @json(route('panel.kira.harita.ara')),
            excel:       @json(route('panel.kira.excel')),
            kml:         @json(route('panel.kira.harita.kml')),
            geojsonIndir:@json(route('panel.kira.harita.geojson-indir')),
        },
    };
</script>
<script src="{{ asset('js/harita-esri.js') }}?v=61"></script>
@endpush
