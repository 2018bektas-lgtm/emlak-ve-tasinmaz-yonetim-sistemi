@extends('layouts.panel')

@section('title', 'Taşınmaz Listesi')

@section('content')
@if (session('basari'))
    <div class="flash-success" role="status">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12 l5 5 L20 7"/></svg>
        {{ session('basari') }}
    </div>
@endif

<section class="page-hero">
    <div class="page-hero-text">
        <h2>Taşınmaz Listesi</h2>
        <small>Toplam {{ $toplamKayit }} kayıt · Bu sayfada {{ $tasinmazlar->count() }} · Filtre sonucu {{ $tasinmazlar->total() }}</small>
    </div>
    <div class="page-hero-actions">
        @php
            $secilenSayi = count($gorunurKolonlar);
            $toplamKolon = count($tumKolonlar);
        @endphp
        <button type="button" class="btn-cancel kp-tetik" id="kp-tetik" aria-haspopup="dialog">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="6" height="18" rx="1"/><rect x="11" y="3" width="10" height="8" rx="1"/><rect x="11" y="13" width="10" height="8" rx="1"/></svg>
            Kolonlar
            <span class="kp-tetik-sayi" data-kp-sayac>{{ $secilenSayi }}<em>/{{ $toplamKolon }}</em></span>
        </button>
        @php
            $kolonAnahtarIkonlar = [
                'pin' => '<path d="M12 22s-7-7-7-13a7 7 0 0 1 14 0c0 6-7 13-7 13z"/><circle cx="12" cy="9" r="2.6"/>',
                'ruler' => '<path d="M2 15 L15 2 l7 7 L9 22z"/><path d="M6 15 h1 M9 12 h1 M12 9 h1 M15 6 h1"/>',
                'building' => '<path d="M3 21h18"/><path d="M5 21V6h9v15"/><path d="M14 21V10h5v11"/><path d="M8 9h1 M8 12h1 M8 15h1 M11 9h1 M11 12h1"/>',
                'layers' => '<path d="M12 3 L2 8 l10 5 10-5z"/><path d="M2 13 l10 5 10-5"/><path d="M2 18 l10 5 10-5"/>',
                'flag' => '<path d="M5 21 V4 h13 l-3 5 3 5 H5"/>',
                'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
            ];
        @endphp
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
                    <button type="button" class="kp-mini-btn" data-kp-eylem="tumu">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                        Tümünü seç
                    </button>
                    <button type="button" class="kp-mini-btn" data-kp-eylem="hicbiri">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h16"/></svg>
                        Hiçbirini seçme
                    </button>
                    <button type="button" class="kp-mini-btn" data-kp-eylem="varsayilan">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12 a9 9 0 1 0 3-6.7"/><path d="M3 4 v4 h4"/></svg>
                        Varsayılan
                    </button>
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
        @php
            // Filtreleri URL query'ye taşıyacak yardımcı
            $exportSorgu = request()->except(['page']);
            $exportSorguStr = http_build_query($exportSorgu);
            // Görünen sayfadaki taşınmaz id'leri (harita "sadece bunları göster" için)
            $sayfaIdler = $tasinmazlar->pluck('id')->implode(',');
        @endphp
        {{-- İndirme dropdown — seçili taşınmazlara göre --}}
        <div class="tl-dd tl-hero-dd" id="tl-indir-dd">
            <button type="button" class="btn-cancel tl-dd-btn" data-dd-toggle data-indir-btn>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                İndir <span class="tl-indir-sayac" data-indir-sayac hidden></span>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="tl-dd-menu tl-indir-menu">
                <p class="tl-indir-hint" data-indir-hint>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v.01M11 12h1v4h1"/></svg>
                    Önce tablodan en az bir taşınmaz seçin.
                </p>
                <a href="{{ route('panel.tasinmazlar.harita') }}" data-indir-eylem="harita" data-base="{{ route('panel.tasinmazlar.harita') }}" target="_blank" rel="noopener" class="tl-dd-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4L3 6v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14M15 6v14"/></svg>
                    <span>Haritada Göster</span>
                </a>
                <a href="{{ route('panel.tasinmazlar.excel') }}" data-indir-eylem="excel" data-base="{{ route('panel.tasinmazlar.excel') }}" class="tl-dd-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 12h8M12 8v8"/></svg>
                    <span>Excel <em>(.xlsx)</em></span>
                </a>
                <a href="{{ route('panel.tasinmazlar.kml') }}" data-indir-eylem="kml" data-base="{{ route('panel.tasinmazlar.kml') }}" class="tl-dd-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-7-7-7-13a7 7 0 0 1 14 0c0 6-7 13-7 13z"/><circle cx="12" cy="9" r="2.6"/></svg>
                    <span>KML <em>(Google Earth)</em></span>
                </a>
                <a href="{{ route('panel.tasinmazlar.pdf') }}" data-indir-eylem="pdf" data-base="{{ route('panel.tasinmazlar.pdf') }}" target="_blank" rel="noopener" class="tl-dd-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                    <span>PDF / Yazdır</span>
                </a>
            </div>
        </div>
        @can('tasinmaz.olustur')
            <a href="{{ route('panel.tasinmazlar.olustur') }}" class="btn-submit">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Taşınmaz Ekle
            </a>
        @endcan
    </div>
</section>

