@extends('layouts.panel')

@section('title', 'Taşınmaz Düzenle · #' . $tasinmaz->id)

@push('head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" crossorigin="anonymous">
@endpush

@push('scripts')
    <script src="{{ asset('js/hisse-tablo.js') }}?v=2"></script>
    <script src="{{ asset('js/yapi-tablo.js') }}?v=1"></script>
@endpush

@section('content')
@if (session('basari'))
    <div class="flash-success" role="status">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12 l5 5 L20 7"/></svg>
        {{ session('basari') }}
    </div>
@endif

@if ($errors->any())
    <div class="flash-error" role="alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8 v4 M12 16 v0.01"/></svg>
        Lütfen aşağıdaki alanları kontrol edin.
    </div>
@endif

<form method="POST" action="{{ $tasinmaz->rota('guncelle') }}" class="form-shell" enctype="multipart/form-data" novalidate>
    @csrf
    @method('PUT')

    {{-- 01 · Konum haritası --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">01</span>
            <div class="form-section-head-text">
                <h3>Konum Haritası</h3>
                <small>Parselin sınırlarını çizin. Boş alana tıklayarak nokta ekleyin, ilk noktaya tıklayarak veya çift tıklayarak kapatın.</small>
            </div>
        </div>

        <div class="hrt-shell" id="hrt-shell">
            {{-- ÜST: Ana ikonik menü (kalıcı) --}}
            <div class="hrt-menu" role="toolbar" aria-label="Harita menüsü">
                <div class="hrt-menu-left">
                    {{-- Katmanlar dropdown --}}
                    <div class="hrt-dropdown" id="hrt-dd-katmanlar">
                        <button type="button" class="hrt-mbtn" id="hrt-mbtn-katmanlar" title="Harita katmanları" aria-label="Katmanlar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 L2 8 l10 6 l10-6z"/><path d="M2 16 l10 6 l10-6"/><path d="M2 12 l10 6 l10-6"/></svg>
                            <span class="hrt-mbtn-label">Katman</span>
                        </button>
                        <div class="hrt-dropdown-menu" id="hrt-dd-menu-katmanlar">
                            <div class="hrt-dropdown-title">Harita Katmanları — <span id="hrt-katman-aktif-ad">Uydu+Etiket</span></div>
                            <div class="hrt-layers-grid" id="hrt-layers-grid"></div>
                        </div>
                    </div>

                    {{-- Poligon Çiz --}}
                    <button type="button" class="hrt-mbtn" id="hrt-mbtn-cizim" title="Poligon çiz (P)" aria-label="Poligon çiz">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8 L12 3 L20 8 L18 20 L6 20 z"/></svg>
                        <span class="hrt-mbtn-label">Çiz</span>
                    </button>

                    {{-- Konuma Git --}}
                    <button type="button" class="hrt-mbtn" id="hrt-mbtn-gps" title="Konumuma git" aria-label="Konumuma git">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/></svg>
                        <span class="hrt-mbtn-label">Konumum</span>
                    </button>

                    {{-- Bilgi Sorgu (haritaya tıkla → adres bilgisi) --}}
                    <button type="button" class="hrt-mbtn" id="hrt-mbtn-bilgi" title="Haritada bir noktaya tıklayarak konum bilgisi alın" aria-label="Bilgi sorgu">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8 h.01"/><path d="M11 12 h1 v4 h1"/></svg>
                        <span class="hrt-mbtn-label">Bilgi</span>
                    </button>

                    {{-- Ada / Parsel Sorgu dropdown --}}
                    <div class="hrt-dropdown" id="hrt-dd-adaparsel">
                        <button type="button" class="hrt-mbtn" id="hrt-mbtn-adaparsel" title="Ada / parsel sorgula" aria-label="Ada parsel sorgu">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg>
                            <span class="hrt-mbtn-label">Ada/Parsel</span>
                        </button>
                        <div class="hrt-dropdown-menu hrt-dropdown-form" id="hrt-dd-menu-adaparsel">
                            <div class="hrt-dropdown-title">TKGM Ada / Parsel Sorgu</div>
                            <div class="hrt-form-row">
                                <label>İl</label>
                                <select id="hrt-ap-il">
                                    <option value="">— İl seçin —</option>
                                    @foreach ($iller as $il)
                                        <option value="{{ $il->id }}" data-tkgm-id="{{ $il->tkgm_id }}">{{ $il->ad }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="hrt-form-row">
                                <label>İlçe</label>
                                <select id="hrt-ap-ilce" disabled>
                                    <option value="">— Önce il seçin —</option>
                                </select>
                            </div>
                            <div class="hrt-form-row">
                                <label>Mahalle</label>
                                <select id="hrt-ap-mahalle" disabled>
                                    <option value="">— Önce ilçe seçin —</option>
                                </select>
                            </div>
                            <div class="hrt-form-row hrt-form-2">
                                <div>
                                    <label>Ada</label>
                                    <input type="text" id="hrt-ap-ada" placeholder="123" autocomplete="off">
                                </div>
                                <div>
                                    <label>Parsel</label>
                                    <input type="text" id="hrt-ap-parsel" placeholder="45" autocomplete="off">
                                </div>
                            </div>
                            <button type="button" class="hrt-form-submit" id="hrt-ap-sorgula" disabled>Sorgula</button>
                            <div class="hrt-form-info" id="hrt-ap-info">İl · İlçe · Mahalle seçin, ada ve parselı girin.</div>
                        </div>
                    </div>

                    {{-- KML Yükle --}}
                    <label class="hrt-mbtn" for="hrt-kml-file" title="KML dosyası yükle" role="button" tabindex="0">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg>
                        <span class="hrt-mbtn-label">KML</span>
                    </label>
                    <input type="file" id="hrt-kml-file" accept=".kml,.xml" hidden>

                    {{-- Tam Ekran --}}
                    <button type="button" class="hrt-mbtn" id="hrt-mbtn-fullscreen" title="Tam ekran (F)" aria-label="Tam ekran">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9V3h6M21 9V3h-6M3 15v6h6M21 15v6h-6"/></svg>
                        <span class="hrt-mbtn-label">Tam Ekran</span>
                    </button>
                </div>

                <div class="hrt-menu-right">
                    {{-- Koşullu: Sil butonu (polygon çift tıklandığında görünür) --}}
                    <button type="button" class="hrt-mbtn hrt-mbtn-danger" id="hrt-mbtn-sil" title="Poligonu sil (Del)" aria-label="Sil" hidden>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4 a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14 a2 2 0 0 1-2 2H8 a2 2 0 0 1-2-2L5 6"/></svg>
                        <span class="hrt-mbtn-label">Sil</span>
                    </button>

                    {{-- Arama --}}
                    <div class="hrt-search-wrap">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21 l-4.35-4.35"/></svg>
                        <input type="text" id="hrt-arama" placeholder="Adres veya koordinat ara..." autocomplete="off">
                        <button type="button" id="hrt-arama-btn" title="Ara">Ara</button>
                        <div class="hrt-arama-sonuc" id="hrt-arama-sonuc" hidden></div>
                    </div>
                </div>
            </div>

            {{-- Çizim alt-şeridi (sadece çizim modunda görünür) --}}
            <div class="hrt-cizim-arac" id="hrt-cizim-arac" hidden>
                <span class="hrt-cizim-mode">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8 L12 3 L20 8 L18 20 L6 20 z"/></svg>
                    Çizim modu aktif
                </span>
                <button type="button" class="hrt-cizim-btn" id="hrt-tool-undo" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7 v6 h6"/><path d="M21 17 a9 9 0 0 0-15-6.7 L3 13"/></svg>
                    Geri al
                </button>
                <button type="button" class="hrt-cizim-btn hrt-cizim-btn-ok" id="hrt-tool-bitir" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12 l5 5 L20 7"/></svg>
                    Bitir
                </button>
                <button type="button" class="hrt-cizim-btn" id="hrt-tool-iptal">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 L6 18M6 6l12 12"/></svg>
                    İptal
                </button>
                <span class="hrt-cizim-hint">Boş alana tıklayarak nokta ekleyin · İlk noktaya tık / çift tık / Enter ile kapatın · ESC iptal</span>
            </div>

            {{-- Bilgi chip barı --}}
            <div class="hrt-info-bar" id="hrt-info-bar">
                <div class="hrt-chip">
                    <span class="hrt-chip-label">Nokta</span>
                    <span class="hrt-chip-value" id="hrt-info-nokta">0</span>
                </div>
                <div class="hrt-chip">
                    <span class="hrt-chip-label">Alan</span>
                    <span class="hrt-chip-value" id="hrt-info-alan">—</span>
                </div>
                <div class="hrt-chip">
                    <span class="hrt-chip-label">Çevre</span>
                    <span class="hrt-chip-value" id="hrt-info-cevre">—</span>
                </div>
                <div class="hrt-chip hrt-chip-wide">
                    <span class="hrt-chip-label">Merkez</span>
                    <span class="hrt-chip-value" id="hrt-info-merkez">—</span>
                </div>
                <div class="hrt-chip hrt-chip-hint" id="hrt-hint">Menüden bir işlem seçin</div>
            </div>

            {{-- Harita gövdesi --}}
            <div class="hrt-body">
                <div id="harita" class="hrt-map"
                     data-lat="{{ old('lat', $tasinmaz->koordinat->lat ?? '') }}"
                     data-lng="{{ old('lng', $tasinmaz->koordinat->lng ?? '') }}"
                     data-koordinat='{{ old('koordinat', $tasinmaz->koordinat && $tasinmaz->koordinat->koordinat ? json_encode($tasinmaz->koordinat->koordinat) : '') }}'></div>
            </div>
        </div>

        <input type="hidden" name="lat" id="lat" value="{{ old('lat', $tasinmaz->koordinat->lat ?? '') }}">
        <input type="hidden" name="lng" id="lng" value="{{ old('lng', $tasinmaz->koordinat->lng ?? '') }}">
        <input type="hidden" name="koordinat" id="koordinat" value='{{ old('koordinat', $tasinmaz->koordinat && $tasinmaz->koordinat->koordinat ? json_encode($tasinmaz->koordinat->koordinat) : '') }}'>
    </section>

    {{-- 02 · Konum bilgileri (5 kolon: il/ilçe/mahalle/ada/parsel) --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">02</span>
            <div class="form-section-head-text">
                <h3>Konum Bilgileri</h3>
                <small>İl / ilçe / mahalle ile ada / parsel bilgileri.</small>
            </div>
        </div>

        <div class="form-row form-row-5">
            <div class="form-field {{ $errors->has('il_id') ? 'has-error' : '' }}">
                <label for="il_id">İl <span class="required">*</span></label>
                <select id="il_id" name="il_id" class="form-select" data-cascade="il" required>
                    <option value="">— İl seçin —</option>
                    @foreach ($iller as $il)
                        <option value="{{ $il->id }}" data-tkgm-id="{{ $il->tkgm_id }}" @selected(old('il_id', $tasinmaz->il_id) == $il->id)>{{ $il->ad }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-field {{ $errors->has('ilce_id') ? 'has-error' : '' }}">
                <label for="ilce_id">İlçe <span class="required">*</span></label>
                <select id="ilce_id" name="ilce_id" class="form-select" data-cascade="ilce" {{ $tasinmaz->il_id ? '' : 'disabled' }} required>
                    <option value="">— İlçe seçin —</option>
                    @foreach (($ilcelerOnceden ?? []) as $ilce)
                        <option value="{{ $ilce->id }}" data-tkgm-id="{{ $ilce->tkgm_id }}" @selected(old('ilce_id', $tasinmaz->ilce_id) == $ilce->id)>{{ $ilce->ad }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-field {{ $errors->has('mahalle_id') ? 'has-error' : '' }}">
                <label for="mahalle_id">Mahalle <span class="required">*</span></label>
                <select id="mahalle_id" name="mahalle_id" class="form-select" data-cascade="mahalle" {{ $tasinmaz->ilce_id ? '' : 'disabled' }} required>
                    <option value="">— Mahalle seçin —</option>
                    @foreach (($mahallelerOnceden ?? []) as $mahalle)
                        <option value="{{ $mahalle->id }}" data-tkgm-id="{{ $mahalle->tkgm_id }}" @selected(old('mahalle_id', $tasinmaz->mahalle_id) == $mahalle->id)>{{ $mahalle->ad }}</option>
                    @endforeach
                </select>
                @error('mahalle_id')<span class="error">{{ $message }}</span>@enderror
            </div>

            <div class="form-field {{ $errors->has('ada') ? 'has-error' : '' }}">
                <label for="ada">Ada <span class="required">*</span></label>
                <input id="ada" name="ada" type="text" class="form-input" value="{{ old('ada', $tasinmaz->ada) }}" required maxlength="20" placeholder="123">
                @error('ada')<span class="error">{{ $message }}</span>@enderror
            </div>

            <div class="form-field {{ $errors->has('parsel') ? 'has-error' : '' }}">
                <label for="parsel">Parsel <span class="required">*</span></label>
                <input id="parsel" name="parsel" type="text" class="form-input" value="{{ old('parsel', $tasinmaz->parsel) }}" required maxlength="20" placeholder="45">
                @error('parsel')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
    </section>

    {{-- 03 · Fiziksel bilgiler (2 kolon) --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">03</span>
            <div class="form-section-head-text">
                <h3>Fiziksel Bilgiler</h3>
                <small>Parselin toplam alanı ve niteliği.</small>
            </div>
        </div>

        <div class="form-row form-row-2">
            <div class="form-field {{ $errors->has('alan') ? 'has-error' : '' }}">
                <label for="alan">Alan (m²) <span class="required">*</span></label>
                @php
                    $alanEski = old('alan', $tasinmaz->alan);
                    if ($alanEski !== null && $alanEski !== '') {
                        $tmp = str_contains((string)$alanEski, ',') && str_contains((string)$alanEski, '.')
                            ? str_replace(',', '.', str_replace('.', '', (string)$alanEski))
                            : str_replace(',', '.', (string)$alanEski);
                        $alanEskiTR = is_numeric($tmp) ? number_format((float) $tmp, 2, ',', '.') : $alanEski;
                    } else { $alanEskiTR = ''; }
                @endphp
                <input id="alan" name="alan" type="text" inputmode="decimal"
                       class="form-input"
                       value="{{ $alanEskiTR }}"
                       required placeholder="1.234,56" autocomplete="off">
                <span class="hint">Türkçe biçim: nokta binlik, virgül ondalık (ör. 1.234,56)</span>
                @error('alan')<span class="error">{{ $message }}</span>@enderror
            </div>

            <div class="form-field {{ $errors->has('nitelik') ? 'has-error' : '' }}">
                <label for="nitelik">Nitelik <span class="required">*</span></label>
                <input id="nitelik" name="nitelik" type="text" class="form-input" value="{{ old('nitelik', $tasinmaz->nitelik) }}" required maxlength="100" placeholder="Arsa · Tarla · Bahçe · Bağ">
                @error('nitelik')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
    </section>

    {{-- 04 · İmar bilgileri --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">04</span>
            <div class="form-section-head-text">
                <h3>İmar Bilgileri</h3>
                <small>Parselin imar durumu, emsal (KAKS), gabari ve plan notları.</small>
            </div>
        </div>

        <div class="form-row">
            <div class="form-field {{ $errors->has('imar_durumu_id') ? 'has-error' : '' }}">
                <label for="imar_durumu_id">İmar Durumu <span class="required">*</span></label>
                <div class="mks-alan">
                    <select id="imar_durumu_id" name="imar_durumu_id" class="form-select" required>
                        <option value="">— Seçiniz —</option>
                        @foreach ($imarDurumlari as $id)
                            <option value="{{ $id->id }}" @selected(old('imar_durumu_id', $tasinmaz->imar->imar_durumu_id ?? null) == $id->id)>{{ $id->ad }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="mks-ekle-btn" id="imar-ekle-btn" title="Yeni imar durumu ekle">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                    </button>
                </div>
                <span class="hint">Listede yoksa <strong>+</strong> butonuyla yeni ekleyebilirsiniz.</span>
                @error('imar_durumu_id')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>

        <div class="form-row form-row-2">
            <div class="form-field {{ $errors->has('emsal') ? 'has-error' : '' }}">
                <label for="emsal">Emsal (KAKS) <span class="required">*</span></label>
                @php
                    $emsalEski = old('emsal', $tasinmaz->imar->emsal ?? null);
                    if ($emsalEski !== null && $emsalEski !== '') {
                        $tmp = str_contains((string)$emsalEski, ',') && str_contains((string)$emsalEski, '.')
                            ? str_replace(',', '.', str_replace('.', '', (string)$emsalEski))
                            : str_replace(',', '.', (string)$emsalEski);
                        $emsalEskiTR = is_numeric($tmp) ? number_format((float) $tmp, 2, ',', '.') : $emsalEski;
                    } else { $emsalEskiTR = ''; }
                @endphp
                <input id="emsal" name="emsal" type="text" inputmode="decimal"
                       class="form-input" required
                       value="{{ $emsalEskiTR }}"
                       maxlength="8" placeholder="1,50" autocomplete="off">
                <span class="hint">Örn. 1,50 — en fazla 999,99</span>
                @error('emsal')<span class="error">{{ $message }}</span>@enderror
            </div>

            <div class="form-field {{ $errors->has('yenaz_yencok') ? 'has-error' : '' }}">
                <label for="yenaz_yencok">Yen az / Yen çok <span class="required">*</span></label>
                <input id="yenaz_yencok" name="yenaz_yencok" type="text" class="form-input" required
                       value="{{ old('yenaz_yencok', $tasinmaz->imar->yenaz_yencok ?? '') }}" maxlength="50"
                       placeholder="Yençok: 12.50 m · Serbest" autocomplete="off">
                <span class="hint">Gabari veya serbest yükseklik bilgisi.</span>
                @error('yenaz_yencok')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>

        <div class="form-row">
            <div class="form-field {{ $errors->has('imar_notu') ? 'has-error' : '' }}">
                <label for="imar_notu">İmar Notu</label>
                <textarea id="imar_notu" name="imar_notu" class="form-textarea" rows="3" maxlength="5000"
                          placeholder="Plan notları, lejant açıklamaları...">{{ old('imar_notu', $tasinmaz->imar->imar_notu ?? '') }}</textarea>
                @error('imar_notu')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
    </section>

    @php
        $kategoriMevcut = $tasinmaz->kategori;
        $ekbilgiMevcut = $tasinmaz->ekbilgi;
        $ekbilgiSatis = old('ekbilgi.satis_durumu', $ekbilgiMevcut && $ekbilgiMevcut->satis_durumu
            ? ($ekbilgiMevcut->satis_durumu instanceof \App\Enums\SatisDurumu
                ? $ekbilgiMevcut->satis_durumu->value
                : $ekbilgiMevcut->satis_durumu)
            : 'envanterde');
    @endphp

    {{-- 05 · Sınıflandırma (kategori) --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">05</span>
            <div class="form-section-head-text">
                <h3>Sınıflandırma</h3>
                <small>Arsanın muhasebe/kayıt türü/kullanım bilgisi. Üzerinde bağımsız bölüm varsa her BBN kendi ayrı sınıflandırmasını Bölüm 07'de taşır.</small>
            </div>
        </div>

        <div class="form-row form-row-2">
            <div class="form-field {{ $errors->has('kategori.muhasebe_kayit_id') ? 'has-error' : '' }}">
                <label for="kategori-muhasebe">Muhasebe Kaydı <span class="required">*</span></label>
                <select id="kategori-muhasebe" name="kategori[muhasebe_kayit_id]" class="form-select" data-tree="1" required>
                    <option value="">— Seçilmedi —</option>
                    @foreach ($muhasebeKayitlari as $mk)
                        <option value="{{ $mk['id'] }}"
                                data-parent-id="{{ $mk['parent_id'] ?? '' }}"
                                data-seviye="{{ $mk['seviye'] }}"
                                @selected(old('kategori.muhasebe_kayit_id', $kategoriMevcut->muhasebe_kayit_id ?? null) == $mk['id'])>{{ $mk['etiket'] }}</option>
                    @endforeach
                </select>
                @error('kategori.muhasebe_kayit_id')<span class="error">{{ $message }}</span>@enderror
            </div>

            <div class="form-field {{ $errors->has('kategori.kayit_turu_id') ? 'has-error' : '' }}">
                <label for="kategori-kayit-turu">Kayıt Türü <span class="required">*</span></label>
                <select id="kategori-kayit-turu" name="kategori[kayit_turu_id]" class="form-select" data-tree="1" required>
                    <option value="">— Seçilmedi —</option>
                    @foreach ($kayitTurleri as $kt)
                        <option value="{{ $kt['id'] }}"
                                data-parent-id="{{ $kt['parent_id'] ?? '' }}"
                                data-seviye="{{ $kt['seviye'] }}"
                                @selected(old('kategori.kayit_turu_id', $kategoriMevcut->kayit_turu_id ?? null) == $kt['id'])>{{ $kt['etiket'] }}</option>
                    @endforeach
                </select>
                @error('kategori.kayit_turu_id')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>

        <div class="form-row form-row-2">
            <div class="form-field {{ $errors->has('kategori.mevcut_kullanim_sekli') ? 'has-error' : '' }}">
                <label for="kategori-kullanim">Mevcut Kullanım Şekli <span class="required">*</span></label>
                <div class="mks-alan">
                    <select id="kategori-kullanim" name="kategori[mevcut_kullanim_sekli]" class="form-select" required>
                        <option value="">— Seçiniz —</option>
                        @foreach ($mevcutKullanimSekilleri as $mks)
                            <option value="{{ $mks->ad }}" @selected(old('kategori.mevcut_kullanim_sekli', $kategoriMevcut->mevcut_kullanim_sekli ?? null) === $mks->ad)>{{ $mks->ad }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="mks-ekle-btn" id="mks-ekle-btn" title="Yeni kullanım şekli ekle">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                    </button>
                </div>
                @error('kategori.mevcut_kullanim_sekli')<span class="error">{{ $message }}</span>@enderror
            </div>

            <div class="form-field">
                <label>Bina Durumu</label>
                <label class="form-check">
                    <input type="hidden" name="uzeri_bina_var_mi" value="0">
                    <input type="checkbox" name="uzeri_bina_var_mi" value="1" @checked(old('uzeri_bina_var_mi', $tasinmaz->uzeri_bina_var_mi))>
                    <span>Arsa üzerinde bina var</span>
                </label>
            </div>
        </div>
    </section>

    {{-- 06 · Ek Bilgi (ekbilgi) --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">06</span>
            <div class="form-section-head-text">
                <h3>Ek Bilgi (Durum)</h3>
                <small>İşgal / satış / meclis kararı / tahsis / üst hakkı / kira gibi durum bayrakları — arsa için. BBN'lerin kendi durumu Bölüm 07'de.</small>
            </div>
        </div>

        <div class="form-row form-row-2">
            <div class="form-field {{ $errors->has('ekbilgi.isgal_durumu') ? 'has-error' : '' }}">
                <label for="ekbilgi-isgal">İşgal Durumu <span class="required">*</span></label>
                <select id="ekbilgi-isgal" name="ekbilgi[isgal_durumu]" class="form-select" required>
                    <option value="">— Seçiniz —</option>
                    <option value="yok" @selected(old('ekbilgi.isgal_durumu', $ekbilgiMevcut->isgal_durumu ?? null) === 'yok')>Yok</option>
                    <option value="var" @selected(old('ekbilgi.isgal_durumu', $ekbilgiMevcut->isgal_durumu ?? null) === 'var')>Var</option>
                    <option value="kismi" @selected(old('ekbilgi.isgal_durumu', $ekbilgiMevcut->isgal_durumu ?? null) === 'kismi')>Kısmi</option>
                </select>
                @error('ekbilgi.isgal_durumu')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="ekbilgi-satis">Satış Durumu <span class="required">*</span></label>
                <select id="ekbilgi-satis" name="ekbilgi[satis_durumu]" class="form-select">
                    @foreach (\App\Enums\SatisDurumu::cases() as $sd)
                        <option value="{{ $sd->value }}" @selected($ekbilgiSatis === $sd->value)>{{ $sd->etiket() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-row form-row-4">
            <div class="form-field">
                <label>Meclis Satış Kararı</label>
                <label class="form-check">
                    <input type="hidden" name="ekbilgi[meclis_satis_karari_var]" value="0">
                    <input type="checkbox" name="ekbilgi[meclis_satis_karari_var]" value="1" @checked(old('ekbilgi.meclis_satis_karari_var', $ekbilgiMevcut->meclis_satis_karari_var ?? false))>
                    <span>Karar var</span>
                </label>
            </div>
            <div class="form-field">
                <label>Tahsis</label>
                <label class="form-check">
                    <input type="hidden" name="ekbilgi[tahsis_var]" value="0">
                    <input type="checkbox" name="ekbilgi[tahsis_var]" value="1" @checked(old('ekbilgi.tahsis_var', $ekbilgiMevcut->tahsis_var ?? false))>
                    <span>Tahsis var</span>
                </label>
            </div>
            <div class="form-field">
                <label>Üst Hakkı</label>
                <label class="form-check">
                    <input type="hidden" name="ekbilgi[ust_hakki_var]" value="0">
                    <input type="checkbox" name="ekbilgi[ust_hakki_var]" value="1" @checked(old('ekbilgi.ust_hakki_var', $ekbilgiMevcut->ust_hakki_var ?? false))>
                    <span>Üst hakkı var</span>
                </label>
            </div>
            <div class="form-field">
                <label>Kira</label>
                <label class="form-check">
                    <input type="hidden" name="ekbilgi[kira_var]" value="0">
                    <input type="checkbox" name="ekbilgi[kira_var]" value="1" @checked(old('ekbilgi.kira_var', $ekbilgiMevcut->kira_var ?? false))>
                    <span>Kirada</span>
                </label>
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="ekbilgi-aciklama">Açıklama</label>
                <textarea id="ekbilgi-aciklama" name="ekbilgi[aciklama]" class="form-textarea" rows="3" maxlength="5000" placeholder="Ek notlar, gözlemler, durum açıklaması...">{{ old('ekbilgi.aciklama', $ekbilgiMevcut->aciklama ?? '') }}</textarea>
            </div>
        </div>
    </section>

    {{-- 07 · Bağımsız Bölümler (yapı) --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">07</span>
            <div class="form-section-head-text">
                <h3>Bağımsız Bölümler</h3>
                <small>Arsa üzerine bina/daire/dükkan/depo varsa buradan ekleyin. Her BBN kendi muhasebe / satış / kira / tahsis / açıklama bilgisini bağımsız olarak taşır.</small>
            </div>
        </div>
        @include('panel.tasinmazlar._yapi-panel', [
            'yapiEski' => $tasinmaz->yapilar->map->toFormArray()->values()->all(),
            'yapiBase' => route('panel.tasinmazlar.yapi.store', $tasinmaz->duzenleParams()),
        ])
    </section>

    {{-- 08 · Tapu --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">08</span>
            <div class="form-section-head-text">
                <h3>Tapu Bilgileri</h3>
                <small>TAKBİS zemin, cilt / sayfa ve tapu kaydı. İsteğe bağlı.</small>
            </div>
        </div>

        @php
            $tapuMevcut = $tasinmaz->tapu;
            $tapu = old('tapu', $tapuMevcut ? [
                'takbis_zemin_no' => $tapuMevcut->takbis_zemin_no,
                'cilt_no' => $tapuMevcut->cilt_no,
                'sayfa_no' => $tapuMevcut->sayfa_no,
                'tapu_durumu' => $tapuMevcut->tapu_durumu,
                'tapu_tarihi' => optional($tapuMevcut->tapu_tarihi)->format('Y-m-d'),
            ] : []);
        @endphp

        <div class="form-row form-row-4">
            <div class="form-field {{ $errors->has('tapu.takbis_zemin_no') ? 'has-error' : '' }}">
                <label for="tapu_takbis">TAKBİS Zemin No <span class="required">*</span></label>
                <input id="tapu_takbis" type="text" class="form-input" name="tapu[takbis_zemin_no]" required
                       value="{{ $tapu['takbis_zemin_no'] ?? '' }}" maxlength="30" placeholder="1234567890" autocomplete="off">
                @error('tapu.takbis_zemin_no')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field {{ $errors->has('tapu.cilt_no') ? 'has-error' : '' }}">
                <label for="tapu_cilt">Cilt No <span class="required">*</span></label>
                <input id="tapu_cilt" type="text" class="form-input" name="tapu[cilt_no]" required
                       value="{{ $tapu['cilt_no'] ?? '' }}" maxlength="20" placeholder="12" autocomplete="off">
                @error('tapu.cilt_no')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field {{ $errors->has('tapu.sayfa_no') ? 'has-error' : '' }}">
                <label for="tapu_sayfa">Sayfa No <span class="required">*</span></label>
                <input id="tapu_sayfa" type="text" class="form-input" name="tapu[sayfa_no]" required
                       value="{{ $tapu['sayfa_no'] ?? '' }}" maxlength="20" placeholder="45" autocomplete="off">
                @error('tapu.sayfa_no')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field {{ $errors->has('tapu.tapu_durumu') ? 'has-error' : '' }}">
                <label for="tapu_durumu">Tapu Durumu <span class="required">*</span></label>
                <select id="tapu_durumu" class="form-select" name="tapu[tapu_durumu]" required>
                    <option value="">— Seçiniz —</option>
                    <option value="aktif" @selected(($tapu['tapu_durumu'] ?? '') === 'aktif')>Aktif</option>
                    <option value="pasif" @selected(($tapu['tapu_durumu'] ?? '') === 'pasif')>Pasif</option>
                </select>
                @error('tapu.tapu_durumu')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>

        <div class="form-row form-row-2">
            <div class="form-field {{ $errors->has('tapu.tapu_tarihi') ? 'has-error' : '' }}">
                <label for="tapu_tarihi">Tapu Tarihi</label>
                <input id="tapu_tarihi" type="date" class="form-input" name="tapu[tapu_tarihi]"
                       value="{{ $tapu['tapu_tarihi'] ?? '' }}">
            </div>
            <div class="form-field {{ $errors->has('tapu.tapu_kaydi_pdf') ? 'has-error' : '' }}">
                <label for="tapu_pdf">Tapu Kaydı (PDF)</label>
                <input id="tapu_pdf" type="file" class="form-input" name="tapu[tapu_kaydi_pdf]" accept="application/pdf,.pdf">
                @if ($tasinmaz->tapu && $tasinmaz->tapu->tapu_kaydi_pdf)
                    <span class="hint">
                        Mevcut:
                        <a href="{{ $tasinmaz->tapu->pdfUrl() }}"
                           target="_blank" style="color: #1e2c47; font-weight: 700; text-decoration: underline;">
                            PDF'i aç
                        </a>
                        — yeni dosya seçerseniz mevcutun yerini alır.
                    </span>
                @else
                    <span class="hint">PDF, en fazla 10 MB.</span>
                @endif
                @error('tapu.tapu_kaydi_pdf')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
    </section>

    {{-- 09 · Hisse bilgileri --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">09</span>
            <div class="form-section-head-text">
                <h3>Hisse Bilgileri</h3>
                <small>Hisse ekleme, güncelleme ve silme anında kaydedilir — taşınmaz formunu ayrıca göndermeniz gerekmez.</small>
            </div>
        </div>

        @php
            $hisselerEski = $tasinmaz->hisseler->map->toFormArray()->values()->all();
        @endphp

        <div class="hisse-shell" id="hisse-shell"
             data-eski='@json($hisselerEski)'
             data-hisse-base="{{ route('panel.tasinmazlar.hisseler.store', $tasinmaz->duzenleParams()) }}">
            <div class="hisse-tablo-kabuk">
                <table class="hisse-tablo" id="hisse-tablo">
                    <thead>
                        <tr>
                            <th class="hisse-th-no">#</th>
                            <th>Pay / Payda</th>
                            <th>Hisseye Düşen (m²)</th>
                            <th>Durum</th>
                            <th>İşlem</th>
                            <th>Edinme Tarihi</th>
                            <th class="hisse-th-eylem" aria-label="Eylemler"></th>
                        </tr>
                    </thead>
                    <tbody id="hisse-tablo-body"></tbody>
                </table>
                <div class="hisse-tablo-bos" id="hisse-tablo-bos">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 3v18"/></svg>
                    <span>Henüz hisse eklenmedi. Aşağıdaki butonla ekleyebilirsiniz.</span>
                </div>
            </div>

            {{-- Aktif hisse toplam + limit uyarısı — JS canlı günceller --}}
            <div class="hisse-toplam-bar" id="hisse-toplam-bar" hidden>
                <div class="hisse-toplam-satir">
                    <span class="hisse-toplam-etiket">Aktif Hisseler Toplamı</span>
                    <strong class="hisse-toplam-deger" id="hisse-toplam-deger">0,00</strong>
                    <span class="hisse-toplam-ayrac">/</span>
                    <span class="hisse-toplam-taban" id="hisse-toplam-taban">— m²</span>
                    <span class="hisse-toplam-oran" id="hisse-toplam-oran"></span>
                </div>
                <div class="hisse-toplam-uyari" id="hisse-toplam-uyari" hidden>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                    <span>Aktif hisselerin toplamı taşınmaz alanını aşıyor. Pay/payda değerlerini kontrol edin.</span>
                </div>
            </div>

            <button type="button" class="hisse-ekle-btn" id="hisse-ekle-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Hisse Ekle
            </button>

            {{-- Backend'e gönderilecek hidden inputlar (JS state'ten sync eder) --}}
            <div id="hisse-hidden-alan" hidden aria-hidden="true"></div>
        </div>
    </section>

    {{-- 10 · Resimler --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">10</span>
            <div class="form-section-head-text">
                <h3>Resimler</h3>
                <small>JPEG, PNG, WebP · dosya başına max 5 MB · birden fazla seçebilirsiniz · ilk resim otomatik kapak olur.</small>
            </div>
        </div>

        <div class="rsm-shell">
            {{-- Mevcut resimler --}}
            @if ($tasinmaz->resimler->count())
                <div class="rsm-mevcut">
                    <div class="rsm-onizleme-baslik">
                        <span>Mevcut resimler: <strong>{{ $tasinmaz->resimler->count() }}</strong></span>
                        <span style="font-size: 0.72rem; color: #6c7484;">Sağ üstteki × ile silinmek üzere işaretle — güncelleme sırasında silinir</span>
                    </div>
                    <div class="rsm-grid">
                        @foreach ($tasinmaz->resimler as $resim)
                            <div class="rsm-kart {{ $resim->kapak_mi ? 'is-kapak' : '' }}" data-mevcut-id="{{ $resim->id }}">
                                <img class="rsm-kart-resim" src="{{ $resim->url() }}" alt="Resim #{{ $resim->id }}">
                                @if ($resim->kapak_mi)
                                    <span class="rsm-kapak-rozet">Kapak</span>
                                @endif
                                <div class="rsm-kart-alt">
                                    <span class="rsm-kart-ad">Resim #{{ $resim->id }}</span>
                                    <span class="rsm-kart-boyut">{{ number_format(($resim->boyut ?? 0) / 1024, 1, ',', '.') }} KB</span>
                                </div>
                                <button type="button" class="rsm-kart-sil rsm-mevcut-sil" data-id="{{ $resim->id }}" title="Silmek üzere işaretle">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                    <div id="rsm-silinen-container"></div>
                </div>
            @endif

            <label for="rsm-dosya" class="rsm-dropzone" id="rsm-dropzone">
                <div class="rsm-dropzone-icon">
                    <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg>
                </div>
                <div class="rsm-dropzone-text">
                    <strong>Resim seçin</strong> veya buraya sürükleyip bırakın
                    <span>Birden fazla seçebilirsiniz — Ctrl / Shift ile</span>
                </div>
            </label>
            <input type="file" id="rsm-dosya" name="images[]" multiple accept="image/jpeg,image/png,image/webp" hidden>

            <div class="rsm-hata" id="rsm-hata" hidden></div>

            <div class="rsm-onizleme" id="rsm-onizleme" hidden>
                <div class="rsm-onizleme-baslik">
                    <span>Seçilen dosyalar: <strong id="rsm-sayi">0</strong></span>
                    <button type="button" class="rsm-clear-btn" id="rsm-clear-btn">Tümünü kaldır</button>
                </div>
                <div class="rsm-grid" id="rsm-grid"></div>
            </div>

            @error('images')<span class="error">{{ $message }}</span>@enderror
            @error('images.*')<span class="error">{{ $message }}</span>@enderror
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ route('panel.dashboard') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M5 12 l5 5 L20 7"/>
            </svg>
            Değişiklikleri Kaydet
        </button>
    </div>
</form>

{{-- Hisse Ekle/Düzenle Modal --}}
<div class="modal-backdrop" id="hisse-modal" hidden>
    <div class="modal-shell modal-shell-lg" role="dialog" aria-labelledby="hisse-modal-baslik" aria-modal="true">
        <div class="modal-head">
            <h4 id="hisse-modal-baslik">Hisse Ekle</h4>
            <button type="button" class="modal-close" data-hisse-modal-kapat aria-label="Kapat">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-govde">
            <div class="modal-mesaj" id="hisse-modal-mesaj" hidden></div>
            <div class="form-row form-row-3">
                <div class="form-field">
                    <label>Hisse No</label>
                    <input type="number" data-fld="hisse_no" class="form-input" min="1" placeholder="1">
                </div>
                <div class="form-field">
                    <label>Pay</label>
                    <input type="text" data-fld="hisse_pay" class="form-input" inputmode="decimal" placeholder="1">
                </div>
                <div class="form-field">
                    <label>Payda</label>
                    <input type="text" data-fld="hisse_payda" class="form-input" inputmode="decimal" placeholder="2">
                </div>
            </div>

            <div class="hisse-hesap-bar">
                <span class="hisse-hesap-etiket">Hisseye Düşen Alan</span>
                <strong class="hisse-hesap-deger" data-modal-hesap>—</strong>
                <span class="hisse-hesap-birim">m²</span>
                <span class="hisse-hesap-formul" title="pay / payda × taşınmaz alanı">= pay / payda × alan</span>
            </div>
            <div class="hisse-modal-etki" data-modal-etki hidden>
                <span class="hisse-modal-etki-etiket">Aktif toplam (bu hisse dahil)</span>
                <strong data-modal-etki-deger>0,00</strong>
                <span class="hisse-modal-etki-ayrac">/</span>
                <span data-modal-etki-taban>— m²</span>
                <span class="hisse-modal-etki-uyari" data-modal-etki-uyari hidden>Alan aşılıyor</span>
            </div>

            <div class="form-row form-row-2">
                <div class="form-field">
                    <label>Yevmiye No</label>
                    <input type="number" data-fld="yevmiye_no" class="form-input" min="0" placeholder="12345">
                </div>
                <div class="form-field">
                    <label>Edinme Tarihi</label>
                    <input type="date" data-fld="edinme_tarihi" class="form-input">
                </div>
            </div>

            <div class="form-row form-row-3">
                <div class="form-field">
                    <label>Edinme Şekli</label>
                    <input type="text" data-fld="edinme_sekli" class="form-input" maxlength="100" placeholder="Satın alma, İntikal, Bağış...">
                </div>
                <div class="form-field">
                    <label>Hisse Durumu</label>
                    <select data-fld="hisse_durum" class="form-select">
                        <option value="aktif">Aktif</option>
                        <option value="pasif">Pasif</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>İşlem Tipi</label>
                    <select data-fld="islem_tipi" class="form-select">
                        <option value="">— Seçilmedi —</option>
                        <option value="alis">Alış</option>
                        <option value="satis">Satış</option>
                    </select>
                </div>
            </div>

            <div class="form-row form-row-2">
                <div class="form-field">
                    <label>Kayıtlardan Çıkış Sebebi</label>
                    <input type="text" data-fld="kayitlardan_cikis" class="form-input" maxlength="150" placeholder="Terkin, satış vb.">
                </div>
                <div class="form-field">
                    <label>Kayıtlardan Çıkış Tarihi</label>
                    <input type="date" data-fld="kayitlardan_cikistarihi" class="form-input">
                </div>
            </div>

            <div class="hisse-bedel-baslik">Bedel Bilgileri</div>
            <div class="form-row form-row-4">
                <div class="form-field">
                    <label>Maliyet Bedeli</label>
                    <input type="text" data-fld="maliyet_bedeli" data-tr-numeric class="form-input" inputmode="decimal" placeholder="0,00">
                </div>
                <div class="form-field">
                    <label>Rayiç Bedel</label>
                    <input type="text" data-fld="rayic_bedel" data-tr-numeric class="form-input" inputmode="decimal" placeholder="0,00">
                </div>
                <div class="form-field">
                    <label>Emlak Vergi Değeri</label>
                    <input type="text" data-fld="emlak_vd" data-tr-numeric class="form-input" inputmode="decimal" placeholder="0,00">
                </div>
                <div class="form-field">
                    <label>İz Bedeli</label>
                    <input type="text" data-fld="iz_bedeli" data-tr-numeric class="form-input" inputmode="decimal" placeholder="0,00">
                </div>
            </div>
        </div>
        <div class="modal-alt">
            <button type="button" class="btn-cancel" data-hisse-modal-kapat>Vazgeç</button>
            <button type="button" class="btn-submit" id="hisse-modal-kaydet">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                <span data-modal-btn>Ekle</span>
            </button>
        </div>
    </div>
</div>


{{-- Modal: Yeni "İmar Durumu" ekle --}}
<div class="modal-backdrop" id="imar-modal" hidden>
    <div class="modal-shell" role="dialog" aria-labelledby="imar-modal-baslik" aria-modal="true">
        <div class="modal-head">
            <h4 id="imar-modal-baslik">Yeni İmar Durumu Ekle</h4>
            <button type="button" class="modal-close" id="imar-modal-kapat" aria-label="Kapat">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-govde">
            <label for="imar-modal-ad" class="modal-label">İmar Durumu Adı</label>
            <input type="text" id="imar-modal-ad" class="form-input" placeholder="Örn. KONUT ALANI" maxlength="100" autocomplete="off">
            <div class="modal-hint">Otomatik olarak <strong>BÜYÜK HARF</strong>'e çevrilir. Aynı ad varsa mevcut kayıt seçilir.</div>
            <div class="modal-mesaj" id="imar-modal-mesaj" hidden></div>
        </div>
        <div class="modal-alt">
            <button type="button" class="btn-cancel" id="imar-modal-vazgec">Vazgeç</button>
            <button type="button" class="btn-submit" id="imar-modal-kaydet">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Ekle ve Seç
            </button>
        </div>
    </div>
</div>

{{-- Modal: Yeni "Mevcut Kullanım Şekli" ekle --}}
<div class="modal-backdrop" id="mks-modal" hidden>
    <div class="modal-shell" role="dialog" aria-labelledby="mks-modal-baslik" aria-modal="true">
        <div class="modal-head">
            <h4 id="mks-modal-baslik">Yeni Kullanım Şekli Ekle</h4>
            <button type="button" class="modal-close" id="mks-modal-kapat" aria-label="Kapat">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-govde">
            <label for="mks-modal-ad" class="modal-label">Kullanım Şekli Adı</label>
            <input type="text" id="mks-modal-ad" class="form-input" placeholder="Örn. TARLA" maxlength="150" autocomplete="off">
            <div class="modal-hint">Otomatik olarak <strong>BÜYÜK HARF</strong>'e çevrilir. Aynı ad varsa mevcut kayıt seçilir.</div>
            <div class="modal-mesaj" id="mks-modal-mesaj" hidden></div>
        </div>
        <div class="modal-alt">
            <button type="button" class="btn-cancel" id="mks-modal-vazgec">Vazgeç</button>
            <button type="button" class="btn-submit" id="mks-modal-kaydet">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Ekle ve Seç
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    const ilSelect      = document.getElementById('il_id');
    const ilceSelect    = document.getElementById('ilce_id');
    const mahalleSelect = document.getElementById('mahalle_id');

    const ilceUrlPattern     = @json(route('panel.ajax.ilceler', ['il' => 0]));
    const mahalleUrlPattern  = @json(route('panel.ajax.mahalleler', ['ilce' => 0]));
    const eskiIlceId    = @json(old('ilce_id', $tasinmaz->ilce_id ?? null));
    const eskiMahalleId = @json(old('mahalle_id', $tasinmaz->mahalle_id ?? null));

    function urlYap(pattern, id) {
        return pattern.replace(/\/0(?=[/?]|$)/, '/' + id);
    }

    function secBosalt(select, placeholder, enable = false) {
        select.innerHTML = '<option value="">' + placeholder + '</option>';
        select.disabled = !enable;
    }

    async function ilceleriYukle(ilId, secilecekIlceId = null) {
        secBosalt(ilceSelect, '— Yükleniyor... —');
        secBosalt(mahalleSelect, '— Önce ilçe seçin —');

        try {
            const yanit = await fetch(urlYap(ilceUrlPattern, ilId), { headers: { 'Accept': 'application/json' } });
            const ilceler = await yanit.json();
            secBosalt(ilceSelect, '— İlçe seçin —', true);
            ilceler.forEach(i => {
                const opt = new Option(i.ad, i.id);
                if (i.tkgm_id) opt.dataset.tkgmId = i.tkgm_id;
                if (secilecekIlceId && String(i.id) === String(secilecekIlceId)) opt.selected = true;
                ilceSelect.add(opt);
            });
            if (secilecekIlceId) ilceSelect.dispatchEvent(new Event('change'));
        } catch (e) {
            secBosalt(ilceSelect, '— Yükleme hatası —');
        }
    }

    async function mahalleleriYukle(ilceId, secilecekMahalleId = null) {
        secBosalt(mahalleSelect, '— Yükleniyor... —');

        try {
            const yanit = await fetch(urlYap(mahalleUrlPattern, ilceId), { headers: { 'Accept': 'application/json' } });
            const mahalleler = await yanit.json();
            secBosalt(mahalleSelect, '— Mahalle seçin —', true);
            mahalleler.forEach(m => {
                const opt = new Option(m.ad, m.id);
                if (m.tkgm_id) opt.dataset.tkgmId = m.tkgm_id;
                if (secilecekMahalleId && String(m.id) === String(secilecekMahalleId)) opt.selected = true;
                mahalleSelect.add(opt);
            });
            if (secilecekMahalleId) {
                mahalleSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
        } catch (e) {
            secBosalt(mahalleSelect, '— Yükleme hatası —');
        }
    }

    ilSelect.addEventListener('change', function () {
        if (this.value) {
            ilceleriYukle(this.value);
        } else {
            secBosalt(ilceSelect, '— Önce il seçin —');
            secBosalt(mahalleSelect, '— Önce ilçe seçin —');
        }
    });

    ilceSelect.addEventListener('change', function () {
        if (this.value) mahalleleriYukle(this.value);
        else secBosalt(mahalleSelect, '— Önce ilçe seçin —');
    });

    // Eski input (validation hatası sonrası) restore
    if (ilSelect.value) {
        ilceleriYukle(ilSelect.value, eskiIlceId).then(() => {
            if (eskiMahalleId) mahalleleriYukle(eskiIlceId, eskiMahalleId);
        });
    }
})();
</script>

<script>
/* --------------------------------------------------
 * İMAR DURUMU — Modal + dinamik ekleme
 * -------------------------------------------------- */
(function () {
    const acButon = document.getElementById('imar-ekle-btn');
    const modal = document.getElementById('imar-modal');
    const kapatBtn = document.getElementById('imar-modal-kapat');
    const vazgecBtn = document.getElementById('imar-modal-vazgec');
    const kaydetBtn = document.getElementById('imar-modal-kaydet');
    const adInput = document.getElementById('imar-modal-ad');
    const mesajEl = document.getElementById('imar-modal-mesaj');
    const imarSelect = document.getElementById('imar_durumu_id');
    if (!acButon || !modal || !imarSelect) return;

    const storeUrl = @json(route('panel.ajax.imar-durumu.store'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value;

    function mesaj(txt, tur) {
        if (!txt) { mesajEl.hidden = true; return; }
        mesajEl.textContent = txt;
        mesajEl.className = 'modal-mesaj is-' + (tur || 'info');
        mesajEl.hidden = false;
    }

    function ac() {
        modal.hidden = false;
        adInput.value = '';
        mesaj('');
        setTimeout(() => adInput.focus(), 50);
    }
    function kapat() { modal.hidden = true; }

    acButon.addEventListener('click', ac);
    kapatBtn.addEventListener('click', kapat);
    vazgecBtn.addEventListener('click', kapat);
    modal.addEventListener('click', (e) => { if (e.target === modal) kapat(); });
    document.addEventListener('keydown', (e) => { if (!modal.hidden && e.key === 'Escape') kapat(); });

    adInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); kaydetBtn.click(); }
    });

    kaydetBtn.addEventListener('click', async function () {
        const ad = adInput.value.trim();
        if (!ad) { mesaj('Ad boş olamaz.', 'error'); return; }
        adInput.value = ad.toLocaleUpperCase('tr');
        kaydetBtn.disabled = true;
        mesaj('Kaydediliyor...', 'info');
        try {
            const r = await fetch(storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ad }),
            });
            const veri = await r.json();
            if (!r.ok) {
                mesaj((veri.errors?.ad?.[0]) || veri.message || 'Kayıt hatası (HTTP ' + r.status + ')', 'error');
                return;
            }
            let opt = Array.from(imarSelect.options).find(o => String(o.value) === String(veri.id));
            if (!opt) {
                opt = new Option(veri.ad, veri.id);
                const sonrasi = Array.from(imarSelect.options).find(o =>
                    o.value && o.textContent.trim().localeCompare(veri.ad, 'tr') > 0
                );
                if (sonrasi) imarSelect.insertBefore(opt, sonrasi);
                else imarSelect.appendChild(opt);
            }
            imarSelect.value = String(veri.id);
            imarSelect.dispatchEvent(new Event('change', { bubbles: true }));
            kapat();
        } catch (e) {
            mesaj('İstek başarısız: ' + e.message, 'error');
        } finally {
            kaydetBtn.disabled = false;
        }
    });
})();
</script>

<script>
/* --------------------------------------------------
 * RESİM YÜKLEME — çoklu seçim + preview + kaldır + kapak
 * -------------------------------------------------- */
(function () {
    const dosyaInp = document.getElementById('rsm-dosya');
    const dropzone = document.getElementById('rsm-dropzone');
    const onizleme = document.getElementById('rsm-onizleme');
    const grid = document.getElementById('rsm-grid');
    const sayiEl = document.getElementById('rsm-sayi');
    const clearBtn = document.getElementById('rsm-clear-btn');
    const hataEl = document.getElementById('rsm-hata');
    if (!dosyaInp || !dropzone) return;

    const MAX_DOSYA = 20;
    const MAX_MB = 5;
    const KABUL = ['image/jpeg', 'image/png', 'image/webp'];
    let dosyalar = []; // File[] — kullanıcının kontrolünde

    function bytesToStr(n) {
        if (n < 1024) return n + ' B';
        if (n < 1024*1024) return (n/1024).toFixed(1) + ' KB';
        return (n/1024/1024).toFixed(2) + ' MB';
    }

    function hata(txt) {
        if (!txt) { hataEl.hidden = true; hataEl.textContent = ''; return; }
        hataEl.textContent = txt;
        hataEl.hidden = false;
    }

    function fileListYap(arr) {
        const dt = new DataTransfer();
        arr.forEach(f => dt.items.add(f));
        return dt.files;
    }

    function render() {
        grid.innerHTML = '';
        dosyalar.forEach((f, i) => {
            const url = URL.createObjectURL(f);
            const kart = document.createElement('div');
            kart.className = 'rsm-kart' + (i === 0 ? ' is-kapak' : '');
            kart.innerHTML = `
                <img class="rsm-kart-resim" src="${url}" alt="${f.name}" onload="URL.revokeObjectURL(this.src)">
                ${i === 0 ? '<span class="rsm-kapak-rozet">Kapak</span>' : ''}
                <div class="rsm-kart-alt">
                    <span class="rsm-kart-ad" title="${f.name}">${f.name}</span>
                    <span class="rsm-kart-boyut">${bytesToStr(f.size)}</span>
                </div>
                <button type="button" class="rsm-kart-sil" data-idx="${i}" title="Kaldır">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            `;
            grid.appendChild(kart);
        });
        sayiEl.textContent = dosyalar.length;
        onizleme.hidden = dosyalar.length === 0;
        // Native input'un FileList'ini de güncelle (form submit için)
        dosyaInp.files = fileListYap(dosyalar);
    }

    function ekle(yeni) {
        hata('');
        const uygun = [];
        for (const f of yeni) {
            if (!KABUL.includes(f.type)) { hata(`"${f.name}" desteklenmeyen format — sadece JPG/PNG/WebP.`); continue; }
            if (f.size > MAX_MB * 1024 * 1024) { hata(`"${f.name}" ${MAX_MB} MB üzerinde.`); continue; }
            // Aynı dosya iki kez seçilirse atla
            if (dosyalar.some(x => x.name === f.name && x.size === f.size && x.lastModified === f.lastModified)) continue;
            uygun.push(f);
        }
        if (dosyalar.length + uygun.length > MAX_DOSYA) {
            hata(`En fazla ${MAX_DOSYA} resim yüklenebilir.`);
            uygun.splice(MAX_DOSYA - dosyalar.length);
        }
        dosyalar = dosyalar.concat(uygun);
        render();
    }

    dosyaInp.addEventListener('change', (e) => {
        if (e.target.files && e.target.files.length) {
            ekle(Array.from(e.target.files));
            // Not: dosyaInp.files render'da yeniden set edilir
        }
    });

    // Drag & drop
    ['dragenter', 'dragover'].forEach(ev =>
        dropzone.addEventListener(ev, (e) => { e.preventDefault(); dropzone.classList.add('is-uzeri'); })
    );
    ['dragleave', 'drop'].forEach(ev =>
        dropzone.addEventListener(ev, (e) => { e.preventDefault(); dropzone.classList.remove('is-uzeri'); })
    );
    dropzone.addEventListener('drop', (e) => {
        if (e.dataTransfer?.files?.length) ekle(Array.from(e.dataTransfer.files));
    });

    // Sil butonları (event delegation)
    grid.addEventListener('click', (e) => {
        const btn = e.target.closest('.rsm-kart-sil');
        if (!btn) return;
        const idx = parseInt(btn.dataset.idx, 10);
        if (!isNaN(idx)) {
            dosyalar.splice(idx, 1);
            render();
        }
    });

    clearBtn.addEventListener('click', () => {
        dosyalar = [];
        hata('');
        render();
    });

    // --- Mevcut resimleri silmek üzere işaretle (form submit'te silinen_resimler[] gönderilir) ---
    const silinenContainer = document.getElementById('rsm-silinen-container');
    document.querySelectorAll('.rsm-mevcut-sil').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const id = btn.dataset.id;
            const kart = btn.closest('.rsm-kart');
            if (!id || !kart || !silinenContainer) return;
            // Hidden input olarak ekle
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'silinen_resimler[]';
            inp.value = id;
            silinenContainer.appendChild(inp);
            // Kart görsel olarak fade + kaldır
            kart.style.opacity = '0.3';
            kart.style.pointerEvents = 'none';
            btn.style.display = 'none';
            const rozet = document.createElement('span');
            rozet.className = 'rsm-silinecek-rozet';
            rozet.textContent = 'Silinecek';
            kart.appendChild(rozet);
        });
    });
})();
</script>

<script>
/* --------------------------------------------------
 * MEVCUT KULLANIM ŞEKLİ — Modal + dinamik ekleme
 * -------------------------------------------------- */
(function () {
    const acButon = document.getElementById('mks-ekle-btn');
    const modal = document.getElementById('mks-modal');
    const kapatBtn = document.getElementById('mks-modal-kapat');
    const vazgecBtn = document.getElementById('mks-modal-vazgec');
    const kaydetBtn = document.getElementById('mks-modal-kaydet');
    const adInput = document.getElementById('mks-modal-ad');
    const mesajEl = document.getElementById('mks-modal-mesaj');
    const mksSelect = document.getElementById('mevcut_kullanim_sekli');
    if (!acButon || !modal) return;

    const storeUrl = @json(route('panel.ajax.mevcut-kullanim.store'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value;

    function mesaj(txt, tur) {
        if (!txt) { mesajEl.hidden = true; return; }
        mesajEl.textContent = txt;
        mesajEl.className = 'modal-mesaj is-' + (tur || 'info');
        mesajEl.hidden = false;
    }

    function ac() {
        modal.hidden = false;
        adInput.value = '';
        mesaj('');
        setTimeout(() => adInput.focus(), 50);
    }
    function kapat() { modal.hidden = true; }

    acButon.addEventListener('click', ac);
    kapatBtn.addEventListener('click', kapat);
    vazgecBtn.addEventListener('click', kapat);
    modal.addEventListener('click', (e) => { if (e.target === modal) kapat(); });
    document.addEventListener('keydown', (e) => { if (!modal.hidden && e.key === 'Escape') kapat(); });

    adInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); kaydetBtn.click(); }
    });

    kaydetBtn.addEventListener('click', async function () {
        const ad = adInput.value.trim();
        if (!ad) { mesaj('Ad boş olamaz.', 'error'); return; }
        // Ada büyük harf normalize et (backend de yapıyor ama önizleme için)
        adInput.value = ad.toLocaleUpperCase('tr');
        kaydetBtn.disabled = true;
        mesaj('Kaydediliyor...', 'info');
        try {
            const r = await fetch(storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ad }),
            });
            const veri = await r.json();
            if (!r.ok) {
                mesaj((veri.errors?.ad?.[0]) || veri.message || 'Kayıt hatası (HTTP ' + r.status + ')', 'error');
                return;
            }
            // Var olan option'ı bul ya da yeni ekle
            let opt = Array.from(mksSelect.options).find(o => o.value === veri.ad);
            if (!opt) {
                opt = new Option(veri.ad, veri.ad);
                // alfabetik yerleştir
                const sonrasi = Array.from(mksSelect.options).find(o => o.value && o.value > veri.ad);
                if (sonrasi) mksSelect.insertBefore(opt, sonrasi);
                else mksSelect.appendChild(opt);
            }
            mksSelect.value = veri.ad;
            mksSelect.dispatchEvent(new Event('change'));
            kapat();
        } catch (e) {
            mesaj('İstek başarısız: ' + e.message, 'error');
        } finally {
            kaydetBtn.disabled = false;
        }
    });
})();
</script>

<script>
/* ==================================================================
 * ARAMALI CUSTOM SELECT — .aselect
 *   - Tüm <select> elementlerini enhance eder
 *   - Native <select> hidden kalır, form submit'te değeri gönderir
 *   - Options değişimlerini MutationObserver ile takip eder (cascade uyumlu)
 *   - TR normalize arama, klavye ile navigasyon (↑↓ Enter ESC)
 * ================================================================== */
(function () {
    const CARET_SVG = '<svg class="aselect-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>';
    const SEARCH_SVG = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>';

    function trNorm(s) {
        return String(s || '')
            .toLocaleLowerCase('tr')
            .replace(/i̇/g, 'i')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function aselect(sel) {
        if (sel.dataset.aselectDone === '1') return;
        // Multiple / size > 1 select'leri skip et — hardcase
        if (sel.multiple || sel.size > 1) return;
        // Harita ada/parsel paneli: native select (aselect iç içe dropdown'da kesiliyor)
        if (sel.closest('.hrt-dropdown-menu')) return;
        // Modal içi: native select (overflow-y panel kesmesin)
        if (sel.closest('.modal-shell')) return;
        sel.dataset.aselectDone = '1';

        const wrap = document.createElement('div');
        wrap.className = 'aselect';
        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(sel);
        sel.classList.add('aselect-native');
        sel.tabIndex = -1;

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'aselect-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.innerHTML = '<span class="aselect-label"></span>' + CARET_SVG;
        wrap.appendChild(trigger);

        const panel = document.createElement('div');
        panel.className = 'aselect-panel';
        panel.setAttribute('role', 'listbox');
        panel.innerHTML =
            '<div class="aselect-search-wrap">' + SEARCH_SVG +
            '<input type="text" class="aselect-search" placeholder="Ara..." autocomplete="off"></div>' +
            '<div class="aselect-options"></div>' +
            '<div class="aselect-empty" hidden>Sonuç bulunamadı</div>';
        wrap.appendChild(panel);

        const labelEl = trigger.querySelector('.aselect-label');
        const searchEl = panel.querySelector('.aselect-search');
        const listEl = panel.querySelector('.aselect-options');
        const emptyEl = panel.querySelector('.aselect-empty');
        let highlightIdx = -1;
        const expandedIds = new Set(); // tree mode — açık parent id'leri
        // İlk açılışta seçili node'un tüm parent'larını expand et
        function seciliyeExpand() {
            if (!sel.value) return;
            let cur = Array.from(sel.options).find(o => o.value === sel.value);
            while (cur && cur.dataset.parentId) {
                expandedIds.add(cur.dataset.parentId);
                cur = Array.from(sel.options).find(o => o.value === cur.dataset.parentId);
            }
        }
        seciliyeExpand();

        function refreshLabel() {
            const opt = sel.options[sel.selectedIndex];
            if (opt && opt.value) {
                labelEl.textContent = opt.textContent.trim();
                trigger.classList.remove('is-empty');
            } else {
                labelEl.textContent = (opt ? opt.textContent.trim() : 'Seçin');
                trigger.classList.add('is-empty');
            }
            wrap.classList.toggle('is-disabled', sel.disabled);
            trigger.disabled = sel.disabled;
        }

        function isTreeMode() {
            return sel.dataset.tree === '1' || Array.from(sel.options).some(o => o.dataset.parentId !== undefined && o.value !== '');
        }

        function optionOge(opt, i, treeInfo) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'aselect-option';
            item.setAttribute('role', 'option');
            if (!opt.value) item.classList.add('is-placeholder');
            if (opt.value === sel.value) item.classList.add('is-selected');
            if (opt.disabled) item.classList.add('is-disabled');
            item.dataset.idx = i;

            if (treeInfo) {
                item.classList.add('aselect-tree-option');
                item.style.paddingLeft = (10 + treeInfo.depth * 18) + 'px';
                // Caret / expander
                const caret = document.createElement('span');
                caret.className = 'aselect-tree-caret';
                if (treeInfo.hasChildren) {
                    caret.classList.add('is-parent');
                    caret.classList.toggle('is-acik', expandedIds.has(opt.value));
                    caret.innerHTML = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>';
                    caret.addEventListener('click', (e) => {
                        e.stopPropagation();
                        if (expandedIds.has(opt.value)) expandedIds.delete(opt.value);
                        else expandedIds.add(opt.value);
                        const oldScroll = listEl.scrollTop;
                        renderOptions(searchEl.value, { keepScroll: true });
                        listEl.scrollTop = oldScroll;
                    });
                }
                item.appendChild(caret);

                const lbl = document.createElement('span');
                lbl.className = 'aselect-tree-label';
                lbl.textContent = opt.textContent.trim();
                item.appendChild(lbl);
            } else {
                item.textContent = opt.textContent.trim();
            }

            item.addEventListener('click', (ev) => {
                ev.stopPropagation();
                if (opt.disabled) return;
                sel.value = opt.value;
                sel.dispatchEvent(new Event('change', { bubbles: true }));
                refreshLabel();
                kapat();
            });
            return item;
        }

        function renderOptions(q = '', opts = {}) {
            listEl.innerHTML = '';
            const nq = trNorm(q);
            const visible = [];
            const keepScroll = !!opts.keepScroll;

            if (!isTreeMode()) {
                Array.from(sel.options).forEach((opt, i) => {
                    const text = opt.textContent.trim();
                    if (nq && !trNorm(text).includes(nq)) return;
                    const item = optionOge(opt, i, null);
                    item.addEventListener('mouseenter', () => setHighlight(visible.indexOf(item)));
                    listEl.appendChild(item);
                    visible.push(item);
                });
            } else {
                // Tree
                const arr = Array.from(sel.options);
                const gercekOptions = arr.filter(o => o.value !== '');
                const cocuklarOf = new Map();
                gercekOptions.forEach(o => {
                    const p = o.dataset.parentId || '';
                    if (!cocuklarOf.has(p)) cocuklarOf.set(p, []);
                    cocuklarOf.get(p).push(o);
                });

                // Search: match olan + tüm ata'ları görünür olsun
                let visibleIds = null;
                if (nq) {
                    visibleIds = new Set();
                    gercekOptions.forEach(o => {
                        if (trNorm(o.textContent).includes(nq)) {
                            visibleIds.add(o.value);
                            let cur = o;
                            while (cur && cur.dataset.parentId) {
                                visibleIds.add(cur.dataset.parentId);
                                expandedIds.add(cur.dataset.parentId); // search'te otomatik aç
                                cur = gercekOptions.find(x => x.value === cur.dataset.parentId);
                            }
                        }
                    });
                }

                // Placeholder (empty option) — sadece filter yokken
                const placeholder = arr.find(o => o.value === '');
                if (placeholder && !nq) {
                    const item = optionOge(placeholder, arr.indexOf(placeholder), null);
                    item.addEventListener('mouseenter', () => setHighlight(visible.indexOf(item)));
                    listEl.appendChild(item);
                    visible.push(item);
                }

                function dallariCiz(parentId, depth) {
                    const cocuklar = cocuklarOf.get(parentId) || [];
                    cocuklar.forEach(opt => {
                        if (visibleIds && !visibleIds.has(opt.value)) return;
                        const hasKids = (cocuklarOf.get(opt.value) || []).length > 0;
                        const idx = arr.indexOf(opt);
                        const item = optionOge(opt, idx, { depth, hasChildren: hasKids });
                        item.addEventListener('mouseenter', () => setHighlight(visible.indexOf(item)));
                        listEl.appendChild(item);
                        visible.push(item);
                        // Alt dalları göster: expanded veya search'te
                        if (hasKids && (expandedIds.has(opt.value) || nq)) {
                            dallariCiz(opt.value, depth + 1);
                        }
                    });
                }
                dallariCiz('', 0);
            }

            emptyEl.hidden = visible.length > 0;
            highlightIdx = visible.findIndex(x => x.classList.contains('is-selected'));
            if (highlightIdx < 0 && visible.length) highlightIdx = 0;
            setHighlight(highlightIdx, { skipScroll: keepScroll });
            return visible;
        }

        function setHighlight(idx, opts = {}) {
            const items = listEl.querySelectorAll('.aselect-option');
            items.forEach((it, i) => it.classList.toggle('is-highlight', i === idx));
            const el = items[idx];
            if (el && !opts.skipScroll) el.scrollIntoView({ block: 'nearest' });
            highlightIdx = idx;
        }

        function ac() {
            if (sel.disabled) return;
            document.querySelectorAll('.aselect.is-acik').forEach(w => { if (w !== wrap) w.classList.remove('is-acik'); });
            wrap.classList.add('is-acik');
            searchEl.value = '';
            seciliyeExpand(); // seçili node'un ata'larını aç
            renderOptions();
            setTimeout(() => searchEl.focus(), 20);
        }
        function kapat() { wrap.classList.remove('is-acik'); }

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            wrap.classList.contains('is-acik') ? kapat() : ac();
        });

        // Dışa tıklayınca kapat
        document.addEventListener('click', (e) => {
            if (!wrap.contains(e.target)) kapat();
        });

        // Arama input olayları
        searchEl.addEventListener('input', () => renderOptions(searchEl.value));
        searchEl.addEventListener('keydown', (e) => {
            const items = listEl.querySelectorAll('.aselect-option:not(.is-disabled)');
            if (e.key === 'Escape') { e.preventDefault(); kapat(); trigger.focus(); }
            else if (e.key === 'ArrowDown') { e.preventDefault(); setHighlight(Math.min(highlightIdx + 1, items.length - 1)); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); setHighlight(Math.max(highlightIdx - 1, 0)); }
            else if (e.key === 'Enter') { e.preventDefault(); items[highlightIdx]?.click(); }
        });

        // Native select değişikliklerini yansıt (options ekle/çıkar, value, disabled)
        const observer = new MutationObserver(() => {
            refreshLabel();
            if (wrap.classList.contains('is-acik')) renderOptions(searchEl.value);
        });
        observer.observe(sel, { childList: true, attributes: true, attributeFilter: ['disabled'] });
        sel.addEventListener('change', refreshLabel);

        refreshLabel();
    }

    // Sayfa yüklendiğinde ve dinamik eklenenler için
    function enhanceAll(root = document) {
        root.querySelectorAll('select:not([data-aselect-done])').forEach(aselect);
    }
    enhanceAll();

    // Modal'da yeni select gelirse (veya dinamik eklenen options)
    // — hangi select'lerin dinamik olduğunu bilmiyoruz; DOM observer kur
    const bodyObs = new MutationObserver((muts) => {
        for (const m of muts) {
            m.addedNodes.forEach(n => {
                if (n.nodeType === 1) {
                    if (n.matches?.('select')) aselect(n);
                    n.querySelectorAll?.('select:not([data-aselect-done])').forEach(aselect);
                }
            });
        }
    });
    bodyObs.observe(document.body, { childList: true, subtree: true });

    // Global export — başka scriptler çağırabilsin
    window.aselect = aselect;
    window.aselectRefreshAll = enhanceAll;
})();
</script>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" crossorigin="anonymous"></script>
<script>
/* ------------------------------------------------------------------
 * ÖZEL HARİTA MOTORU — Polygon çizim (leaflet.draw yok)
 * ------------------------------------------------------------------ */
(function () {
    const haritaEl = document.getElementById('harita');
    if (!haritaEl) return;

    // ---------- Başlangıç ----------
    const eskiLat = parseFloat(haritaEl.dataset.lat);
    const eskiLng = parseFloat(haritaEl.dataset.lng);
    const eskiKoordinat = haritaEl.dataset.koordinat || '';
    const baslangicLat = Number.isFinite(eskiLat) ? eskiLat : 39.0;
    const baslangicLng = Number.isFinite(eskiLng) ? eskiLng : 35.0;
    const baslangicZoom = (Number.isFinite(eskiLat) && Number.isFinite(eskiLng)) ? 17 : 6;

    const harita = L.map('harita', {
        zoomControl: false,
        attributionControl: false,
        doubleClickZoom: false,
    }).setView([baslangicLat, baslangicLng], baslangicZoom);

    L.control.zoom({ position: 'topleft' }).addTo(harita);
    L.control.attribution({ position: 'bottomleft', prefix: false }).addTo(harita);

    // ---------- Katmanlar ----------
    const altDomains = ['mt0', 'mt1', 'mt2', 'mt3'];
    const katmanTanim = [
        { id: 'hibrit', ad: 'Uydu + Etiket', ozet: 'Google', preview: 'https://mt1.google.com/vt/lyrs=y&x=299&y=193&z=9', layer: L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { subdomains: altDomains, maxZoom: 20 }) },
        { id: 'uydu',   ad: 'Uydu',          ozet: 'Google', preview: 'https://mt1.google.com/vt/lyrs=s&x=299&y=193&z=9', layer: L.tileLayer('https://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', { subdomains: altDomains, maxZoom: 20 }) },
        { id: 'yol',    ad: 'Yol Haritası',  ozet: 'Google', preview: 'https://mt1.google.com/vt/lyrs=m&x=299&y=193&z=9', layer: L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', { subdomains: altDomains, maxZoom: 20 }) },
        { id: 'arazi',  ad: 'Arazi',         ozet: 'Google', preview: 'https://mt1.google.com/vt/lyrs=p&x=299&y=193&z=9', layer: L.tileLayer('https://{s}.google.com/vt/lyrs=p&x={x}&y={y}&z={z}', { subdomains: altDomains, maxZoom: 20 }) },
        { id: 'osm',    ad: 'OpenStreetMap', ozet: 'OSM',    preview: 'https://a.tile.openstreetmap.org/9/299/193.png',   layer: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }) },
    ];
    let aktifKatman = katmanTanim[0];
    aktifKatman.layer.addTo(harita);

    const layersGrid = document.getElementById('hrt-layers-grid');
    katmanTanim.forEach(kt => {
        const tile = document.createElement('button');
        tile.type = 'button';
        tile.className = 'hrt-layer-tile' + (kt.id === aktifKatman.id ? ' is-aktif' : '');
        tile.dataset.katman = kt.id;
        tile.innerHTML = `
            <span class="hrt-layer-thumb" style="background-image:url('${kt.preview}')"></span>
            <span class="hrt-layer-meta">
                <span class="hrt-layer-ad">${kt.ad}</span>
                <span class="hrt-layer-kaynak">${kt.ozet}</span>
            </span>
        `;
        tile.addEventListener('click', () => katmanDegistir(kt.id));
        layersGrid.appendChild(tile);
    });

    const aktifKatmanAdEl = document.getElementById('hrt-katman-aktif-ad');
    aktifKatmanAdEl.textContent = aktifKatman.ad;
    function katmanDegistir(id) {
        const yeni = katmanTanim.find(k => k.id === id);
        if (!yeni || yeni.id === aktifKatman.id) return;
        harita.removeLayer(aktifKatman.layer);
        yeni.layer.addTo(harita);
        aktifKatman = yeni;
        aktifKatmanAdEl.textContent = yeni.ad;
        layersGrid.querySelectorAll('.hrt-layer-tile').forEach(t => {
            t.classList.toggle('is-aktif', t.dataset.katman === id);
        });
    }

    // ---------- Dropdown toggle helper (tüm dropdown'lar için) ----------
    function dropdownKur(ddId, btnId) {
        const dd = document.getElementById(ddId);
        const btn = document.getElementById(btnId);
        if (!dd || !btn) return;
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            // Diğer dropdown'ları kapat
            document.querySelectorAll('.hrt-dropdown.is-acik').forEach(d => {
                if (d !== dd) d.classList.remove('is-acik');
            });
            dd.classList.toggle('is-acik');
        });
        document.addEventListener('click', (e) => {
            if (!dd.contains(e.target)) dd.classList.remove('is-acik');
        });
    }
    dropdownKur('hrt-dd-katmanlar', 'hrt-mbtn-katmanlar');
    dropdownKur('hrt-dd-adaparsel', 'hrt-mbtn-adaparsel');

    // ---------- Formu doldurma ----------
    const latInput = document.getElementById('lat');
    const lngInput = document.getElementById('lng');
    const koordinatInput = document.getElementById('koordinat');
    const alanInput = document.getElementById('alan');
    const bilgiNokta = document.getElementById('hrt-info-nokta');
    const bilgiAlan = document.getElementById('hrt-info-alan');
    const bilgiCevre = document.getElementById('hrt-info-cevre');
    const bilgiMerkez = document.getElementById('hrt-info-merkez');
    const hintEl = document.getElementById('hrt-hint');

    // ---------- Polygon çizim motoru ----------
    const cizim = {
        modu: 'idle',           // 'idle' | 'cizim' | 'duzenle'
        noktalar: [],           // L.LatLng[]
        polygon: null,          // L.Polygon (tamamlanmış)
        tempLine: null,         // L.Polyline (canlı önizleme)
        tempNoktaMarkerlari: [], // çizim sırasında noktalar
        vertexMarkerlari: [],   // düzenleme vertex tutamaçları
        edgeMarkerlari: [],     // orta-noktalar (yeni vertex ekleme)
        farePozisyonu: null,
    };

    // Ana menü butonları
    const btnCizim = document.getElementById('hrt-mbtn-cizim');
    const btnGps = document.getElementById('hrt-mbtn-gps');
    const btnBilgi = document.getElementById('hrt-mbtn-bilgi');
    const btnFullscreen = document.getElementById('hrt-mbtn-fullscreen');
    const btnSil = document.getElementById('hrt-mbtn-sil');
    // Çizim alt-şerit
    const cizimArac = document.getElementById('hrt-cizim-arac');
    const btnUndo = document.getElementById('hrt-tool-undo');
    const btnBitir = document.getElementById('hrt-tool-bitir');
    const btnIptal = document.getElementById('hrt-tool-iptal');

    function ipucu(metin) { hintEl.textContent = metin; }

    function modaGir(yeniMod) {
        // Temizle
        temizleGeciciCizim();
        temizleVertexler();

        cizim.modu = yeniMod;
        // Ana menü aktif vurgular
        btnCizim.classList.toggle('is-aktif', yeniMod === 'cizim');
        btnBilgi.classList.toggle('is-aktif', yeniMod === 'bilgi');
        // İmleç sınıfları
        haritaEl.classList.toggle('hrt-mod-cizim', yeniMod === 'cizim');
        haritaEl.classList.toggle('hrt-mod-duzenle', yeniMod === 'duzenle');
        haritaEl.classList.toggle('hrt-mod-bilgi', yeniMod === 'bilgi');
        // Alt-şerit görünürlüğü
        cizimArac.hidden = (yeniMod !== 'cizim');

        if (yeniMod === 'cizim') {
            ipucu('Boş alana tıklayın · İlk noktaya tıklayarak veya çift tıklayarak kapatın · ESC iptal');
            if (cizim.polygon) {
                harita.removeLayer(cizim.polygon);
                cizim.polygon = null;
                silButonuGoster(false);
            }
            cizim.noktalar = [];
            butonlariGuncelle();
        } else if (yeniMod === 'duzenle') {
            if (!cizim.polygon) { modaGir('idle'); return; }
            ipucu('Vertex sürükleyin · Orta noktaya yeni vertex · Vertex çift tık silme · Poligona çift tık: menüde Sil belirir');
            vertexleriCiz();
        } else if (yeniMod === 'bilgi') {
            ipucu('Haritada bir noktaya tıklayın — konum bilgisi görüntülenecek');
        } else {
            ipucu(cizim.polygon ? 'Poligon hazır — düzenlemek için üzerine tıklayın, silmek için çift tıklayın' : 'Menüden bir işlem seçin');
        }
    }

    function butonlariGuncelle() {
        const varPolygon = !!cizim.polygon;
        const cizimAktif = cizim.modu === 'cizim';
        btnUndo.disabled = !(cizimAktif && cizim.noktalar.length > 0);
        btnBitir.disabled = !(cizimAktif && cizim.noktalar.length >= 3);
        bilgiNokta.textContent = varPolygon
            ? cizim.polygon.getLatLngs()[0].length
            : cizim.noktalar.length;
    }

    function silButonuGoster(goster) {
        btnSil.hidden = !goster;
    }

    function temizleGeciciCizim() {
        if (cizim.tempLine) { harita.removeLayer(cizim.tempLine); cizim.tempLine = null; }
        cizim.tempNoktaMarkerlari.forEach(m => harita.removeLayer(m));
        cizim.tempNoktaMarkerlari = [];
    }

    function temizleVertexler() {
        cizim.vertexMarkerlari.forEach(m => harita.removeLayer(m));
        cizim.vertexMarkerlari = [];
        cizim.edgeMarkerlari.forEach(m => harita.removeLayer(m));
        cizim.edgeMarkerlari = [];
    }

    // --- Çizim sırasında nokta ekleme ---
    function noktaEkle(latlng) {
        cizim.noktalar.push(latlng);
        // görsel nokta
        const idx = cizim.noktalar.length - 1;
        const marker = noktaMarkeriYap(latlng, idx === 0);
        marker.on('click', function (e) {
            L.DomEvent.stopPropagation(e);
            // İlk noktaya tıklandıysa polygonu kapat
            if (idx === 0 && cizim.noktalar.length >= 3) polygonKapat();
        });
        cizim.tempNoktaMarkerlari.push(marker);
        cizimiCiz();
        butonlariGuncelle();
    }

    function noktaMarkeriYap(latlng, ilk = false) {
        const html = ilk
            ? '<div class="hrt-vertex hrt-vertex-first" title="İlk nokta — tıklayarak kapat"></div>'
            : '<div class="hrt-vertex"></div>';
        return L.marker(latlng, {
            icon: L.divIcon({ className: 'hrt-vertex-wrap', html, iconSize: [16, 16], iconAnchor: [8, 8] }),
            interactive: true,
            keyboard: false,
        }).addTo(harita);
    }

    function cizimiCiz() {
        if (cizim.tempLine) harita.removeLayer(cizim.tempLine);
        const noktalar = cizim.noktalar.slice();
        if (cizim.farePozisyonu && cizim.modu === 'cizim') noktalar.push(cizim.farePozisyonu);
        if (noktalar.length < 2) return;
        cizim.tempLine = L.polyline(noktalar, {
            color: '#f59e0b',
            weight: 2,
            dashArray: '4 4',
            opacity: 0.9,
            interactive: false,
        }).addTo(harita);
    }

    function polygonKapat() {
        if (cizim.noktalar.length < 3) return;
        const noktalar = cizim.noktalar.slice();
        temizleGeciciCizim();
        cizim.noktalar = [];

        cizim.polygon = L.polygon(noktalar, {
            color: '#f59e0b',
            weight: 2,
            fillColor: '#f59e0b',
            fillOpacity: 0.22,
            interactive: true,
        }).addTo(harita);

        poligonOlaylariBagla();
        formuGuncelle();
        modaGir('duzenle');
    }

    function poligonOlaylariBagla() {
        if (!cizim.polygon) return;
        cizim.polygon.on('click', function (e) {
            L.DomEvent.stopPropagation(e);
            if (cizim.modu !== 'duzenle') modaGir('duzenle');
        });
        cizim.polygon.on('dblclick', function (e) {
            L.DomEvent.stopPropagation(e);
            // Menüde Sil butonunu göster ve dikkat çek
            silButonuGoster(true);
            btnSil.classList.add('is-vurgu');
            setTimeout(() => btnSil.classList.remove('is-vurgu'), 1600);
            ipucu('Sağ üstteki Sil butonuna tıklayarak poligonu kaldırabilirsiniz');
        });
    }

    function polygonSil() {
        if (cizim.polygon) { harita.removeLayer(cizim.polygon); cizim.polygon = null; }
        if (parselEtiketi) { harita.removeLayer(parselEtiketi); parselEtiketi = null; }
        temizleGeciciCizim();
        temizleVertexler();
        cizim.noktalar = [];
        latInput.value = '';
        lngInput.value = '';
        koordinatInput.value = '';
        bilgiAlan.textContent = '—';
        bilgiCevre.textContent = '—';
        bilgiMerkez.textContent = '—';
        silButonuGoster(false);
        modaGir('idle');
    }

    function noktaGeriAl() {
        if (cizim.modu !== 'cizim' || cizim.noktalar.length === 0) return;
        cizim.noktalar.pop();
        const son = cizim.tempNoktaMarkerlari.pop();
        if (son) harita.removeLayer(son);
        cizimiCiz();
        butonlariGuncelle();
    }

    // --- Vertex düzenleme ---
    function vertexleriCiz() {
        temizleVertexler();
        if (!cizim.polygon) return;
        const halka = cizim.polygon.getLatLngs()[0];

        halka.forEach((latlng, i) => {
            const marker = L.marker(latlng, {
                draggable: true,
                icon: L.divIcon({ className: 'hrt-vertex-wrap', html: '<div class="hrt-vertex hrt-vertex-edit"></div>', iconSize: [14, 14], iconAnchor: [7, 7] }),
            }).addTo(harita);

            marker.on('drag', function (e) {
                halka[i] = e.target.getLatLng();
                cizim.polygon.setLatLngs([halka]);
                edgeMarkerlariniGuncelle();
                formuGuncelle();
            });

            marker.on('dblclick', function (e) {
                L.DomEvent.stopPropagation(e);
                if (halka.length <= 3) { ipucu('En az 3 vertex gerekli'); return; }
                halka.splice(i, 1);
                cizim.polygon.setLatLngs([halka]);
                vertexleriCiz();
                formuGuncelle();
            });

            cizim.vertexMarkerlari.push(marker);
        });

        edgeMarkerlariniGuncelle();
    }

    function edgeMarkerlariniGuncelle() {
        cizim.edgeMarkerlari.forEach(m => harita.removeLayer(m));
        cizim.edgeMarkerlari = [];
        if (!cizim.polygon) return;
        const halka = cizim.polygon.getLatLngs()[0];

        for (let i = 0; i < halka.length; i++) {
            const a = halka[i];
            const b = halka[(i + 1) % halka.length];
            const orta = L.latLng((a.lat + b.lat) / 2, (a.lng + b.lng) / 2);
            const idx = i + 1;
            const marker = L.marker(orta, {
                draggable: true,
                icon: L.divIcon({ className: 'hrt-vertex-wrap', html: '<div class="hrt-vertex hrt-vertex-edge"></div>', iconSize: [10, 10], iconAnchor: [5, 5] }),
            }).addTo(harita);

            marker.on('dragstart', function () {
                // Sürüklendiğinde yeni bir vertex olarak halkaya ekle
                halka.splice(idx, 0, marker.getLatLng());
                cizim.polygon.setLatLngs([halka]);
            });
            marker.on('drag', function (e) {
                halka[idx] = e.target.getLatLng();
                cizim.polygon.setLatLngs([halka]);
                formuGuncelle();
            });
            marker.on('dragend', function () {
                vertexleriCiz();
                formuGuncelle();
            });

            cizim.edgeMarkerlari.push(marker);
        }
    }

    // ---------- Form + bilgi güncelleme ----------
    function formuGuncelle() {
        if (!cizim.polygon) return;
        const halka = cizim.polygon.getLatLngs()[0];
        // GeoJSON (lng, lat)
        const koord = halka.map(p => [p.lng, p.lat]);
        if (koord.length > 0) koord.push([koord[0][0], koord[0][1]]);
        koordinatInput.value = JSON.stringify({ type: 'Polygon', coordinates: [koord] });

        const merkez = merkezHesapla(halka);
        latInput.value = merkez.lat.toFixed(7);
        lngInput.value = merkez.lng.toFixed(7);

        const alanM2 = geodesikAlan(halka);
        const cevreM = geodesikCevre(halka);
        bilgiAlan.textContent = alanBiciminde(alanM2);
        bilgiCevre.textContent = uzunlukBiciminde(cevreM);
        bilgiMerkez.textContent = merkez.lat.toFixed(5) + ', ' + merkez.lng.toFixed(5);
        bilgiNokta.textContent = halka.length;

        // Alan alanını otomatik doldur (kullanıcı elle değiştirmediyse override etme)
        if (alanInput && !alanInput.dataset.elleGirildi) {
            alanInput.value = formatlaTR(alanM2, 2);
        }
    }

    if (alanInput) {
        alanInput.addEventListener('input', () => { alanInput.dataset.elleGirildi = '1'; });
        alanInput.addEventListener('blur', () => {
            const n = parseTR(alanInput.value);
            if (n !== null) alanInput.value = formatlaTR(n, 2);
        });
    }

    function merkezHesapla(latlngs) {
        let lat = 0, lng = 0;
        latlngs.forEach(p => { lat += p.lat; lng += p.lng; });
        return { lat: lat / latlngs.length, lng: lng / latlngs.length };
    }

    // Geodesik (spherical excess) alan hesabı — m²
    function geodesikAlan(latlngs) {
        const R = 6378137;
        const n = latlngs.length;
        if (n < 3) return 0;
        let toplam = 0;
        for (let i = 0; i < n; i++) {
            const p1 = latlngs[i];
            const p2 = latlngs[(i + 1) % n];
            toplam += rad(p2.lng - p1.lng) * (2 + Math.sin(rad(p1.lat)) + Math.sin(rad(p2.lat)));
        }
        return Math.abs(toplam * R * R / 2);
    }
    function geodesikCevre(latlngs) {
        let toplam = 0;
        for (let i = 0; i < latlngs.length; i++) {
            toplam += latlngs[i].distanceTo(latlngs[(i + 1) % latlngs.length]);
        }
        return toplam;
    }
    function rad(d) { return d * Math.PI / 180; }

    // TR biçim: 1234.56 → "1.234,56"
    function formatlaTR(num, ondalik = 2) {
        if (!Number.isFinite(num)) return '';
        return num.toLocaleString('tr-TR', { minimumFractionDigits: ondalik, maximumFractionDigits: ondalik });
    }
    // "690,00" | "690.00" | "1.065,67" | "1,234.56" → number
    function parseTR(str) {
        if (str == null || str === '') return null;
        const s = String(str).trim().replace(/[\s\u00a0\u202f]/g, '');
        if (!s) return null;
        const m = s.match(/^(.+)[.,](\d{1,2})$/);
        let normalized;
        if (m) {
            normalized = m[1].replace(/[.,]/g, '') + '.' + m[2];
        } else {
            normalized = s.replace(/[.,]/g, '');
        }
        const n = parseFloat(normalized);
        return Number.isFinite(n) ? n : null;
    }

    function alanBiciminde(m2) {
        if (m2 >= 10000) return formatlaTR(m2 / 10000, 2) + ' ha';
        return formatlaTR(m2, 2) + ' m²';
    }
    function uzunlukBiciminde(m) {
        if (m >= 1000) return formatlaTR(m / 1000, 2) + ' km';
        return formatlaTR(m, 1) + ' m';
    }

    // ---------- Harita olayları ----------
    harita.on('click', function (e) {
        if (cizim.modu === 'cizim') {
            noktaEkle(e.latlng);
        } else if (cizim.modu === 'bilgi') {
            bilgiSorguYap(e.latlng);
        }
        // 'idle' ve 'duzenle' modlarında haritanın kendi click davranışı
    });

    harita.on('dblclick', function (e) {
        if (cizim.modu === 'cizim' && cizim.noktalar.length >= 3) {
            L.DomEvent.stopPropagation(e);
            polygonKapat();
        }
    });

    harita.on('mousemove', function (e) {
        if (cizim.modu !== 'cizim' || cizim.noktalar.length === 0) return;
        cizim.farePozisyonu = e.latlng;
        cizimiCiz();
    });

    // ---------- Klavye kısayolları ----------
    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) return;
        if (e.key === 'Escape') {
            if (cizim.modu === 'cizim') {
                cizim.noktalar = [];
                temizleGeciciCizim();
                modaGir(cizim.polygon ? 'duzenle' : 'idle');
            } else if (cizim.modu === 'bilgi') {
                modaGir('idle');
            }
        } else if (e.key === 'Enter') {
            if (cizim.modu === 'cizim' && cizim.noktalar.length >= 3) polygonKapat();
        } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
            noktaGeriAl();
        } else if (e.key === 'Delete') {
            if (cizim.polygon) polygonSil();
        } else if (e.key.toLowerCase() === 'p') {
            modaGir('cizim');
        } else if (e.key.toLowerCase() === 'f') {
            btnFullscreen.click();
        }
    });

    // ---------- Ana menü butonları ----------
    btnCizim.addEventListener('click', () => modaGir(cizim.modu === 'cizim' ? 'idle' : 'cizim'));
    btnBilgi.addEventListener('click', () => modaGir(cizim.modu === 'bilgi' ? 'idle' : 'bilgi'));
    btnSil.addEventListener('click', polygonSil);
    btnGps.addEventListener('click', function () {
        if (!navigator.geolocation) { ipucu('Tarayıcınız konum servisini desteklemiyor'); return; }
        ipucu('Konum alınıyor...');
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                harita.setView([pos.coords.latitude, pos.coords.longitude], 18);
                ipucu('Konumunuz haritada');
            },
            () => ipucu('Konum alınamadı')
        );
    });
    btnFullscreen.addEventListener('click', function () {
        const el = document.getElementById('hrt-shell');
        if (!document.fullscreenElement) {
            el.requestFullscreen && el.requestFullscreen();
        } else {
            document.exitFullscreen && document.exitFullscreen();
        }
    });
    document.addEventListener('fullscreenchange', () => {
        setTimeout(() => harita.invalidateSize(), 200);
    });

    // ---------- Çizim alt-şerit butonları ----------
    btnUndo.addEventListener('click', noktaGeriAl);
    btnBitir.addEventListener('click', function () {
        if (cizim.modu === 'cizim' && cizim.noktalar.length >= 3) polygonKapat();
    });
    btnIptal.addEventListener('click', function () {
        cizim.noktalar = [];
        temizleGeciciCizim();
        modaGir(cizim.polygon ? 'duzenle' : 'idle');
    });

    // ---------- Bilgi Sorgu (TKGM MEGSIS parsel) ----------
    const tkgmUrlPattern = @json(route('panel.ajax.tkgm-parsel', ['lat' => '__LAT__', 'lng' => '__LNG__']));

    async function bilgiSorguYap(latlng) {
        const popup = L.popup({ closeButton: true, autoClose: false, className: 'hrt-info-popup', maxWidth: 340 })
            .setLatLng(latlng)
            .setContent('<div class="hrt-popup-loading">TKGM parseli sorgulanıyor...</div>')
            .openOn(harita);

        try {
            const url = tkgmUrlPattern.replace('__LAT__', latlng.lat).replace('__LNG__', latlng.lng);
            const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const veri = await r.json();

            if (!r.ok || veri.hata) {
                popup.setContent(`<div class="hrt-popup-loading">${veri.hata || 'Sorgu başarısız (HTTP ' + r.status + ')'}</div>`);
                return;
            }

            if (!veri.geometry || !veri.properties) {
                popup.setContent('<div class="hrt-popup-loading">Bu konumda parsel bulunamadı</div>');
                return;
            }

            // Popup'ı kapat, parselYerlestir kendi popup'ını açacak
            harita.closePopup(popup);
            parselYerlestir(veri);

        } catch (e) {
            popup.setContent('<div class="hrt-popup-loading">Sorgu hatası: ' + e.message + '</div>');
        }
    }

    // ---------- TKGM parselini haritaya + forma yerleştir ----------
    function trAlanCevir(txt) {
        return parseTR(txt);
    }

    // Parsel etiketi marker'ı (bilgi/ada-parsel/KML sonrası haritada gösterilir)
    let parselEtiketi = null;
    function parselEtiketiGoster(latlng, adaParsel) {
        if (parselEtiketi) { harita.removeLayer(parselEtiketi); parselEtiketi = null; }
        if (!adaParsel) return;
        parselEtiketi = L.marker(latlng, {
            icon: L.divIcon({
                className: 'hrt-parsel-etiket-wrap',
                html: `<div class="hrt-parsel-etiket">${adaParsel}</div>`,
                iconAnchor: [40, 0],
            }),
            interactive: false,
        }).addTo(harita);
    }

    function parselYerlestir(veri) {
        const p = veri.properties || {};
        const gj = veri.geometry;

        // 1. Poligonu haritaya çiz (mevcut varsa kaldır)
        if (cizim.polygon) harita.removeLayer(cizim.polygon);
        if (parselEtiketi) { harita.removeLayer(parselEtiketi); parselEtiketi = null; }
        temizleGeciciCizim();
        temizleVertexler();

        const halka = gj.coordinates[0].map(c => L.latLng(c[1], c[0]));
        if (halka.length > 1 && halka[0].equals(halka[halka.length - 1])) halka.pop();

        cizim.polygon = L.polygon(halka, {
            color: '#f59e0b', weight: 2, fillColor: '#f59e0b', fillOpacity: 0.22,
        }).addTo(harita);
        poligonOlaylariBagla();
        harita.fitBounds(cizim.polygon.getBounds(), { padding: [50, 50] });
        formuGuncelle();
        modaGir('duzenle');

        // Parsel etiketi marker'ı — poligonun üst-orta noktasında "ada/parsel"
        if (p.adaNo || p.parselNo) {
            const bounds = cizim.polygon.getBounds();
            const etiketLatLng = L.latLng(bounds.getNorth(), (bounds.getWest() + bounds.getEast()) / 2);
            parselEtiketiGoster(etiketLatLng, `${p.adaNo || '—'}/${p.parselNo || '—'}`);
        }

        // 2. Popup — parsel bilgi kartı polygon merkezinde
        const merkez = cizim.polygon.getBounds().getCenter();
        const alanNumForPopup = trAlanCevir(p.alan);
        const alanTxt = alanNumForPopup !== null ? formatlaTR(alanNumForPopup, 2) + ' m²' : (p.alan || '—');
        const html = `
            <div class="hrt-popup-title">${p.ozet || (p.mahalleAd || '') + ' · ' + (p.adaNo || '') + '/' + (p.parselNo || '')}</div>
            <table class="hrt-popup-tablo">
                <tr><th>İl</th><td>${p.ilAd || '—'}</td></tr>
                <tr><th>İlçe</th><td>${p.ilceAd || '—'}</td></tr>
                <tr><th>Mahalle</th><td>${p.mahalleAd || '—'}</td></tr>
                <tr><th>Ada / Parsel</th><td>${p.adaNo || '—'} / ${p.parselNo || '—'}</td></tr>
                <tr><th>Alan</th><td>${alanTxt}</td></tr>
                <tr><th>Nitelik</th><td>${p.nitelik || '—'}</td></tr>
                ${p.pafta ? '<tr><th>Pafta</th><td>' + p.pafta + '</td></tr>' : ''}
                ${p.mevkii ? '<tr><th>Mevkii</th><td>' + p.mevkii + '</td></tr>' : ''}
            </table>
            <div class="hrt-popup-badge">✓ Form otomatik dolduruldu</div>
        `;
        L.popup({ closeButton: true, autoClose: false, className: 'hrt-info-popup', maxWidth: 340 })
            .setLatLng(merkez)
            .setContent(html)
            .openOn(harita);

        // 3. Formu doldur — text/number alanlar
        const setDeger = (id, val) => {
            const el = document.getElementById(id);
            if (el && val != null && val !== '') el.value = val;
        };
        setDeger('ada', p.adaNo);
        setDeger('parsel', p.parselNo);
        setDeger('nitelik', p.nitelik);

        const alanNum = trAlanCevir(p.alan);
        if (alanNum !== null) {
            const alanEl = document.getElementById('alan');
            if (alanEl) {
                alanEl.value = formatlaTR(alanNum, 2);
                alanEl.dataset.elleGirildi = '1'; // formuGuncelle override etmesin
            }
            // Bilgi çubuğunda da tapu/TKGM resmi alanını göster
            if (bilgiAlan) bilgiAlan.textContent = formatlaTR(alanNum, 2) + ' m²';
        }

        // 4. İl / İlçe / Mahalle cascade select (async)
        ilIlceMahalleDoldur(p.ilAd, p.ilceAd, p.mahalleAd, {
            ilTkgmId: p.ilId,
            ilceTkgmId: p.ilceId,
            mahalleTkgmId: p.mahalleId,
            ilId: veri._lokasyonIds?.ilId,
            ilceId: veri._lokasyonIds?.ilceId,
            mahalleId: veri._lokasyonIds?.mahalleId,
        });

        // 5. Ekstra: mevkii/pafta gibi bilgi varsa açıklama alanına ipucu bırak
        const aciklamaEl = document.getElementById('aciklama');
        if (aciklamaEl && !aciklamaEl.value.trim()) {
            const notlar = [];
            if (p.zeminKmdurum) notlar.push('Zemin/KM Durum: ' + p.zeminKmdurum);
            if (p.pafta) notlar.push('Pafta: ' + p.pafta);
            if (p.mevkii) notlar.push('Mevkii: ' + p.mevkii);
            if (notlar.length) aciklamaEl.value = 'TKGM sorgu (' + new Date().toLocaleString('tr') + ')\n' + notlar.join(' · ');
        }

        ipucu('Parsel yerleştirildi ve form dolduruldu (' + halka.length + ' nokta)');
    }

    // ---------- Cascade select otomatik doldurma ----------
    function trFold(s) {
        return String(s || '')
            .toLocaleLowerCase('tr')
            .replace(/i̇/g, 'i')
            .replace(/ı/g, 'i')
            .replace(/ğ/g, 'g')
            .replace(/ü/g, 'u')
            .replace(/ş/g, 's')
            .replace(/ö/g, 'o')
            .replace(/ç/g, 'c')
            .replace(/â/g, 'a')
            .replace(/î/g, 'i')
            .replace(/û/g, 'u')
            .replace(/[.,/\-_]/g, ' ')
            .replace(/\b(mahallesi|mahalle|mah|mh|koyu|koy|beldesi|belde)\b/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function selectDegerYay(select, opt) {
        if (!opt) return null;
        select.value = String(opt.value);
        opt.selected = true;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        return opt;
    }

    function selectOptionBulSec(select, hedefAd, tkgmId, yerelId) {
        const opts = Array.from(select.options).filter(o => o.value);
        let opt = null;
        if (yerelId != null && yerelId !== '') {
            opt = opts.find(o => String(o.value) === String(yerelId));
        }
        if (!opt && tkgmId != null && tkgmId !== '') {
            opt = opts.find(o => o.dataset.tkgmId && String(o.dataset.tkgmId) === String(tkgmId));
        }
        if (!opt && hedefAd) {
            const hedef = trFold(hedefAd);
            if (hedef) {
                opt = opts.find(o => trFold(o.textContent) === hedef);
                if (!opt) {
                    const kismi = opts.filter(o => {
                        const n = trFold(o.textContent);
                        return n && (n.includes(hedef) || hedef.includes(n));
                    });
                    kismi.sort((a, b) => trFold(b.textContent).length - trFold(a.textContent).length);
                    opt = kismi[0] || null;
                }
            }
        }
        return opt ? selectDegerYay(select, opt) : null;
    }

    function selectDolduruncaBekle(select, zamanAsimi = 8000) {
        return new Promise((resolve) => {
            const t0 = Date.now();
            const tick = () => {
                if (select.options.length > 1 && !select.disabled) {
                    resolve(true);
                    return;
                }
                if (Date.now() - t0 > zamanAsimi) {
                    resolve(false);
                    return;
                }
                setTimeout(tick, 50);
            };
            // Cascade handler senkron temizlesin, sonra bekle
            setTimeout(tick, 0);
        });
    }

    async function ilIlceMahalleDoldur(ilAd, ilceAd, mahalleAd, ids = {}) {
        const ilSel = document.getElementById('il_id');
        const ilceSel = document.getElementById('ilce_id');
        const mahalleSel = document.getElementById('mahalle_id');
        if (!ilSel) return;

        const ilOpt = selectOptionBulSec(ilSel, ilAd, ids.ilTkgmId, ids.ilId);
        if (!ilOpt) {
            if (ilAd) ipucu('İl "' + ilAd + '" listede bulunamadı');
            return;
        }

        if (!ilceAd && !ids.ilceId && !ids.ilceTkgmId) return;
        const ilceHazir = await selectDolduruncaBekle(ilceSel);
        if (!ilceHazir) return;
        const ilceOpt = selectOptionBulSec(ilceSel, ilceAd, ids.ilceTkgmId, ids.ilceId);
        if (!ilceOpt) {
            ipucu('İlçe "' + (ilceAd || ids.ilceId) + '" listede bulunamadı');
            return;
        }

        if (!mahalleAd && !ids.mahalleId && !ids.mahalleTkgmId) return;
        const mahalleHazir = await selectDolduruncaBekle(mahalleSel);
        if (!mahalleHazir) return;
        const mahalleOpt = selectOptionBulSec(mahalleSel, mahalleAd, ids.mahalleTkgmId, ids.mahalleId);
        if (!mahalleOpt) ipucu('Mahalle "' + (mahalleAd || ids.mahalleId) + '" listede bulunamadı');
    }

    // ---------- Ada / Parsel Sorgu (TKGM cascade) ----------
    const apIl = document.getElementById('hrt-ap-il');
    const apIlce = document.getElementById('hrt-ap-ilce');
    const apMahalle = document.getElementById('hrt-ap-mahalle');
    const apAda = document.getElementById('hrt-ap-ada');
    const apParsel = document.getElementById('hrt-ap-parsel');
    const apSorgulaBtn = document.getElementById('hrt-ap-sorgula');
    const apInfoEl = document.getElementById('hrt-ap-info');
    @php
        $__tkgmApUrl = route('panel.ajax.tkgm-parsel-adaparsel', ['mahalleTkgmId' => 0, 'ada' => '__ADA__', 'parsel' => '__PARSEL__']);
        $__apIlceUrl = route('panel.ajax.ilceler', ['il' => 0]);
        $__apMahalleUrl = route('panel.ajax.mahalleler', ['ilce' => 0]);
    @endphp
    const tkgmApUrlPattern = @json($__tkgmApUrl);
    const apIlceUrlPattern = @json($__apIlceUrl);
    const apMahalleUrlPattern = @json($__apMahalleUrl);

    function apInfo(mesaj, tur) {
        apInfoEl.textContent = mesaj;
        apInfoEl.className = 'hrt-form-info' + (tur ? ' is-' + tur : '');
    }

    function apSelectBosalt(select, placeholder, enable = false) {
        select.innerHTML = '<option value="">' + placeholder + '</option>';
        select.disabled = !enable;
    }

    function apButonDurum() {
        const secili = apMahalle.value && apMahalle.selectedOptions[0]?.dataset.tkgmId;
        const hasAdaParsel = apAda.value.trim() && apParsel.value.trim();
        apSorgulaBtn.disabled = !(secili && hasAdaParsel);
    }

    apIl.addEventListener('change', async function () {
        apSelectBosalt(apIlce, '— Yükleniyor... —');
        apSelectBosalt(apMahalle, '— Önce ilçe seçin —');
        apButonDurum();
        if (!this.value) { apSelectBosalt(apIlce, '— Önce il seçin —'); return; }
        try {
            const url = apIlceUrlPattern.replace(/\/0(?=[/?]|$)/, '/' + this.value);
            const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const ilceler = await r.json();
            apSelectBosalt(apIlce, '— İlçe seçin —', true);
            ilceler.forEach(i => {
                const opt = new Option(i.ad, i.id);
                if (i.tkgm_id) opt.dataset.tkgmId = i.tkgm_id;
                apIlce.add(opt);
            });
        } catch (e) {
            apSelectBosalt(apIlce, '— Yükleme hatası —');
        }
    });

    apIlce.addEventListener('change', async function () {
        apSelectBosalt(apMahalle, '— Yükleniyor... —');
        apButonDurum();
        if (!this.value) { apSelectBosalt(apMahalle, '— Önce ilçe seçin —'); return; }
        try {
            const url = apMahalleUrlPattern.replace(/\/0(?=[/?]|$)/, '/' + this.value);
            const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const mahalleler = await r.json();
            apSelectBosalt(apMahalle, '— Mahalle seçin —', true);
            mahalleler.forEach(m => {
                const opt = new Option(m.ad, m.id);
                if (m.tkgm_id) opt.dataset.tkgmId = m.tkgm_id;
                apMahalle.add(opt);
            });
        } catch (e) {
            apSelectBosalt(apMahalle, '— Yükleme hatası —');
        }
    });

    apMahalle.addEventListener('change', apButonDurum);
    apAda.addEventListener('input', apButonDurum);
    apParsel.addEventListener('input', apButonDurum);

    apSorgulaBtn.addEventListener('click', async function () {
        const opt = apMahalle.selectedOptions[0];
        const tkgmId = opt?.dataset.tkgmId;
        const ada = apAda.value.trim();
        const parsel = apParsel.value.trim();
        if (!tkgmId) { apInfo('Seçilen mahallenin TKGM ID bilgisi yok.', 'error'); return; }
        if (!ada || !parsel) { apInfo('Ada ve parsel gerekli.', 'error'); return; }

        apInfo('TKGM sorgulanıyor...');
        apSorgulaBtn.disabled = true;
        try {
            const url = tkgmApUrlPattern
                .replace(/\/0(?=\/)/, '/' + tkgmId)
                .replace('__ADA__', encodeURIComponent(ada))
                .replace('__PARSEL__', encodeURIComponent(parsel));
            const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const veri = await r.json();
            if (!r.ok || veri.hata) {
                apInfo(veri.hata || 'Sorgu başarısız (HTTP ' + r.status + ')', 'error');
                return;
            }
            if (!veri.geometry || !veri.properties) {
                apInfo('Parsel bulunamadı.', 'error');
                return;
            }
            veri._lokasyonIds = {
                ilId: apIl.value,
                ilceId: apIlce.value,
                mahalleId: apMahalle.value,
            };
            parselYerlestir(veri);
            apInfo('Parsel bulundu ve haritaya yerleştirildi.', 'ok');
            document.getElementById('hrt-dd-adaparsel').classList.remove('is-acik');
        } catch (e) {
            apInfo('Sorgu hatası: ' + e.message, 'error');
        } finally {
            apSorgulaBtn.disabled = false;
            apButonDurum();
        }
    });

    // ---------- KML Yükle ----------
    // KML → TKGM benzeri veri objesi → parselYerlestir (sorguyla aynı akış)
    document.getElementById('hrt-kml-file').addEventListener('change', function (e) {
        const dosya = e.target.files && e.target.files[0];
        if (!dosya) return;
        const okuyucu = new FileReader();
        okuyucu.onload = function (ev) {
            try {
                const xml = new DOMParser().parseFromString(ev.target.result, 'text/xml');
                if (xml.getElementsByTagName('parsererror').length) { ipucu('KML okunamadı — geçersiz XML'); return; }

                // 1) Polygon coordinates
                const koordEl = xml.querySelector('Polygon coordinates') || xml.querySelector('coordinates');
                if (!koordEl) { ipucu('KML içinde Polygon bulunamadı'); return; }
                const raw = koordEl.textContent.trim().split(/\s+/);
                const coords = raw.map(s => {
                    const parts = s.split(',');
                    const lng = parseFloat(parts[0]);
                    const lat = parseFloat(parts[1]);
                    return (Number.isFinite(lat) && Number.isFinite(lng)) ? [lng, lat] : null;
                }).filter(Boolean);
                if (coords.length < 3) { ipucu('KML poligonu geçersiz (< 3 nokta)'); return; }
                // Halkayı kapat (parselYerlestir ilk=son ise pop ediyor)
                if (coords[0][0] !== coords[coords.length - 1][0] || coords[0][1] !== coords[coords.length - 1][1]) {
                    coords.push([coords[0][0], coords[0][1]]);
                }

                // 2) ExtendedData / SimpleData → key-value (TKGM KML her iki biçimi de kullanır)
                const kv = {};
                const kvYaz = (key, val) => {
                    const k = (key || '').trim();
                    const v = (val || '').trim();
                    if (!k || v === '') return;
                    kv[k] = v;
                    kv[k.toLocaleLowerCase('tr')] = v;
                };
                xml.querySelectorAll('ExtendedData Data').forEach(d => {
                    kvYaz(d.getAttribute('name'), d.querySelector('value')?.textContent);
                });
                xml.querySelectorAll('SimpleData').forEach(d => {
                    kvYaz(d.getAttribute('name'), d.textContent);
                });
                const val = (...keys) => {
                    for (const k of keys) {
                        if (kv[k] != null && kv[k] !== '') return kv[k];
                        const low = String(k).toLocaleLowerCase('tr');
                        if (kv[low] != null && kv[low] !== '') return kv[low];
                    }
                    return '';
                };

                // 3) TKGM-uyumlu properties
                const properties = {
                    ilAd: val('İl', 'il', 'ilAd', 'IlAd', 'IL'),
                    ilceAd: val('İlçe', 'ilce', 'ilceAd', 'IlceAd', 'ILCE'),
                    mahalleAd: val('Mahalle', 'mahalle', 'mahalleAd', 'MahalleAd', 'MAHALLE'),
                    ilId: val('ilId', 'IlId', 'il_id'),
                    ilceId: val('ilceId', 'IlceId', 'ilce_id'),
                    mahalleId: val('mahalleId', 'MahalleId', 'mahalle_id', 'MahalleID'),
                    adaNo: val('Ada', 'AdaNo', 'ada', 'adaNo', 'ADA'),
                    parselNo: val('ParselNo', 'Parsel', 'parsel', 'parselNo', 'PARSEL'),
                    alan: val('Alan', 'alan', 'ALAN'),
                    nitelik: val('Nitelik', 'nitelik', 'NITELIK'),
                    pafta: val('Pafta', 'pafta'),
                    mevkii: val('Mevkii', 'mevkii'),
                    zeminKmdurum: val('ZeminKmDurum', 'zeminKmdurum', 'Durum'),
                    ozet: '',
                };
                const nameEl = xml.querySelector('Placemark name') || xml.querySelector('name');
                properties.ozet = (nameEl?.textContent?.trim())
                    || ((properties.mahalleAd || '') + ' · ' + (properties.adaNo || '') + '/' + (properties.parselNo || ''));

                const veri = {
                    type: 'Feature',
                    geometry: { type: 'Polygon', coordinates: [coords] },
                    properties,
                };

                // 4) Aynı yerleştirme akışı (popup + form + cascade)
                parselYerlestir(veri);
                ipucu('KML yüklendi: ' + (coords.length - 1) + ' nokta · Form dolduruldu');
            } catch (err) {
                ipucu('KML okuma hatası: ' + err.message);
            }
        };
        okuyucu.readAsText(dosya);
        e.target.value = ''; // aynı dosyayı tekrar seçebilmek için
    });

    // ---------- Arama (Nominatim) ----------
    const aramaInput = document.getElementById('hrt-arama');
    const aramaBtn = document.getElementById('hrt-arama-btn');
    const aramaSonucEl = document.getElementById('hrt-arama-sonuc');
    let aramaZamanlayici = null;

    function aramaYap() {
        const q = aramaInput.value.trim();
        if (!q) return;

        // Direkt koordinat girişi: "39.92, 32.85"
        const koordMatch = q.match(/^(-?\d+([.,]\d+)?)[,\s]+(-?\d+([.,]\d+)?)$/);
        if (koordMatch) {
            const lat = parseFloat(koordMatch[1].replace(',', '.'));
            const lng = parseFloat(koordMatch[3].replace(',', '.'));
            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                harita.setView([lat, lng], 18);
                aramaSonucEl.hidden = true;
                ipucu(`Koordinat merkezlendi: ${lat.toFixed(5)}, ${lng.toFixed(5)}`);
                return;
            }
        }

        aramaSonucEl.innerHTML = '<div class="hrt-arama-item is-loading">Aranıyor...</div>';
        aramaSonucEl.hidden = false;

        fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=5&countrycodes=tr&q=${encodeURIComponent(q)}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(sonuclar => {
            if (!sonuclar || sonuclar.length === 0) {
                aramaSonucEl.innerHTML = '<div class="hrt-arama-item is-bos">Sonuç bulunamadı</div>';
                return;
            }
            aramaSonucEl.innerHTML = '';
            sonuclar.forEach(s => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'hrt-arama-item';
                item.innerHTML = `
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21c-4-5-7-8-7-12a7 7 0 0 1 14 0c0 4-3 7-7 12z"/><circle cx="12" cy="9" r="2.5"/></svg>
                    <span>${s.display_name}</span>
                `;
                item.addEventListener('click', () => {
                    harita.setView([parseFloat(s.lat), parseFloat(s.lon)], 17);
                    aramaSonucEl.hidden = true;
                    aramaInput.value = s.display_name.split(',')[0];
                });
                aramaSonucEl.appendChild(item);
            });
        })
        .catch(() => {
            aramaSonucEl.innerHTML = '<div class="hrt-arama-item is-bos">Arama hatası</div>';
        });
    }

    aramaBtn.addEventListener('click', aramaYap);
    aramaInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); aramaYap(); }
    });
    aramaInput.addEventListener('input', function () {
        clearTimeout(aramaZamanlayici);
        if (!this.value.trim()) { aramaSonucEl.hidden = true; return; }
        aramaZamanlayici = setTimeout(aramaYap, 500);
    });
    document.addEventListener('click', (e) => {
        if (!aramaSonucEl.contains(e.target) && e.target !== aramaInput) {
            aramaSonucEl.hidden = true;
        }
    });

    // ---------- Eski koordinat geri yükleme (validation hatası sonrası) ----------
    if (eskiKoordinat) {
        try {
            const gj = JSON.parse(eskiKoordinat);
            if (gj && gj.type === 'Polygon' && gj.coordinates && gj.coordinates[0]) {
                const halka = gj.coordinates[0].slice(0, -1).map(p => L.latLng(p[1], p[0]));
                cizim.polygon = L.polygon(halka, {
                    color: '#f59e0b', weight: 2, fillColor: '#f59e0b', fillOpacity: 0.22,
                }).addTo(harita);
                poligonOlaylariBagla();
                harita.fitBounds(cizim.polygon.getBounds(), { padding: [40, 40] });
                formuGuncelle();
                modaGir('duzenle');
            }
        } catch (e) { /* yut */ }
    }

    // Sidebar toggle sonrası boyut tazele
    setTimeout(() => harita.invalidateSize(), 200);
    window.addEventListener('resize', () => harita.invalidateSize());
    // Formda selected il/ilçe/mahalle ilan olduğunda otomatik zoom yok — kullanıcı manuel
})();
</script>
@endpush
