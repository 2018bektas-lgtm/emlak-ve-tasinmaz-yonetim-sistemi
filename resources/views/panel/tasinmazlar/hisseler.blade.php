@extends('layouts.panel')

@section('title', 'Hisse Listesi')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/tasinmaz-liste.css') }}?v=7">
@endpush

@section('content')
@php
    $sayi = function ($deger, int $ondalik = 2): string {
        if ($deger === null || $deger === '') {
            return '—';
        }
        $metin = number_format((float) $deger, $ondalik, ',', '.');

        return $ondalik > 0 ? (rtrim(rtrim($metin, '0'), ',') ?: '0') : $metin;
    };
    $carpi = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>';
    $aktifFiltreler = collect($filtre)->filter(fn ($v) => filled($v))->keys();
    $islemSecenekleri = ['alis' => 'Alış', 'satis' => 'Satış'];
    $metinler = [
        ['Nitelik', 'nitelik', 'Arsa, tarla…'],
        ['Hisse no', 'hisse_no', '7'],
        ['Edinme', 'edinme', 'Satın alma'],
        ['Yevmiye', 'yevmiye', '555'],
        ['Kayıtlardan çıkış', 'cikis', 'Terkin'],
    ];
    $payAlanlar = [
        ['Pay', 'pay_min', 'pay_max', '1'],
        ['Payda', 'payda_min', 'payda_max', '2'],
        ['Oran %', 'oran_min', 'oran_max', '25'],
        ['Hisse m²', 'hisse_min', 'hisse_max', '50'],
        ['Parsel m²', 'alan_min', 'alan_max', '200'],
        ['Hisse adedi', 'adet_min', 'adet_max', '1'],
        ['Aktif hisse', 'aktif_min', 'aktif_max', '1'],
    ];
    $bedeller = [
        ['Rayiç ₺', 'rayic_min', 'rayic_max', '1.000'],
        ['Maliyet ₺', 'maliyet_min', 'maliyet_max', '5.000'],
        ['Emlak VD ₺', 'emlak_min', 'emlak_max', '250'],
        ['İz bedeli ₺', 'iz_min', 'iz_max', '100'],
    ];
    $tarihler = [
        ['Edinme tarihi', 'tarih_min', 'tarih_max'],
        ['Çıkış tarihi', 'cikis_tarih_min', 'cikis_tarih_max'],
    ];
    $araliklar = array_merge($payAlanlar, $bedeller, $tarihler);
    $gelismisAnahtarlar = array_merge(array_column($metinler, 1), array_column($araliklar, 1), array_column($araliklar, 2));
    $gelismisAcik = collect($gelismisAnahtarlar)->contains(fn ($k) => filled($filtre[$k] ?? null));
    $aralikMetni = function (string $min, string $max): string {
        if ($min !== '' && $max !== '') {
            return $min.' – '.$max;
        }

        return $min !== '' ? '≥ '.$min : '≤ '.$max;
    };
    $tarihYazi = function (string $iso): string {
        if ($iso === '') {
            return '';
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $iso);

        return $dt ? $dt->format('d.m.Y') : $iso;
    };
    $ilAdi = $filtre['il_id'] ? optional($iller->firstWhere('id', (int) $filtre['il_id']))->ad : null;
    $ilceAdi = $filtre['ilce_id'] ? optional($ilceler->firstWhere('id', (int) $filtre['ilce_id']))->ad : null;
    $mahalleAdi = $filtre['mahalle_id'] ? optional($mahalleler->firstWhere('id', (int) $filtre['mahalle_id']))->ad : null;
    $durumLink = fn (?string $durum) => route('panel.tasinmazlar.hisseler', array_filter(
        array_merge(request()->except(['page', 'durum']), ['durum' => $durum]),
        fn ($v) => $v !== null && $v !== ''
    ));
    $durumKartlari = [
        null => ['Tümü', $toplamKayit, $toplamM2, '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'],
        'aktif' => ['Aktif', $durumSayilari['aktif'] ?? 0, $durumM2['aktif'] ?? 0, '<path d="M5 12l5 5L20 7"/>'],
        'pasif' => ['Pasif', $durumSayilari['pasif'] ?? 0, $durumM2['pasif'] ?? 0, '<path d="M4 12h16"/>'],
    ];
    $goster = fn (string $k) => in_array($k, $gorunurKolonlar, true);
    $monoKolon = ['ada', 'parsel', 'hisse_no', 'pay_payda', 'oran', 'hisse_m2', 'parsel_m2', 'tarih', 'yevmiye', 'rayic', 'maliyet', 'emlak_vd', 'iz_bedeli', 'cikis_tarihi', 'aktif_hisse'];
    $exportSorgu = http_build_query(request()->except(['page']));
    $exportEk = $exportSorgu !== '' ? '?'.$exportSorgu : '';
    $secilenSayi = count($gorunurKolonlar);
    $toplamKolon = count($tumKolonlar);
    $kolonAnahtarIkonlar = [
        'pin' => '<path d="M12 22s-7-7-7-13a7 7 0 0 1 14 0c0 6-7 13-7 13z"/><circle cx="12" cy="9" r="2.6"/>',
        'ruler' => '<path d="M2 15 L15 2 l7 7 L9 22z"/><path d="M6 15 h1 M9 12 h1 M12 9 h1 M15 6 h1"/>',
        'layers' => '<path d="M12 3 L2 8 l10 5 10-5z"/><path d="M2 13 l10 5 10-5"/><path d="M2 18 l10 5 10-5"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
    ];