@php
    $aktifFiltreler = collect($filtre)->filter(fn ($v) => filled($v))->keys();
    $gelismisAcik = $aktifFiltreler->intersect([
        'ilce_id', 'mahalle_id', 'nitelik', 'satis_durumu', 'mulkiyet', 'bina',
        'kira_var', 'tahsis_var', 'ust_hakki_var', 'meclis_satis_karari_var',
    ])->isNotEmpty();

    $satisSecenekleri = [
        'envanterde' => 'Envanterde',
        'hazirlik' => 'Hazırlık',
        'satista' => 'Satışta',
        'satildi' => 'Satıldı',
        'iptal' => 'İptal',
    ];
    $bayrakSecenekleri = [
        'kira_var' => 'Kira',
        'tahsis_var' => 'Tahsis',
        'ust_hakki_var' => 'Üst Hakkı',
        'meclis_satis_karari_var' => 'Meclis Kararı',
    ];
    $ilAdi = $filtre['il_id'] ? optional($iller->firstWhere('id', (int) $filtre['il_id']))->ad : null;
    $ilceAdi = $filtre['ilce_id'] ? optional($ilceler->firstWhere('id', (int) $filtre['ilce_id']))->ad : null;
    $mahalleAdi = $filtre['mahalle_id'] ? optional($mahalleler->firstWhere('id', (int) $filtre['mahalle_id']))->ad : null;
@endphp

<form method="GET" action="{{ route('panel.tasinmazlar.index') }}" class="tl-filtre" id="tl-filtre-form">
    <div class="tl-filtre-satir tl-filtre-ust">
        <div class="tl-filtre-alan">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" name="q" value="{{ $filtre['q'] }}" placeholder="Ada / parsel / nitelik / kullanım / açıklama / TAKBİS…" autocomplete="off">
        </div>
        <button type="submit" class="btn-submit tl-filtre-btn" title="Aramayı çalıştır">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            Ara
        </button>
        <button type="button" class="tl-filtre-toggle" id="tl-filtre-toggle" aria-expanded="{{ $gelismisAcik ? 'true' : 'false' }}" aria-controls="tl-filtre-gelismis">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M7 12h10M10 18h4"/></svg>
            Gelişmiş
            <svg class="tl-filtre-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        @if ($aktifFiltreler->isNotEmpty())
            <a href="{{ route('panel.tasinmazlar.index') }}" class="tl-filtre-clear" title="Tüm filtreleri temizle">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                Temizle
            </a>
        @endif
    </div>

    <section class="form-section tl-filtre-gelismis" id="tl-filtre-gelismis" {{ $gelismisAcik ? '' : 'hidden' }}>
        <div class="form-section-head">
            <span class="form-section-num">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h18M6 12h12M10 20h4"/></svg>
            </span>
            <div class="form-section-head-text">
                <h3>Gelişmiş Filtre</h3>
                <small>Konum, satış, mülkiyet ve durum bayraklarına göre daralt.</small>
            </div>
        </div>

        <div class="form-row form-row-4">
            <div class="form-field">
                <label for="tl-fltr-il">İl</label>
                <select name="il_id" class="form-select" id="tl-fltr-il">
                    <option value="">Tümü</option>
                    @foreach ($iller as $il)
                        <option value="{{ $il->id }}" @selected($filtre['il_id'] == $il->id)>{{ $il->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="tl-fltr-ilce">İlçe</label>
                <select name="ilce_id" class="form-select" id="tl-fltr-ilce" data-secili="{{ $filtre['ilce_id'] }}">
                    <option value="">Tümü</option>
                    @foreach ($ilceler as $ilce)
                        <option value="{{ $ilce->id }}" @selected($filtre['ilce_id'] == $ilce->id)>{{ $ilce->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="tl-fltr-mahalle">Mahalle</label>
                <select name="mahalle_id" class="form-select" id="tl-fltr-mahalle" data-secili="{{ $filtre['mahalle_id'] }}">
                    <option value="">Tümü</option>
                    @foreach ($mahalleler as $mah)
                        <option value="{{ $mah->id }}" @selected($filtre['mahalle_id'] == $mah->id)>{{ $mah->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="tl-fltr-nitelik">Nitelik</label>
                <input id="tl-fltr-nitelik" type="text" name="nitelik" value="{{ $filtre['nitelik'] }}" class="form-input" placeholder="Arsa, Tarla…">
            </div>
        </div>

        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="tl-fltr-satis">Satış Durumu</label>
                <select id="tl-fltr-satis" name="satis_durumu" class="form-select">
                    <option value="">Tümü</option>
                    @foreach ($satisSecenekleri as $val => $lbl)
                        <option value="{{ $val }}" @selected($filtre['satis_durumu'] === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="tl-fltr-mulkiyet">Mülkiyet</label>
                <select id="tl-fltr-mulkiyet" name="mulkiyet" class="form-select">
                    <option value="">Tümü</option>
                    <option value="TAM" @selected($filtre['mulkiyet'] === 'TAM')>TAM</option>
                    <option value="HISSELI" @selected($filtre['mulkiyet'] === 'HISSELI')>HİSSELİ</option>
                </select>
            </div>
            <div class="form-field">
                <label for="tl-fltr-bina">Bina Durumu</label>
                <select id="tl-fltr-bina" name="bina" class="form-select">
                    <option value="">Tümü</option>
                    <option value="1" @selected($filtre['bina'] === '1')>Var</option>
                    <option value="0" @selected($filtre['bina'] === '0')>Yok</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-field tl-filtre-bayraklar">
                <label>Durum Bayrakları</label>
                <div class="tl-filtre-chipler">
                    @foreach ($bayrakSecenekleri as $alan => $lbl)
                        @php $val = $filtre[$alan] ?? ''; @endphp
                        <div class="tl-tri-chip" data-alan="{{ $alan }}">
                            <span class="tl-tri-lbl">{{ $lbl }}</span>
                            <div class="tl-tri-btnler">
                                <label class="tl-tri-btn" title="Var olanlar">
                                    <input type="radio" name="{{ $alan }}" value="1" @checked($val === '1')>
                                    <span>Var</span>
                                </label>
                                <label class="tl-tri-btn" title="Olmayanlar">
                                    <input type="radio" name="{{ $alan }}" value="0" @checked($val === '0')>
                                    <span>Yok</span>
                                </label>
                                <label class="tl-tri-btn" title="Filtre yok">
                                    <input type="radio" name="{{ $alan }}" value="" @checked($val === '')>
                                    <span>—</span>
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    @if ($aktifFiltreler->isNotEmpty())
        <div class="tl-filtre-aktif" role="status" aria-label="Aktif filtreler">
            <span class="tl-filtre-aktif-etkt">Aktif:</span>
            @if ($filtre['q'])
                <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except(['q','page']))) }}" class="tl-fchip">
                    <span>Ara: <strong>{{ $filtre['q'] }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
            @endif
            @if ($ilAdi)
                <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except(['il_id','ilce_id','mahalle_id','page']))) }}" class="tl-fchip">
                    <span>İl: <strong>{{ $ilAdi }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
            @endif
            @if ($ilceAdi)
                <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except(['ilce_id','mahalle_id','page']))) }}" class="tl-fchip">
                    <span>İlçe: <strong>{{ $ilceAdi }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
            @endif
            @if ($mahalleAdi)
                <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except(['mahalle_id','page']))) }}" class="tl-fchip">
                    <span>Mahalle: <strong>{{ $mahalleAdi }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
            @endif
            @if ($filtre['nitelik'])
                <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except(['nitelik','page']))) }}" class="tl-fchip">
                    <span>Nitelik: <strong>{{ $filtre['nitelik'] }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
            @endif
            @if ($filtre['satis_durumu'])
                <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except(['satis_durumu','page']))) }}" class="tl-fchip">
                    <span>Satış: <strong>{{ $satisSecenekleri[$filtre['satis_durumu']] ?? $filtre['satis_durumu'] }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
            @endif
            @if ($filtre['mulkiyet'])
                <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except(['mulkiyet','page']))) }}" class="tl-fchip">
                    <span>Mülkiyet: <strong>{{ $filtre['mulkiyet'] === 'TAM' ? 'TAM' : 'HİSSELİ' }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
            @endif
            @if ($filtre['bina'] === '1' || $filtre['bina'] === '0')
                <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except(['bina','page']))) }}" class="tl-fchip">
                    <span>Bina: <strong>{{ $filtre['bina'] === '1' ? 'Var' : 'Yok' }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
            @endif
            @foreach ($bayrakSecenekleri as $alan => $lbl)
                @php $val = $filtre[$alan] ?? ''; @endphp
                @if ($val === '1' || $val === '0')
                    <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except([$alan,'page']))) }}" class="tl-fchip">
                        <span>{{ $lbl }}: <strong>{{ $val === '1' ? 'Var' : 'Yok' }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </a>
                @endif
            @endforeach
        </div>
    @endif
</form>

<script>
(function () {
    // Gelişmiş filtre toggle
    const toggleBtn = document.getElementById('tl-filtre-toggle');
    const panel = document.getElementById('tl-filtre-gelismis');
    if (toggleBtn && panel) {
        toggleBtn.addEventListener('click', function () {
            const acilacak = panel.hidden;
            panel.hidden = !acilacak;
            toggleBtn.setAttribute('aria-expanded', acilacak ? 'true' : 'false');
        });
    }

    // Cascade: il → ilçe → mahalle (AJAX)
    const ilSel = document.getElementById('tl-fltr-il');
    const ilceSel = document.getElementById('tl-fltr-ilce');
    const mahalleSel = document.getElementById('tl-fltr-mahalle');
    if (!ilSel || !ilceSel || !mahalleSel) return;

    const ilceEndpoint = @json(url('/panel/ajax/ilceler'));
    const mahalleEndpoint = @json(url('/panel/ajax/mahalleler'));

    function optionsSet(sel, kayitlar, secili) {
        while (sel.options.length > 1) sel.remove(1);
        (kayitlar || []).forEach(function (k) {
            const o = document.createElement('option');
            o.value = k.id;
            o.textContent = k.ad;
            if (String(secili) === String(k.id)) o.selected = true;
            sel.appendChild(o);
        });
    }

    async function fetchJSON(url) {
        try {
            const r = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!r.ok) return [];
            return await r.json();
        } catch (e) { return []; }
    }

    ilSel.addEventListener('change', async function () {
        optionsSet(ilceSel, [], '');
        optionsSet(mahalleSel, [], '');
        if (!ilSel.value) return;
        const data = await fetchJSON(ilceEndpoint + '/' + ilSel.value);
        optionsSet(ilceSel, data, '');
    });

    ilceSel.addEventListener('change', async function () {
        optionsSet(mahalleSel, [], '');
        if (!ilceSel.value) return;
        const data = await fetchJSON(mahalleEndpoint + '/' + ilceSel.value);
        optionsSet(mahalleSel, data, '');
    });
})();
</script>

