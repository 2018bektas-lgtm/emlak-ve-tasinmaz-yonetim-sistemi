@extends('layouts.panel')

@section('title', 'Taşınmaz Haritası')
@section('contentClass', 'is-flush')
@section('shellClass', 'is-map-full')

@push('head')
    <link rel="stylesheet" href="https://js.arcgis.com/4.30/esri/themes/light/main.css">
@endpush

@section('content')
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

        <details class="hrm-details" open>
            <summary class="hrm-summary">
                <svg class="hrm-summary-caret" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                <span>Altlık</span>
            </summary>
            <div class="hrm-details-icerik">
                <div class="hrt-layers-grid" id="hrm-altlik-grid"></div>
            </div>
        </details>

        <details class="hrm-details" open>
            <summary class="hrm-summary">
                <svg class="hrm-summary-caret" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                <span>Veri Katmanları</span>
            </summary>
            <div class="hrm-details-icerik">

            <div class="hrm-katman-kart" data-aktif="1">
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
                </div>
            </div>

            <div class="hrm-katman-kart">
                <div class="hrm-katman-ust">
                    <span class="hrm-katman-ikon" style="background:#ec4899;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12 h4 v-4 h4 v4 h4 v-4 h4 v4 h2 M3 12 v6 h18 v-6"/></svg>
                    </span>
                    <div class="hrm-katman-meta">
                        <span class="hrm-katman-ad">İmar Planı (UIP)</span>
                        <span class="hrm-katman-alt">Ankara BB · uip_esri3 tile servisi</span>
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
                </div>
            </div>

            <div class="hrm-katman-kart">
                <div class="hrm-katman-ust">
                    <span class="hrm-katman-ikon" style="background:#8b5cf6;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16 M4 12h16 M4 18h16 M9 3v18 M15 3v18"/></svg>
                    </span>
                    <div class="hrm-katman-meta">
                        <span class="hrm-katman-ad">Parselasyon</span>
                        <span class="hrm-katman-alt">Ankara BB CBS · Gis Proxy</span>
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
                </div>
            </div>

            <div class="hrm-katman-kart">
                <div class="hrm-katman-ust">
                    <span class="hrm-katman-ikon" style="background:#16a34a;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21 h18 M5 21 V9 l7-5 7 5 v12 M9 21 v-6 h6 v6"/></svg>
                    </span>
                    <div class="hrm-katman-meta">
                        <span class="hrm-katman-ad">Belediye Taşınmazları</span>
                        <span class="hrm-katman-alt">Ankara BB CBS · Gis Proxy</span>
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
                </div>
            </div>
            </div>
        </details>

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