@endphp
<div class="tl-sayfa">
<section class="page-hero">
    <div class="page-hero-text">
        <p class="tl-eyebrow">Envanter</p>
        <h2>Hisseler</h2>
        <small>
            <strong>{{ number_format($hisseler->total(), 0, ',', '.') }}</strong> sonuç
            <span aria-hidden="true">·</span> toplam {{ number_format($toplamKayit, 0, ',', '.') }} kayıt
            <span aria-hidden="true">·</span> bu sayfada {{ $hisseler->count() }}
        </small>
    </div>
    <div class="page-hero-actions">
        <button type="button" class="btn-cancel kp-tetik" id="kp-tetik" aria-haspopup="dialog">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="6" height="18" rx="1"/><rect x="11" y="3" width="10" height="8" rx="1"/><rect x="11" y="13" width="10" height="8" rx="1"/></svg>
            Kolonlar
            <span class="kp-tetik-sayi" data-kp-sayac>{{ $secilenSayi }}<em>/{{ $toplamKolon }}</em></span>
        </button>
        <div class="kp-backdrop" id="kp-backdrop" hidden>
            <div class="kp-panel" role="dialog" aria-modal="true" aria-label="Kolon seçimi">
                <div class="kp-head">
                    <div>
                        <h3>Kolonları Yönet</h3>
                        <small><span data-kp-sayac>{{ $secilenSayi }}</span> / {{ $toplamKolon }} kolon seçili</small>
                    </div>
                    <button type="button" class="kp-kapat" id="kp-kapat" aria-label="Kapat">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="kp-ara-wrap">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
                    <input type="search" id="kp-ara" placeholder="Kolon ara..." autocomplete="off">
                    <button type="button" class="kp-ara-temizle" id="kp-ara-temizle" aria-label="Aramayı temizle" hidden>
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="kp-toplu">
                    <button type="button" class="kp-mini-btn" data-kp-eylem="tumu">Tümünü seç</button>
                    <button type="button" class="kp-mini-btn" data-kp-eylem="hicbiri">Hiçbirini seçme</button>
                    <button type="button" class="kp-mini-btn" data-kp-eylem="varsayilan">Varsayılan</button>
                </div>
                <div class="kp-liste" id="kp-liste">
                    @foreach ($kolonGruplari as $grupAd => $grup)
                        @php $grupKolonlar = $grup['kolonlar']; @endphp
                        @if (empty($grupKolonlar)) @continue @endif
                        <div class="kp-grup" data-kp-grup>
                            <div class="kp-grup-head">
                                <span class="kp-grup-ikon">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $kolonAnahtarIkonlar[$grup['ikon']] ?? '' !!}</svg>
                                </span>
                                <span class="kp-grup-ad">{{ $grupAd }}</span>
                                <span class="kp-grup-sayac" data-kp-grup-sayac>0/{{ count($grupKolonlar) }}</span>
                                <button type="button" class="kp-grup-toggle" data-kp-grup-toggle title="Bu grubun tümünü aç/kapa">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                                </button>
                            </div>
                            <div class="kp-grup-icerik">
                                @foreach ($grupKolonlar as $anahtar => $etiket)
                                    <label class="kp-secim" data-kp-anahtar="{{ $anahtar }}">
                                        <input type="checkbox" class="kp-cb" value="{{ $anahtar }}" @checked(in_array($anahtar, $gorunurKolonlar, true))>
                                        <span class="kp-secim-kutucuk" aria-hidden="true">
                                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                                        </span>
                                        <span class="kp-secim-ad">{{ $etiket }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <p class="kp-bos" id="kp-bos" hidden>Aramanıza uyan kolon yok.</p>
                </div>
                <div class="kp-alt">
                    <span class="kp-alt-durum" id="kp-durum" hidden></span>
                    <button type="button" class="btn-cancel" id="kp-vazgec">Kapat</button>
                </div>
            </div>
        </div>
        <div class="tl-dd tl-hero-dd" id="hl-indir-dd">
            <button type="button" class="btn-cancel tl-dd-btn" data-dd-toggle data-indir-btn>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                İndir <span class="tl-indir-sayac" data-indir-sayac hidden></span>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="tl-dd-menu tl-indir-menu">
                <p class="tl-indir-hint" data-indir-hint>
                    Excel ve PDF seçili kolonlarla iner. Satır seçilmezse mevcut filtre, seçilirse yalnız o satırlar.
                </p>
                <a href="{{ route('panel.tasinmazlar.hisseler.excel').$exportEk }}" data-indir-eylem="excel" data-base="{{ route('panel.tasinmazlar.hisseler.excel') }}" data-filtre="{{ $exportSorgu }}" class="tl-dd-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 12h8M12 8v8"/></svg>
                    <span>Excel <em>(.xlsx)</em></span>
                </a>
                <a href="{{ route('panel.tasinmazlar.hisseler.kml').$exportEk }}" data-indir-eylem="kml" data-base="{{ route('panel.tasinmazlar.hisseler.kml') }}" data-filtre="{{ $exportSorgu }}" class="tl-dd-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-7-7-7-13a7 7 0 0 1 14 0c0 6-7 13-7 13z"/><circle cx="12" cy="9" r="2.6"/></svg>
                    <span>KML <em>(parsel sınırı)</em></span>
                </a>
                <a href="{{ route('panel.tasinmazlar.hisseler.pdf').$exportEk }}" data-indir-eylem="pdf" data-base="{{ route('panel.tasinmazlar.hisseler.pdf') }}" data-filtre="{{ $exportSorgu }}" target="_blank" rel="noopener" class="tl-dd-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                    <span>PDF / Yazdır</span>
                </a>
            </div>
        </div>
        <a href="{{ route('panel.tasinmazlar.index') }}" class="btn-cancel">Taşınmaz Listesi</a>
    </div>
</section>

<nav class="tl-tipler" aria-label="Hisse durumuna göre süz">
    @foreach ($durumKartlari as $durum => [$ad, $adet, $m2, $ikon])
        @php $aktif = ($filtre['durum'] ?? null) === ($durum ?: null); @endphp
        <a href="{{ $durumLink($durum ?: null) }}" @class(['tl-tip', 'is-aktif' => $aktif]) @if ($aktif) aria-current="true" @endif>
            <span class="tl-tip-ikon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $ikon !!}</svg>
            </span>
            <span class="tl-tip-metin">
                <span class="tl-tip-ad">{{ $ad }}</span>
                <strong class="tl-tip-adet">{{ number_format($m2, 2, ',', '.') }} <em>m²</em></strong>
                <span class="tl-tip-m2">{{ number_format($adet, 0, ',', '.') }} hisse</span>
            </span>
        </a>
    @endforeach
</nav>

<form method="GET" action="{{ route('panel.tasinmazlar.hisseler') }}" class="tl-filtre hl-filtre" id="hl-filtre-form">
    @if ($filtre['durum'])
        <input type="hidden" name="durum" value="{{ $filtre['durum'] }}">
    @endif
    <div class="hl-filtre-grid">
        <label class="hl-alan">
            <span>Ada</span>
            <input type="text" name="ada" value="{{ $filtre['ada'] }}" inputmode="numeric" autocomplete="off" placeholder="123">
        </label>
        <label class="hl-alan">
            <span>Parsel</span>
            <input type="text" name="parsel" value="{{ $filtre['parsel'] }}" inputmode="numeric" autocomplete="off" placeholder="4">
        </label>
        <label class="hl-alan">
            <span>İl</span>
            <select name="il_id" class="form-select" id="hl-fltr-il">
                <option value="">Tümü</option>
                @foreach ($iller as $il)
                    <option value="{{ $il->id }}" @selected($filtre['il_id'] == $il->id)>{{ $il->ad }}</option>
                @endforeach
            </select>
        </label>
        <label class="hl-alan">
            <span>İlçe</span>
            <select name="ilce_id" class="form-select" id="hl-fltr-ilce">
                <option value="">Tümü</option>
                @foreach ($ilceler as $ilce)
                    <option value="{{ $ilce->id }}" @selected($filtre['ilce_id'] == $ilce->id)>{{ $ilce->ad }}</option>
                @endforeach
            </select>
        </label>
        <label class="hl-alan">
            <span>Mahalle</span>
            <select name="mahalle_id" class="form-select" id="hl-fltr-mahalle">
                <option value="">Tümü</option>
                @foreach ($mahalleler as $mah)
                    <option value="{{ $mah->id }}" @selected($filtre['mahalle_id'] == $mah->id)>{{ $mah->ad }}</option>
                @endforeach
            </select>
        </label>
        <label class="hl-alan hl-alan-ara">
            <span>Ara</span>
            <input type="search" name="q" value="{{ $filtre['q'] }}" placeholder="Hisse no, yevmiye, edinme, nitelik…" autocomplete="off">
        </label>
    </div>
    <div class="hl-filtre-alt">
        <div class="hl-islem" role="radiogroup" aria-label="İşlem tipi">
            <span>İşlem</span>
            <label>
                <input type="radio" name="islem" value="" @checked(! $filtre['islem'])>
                <span>Tümü</span>
            </label>
            @foreach ($islemSecenekleri as $val => $lbl)
                <label>
                    <input type="radio" name="islem" value="{{ $val }}" @checked($filtre['islem'] === $val)>
                    <span>{{ $lbl }}</span>
                </label>
            @endforeach
        </div>
        <button type="button" class="tl-filtre-toggle" id="hl-filtre-toggle" aria-expanded="{{ $gelismisAcik ? 'true' : 'false' }}" aria-controls="hl-filtre-gelismis">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M7 12h10M10 18h4"/></svg>
            Gelişmiş
            <svg class="tl-filtre-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <button type="submit" class="btn-submit tl-filtre-btn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            Listele
        </button>
        @if ($aktifFiltreler->isNotEmpty())
            <a href="{{ route('panel.tasinmazlar.hisseler') }}" class="tl-filtre-clear" title="Tüm filtreleri temizle">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                Temizle
            </a>
        @endif
    </div>

    <section class="form-section tl-filtre-gelismis" id="hl-filtre-gelismis"@unless ($gelismisAcik) hidden @endunless>
        <div class="form-section-head">
            <span class="form-section-num">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h18M6 12h12M10 20h4"/></svg>
            </span>
            <div class="form-section-head-text">
                <h3>Gelişmiş Filtre</h3>
                <small>Tablodaki bütün alanlar. Listele ile uygulanır.</small>
            </div>
        </div>
        <div class="hl-gelismis-grup">
            <p class="hl-gelismis-baslik">Kayıt</p>
            <div class="hl-metinler">
                @foreach ($metinler as [$ad, $anahtar, $ornek])
                    <label class="hl-alan">
                        <span>{{ $ad }}</span>
                        <input type="text" name="{{ $anahtar }}" value="{{ $filtre[$anahtar] }}" autocomplete="off" placeholder="{{ $ornek }}">
                    </label>
                @endforeach
            </div>
        </div>
        <div class="hl-gelismis-grup">
            <p class="hl-gelismis-baslik">Pay, alan ve adet</p>
            <div class="hl-araliklar">
                @foreach ($payAlanlar as [$ad, $minK, $maxK, $ornek])
                    <div class="hl-aralik">
                        <span class="hl-aralik-ad">{{ $ad }}</span>
                        <label class="hl-alan">
                            <span>Min</span>
                            <input type="text" name="{{ $minK }}" value="{{ $filtre[$minK] }}" inputmode="decimal" autocomplete="off" placeholder="{{ $ornek }}">
                        </label>
                        <label class="hl-alan">
                            <span>Max</span>
                            <input type="text" name="{{ $maxK }}" value="{{ $filtre[$maxK] }}" inputmode="decimal" autocomplete="off" placeholder="{{ $ornek }}">
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="hl-gelismis-grup">
            <p class="hl-gelismis-baslik">Bedel</p>
            <div class="hl-araliklar">
                @foreach ($bedeller as [$ad, $minK, $maxK, $ornek])
                    <div class="hl-aralik">
                        <span class="hl-aralik-ad">{{ $ad }}</span>
                        <label class="hl-alan">
                            <span>Min</span>
                            <input type="text" name="{{ $minK }}" value="{{ $filtre[$minK] }}" inputmode="decimal" autocomplete="off" placeholder="{{ $ornek }}">
                        </label>
                        <label class="hl-alan">
                            <span>Max</span>
                            <input type="text" name="{{ $maxK }}" value="{{ $filtre[$maxK] }}" inputmode="decimal" autocomplete="off" placeholder="{{ $ornek }}">
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="hl-gelismis-grup">
            <p class="hl-gelismis-baslik">Tarih</p>
            <div class="hl-tarihler">
                @foreach ($tarihler as [$ad, $minK, $maxK])
                    <div class="hl-aralik">
                        <span class="hl-aralik-ad">{{ $ad }}</span>
                        <label class="hl-alan">
                            <span>Min</span>
                            <input type="date" name="{{ $minK }}" value="{{ $filtre[$minK] }}">
                        </label>
                        <label class="hl-alan">
                            <span>Max</span>
                            <input type="date" name="{{ $maxK }}" value="{{ $filtre[$maxK] }}">
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @if ($aktifFiltreler->isNotEmpty())
        <div class="tl-filtre-aktif" role="status" aria-label="Aktif filtreler">
            <span class="tl-filtre-aktif-etkt">Aktif:</span>
            @if ($filtre['durum'])
                <a href="{{ $durumLink(null) }}" class="tl-fchip">
                    <span>Durum: <strong>{{ $filtre['durum'] === 'aktif' ? 'Aktif' : 'Pasif' }}</strong></span>
                    {!! $carpi !!}
                </a>
            @endif
            @if ($filtre['ada'])
                <a href="{{ route('panel.tasinmazlar.hisseler', request()->except(['ada', 'page'])) }}" class="tl-fchip">
                    <span>Ada: <strong>{{ $filtre['ada'] }}</strong></span>
                    {!! $carpi !!}
                </a>
            @endif
            @if ($filtre['parsel'])
                <a href="{{ route('panel.tasinmazlar.hisseler', request()->except(['parsel', 'page'])) }}" class="tl-fchip">
                    <span>Parsel: <strong>{{ $filtre['parsel'] }}</strong></span>
                    {!! $carpi !!}
                </a>
            @endif
            @if ($filtre['q'])
                <a href="{{ route('panel.tasinmazlar.hisseler', request()->except(['q', 'page'])) }}" class="tl-fchip">
                    <span>Ara: <strong>{{ $filtre['q'] }}</strong></span>
                    {!! $carpi !!}
                </a>
            @endif
            @if ($ilAdi)
                <a href="{{ route('panel.tasinmazlar.hisseler', request()->except(['il_id', 'ilce_id', 'mahalle_id', 'page'])) }}" class="tl-fchip">
                    <span>İl: <strong>{{ $ilAdi }}</strong></span>
                    {!! $carpi !!}
                </a>
            @endif
            @if ($ilceAdi)
                <a href="{{ route('panel.tasinmazlar.hisseler', request()->except(['ilce_id', 'mahalle_id', 'page'])) }}" class="tl-fchip">
                    <span>İlçe: <strong>{{ $ilceAdi }}</strong></span>
                    {!! $carpi !!}
                </a>
            @endif
            @if ($mahalleAdi)
                <a href="{{ route('panel.tasinmazlar.hisseler', request()->except(['mahalle_id', 'page'])) }}" class="tl-fchip">
                    <span>Mahalle: <strong>{{ $mahalleAdi }}</strong></span>
                    {!! $carpi !!}
                </a>
            @endif
            @if ($filtre['islem'])
                <a href="{{ route('panel.tasinmazlar.hisseler', request()->except(['islem', 'page'])) }}" class="tl-fchip">
                    <span>İşlem: <strong>{{ $islemSecenekleri[$filtre['islem']] }}</strong></span>
                    {!! $carpi !!}
                </a>
            @endif
            @foreach ($metinler as [$ad, $anahtar])
                @if (filled($filtre[$anahtar]))
                    <a href="{{ route('panel.tasinmazlar.hisseler', request()->except([$anahtar, 'page'])) }}" class="tl-fchip">
                        <span>{{ $ad }}: <strong>{{ $filtre[$anahtar] }}</strong></span>
                        {!! $carpi !!}
                    </a>
                @endif
            @endforeach
            @foreach ($araliklar as [$ad, $minK, $maxK])
                @if (filled($filtre[$minK]) || filled($filtre[$maxK]))
                    @php
                        $min = str_contains($minK, 'tarih') ? $tarihYazi($filtre[$minK]) : $filtre[$minK];
                        $max = str_contains($maxK, 'tarih') ? $tarihYazi($filtre[$maxK]) : $filtre[$maxK];
                    @endphp
                    <a href="{{ route('panel.tasinmazlar.hisseler', request()->except([$minK, $maxK, 'page'])) }}" class="tl-fchip">
                        <span>{{ $ad }}: <strong>{{ $aralikMetni($min, $max) }}</strong></span>
                        {!! $carpi !!}
                    </a>
                @endif
            @endforeach
        </div>
    @endif
</form>

@if ($hisseler->isEmpty())
    <div class="tl-bos">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 13h6M9 17h4"/></svg>
        <h3>Kayıt bulunamadı</h3>
        <p>{{ $aktifFiltreler->isNotEmpty() ? 'Filtreye uygun hisse yok. Filtreyi temizleyip tekrar deneyin.' : 'Henüz hisse kaydı yok. Hisseler taşınmaz düzenleme ekranından eklenir.' }}</p>
    </div>
@else
<div class="tl-tablo-wrap tl-genis">
    <table class="tl-tablo" id="hl-tablo">
        <thead>
            <tr>
                <th class="tl-sticky-sol tl-th-cb">
                    <label class="tl-cb-wrap">
                        <input type="checkbox" id="hl-tumu-cb" title="Hepsini seç">
                    </label>
                </th>
                <th class="tl-sticky-sol tl-sticky-2 tl-th-islem">İşlem</th>
                @foreach ($tumKolonlar as $k => $etiket)
                    <th data-kolon="{{ $k }}" @class(['is-gizli' => ! $goster($k)])>{{ $etiket }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($hisseler as $h)
                @php
                    $t = $h->tasinmaz;
                    $pay = $h->hisse_pay !== null ? (float) $h->hisse_pay : null;
                    $payda = $h->hisse_payda !== null ? (float) $h->hisse_payda : null;
                    $oran = ($pay !== null && $payda) ? round(($pay / $payda) * 100, 2) : null;
                    $m2 = $h->hesaplananYuzolcum();
                    $aktif = $h->hisse_durum === 'aktif';
                    $adet = (int) ($t->hisseler_count ?? 0);
                    $aktifAdet = (int) ($t->aktif_hisse_adedi ?? 0);
                    $paraHucre = function (?string $alan) use ($h, $sayi): string {
                        return $h->{$alan} !== null ? $sayi($h->{$alan}).' ₺' : '—';
                    };
                @endphp
                <tr data-hisse-id="{{ $h->id }}">
                    <td class="tl-sticky-sol tl-td-cb">
                        <label class="tl-cb-wrap">
                            <input type="checkbox" class="tl-satir-cb" value="{{ $h->id }}">
                        </label>
                    </td>
                    <td class="tl-sticky-sol tl-sticky-2 tl-td-islem">
                        @if ($t)
                            <div class="tl-dd">
                                <button type="button" class="tl-dd-btn" data-dd-toggle>
                                    İşlem
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </button>
                                <div class="tl-dd-menu">
                                    @can('tasinmaz.goruntule')
                                        <a href="{{ $t->rota('detay') }}" class="tl-dd-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                            Taşınmaz
                                        </a>
                                    @endcan
                                    @can('tasinmaz.duzenle')
                                        <a href="{{ $t->rota('duzenle') }}" class="tl-dd-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5 a2.121 2.121 0 0 1 3 3 L7 19 l-4 1 1-4z"/></svg>
                                            Düzenle
                                        </a>
                                    @endcan
                                </div>
                            </div>
                        @else
                            —
                        @endif
                    </td>
                    @foreach ($tumKolonlar as $k => $etiket)
                        <td data-kolon="{{ $k }}" @class(['is-gizli' => ! $goster($k), 'tl-td-mono' => in_array($k, $monoKolon, true), 'tl-hisse-miktar' => $k === 'hisse_m2'])>
                            @switch($k)
                                @case('il') {{ $t?->il?->ad ?? '—' }} @break
                                @case('ilce') {{ $t?->ilce?->ad ?? '—' }} @break
                                @case('mahalle') {{ $t?->mahalle?->ad ?? '—' }} @break
                                @case('ada') <span class="tl-badge">{{ $t?->ada ?? '—' }}</span> @break
                                @case('parsel') <span class="tl-badge">{{ $t?->parsel ?? '—' }}</span> @break
                                @case('nitelik') {{ $t?->nitelik ?? '—' }} @break
                                @case('hisse_no') {{ $h->hisse_no ?: '—' }} @break
                                @case('pay_payda') {{ $pay !== null ? $sayi($pay, 4) : '—' }} / {{ $payda !== null ? $sayi($payda, 4) : '—' }} @break
                                @case('oran') {{ $oran !== null ? '%'.$sayi($oran) : '—' }} @break
                                @case('hisse_m2') {{ $m2 !== null ? number_format($m2, 2, ',', '.').' m²' : '—' }} @break
                                @case('parsel_m2') {{ $t && $t->alan !== null ? number_format((float) $t->alan, 2, ',', '.').' m²' : '—' }} @break
                                @case('hisse_adedi')
                                    @if ($adet > 0)
                                        <span class="tl-pill tl-pill-info">{{ $adet }} hisse</span>
                                        @if ($aktifAdet !== $adet)
                                            <span class="tl-pill tl-pill-mute">{{ $aktifAdet }} aktif</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                    @break
                                @case('aktif_hisse') {{ $aktifAdet }} @break
                                @case('edinme') {{ $h->edinme_sekli ?: '—' }} @break
                                @case('tarih') {{ $h->edinme_tarihi?->format('d.m.Y') ?? '—' }} @break
                                @case('yevmiye') {{ $h->yevmiye_no ?: '—' }} @break
                                @case('islem')
                                    @if ($h->islem_tipi === 'alis')
                                        <span class="tl-pill tl-pill-ok">Alış</span>
                                    @elseif ($h->islem_tipi === 'satis')
                                        <span class="tl-pill tl-pill-warn">Satış</span>
                                    @else
                                        <span class="tl-pill tl-pill-mute">—</span>
                                    @endif
                                    @break
                                @case('durum')
                                    <span class="tl-pill {{ $aktif ? 'tl-pill-ok' : 'tl-pill-mute' }}">{{ $aktif ? 'Aktif' : 'Pasif' }}</span>
                                    @break
                                @case('rayic') {{ $paraHucre('rayic_bedel') }} @break
                                @case('maliyet') {{ $paraHucre('maliyet_bedeli') }} @break
                                @case('emlak_vd') {{ $paraHucre('emlak_vd') }} @break
                                @case('iz_bedeli') {{ $paraHucre('iz_bedeli') }} @break
                                @case('cikis') {{ $h->kayitlardan_cikis ?: '—' }} @break
                                @case('cikis_tarihi') {{ $h->kayitlardan_cikistarihi?->format('d.m.Y') ?? '—' }} @break
                            @endswitch
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="hl-ozet">
    <span>Bu sayfa <strong>{{ number_format($sayfaM2, 2, ',', '.') }} m²</strong></span>
    <span>Listedeki toplam <strong>{{ number_format($sonucM2, 2, ',', '.') }} m²</strong></span>
</div>

<div class="tl-pagination">
    {{ $hisseler->links() }}
</div>
@endif
</div>

<script>
(function () {
    const form = document.getElementById('hl-filtre-form');
    const ilSel = document.getElementById('hl-fltr-il');
    const ilceSel = document.getElementById('hl-fltr-ilce');
    const mahalleSel = document.getElementById('hl-fltr-mahalle');
    const toggleBtn = document.getElementById('hl-filtre-toggle');
    const panel = document.getElementById('hl-filtre-gelismis');
    if (toggleBtn && panel) {
        toggleBtn.addEventListener('click', function () {
            const acilacak = panel.hidden;
            panel.hidden = !acilacak;
            toggleBtn.setAttribute('aria-expanded', acilacak ? 'true' : 'false');
        });
    }
    if (!form || !ilSel || !ilceSel || !mahalleSel) return;

    function gonder(temizlenecek) {
        temizlenecek.forEach(function (sel) { sel.value = ''; });
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
    }

    ilSel.addEventListener('change', function () { gonder([ilceSel, mahalleSel]); });
    ilceSel.addEventListener('change', function () { gonder([mahalleSel]); });
    mahalleSel.addEventListener('change', function () { gonder([]); });
    form.querySelectorAll('input[name="islem"]').forEach(function (radyo) {
        radyo.addEventListener('change', function () { gonder([]); });
    });
})();

(function () {
    const tumu = document.getElementById('hl-tumu-cb');
    const satirlar = document.querySelectorAll('#hl-tablo .tl-satir-cb');
    const indirOgeleri = document.querySelectorAll('[data-indir-eylem]');
    const indirHint = document.querySelector('[data-indir-hint]');
    const indirSayac = document.querySelector('[data-indir-sayac]');

    function indirGuncelle(seciliIdler) {
        const varMi = seciliIdler.length > 0;
        indirOgeleri.forEach(function (el) {
            const base = el.dataset.base || '';
            const filtre = el.dataset.filtre || '';
            el.href = varMi ? (base + '?ids=' + seciliIdler.join(',')) : (base + (filtre ? '?' + filtre : ''));
        });
        if (indirHint) {
            indirHint.textContent = varMi
                ? (seciliIdler.length + ' hisse seçili. Excel ve PDF seçili kolonlarla, yalnız bu satırlar iner.')
                : 'Excel ve PDF seçili kolonlarla iner. Satır seçilmezse mevcut filtre, seçilirse yalnız o satırlar.';
        }
        if (indirSayac) {
            indirSayac.hidden = !varMi;
            indirSayac.textContent = varMi ? seciliIdler.length : '';
        }
    }

    function guncelle() {
        const secili = Array.from(satirlar).filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
        if (tumu) {
            tumu.checked = secili.length > 0 && secili.length === satirlar.length;
            tumu.indeterminate = secili.length > 0 && secili.length < satirlar.length;
        }
        indirGuncelle(secili);
    }

    if (tumu) {
        tumu.addEventListener('change', function () {
            satirlar.forEach(function (c) { c.checked = tumu.checked; });
            guncelle();
        });
    }
    satirlar.forEach(function (c) { c.addEventListener('change', guncelle); });
    guncelle();

    document.querySelectorAll('.tl-dd').forEach(function (dd) {
        const btn = dd.querySelector('[data-dd-toggle]');
        const menu = dd.querySelector('.tl-dd-menu');
        if (!btn || !menu) return;
        document.body.appendChild(menu);
        dd._menu = menu;
    });

    function tumMenuleriKapat(harici) {
        document.querySelectorAll('.tl-dd.is-acik').forEach(function (d) {
            if (d === harici) return;
            d.classList.remove('is-acik');
            if (d._menu) d._menu.classList.remove('is-acik');
        });
    }

    function menuKonumla(dd) {
        const btn = dd.querySelector('[data-dd-toggle]');
        const menu = dd._menu;
        if (!btn || !menu) return;
        const r = btn.getBoundingClientRect();
        menu.style.position = 'fixed';
        menu.style.top = (r.bottom + 4) + 'px';
        menu.style.left = 'auto';
        menu.style.right = (window.innerWidth - r.right) + 'px';
        if (r.right < menu.offsetWidth + 12) {
            menu.style.right = 'auto';
            menu.style.left = r.left + 'px';
        }
        if (r.bottom + menu.offsetHeight + 12 > window.innerHeight) {
            menu.style.top = (r.top - menu.offsetHeight - 4) + 'px';
        }
    }

    document.querySelectorAll('[data-dd-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const dd = btn.closest('.tl-dd');
            const acikMi = dd.classList.contains('is-acik');
            tumMenuleriKapat(acikMi ? null : dd);
            if (acikMi) {
                dd.classList.remove('is-acik');
                if (dd._menu) dd._menu.classList.remove('is-acik');
            } else {
                dd.classList.add('is-acik');
                if (dd._menu) {
                    dd._menu.classList.add('is-acik');
                    requestAnimationFrame(function () { menuKonumla(dd); });
                }
            }
        });
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('.tl-dd-menu')) return;
        tumMenuleriKapat();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') tumMenuleriKapat();
    });
    ['scroll', 'resize'].forEach(function (ev) {
        window.addEventListener(ev, function () {
            document.querySelectorAll('.tl-dd.is-acik').forEach(menuKonumla);
        }, { passive: true });
    });
})();