{{-- Toplu işlem barı (checkbox seçili olduğunda görünür — işlemler sonra eklenecek) --}}
<div class="tl-toplu-bar" id="tl-toplu-bar" hidden>
    <span><strong id="tl-secili-sayi">0</strong> kayıt seçildi</span>
    <span class="tl-toplu-hint">Toplu işlemler ileride eklenecek.</span>
    <button type="button" class="btn-cancel" id="tl-secim-temizle">Seçimi Temizle</button>
</div>

@if ($tasinmazlar->isEmpty())
    <div class="tl-bos">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 13h6M9 17h4"/></svg>
        <h3>Kayıt bulunamadı</h3>
        <p>{{ $filtre['q'] || $filtre['il_id'] ? 'Filtreye uygun taşınmaz yok. Filtreyi temizleyip tekrar deneyin.' : 'Henüz taşınmaz eklenmemiş. Sağ üstteki "Taşınmaz Ekle" butonu ile başlayabilirsiniz.' }}</p>
    </div>
@else
@php $goster = fn (string $k) => in_array($k, $gorunurKolonlar, true); @endphp

{{-- Aktif kolon-filtreleri barı (client-side, popover ile ekli) --}}
<div class="tl-flt-aktif-bar" id="tl-flt-aktif-bar" hidden>
    <span class="tl-flt-aktif-etkt">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5h18l-7 9v6l-4 2v-8z"/></svg>
        Kolon filtresi:
    </span>
    <div class="tl-flt-chip-liste" id="tl-flt-chip-liste"></div>
    <button type="button" class="tl-flt-temizle-hepsi" id="tl-flt-temizle-hepsi">Tümünü temizle</button>
</div>

{{-- Popover — bir kolon başlığına tıklandığında konumlandırılır --}}
<div class="tl-flt-pop" id="tl-flt-pop" hidden>
    <div class="tl-flt-pop-baslik" id="tl-flt-pop-baslik">Kolon Adı</div>
    <div class="tl-flt-pop-govde">
        <input type="text" id="tl-flt-pop-input" placeholder="Bu kolonda ara..." autocomplete="off">
    </div>
    <div class="tl-flt-pop-alt">
        <button type="button" class="tl-flt-pop-btn tl-flt-pop-btn-sil" id="tl-flt-pop-sil">Filtreyi kaldır</button>
        <button type="button" class="tl-flt-pop-btn tl-flt-pop-btn-uygula" id="tl-flt-pop-uygula">Uygula</button>
    </div>
</div>

<div class="tl-tablo-wrap tl-genis">
    <table class="tl-tablo" id="tl-tablo">
        <thead>
            <tr>
                <th class="tl-sticky-sol tl-th-cb">
                    <label class="tl-cb-wrap">
                        <input type="checkbox" id="tl-tumu-cb" title="Hepsini seç">
                    </label>
                </th>
                <th class="tl-sticky-sol tl-sticky-2 tl-th-islem">İşlem</th>

                @foreach ($tumKolonlar as $k => $etiket)
                    <th data-kolon="{{ $k }}" @class(['is-gizli' => ! $goster($k)])>
                        <span class="tl-th-inner">
                            <span class="tl-th-metin">{{ $etiket }}</span>
                            <button type="button" class="tl-th-flt" data-tl-flt="{{ $k }}" data-tl-flt-etiket="{{ $etiket }}" title="Bu kolonda ara" aria-label="Bu kolonda ara">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5h18l-7 9v6l-4 2v-8z"/></svg>
                            </button>
                        </span>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($tasinmazlar as $t)
                @php
                    // Kavram-bazlı ayrım: kategori (hasOne) + ekbilgi (hasOne) + yapılar (hasMany)
                    $kategori = $t->kategori;
                    $ekbilgi = $t->ekbilgi;
                    $yapilar = $t->yapilar;
                    $bbn = $yapilar->first(); // ilk BBN — kolonlarda blok/kat/no gösterimi

                    $aktifHisseler = $t->hisseler->where('hisse_durum', 'aktif');
                    $hisseeToplam = $aktifHisseler->sum('hisse_yuzolcum');
                    $satisOzet = $t->satisOzeti();
                    $mahalleTkgm = $t->mahalle->tkgm_id ?? null;

                    $tkgmUrl = $t->tkgmSorguUrl();
                    $mulkiyet = $t->mulkiyet_durumu;
                    $geometriVar = ($t->koordinat && ! empty($t->koordinat->koordinat));
                    $binaVar = (bool) $t->uzeri_bina_var_mi || $yapilar->isNotEmpty();

                    // Bayraklar: arsa (ekbilgi) VEYA herhangi bir yapıda varsa true
                    $bayrak = function (string $alan) use ($ekbilgi, $yapilar) {
                        if ($ekbilgi && (bool) $ekbilgi->{$alan}) return true;
                        return $yapilar->contains(fn ($y) => (bool) $y->{$alan});
                    };
                    $meclisVar = $bayrak('meclis_satis_karari_var');
                    $tahsisVar = $bayrak('tahsis_var');
                    $ustHakkiVar = $bayrak('ust_hakki_var');
                    $kiraVar = $bayrak('kira_var');

                    // Muhasebe: arsa kategori, yoksa ilk yapı
                    $muhasebeKayit = $kategori?->muhasebeKayit ?? optional($yapilar->first())->muhasebeKayit;
                    $muhasebeFarkli = $yapilar->pluck('muhasebe_kayit_id')->filter()
                        ->push($kategori?->muhasebe_kayit_id)->filter()->unique()->count() > 1;

                    // Açıklama: ekbilgi + yapı açıklamaları + imar notu
                    $aciklamalar = collect([$ekbilgi?->aciklama])
                        ->merge($yapilar->pluck('aciklama'))
                        ->filter()->implode(' · ');
                    $aciklamaBirlesik = trim($aciklamalar.' '.($t->imar->imar_notu ?? ''));
                @endphp
                <tr data-tasinmaz-id="{{ $t->id }}">
                    <td class="tl-sticky-sol tl-td-cb">
                        <label class="tl-cb-wrap">
                            <input type="checkbox" class="tl-satir-cb" value="{{ $t->id }}">
                        </label>
                    </td>
                    <td class="tl-sticky-sol tl-sticky-2 tl-td-islem">
                        <div class="tl-dd">
                            <button type="button" class="tl-dd-btn" data-dd-toggle>
                                İşlem
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <div class="tl-dd-menu">
                                <a href="{{ $t->rota('detay') }}" class="tl-dd-item">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    Detay
                                </a>
                                @can('tasinmaz.duzenle')
                                <a href="{{ $t->rota('duzenle') }}" class="tl-dd-item">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5 a2.121 2.121 0 0 1 3 3 L7 19 l-4 1 1-4z"/></svg>
                                    Düzenle
                                </a>
                                @endcan
                                @can('tasinmaz.sil')
                                <form method="POST" action="{{ $t->rota('sil') }}"
                                      onsubmit="return confirm('#{{ $t->id }} numaralı taşınmazı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="tl-dd-item tl-dd-item-danger">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4 a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14 a2 2 0 0 1-2 2H8 a2 2 0 0 1-2-2L5 6"/></svg>
                                        Sil
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </div>
                    </td>

                    <td class="tl-td-mono" data-kolon="tkgm_parsel" @class(['is-gizli' => ! $goster('tkgm_parsel')])>
                        @if ($tkgmUrl)
                            <a href="{{ $tkgmUrl }}" target="_blank" rel="noopener noreferrer"
                               class="tl-tkgm-link" title="TKGM Parsel Sorgu — yeni sekmede aç">
                                TKGM Parsel
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>
                            </a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="tl-td-mono" data-kolon="takbis" @class(['is-gizli' => ! $goster('takbis')])>{{ $t->tapu->takbis_zemin_no ?? '—' }}</td>
                    <td data-kolon="ilce" @class(['is-gizli' => ! $goster('ilce')])>{{ $t->ilce->ad ?? '—' }}</td>
                    <td data-kolon="mahalle" @class(['is-gizli' => ! $goster('mahalle')])>{{ $t->mahalle->ad ?? '—' }}</td>
                    <td data-kolon="mudurluk" @class(['is-gizli' => ! $goster('mudurluk')])>{{ $t->mudurluk->ad ?? '—' }}</td>
                    <td class="tl-td-mono" data-kolon="ada" @class(['is-gizli' => ! $goster('ada')])><span class="tl-badge">{{ $t->ada ?? '—' }}</span></td>
                    <td class="tl-td-mono" data-kolon="parsel" @class(['is-gizli' => ! $goster('parsel')])><span class="tl-badge">{{ $t->parsel ?? '—' }}</span></td>
                    <td class="tl-td-mono" data-kolon="alan" @class(['is-gizli' => ! $goster('alan')])>{{ $t->alan !== null ? number_format((float) $t->alan, 2, ',', '.') : '—' }}</td>
                    <td data-kolon="nitelik" @class(['is-gizli' => ! $goster('nitelik')])>{{ $t->nitelik ?? '—' }}</td>
                    <td data-kolon="hisse_durumu" @class(['is-gizli' => ! $goster('hisse_durumu')])>
                        @if ($mulkiyet === 'TAM')
                            <span class="tl-pill tl-pill-ok" title="Tek aktif hisse ve pay = payda">TAM</span>
                        @elseif ($mulkiyet === 'HİSSELİ')
                            <span class="tl-pill tl-pill-danger" title="{{ $aktifHisseler->count() }} aktif hisse">HİSSELİ</span>
                        @else
                            <span class="tl-pill tl-pill-mute">—</span>
                        @endif
                    </td>
                    <td class="tl-td-mono" data-kolon="hisse_m2" @class(['is-gizli' => ! $goster('hisse_m2')])>{{ $hisseeToplam > 0 ? number_format($hisseeToplam, 2, ',', '.') : '—' }}</td>
                    <td class="tl-td-mono" data-kolon="bb_no" @class(['is-gizli' => ! $goster('bb_no')])>
                        @if ($yapilar->count() > 1)
                            {{ $bbn->bagimsiz_bolum_no }} <span class="tl-pill tl-pill-info">+{{ $yapilar->count() - 1 }}</span>
                        @else
                            {{ $bbn->bagimsiz_bolum_no ?? '—' }}
                        @endif
                    </td>
                    <td class="tl-td-mono" data-kolon="blok_no" @class(['is-gizli' => ! $goster('blok_no')])>{{ $bbn->blok_no ?? '—' }}</td>
                    <td class="tl-td-mono" data-kolon="kat_no" @class(['is-gizli' => ! $goster('kat_no')])>{{ $bbn->kat_no ?? '—' }}</td>
                    <td data-kolon="imar" @class(['is-gizli' => ! $goster('imar')])>{{ $t->imar->imarDurumu->ad ?? '—' }}</td>
                    <td data-kolon="mulkiyet" @class(['is-gizli' => ! $goster('mulkiyet')])>
                        <span class="tl-pill tl-pill-ok">Kayıtlı</span>
                    </td>
                    <td data-kolon="muhasebe" @class(['is-gizli' => ! $goster('muhasebe')])>
                        @if ($muhasebeKayit)
                            <span title="{{ $muhasebeKayit->ad }}">{{ Str::limit($muhasebeKayit->ad, 40) }}</span>
                            @if ($muhasebeFarkli)
                                <span class="tl-pill tl-pill-mute" title="Arsa ve BBN'lerde farklı muhasebe kayıtları">karışık</span>
                            @endif
                        @else — @endif
                    </td>
                    <td class="tl-td-center" data-kolon="dosya" @class(['is-gizli' => ! $goster('dosya')])>
                        @if ($t->resimler_count > 0)
                            <span class="tl-pill tl-pill-info">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                                {{ $t->resimler_count }}
                            </span>
                        @else
                            <span class="tl-pill tl-pill-mute">—</span>
                        @endif
                    </td>
                    <td data-kolon="satis" @class(['is-gizli' => ! $goster('satis')])>
                        <span class="tl-pill {{ $satisOzet->pill() }}">{{ $satisOzet->etiket() }}</span>
                        @if ($yapilar->count() > 0)
                            @php $satistaSay = $yapilar->filter(fn ($y) => ($y->satis_durumu instanceof \App\Enums\SatisDurumu ? $y->satis_durumu->value : $y->satis_durumu) === 'satista')->count(); @endphp
                            <span class="tl-pill tl-pill-mute" title="Satıştaki BBN / toplam BBN">{{ $satistaSay }}/{{ $yapilar->count() }}</span>
                        @endif
                    </td>
                    <td data-kolon="bina" @class(['is-gizli' => ! $goster('bina')])>{!! $binaVar ? '<span class="tl-pill tl-pill-ok">Var</span>' : '<span class="tl-pill tl-pill-mute">Yok</span>' !!}</td>
                    <td class="tl-td-metin" data-kolon="aciklama" @class(['is-gizli' => ! $goster('aciklama')]) title="{{ $aciklamaBirlesik }}">{{ Str::limit($aciklamaBirlesik, 60) ?: '—' }}</td>
                    <td data-kolon="tahsis" @class(['is-gizli' => ! $goster('tahsis')])>{!! $tahsisVar ? '<span class="tl-pill tl-pill-info">Var</span>' : '<span class="tl-pill tl-pill-mute">Yok</span>' !!}</td>
                    <td data-kolon="ust_hakki" @class(['is-gizli' => ! $goster('ust_hakki')])>{!! $ustHakkiVar ? '<span class="tl-pill tl-pill-info">Var</span>' : '<span class="tl-pill tl-pill-mute">Yok</span>' !!}</td>
                    <td data-kolon="kira" @class(['is-gizli' => ! $goster('kira')])>{!! $kiraVar ? '<span class="tl-pill tl-pill-info">Var</span>' : '<span class="tl-pill tl-pill-mute">Yok</span>' !!}</td>
                    <td data-kolon="meclis" @class(['is-gizli' => ! $goster('meclis')])>{!! $meclisVar ? '<span class="tl-pill tl-pill-warn">Var</span>' : '<span class="tl-pill tl-pill-mute">Yok</span>' !!}</td>
                    <td data-kolon="geometri" @class(['is-gizli' => ! $goster('geometri')])>{!! $geometriVar ? '<span class="tl-pill tl-pill-ok">Var</span>' : '<span class="tl-pill tl-pill-mute">Yok</span>' !!}</td>
                    <td class="tl-td-metin" data-kolon="ek_rapor" @class(['is-gizli' => ! $goster('ek_rapor')])><span class="tl-pill tl-pill-mute">—</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="tl-pagination">
    {{ $tasinmazlar->links() }}