(function () {
    const tetik = document.getElementById('kp-tetik');
    const backdrop = document.getElementById('kp-backdrop');
    const kapat = document.getElementById('kp-kapat');
    const vazgec = document.getElementById('kp-vazgec');
    const ara = document.getElementById('kp-ara');
    const araTemizle = document.getElementById('kp-ara-temizle');
    const durum = document.getElementById('kp-durum');
    const bos = document.getElementById('kp-bos');
    const gruplar = Array.from(document.querySelectorAll('[data-kp-grup]'));
    const kutular = Array.from(document.querySelectorAll('.kp-cb'));
    const sayacElemanlari = Array.from(document.querySelectorAll('[data-kp-sayac]'));
    const kaydetUrl = @json(route('panel.kolon-tercihi.kaydet'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const varsayilanKolonlar = @json(\App\Support\HisseKolonlari::varsayilan());
    if (!tetik || !backdrop) return;

    function tabloyaUygula() {
        const secili = kutular.filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
        document.querySelectorAll('#hl-tablo [data-kolon]').forEach(function (el) {
            el.classList.toggle('is-gizli', secili.indexOf(el.getAttribute('data-kolon')) === -1);
        });
        sayacElemanlari.forEach(function (el) {
            const em = el.querySelector('em');
            const emHtml = em ? em.outerHTML : '';
            el.innerHTML = secili.length + emHtml;
        });
        grupSayaclariGuncelle();
    }

    function grupSayaclariGuncelle() {
        gruplar.forEach(function (g) {
            const cbler = Array.from(g.querySelectorAll('.kp-cb'));
            const secili = cbler.filter(function (c) { return c.checked; }).length;
            const sayac = g.querySelector('[data-kp-grup-sayac]');
            if (sayac) sayac.textContent = secili + '/' + cbler.length;
            g.classList.toggle('is-hepsi', secili === cbler.length);
            g.classList.toggle('is-hicbiri', secili === 0);
        });
    }

    let kayitZamanlayici = null;
    function kaydet() {
        const kolonlar = kutular.filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
        if (kolonlar.length === 0) return;
        if (durum) { durum.hidden = false; durum.textContent = 'Kaydediliyor…'; durum.className = 'kp-alt-durum is-info'; }
        fetch(kaydetUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
            },
            body: JSON.stringify({ tablo: 'hisseler', kolonlar: kolonlar }),
        }).then(function (r) { return r.json(); }).then(function () {
            if (durum) { durum.textContent = 'Kaydedildi'; durum.className = 'kp-alt-durum is-ok'; }
            setTimeout(function () { if (durum) durum.hidden = true; }, 1400);
        }).catch(function () {
            if (durum) { durum.textContent = 'Kaydedilemedi'; durum.className = 'kp-alt-durum is-hata'; }
        });
    }

    function panelAc() {
        backdrop.hidden = false;
        document.body.style.overflow = 'hidden';
        setTimeout(function () { if (ara) ara.focus(); }, 60);
    }
    function panelKapat() {
        backdrop.hidden = true;
        document.body.style.overflow = '';
    }

    tetik.addEventListener('click', panelAc);
    if (kapat) kapat.addEventListener('click', panelKapat);
    if (vazgec) vazgec.addEventListener('click', panelKapat);
    backdrop.addEventListener('click', function (e) { if (e.target === backdrop) panelKapat(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !backdrop.hidden) panelKapat(); });

    kutular.forEach(function (c) {
        c.addEventListener('change', function () {
            if (kutular.filter(function (x) { return x.checked; }).length === 0) {
                c.checked = true;
                return;
            }
            tabloyaUygula();
            clearTimeout(kayitZamanlayici);
            kayitZamanlayici = setTimeout(kaydet, 350);
        });
    });

    document.querySelectorAll('[data-kp-grup-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const grup = btn.closest('[data-kp-grup]');
            const cbler = Array.from(grup.querySelectorAll('.kp-cb'));
            const hepsiAcik = cbler.every(function (c) { return c.checked; });
            cbler.forEach(function (c) { c.checked = !hepsiAcik; });
            if (kutular.filter(function (x) { return x.checked; }).length === 0) {
                cbler.forEach(function (c) { c.checked = true; });
            }
            tabloyaUygula();
            clearTimeout(kayitZamanlayici);
            kayitZamanlayici = setTimeout(kaydet, 350);
        });
    });

    document.querySelectorAll('[data-kp-eylem]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const eylem = btn.dataset.kpEylem;
            if (eylem === 'tumu') kutular.forEach(function (c) { c.checked = true; });
            else if (eylem === 'hicbiri') { kutular.forEach(function (c) { c.checked = false; }); if (kutular[0]) kutular[0].checked = true; }
            else if (eylem === 'varsayilan') kutular.forEach(function (c) { c.checked = varsayilanKolonlar.indexOf(c.value) !== -1; });
            tabloyaUygula();
            clearTimeout(kayitZamanlayici);
            kayitZamanlayici = setTimeout(kaydet, 350);
        });
    });

    if (ara) {
        ara.addEventListener('input', function () {
            const q = ara.value.trim().toLocaleLowerCase('tr');
            if (araTemizle) araTemizle.hidden = q === '';
            let gorunenSayi = 0;
            document.querySelectorAll('.kp-secim').forEach(function (sec) {
                const ad = (sec.querySelector('.kp-secim-ad')?.textContent || '').toLocaleLowerCase('tr');
                const gorunur = q === '' || ad.indexOf(q) !== -1;
                sec.hidden = !gorunur;
                if (gorunur) gorunenSayi++;
            });
            gruplar.forEach(function (g) {
                const gorulenler = Array.from(g.querySelectorAll('.kp-secim')).filter(function (s) { return !s.hidden; });
                g.hidden = gorulenler.length === 0;
            });
            if (bos) bos.hidden = gorunenSayi > 0;
        });
    }
    if (araTemizle) {
        araTemizle.addEventListener('click', function () {
            ara.value = '';
            ara.dispatchEvent(new Event('input'));
            ara.focus();
        });
    }
    grupSayaclariGuncelle();
})();
</script>
@endsection