</div>
@endif

<script>
/* Toplu seçim + satır işlem dropdown */
(function () {
    const tumu = document.getElementById('tl-tumu-cb');
    const satirlar = document.querySelectorAll('.tl-satir-cb');
    const bar = document.getElementById('tl-toplu-bar');
    const sayi = document.getElementById('tl-secili-sayi');
    const temizle = document.getElementById('tl-secim-temizle');

    // İndir dropdown öğeleri — seçime bağlı aktif/pasif ve href güncellemesi
    const indirOgeleri = document.querySelectorAll('[data-indir-eylem]');
    const indirHint = document.querySelector('[data-indir-hint]');
    const indirSayac = document.querySelector('[data-indir-sayac]');

    function indirGuncelle(seciliIdler) {
        const varMi = seciliIdler.length > 0;
        const query = varMi ? ('?ids=' + seciliIdler.join(',')) : '';

        indirOgeleri.forEach(el => {
            const base = el.dataset.base || el.getAttribute('href');
            el.href = base + query;
            el.classList.toggle('is-disabled', !varMi);
            if (!varMi) {
                el.setAttribute('aria-disabled', 'true');
                el.setAttribute('tabindex', '-1');
            } else {
                el.removeAttribute('aria-disabled');
                el.removeAttribute('tabindex');
            }
        });
        if (indirHint) indirHint.hidden = varMi;
        if (indirSayac) {
            indirSayac.hidden = !varMi;
            indirSayac.textContent = varMi ? seciliIdler.length : '';
        }
    }

    // Seçili öğe linklerine tıklamayı devre dışı bırak (disabled iken)
    indirOgeleri.forEach(el => {
        el.addEventListener('click', (e) => {
            if (el.classList.contains('is-disabled')) {
                e.preventDefault();
            }
        });
    });

    function guncelle() {
        const seciliSayi = Array.from(satirlar).filter(c => c.checked).length;
        const seciliIdler = Array.from(satirlar).filter(c => c.checked).map(c => c.value);
        if (sayi) sayi.textContent = seciliSayi;
        if (bar) bar.hidden = seciliSayi === 0;
        if (tumu) {
            tumu.checked = seciliSayi > 0 && seciliSayi === satirlar.length;
            tumu.indeterminate = seciliSayi > 0 && seciliSayi < satirlar.length;
        }
        indirGuncelle(seciliIdler);
    }

    if (tumu) {
        tumu.addEventListener('change', () => {
            satirlar.forEach(c => c.checked = tumu.checked);
            guncelle();
        });
    }
    satirlar.forEach(c => c.addEventListener('change', guncelle));

    if (temizle) {
        temizle.addEventListener('click', () => {
            satirlar.forEach(c => c.checked = false);
            if (tumu) { tumu.checked = false; tumu.indeterminate = false; }
            guncelle();
        });
    }

    // İlk yüklemede indir durumunu ayarla (0 seçili → pasif)
    guncelle();

    // Dropdown menüleri body'ye taşı — overflow/sticky sorunlarını atlatır
    document.querySelectorAll('.tl-dd').forEach(dd => {
        const btn = dd.querySelector('[data-dd-toggle]');
        const menu = dd.querySelector('.tl-dd-menu');
        if (!btn || !menu) return;

        // Menüyü body'ye taşı — dd ile bağını sakla
        document.body.appendChild(menu);
        dd._menu = menu;
        menu._sahip = dd;
    });

    function tumMenuleriKapat(harici) {
        document.querySelectorAll('.tl-dd.is-acik').forEach(d => {
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
        // Menü sağ hizada, butonun altında
        menu.style.position = 'fixed';
        menu.style.top = (r.bottom + 4) + 'px';
        menu.style.left = 'auto';
        menu.style.right = (window.innerWidth - r.right) + 'px';
        // Sağa taşarsa (mobilde) sola hizala
        const menuW = menu.offsetWidth;
        if (r.right < menuW + 12) {
            menu.style.right = 'auto';
            menu.style.left = r.left + 'px';
        }
        // Alta taşarsa yukarı aç
        const menuH = menu.offsetHeight;
        if (r.bottom + menuH + 12 > window.innerHeight) {
            menu.style.top = (r.top - menuH - 4) + 'px';
        }
    }

    document.querySelectorAll('[data-dd-toggle]').forEach(btn => {
        btn.addEventListener('click', (e) => {
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
                    // Konumlandır - display: block olduktan sonra offsetWidth ölçülebilir
                    requestAnimationFrame(() => menuKonumla(dd));
                }
            }
        });
    });

    // Dışa tıklama
    document.addEventListener('click', (e) => {
        // Menüye tıklamada kapatma
        if (e.target.closest('.tl-dd-menu')) return;
        tumMenuleriKapat();
    });
    // ESC kapatır
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') tumMenuleriKapat();
    });
    // Scroll/resize açık menüyü yeniden konumlar
    ['scroll', 'resize'].forEach(ev => {
        window.addEventListener(ev, () => {
            document.querySelectorAll('.tl-dd.is-acik').forEach(dd => menuKonumla(dd));
        }, { passive: true });
    });
})();

/* Kolon başlığındaki süzgeç butonu → popover → client-side filtre + chip bar */
(function () {
    const govde = document.querySelector('#tl-tablo tbody');
    const pop = document.getElementById('tl-flt-pop');
    const popBaslik = document.getElementById('tl-flt-pop-baslik');
    const popInput = document.getElementById('tl-flt-pop-input');
    const popUygula = document.getElementById('tl-flt-pop-uygula');
    const popSil = document.getElementById('tl-flt-pop-sil');
    const chipBar = document.getElementById('tl-flt-aktif-bar');
    const chipListe = document.getElementById('tl-flt-chip-liste');
    const chipTemizle = document.getElementById('tl-flt-temizle-hepsi');
    if (!govde || !pop) return;

    const norm = s => (s || '').toLocaleLowerCase('tr').replace(/\s+/g, ' ').trim();
    const filtreler = {}; // { kolon: {deger, etiket} }
    let aktifKolon = null;
    let aktifEtiket = null;

    function filtrele() {
        const anahtarlar = Object.keys(filtreler);
        let gorunen = 0;
        govde.querySelectorAll('tr[data-tasinmaz-id]').forEach(tr => {
            let goster = true;
            for (const k of anahtarlar) {
                const td = tr.querySelector('[data-kolon="' + k + '"]');
                const metin = norm(td ? td.textContent : '');
                if (! metin.includes(filtreler[k].deger)) { goster = false; break; }
            }
            tr.hidden = !goster;
            if (goster) gorunen++;
        });

        // Sonuç yok bilgi satırı
        let bosRow = govde.querySelector('#tl-flt-bos');
        if (gorunen === 0 && anahtarlar.length > 0) {
            if (!bosRow) {
                bosRow = document.createElement('tr');
                bosRow.id = 'tl-flt-bos';
                bosRow.innerHTML = '<td colspan="99" class="tl-ara-bos-hucre">Bu sayfadaki kayıtlarda kolon filtresine uyan sonuç yok. Filtreyi düzenleyin veya kaldırın.</td>';
                govde.appendChild(bosRow);
            }
            bosRow.hidden = false;
        } else if (bosRow) {
            bosRow.hidden = true;
        }

        chipleriGuncelle();
        thVurgulariGuncelle();
    }

    function chipleriGuncelle() {
        chipListe.innerHTML = '';
        const anahtarlar = Object.keys(filtreler);
        chipBar.hidden = anahtarlar.length === 0;
        anahtarlar.forEach(k => {
            const f = filtreler[k];
            const chip = document.createElement('span');
            chip.className = 'tl-flt-chip';
            chip.innerHTML = '<strong>' + f.etiket.replace(/[<>]/g, '') + ':</strong> <em>' + f.deger.replace(/[<>]/g, '') + '</em>'
                + '<button type="button" data-flt-sil="' + k + '" aria-label="Kaldır">'
                + '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>'
                + '</button>';
            chipListe.appendChild(chip);
        });
    }

    function thVurgulariGuncelle() {
        document.querySelectorAll('[data-tl-flt]').forEach(btn => {
            btn.classList.toggle('is-aktif', !!filtreler[btn.dataset.tlFlt]);
        });
    }

    function popAc(btn) {
        aktifKolon = btn.dataset.tlFlt;
        aktifEtiket = btn.dataset.tlFltEtiket || aktifKolon;
        popBaslik.textContent = aktifEtiket;
        popInput.value = filtreler[aktifKolon]?.deger || '';

        const r = btn.getBoundingClientRect();
        pop.style.top = (window.scrollY + r.bottom + 6) + 'px';
        const solOnceki = window.scrollX + r.left - 4;
        pop.hidden = false;
        // Ekran sağı taşarsa hizala
        const popW = pop.offsetWidth;
        const maxSol = window.scrollX + window.innerWidth - popW - 12;
        pop.style.left = Math.min(solOnceki, maxSol) + 'px';
        setTimeout(() => popInput.focus(), 20);
    }
    function popKapat() {
        pop.hidden = true;
        aktifKolon = null;
    }
    function popUygulaEylem() {
        if (!aktifKolon) return;
        const deger = popInput.value.trim();
        if (deger === '') { delete filtreler[aktifKolon]; }
        else filtreler[aktifKolon] = { deger: norm(deger), etiket: aktifEtiket };
        filtrele();
        popKapat();
    }
    function popSilEylem() {
        if (!aktifKolon) return;
        delete filtreler[aktifKolon];
        filtrele();
        popKapat();
    }

    // Süzgeç butonlarına tıklama
    document.querySelectorAll('[data-tl-flt]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (aktifKolon === btn.dataset.tlFlt) { popKapat(); return; }
            popAc(btn);
        });
    });

    // Popover eylemleri
    popUygula.addEventListener('click', popUygulaEylem);
    popSil.addEventListener('click', popSilEylem);
    popInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); popUygulaEylem(); }
        else if (e.key === 'Escape') popKapat();
    });

    // Dışarı tıklama kapatır
    document.addEventListener('click', (e) => {
        if (pop.hidden) return;
        if (pop.contains(e.target)) return;
        if (e.target.closest('[data-tl-flt]')) return;
        popKapat();
    });

    // Chip × butonu
    chipListe.addEventListener('click', (e) => {
        const sil = e.target.closest('[data-flt-sil]');
        if (!sil) return;
        delete filtreler[sil.dataset.fltSil];
        filtrele();
    });

    // Tümünü temizle
    chipTemizle.addEventListener('click', () => {
        Object.keys(filtreler).forEach(k => delete filtreler[k]);
        filtrele();
    });
})();

/* Kolon paneli — modal, gruplu, aranabilir + tabloya uygula */
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
    const varsayilanKolonlar = @json(App\Support\TasinmazKolonlari::varsayilan());
    if (!tetik || !backdrop) return;

    function tabloyaUygula() {
        const secili = kutular.filter(c => c.checked).map(c => c.value);
        document.querySelectorAll('#tl-tablo [data-kolon]').forEach(el => {
            el.classList.toggle('is-gizli', !secili.includes(el.getAttribute('data-kolon')));
        });
        sayacElemanlari.forEach(el => {
            const em = el.querySelector('em');
            const emHtml = em ? em.outerHTML : '';
            el.innerHTML = secili.length + emHtml;
        });
        grupSayaclariGuncelle();
    }

    function grupSayaclariGuncelle() {
        gruplar.forEach(g => {
            const cbler = Array.from(g.querySelectorAll('.kp-cb'));
            const secili = cbler.filter(c => c.checked).length;
            const sayac = g.querySelector('[data-kp-grup-sayac]');
            if (sayac) sayac.textContent = secili + '/' + cbler.length;
            g.classList.toggle('is-hepsi', secili === cbler.length);
            g.classList.toggle('is-hicbiri', secili === 0);
        });
    }

    let kayitZamanlayici = null;
    function kaydet() {
        const kolonlar = kutular.filter(c => c.checked).map(c => c.value);
        if (kolonlar.length === 0) return;
        if (durum) { durum.hidden = false; durum.textContent = 'Kaydediliyor…'; durum.className = 'kp-alt-durum is-info'; }
        fetch(kaydetUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
            },
            body: JSON.stringify({ tablo: 'tasinmazlar', kolonlar: kolonlar }),
        }).then(r => r.json()).then(() => {
            if (durum) { durum.textContent = 'Kaydedildi'; durum.className = 'kp-alt-durum is-ok'; }
            setTimeout(() => { if (durum) durum.hidden = true; }, 1400);
        }).catch(() => {
            if (durum) { durum.textContent = 'Kaydedilemedi'; durum.className = 'kp-alt-durum is-hata'; }
        });
    }

    function panelAc() {
        backdrop.hidden = false;
        document.body.style.overflow = 'hidden';
        setTimeout(() => ara?.focus(), 60);
    }
    function panelKapat() {
        backdrop.hidden = true;
        document.body.style.overflow = '';
    }

    tetik.addEventListener('click', panelAc);
    kapat?.addEventListener('click', panelKapat);
    vazgec?.addEventListener('click', panelKapat);
    backdrop.addEventListener('click', (e) => { if (e.target === backdrop) panelKapat(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !backdrop.hidden) panelKapat(); });

    // Checkbox değişimi
    kutular.forEach(c => c.addEventListener('change', () => {
        if (kutular.filter(x => x.checked).length === 0) {
            c.checked = true;
            return;
        }
        tabloyaUygula();
        clearTimeout(kayitZamanlayici);
        kayitZamanlayici = setTimeout(kaydet, 350);
    }));

    // Grup toggle butonu (tümünü seç/kapa)
    document.querySelectorAll('[data-kp-grup-toggle]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const grup = btn.closest('[data-kp-grup]');
            const cbler = Array.from(grup.querySelectorAll('.kp-cb'));
            const hepsiAcik = cbler.every(c => c.checked);
            cbler.forEach(c => c.checked = !hepsiAcik);
            if (kutular.filter(x => x.checked).length === 0) {
                cbler.forEach(c => c.checked = true);
            }
            tabloyaUygula();
            clearTimeout(kayitZamanlayici);
            kayitZamanlayici = setTimeout(kaydet, 350);
        });
    });

    // Toplu eylemler
    document.querySelectorAll('[data-kp-eylem]').forEach(btn => {
        btn.addEventListener('click', () => {
            const e = btn.dataset.kpEylem;
            if (e === 'tumu')      kutular.forEach(c => c.checked = true);
            else if (e === 'hicbiri') { kutular.forEach(c => c.checked = false); kutular[0].checked = true; }
            else if (e === 'varsayilan') kutular.forEach(c => c.checked = varsayilanKolonlar.includes(c.value));
            tabloyaUygula();
            clearTimeout(kayitZamanlayici);
            kayitZamanlayici = setTimeout(kaydet, 350);
        });
    });

    // Arama — eşleşme varsa grup otomatik açılır, boşsa varsayılan hâle döner
    ara?.addEventListener('input', () => {
        const q = ara.value.trim().toLocaleLowerCase('tr');
        araTemizle.hidden = q === '';
        let gorunenSayi = 0;
        document.querySelectorAll('.kp-secim').forEach(sec => {
            const ad = sec.querySelector('.kp-secim-ad')?.textContent.toLocaleLowerCase('tr') || '';
            const gorunur = q === '' || ad.includes(q);
            sec.hidden = !gorunur;
            if (gorunur) gorunenSayi++;
        });
        gruplar.forEach(g => {
            const gorulenler = Array.from(g.querySelectorAll('.kp-secim')).filter(s => !s.hidden);
            g.hidden = gorulenler.length === 0;
        });
        if (bos) bos.hidden = gorunenSayi > 0;
    });
    araTemizle?.addEventListener('click', () => {
        ara.value = '';
        ara.dispatchEvent(new Event('input'));
        ara.focus();
    });

    tabloyaUygula();
})();
</script>
@endsection
