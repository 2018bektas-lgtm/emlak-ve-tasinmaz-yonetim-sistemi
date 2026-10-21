@extends('layouts.panel')

@section('title', 'Kira İşleri Sorgulama')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=5">
    {{-- Kolon seçim paneli ve kolon filtresi stilleri burada --}}
    <link rel="stylesheet" href="{{ asset('css/tasinmaz-liste.css') }}?v=7">
    <link rel="stylesheet" href="{{ asset('css/kira.css') }}?v=12">
@endpush

@php
    $tl = fn ($n) => number_format((float) $n, 2, ',', '.');
    $sayi = fn ($n) => number_format((float) $n, 0, ',', '.');
    $sorguDurumsuz = collect(request()->query())->except(['dolu_bos', 'page'])->all();
    $filtreVar = collect($filtre)->filter(fn ($v) => filled($v))->isNotEmpty();
    // Hızlı sorgu rozetleri: hangisi etkin, başlıkta gösterilir
    $etkinKisayol = $filtre['kalan']
        ? $filtre['kalan'].' gün'
        : ($filtre['durum'] === 'dolmus' ? 'Süresi dolmuş' : ($filtre['ana_dosya'] ? 'Ana dosyalar' : null));
    // Ana dosya sorgusunda alt sözleşme toplamı anlam kazanır; kolonu kendiliğinden
    // aç — kullanıcının kayıtlı tercihi ($gorunurKolonlar) değişmez.
    $tabloKolonlari = $filtre['ana_dosya']
        ? array_values(array_unique(array_merge($gorunurKolonlar, ['alt_dosya_sayisi', 'alt_bedel_toplam'])))
        : $gorunurKolonlar;
@endphp

@section('content')
@if (session('basari'))<div class="flash-success" role="status">{{ session('basari') }}</div>@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Kira İşlemleri</p>
        <h2>Kira İşleri Sorgulama</h2>
        <small>
            <strong>{{ $sayi($sayac['tumu']) }}</strong> dosya
            @if ($etkinKisayol) · <span class="kr-kisayol-rozet">{{ $etkinKisayol }}</span> @endif
        </small>
    </div>
    <div class="page-hero-actions">
        @include('panel._kolon-secim')
        <a href="{{ route('panel.kira.excel', request()->query()) }}" class="btn-cancel">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            Excel
        </a>
        @can('kira.olustur')
            <a href="{{ route('panel.kira.alanlar.olustur') }}" class="btn-submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Yeni Kira Alanı
            </a>
        @endcan
    </div>
</section>

<div class="dg-ozet kr-ozet">
    <div class="dg-ozet-kutu"><span>Yürürlükteki yıllık kira</span><strong>{{ $tl($ozet['yillik_toplam']) }} <em>TL</em></strong></div>
    <div class="dg-ozet-kutu {{ $ozet['bir_ay'] ? 'is-uyari' : '' }}"><span>Son 1 ayı kalan</span><strong>{{ $ozet['bir_ay'] }} <em>dosya</em></strong></div>
    <div class="dg-ozet-kutu"><span>Son 3 ayı kalan</span><strong>{{ $ozet['uc_ay'] }} <em>dosya</em></strong></div>
    <div class="dg-ozet-kutu {{ $ozet['dolmus'] ? 'is-uyari' : '' }}"><span>Süresi dolmuş</span><strong>{{ $ozet['dolmus'] }} <em>dosya</em></strong></div>
</div>

<div class="hsl-kisayol-bar" role="tablist" aria-label="Doluluk durumu">
    <a href="{{ route('panel.kira.index', $sorguDurumsuz) }}"
       class="hsl-kisayol {{ $filtre['dolu_bos'] === null ? 'is-aktif' : '' }}" role="tab" aria-selected="{{ $filtre['dolu_bos'] === null ? 'true' : 'false' }}">
        Tümü <span class="say">{{ $sayac['tumu'] }}</span>
    </a>
    <a href="{{ route('panel.kira.index', $sorguDurumsuz + ['dolu_bos' => 'dolu']) }}"
       class="hsl-kisayol {{ $filtre['dolu_bos'] === 'dolu' ? 'is-aktif' : '' }}" role="tab" aria-selected="{{ $filtre['dolu_bos'] === 'dolu' ? 'true' : 'false' }}">
        <span class="kr-nokta" style="background:#dc2626"></span> Dolu <span class="say">{{ $sayac['dolu'] }}</span>
    </a>
    <a href="{{ route('panel.kira.index', $sorguDurumsuz + ['dolu_bos' => 'bos']) }}"
       class="hsl-kisayol {{ $filtre['dolu_bos'] === 'bos' ? 'is-aktif' : '' }}" role="tab" aria-selected="{{ $filtre['dolu_bos'] === 'bos' ? 'true' : 'false' }}">
        <span class="kr-nokta" style="background:#16a34a"></span> Boş <span class="say">{{ $sayac['bos'] }}</span>
    </a>
</div>

{{-- Sorgu formu: referanstaki 17 alanın karşılığı, iki sütunlu --}}
<form method="GET" action="{{ route('panel.kira.index') }}" class="kr-sorgu" id="kiraSorgu">
    @if ($filtre['dolu_bos'])<input type="hidden" name="dolu_bos" value="{{ $filtre['dolu_bos'] }}">@endif
    <input type="hidden" name="per_page" value="{{ $sayfaBoyu }}">

    <div class="hsl-ara kr-ara">
        <svg class="hsl-ara-ikon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="search" name="q" value="{{ $filtre['q'] }}" placeholder="Dosya no, kiracı, işyeri, ada/parsel, ilçe, mahalle…" autocomplete="off">
        <button type="submit" class="hsl-btn">Sorgula</button>
        <button type="button" class="hsl-btn is-ghost" data-detay-ac aria-expanded="{{ $filtreVar ? 'true' : 'false' }}">Detaylı Sorgu</button>
        @if ($filtreVar)
            <a href="{{ route('panel.kira.index') }}" class="hsl-btn is-tehlike">Temizle</a>
        @endif
    </div>

    <div class="kr-detay" id="kiraDetay" @unless ($filtreVar) hidden @endunless>
        <div class="kr-detay-izgara">
            <label class="kr-alan"><span>Dosya No</span><input type="text" name="dosya_no" value="{{ $filtre['dosya_no'] }}" placeholder="Eski dosya no dahil"></label>
            <label class="kr-alan"><span>Kiracı</span><input type="text" name="kiraci" value="{{ $filtre['kiraci'] }}" placeholder="Ad/unvan ya da TC/vergi no"></label>
            <label class="kr-alan"><span>İşyeri Adı</span><input type="text" name="isyeri" value="{{ $filtre['isyeri'] }}"></label>

            <label class="kr-alan">
                <span>İş Kolu</span>
                <select name="is_kolu_id">
                    <option value="">Tümü</option>
                    @foreach ($isKollari as $i)
                        <option value="{{ $i->id }}" @selected((string) $filtre['is_kolu_id'] === (string) $i->id)>{{ $i->ad }}</option>
                    @endforeach
                </select>
            </label>
            <label class="kr-alan">
                <span>İhale Durumu</span>
                <select name="ihale_turu">
                    <option value="">Tümü</option>
                    <option value="ihaleli" @selected($filtre['ihale_turu'] === 'ihaleli')>İhaleli</option>
                    <option value="ihalesiz" @selected($filtre['ihale_turu'] === 'ihalesiz')>İhalesiz</option>
                </select>
            </label>
            <label class="kr-alan">
                <span>Sözleşme Durumu</span>
                <select name="durum">
                    <option value="">Tümü</option>
                    <option value="aktif" @selected($filtre['durum'] === 'aktif')>Yürürlükte</option>
                    <option value="pasif" @selected($filtre['durum'] === 'pasif')>Yürürlükte değil</option>
                    <option value="dolmus" @selected($filtre['durum'] === 'dolmus')>Süresi dolmuş</option>
                </select>
            </label>

            <label class="kr-alan">
                <span>İlçe</span>
                <select name="ilce_id" onchange="this.form.submit()">
                    <option value="">Tümü</option>
                    @foreach ($ilceler as $i)
                        <option value="{{ $i->id }}" @selected((string) $filtre['ilce_id'] === (string) $i->id)>{{ $i->ad }}</option>
                    @endforeach
                </select>
            </label>
            <label class="kr-alan">
                <span>Mahalle</span>
                <select name="mahalle_id" @disabled($mahalleler->isEmpty())>
                    <option value="">{{ $mahalleler->isEmpty() ? 'Önce ilçe seçin' : 'Tümü' }}</option>
                    @foreach ($mahalleler as $m)
                        <option value="{{ $m->id }}" @selected((string) $filtre['mahalle_id'] === (string) $m->id)>{{ $m->ad }}</option>
                    @endforeach
                </select>
            </label>
            <div class="kr-alan kr-ikili">
                <span>Ada / Parsel</span>
                <div>
                    <input type="text" name="ada" value="{{ $filtre['ada'] }}" placeholder="Ada">
                    <input type="text" name="parsel" value="{{ $filtre['parsel'] }}" placeholder="Parsel">
                </div>
            </div>

            <label class="kr-alan">
                <span>Hukuki Durum</span>
                <select name="hukuki_durum_id">
                    <option value="">Tümü</option>
                    @foreach ($hukukiDurumlar as $h)
                        <option value="{{ $h->id }}" @selected((string) $filtre['hukuki_durum_id'] === (string) $h->id)>{{ $h->ad }}</option>
                    @endforeach
                </select>
            </label>
            <label class="kr-alan">
                <span>Kiracılık Durumu</span>
                <select name="kiracilik_durum_id">
                    <option value="">Tümü</option>
                    @foreach ($kiracilikDurumlar as $k)
                        <option value="{{ $k->id }}" @selected((string) $filtre['kiracilik_durum_id'] === (string) $k->id)>{{ $k->ad }}</option>
                    @endforeach
                </select>
            </label>
            <div class="kr-alan kr-ikili">
                <span>Kira Başlangıç / Bitiş</span>
                <div>
                    <input type="date" name="baslangic" value="{{ $filtre['baslangic'] }}" aria-label="Başlangıç tarihinden sonra">
                    <input type="date" name="bitis" value="{{ $filtre['bitis'] }}" aria-label="Bitiş tarihinden önce">
                </div>
            </div>

            <label class="kr-alan kr-onay">
                <input type="checkbox" name="tahliye" value="1" @checked($filtre['tahliye'])>
                <span>Yalnız tahliye ihbarnamesi çekilenler</span>
            </label>
            <label class="kr-alan kr-onay">
                <input type="checkbox" name="ana_dosya" value="1" @checked($filtre['ana_dosya'])>
                <span>Yalnız ana dosyalar (altında dosya bulunanlar)</span>
            </label>
        </div>

        <div class="kr-detay-ayak">
            <span class="hint">Boş bıraktığınız alanlar süzgece girmez.</span>
            <button type="submit" class="btn-submit">Sorgula</button>
        </div>
    </div>
</form>

@if ($kayitlar->isEmpty())
    <div class="hs-bos-kart">
        @if ($sayac['tumu'] === 0 && ! $filtreVar)
            Henüz kira dosyası yok.
        @else
            Sorgunuza uyan kira dosyası bulunamadı.
        @endif
    </div>
@else
    <div class="kr-liste">
    {{-- Aktif kolon filtreleri (istemci tarafı) --}}
    <div class="tl-flt-aktif-bar" id="tl-flt-aktif-bar" hidden>
        <span class="tl-flt-aktif-etkt">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54z"/></svg>
            Kolon filtresi
        </span>
        <div class="tl-flt-chip-liste" id="tl-flt-chip-liste"></div>
        <span class="kr-flt-sayac" id="tl-flt-sayac"></span>
        <button type="button" class="tl-flt-temizle-hepsi" id="tl-flt-temizle-hepsi">Tümünü temizle</button>
    </div>

    {{-- Kolon başlığındaki huni: yazdıkça bu sayfadaki satırları süzer --}}
    <div class="tl-flt-pop" id="tl-flt-pop" hidden>
        <div class="tl-flt-pop-baslik" id="tl-flt-pop-baslik">Kolon</div>
        <div class="tl-flt-pop-govde">
            <input type="text" id="tl-flt-pop-input" placeholder="Bu kolonda ara..." autocomplete="off">
            <p class="kr-flt-sonuc" id="tl-flt-pop-sonuc">Yazdıkça bu sayfadaki satırlar süzülür.</p>
        </div>
        <div class="tl-flt-pop-alt">
            <button type="button" class="tl-flt-pop-btn tl-flt-pop-btn-sil" id="tl-flt-pop-sil">Filtreyi kaldır</button>
        </div>
    </div>

    {{-- tl-genis: başlık ve İşlem sütunu yapışkan, tablo yatay kayar --}}
    <div class="tl-tablo-wrap tl-genis">
        <table class="tl-tablo kr-tablo" id="kiraTablo">
            <thead>
                <tr>
                    <th class="tl-sticky-sol tl-th-islem">İşlem</th>
                    @foreach ($kolonKatalog as $anahtar => $etiket)
                        <th data-kolon="{{ $anahtar }}" @class(['is-gizli' => ! in_array($anahtar, $tabloKolonlari, true)])>
                            <span class="tl-th-inner">
                                <span class="tl-th-metin">{{ $etiket }}</span>
                                <button type="button" class="tl-th-flt" data-tl-flt="{{ $anahtar }}" data-tl-flt-etiket="{{ $etiket }}" title="Bu kolonda ara" aria-label="Bu kolonda ara">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54z"/></svg>
                                </button>
                            </span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($kayitlar as $k)
                    @php
                        $t = $k->tasinmaz;
                        $s = $k->aktifSozlesme;
                        $kalan = $s?->kalanGun();
                        $bedel = $s?->guncelBedel();
                        $ana = $k->kiracilar->firstWhere('tip', \App\Enums\KiraciTipi::Ana);
                        // İlk sınırı olan alanın merkezi — satırdaki harita bağlantısı
                        $p = $k->alanlar->firstWhere(fn ($a) => $a->poligon?->merkez_lat !== null)?->poligon;
                        $merkez = $p ? $p->merkez_lat.','.$p->merkez_lng : null;
                        $altlar = $k->kiracilar->where('tip', \App\Enums\KiraciTipi::Alt);
                        $hucre = function (string $a, array $ek = []) use ($tabloKolonlari) {
                            $ek['is-gizli'] = ! in_array($a, $tabloKolonlari, true);

                            return $ek;
                        };
                    @endphp
                    <tr>
                        <td class="tl-sticky-sol tl-td-islem">
                            <div class="tl-dd">
                                <button type="button" class="tl-dd-btn" data-dd-toggle>
                                    İşlem
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </button>
                                <div class="tl-dd-menu">
                                    <a href="{{ route('panel.kira.detay', $k->id) }}" class="tl-dd-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                        Bilgi Kartı
                                    </a>
                                    @can('kira.duzenle')
                                        <a href="{{ route('panel.kira.duzenle', $k->id) }}" class="tl-dd-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                            Düzenle
                                        </a>
                                    @endcan
                                    @can('kira.olustur')
                                        {{-- Kiracı değişikliği en çok yapılan işlem: listeden tek adımda --}}
                                        <a href="{{ route('panel.kira.donem.olustur', $k->id) }}" class="tl-dd-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                                            Yeni Kiracı
                                        </a>
                                    @endcan
                                    <a href="{{ route('panel.kira.alanlar.index', ['dosya_no' => $k->dosya_no]) }}" class="tl-dd-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7l8-4 8 4-2 12-6 2-6-2z"/></svg>
                                        Kira Alanları
                                    </a>
                                    @can('kira.olustur')
                                        <a href="{{ route('panel.kira.alanlar.olustur', ['dosya' => $k->id]) }}" class="tl-dd-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                                            Alan Ekle
                                        </a>
                                    @endcan
                                    @if ($merkez)
                                        <a href="{{ route('panel.kira.harita', ['merkez' => $merkez, 'zoom' => 17]) }}" class="tl-dd-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.4"/></svg>
                                            Haritada Göster
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td data-kolon="dosya_no" @class($hucre('dosya_no'))>
                            <a href="{{ route('panel.kira.detay', $k->id) }}" class="kr-dosya-link td-mono">{{ $k->dosya_no }}</a>
                        </td>
                        <td data-kolon="eski_dosya_no" @class($hucre('eski_dosya_no', ['td-mono' => true]))>{{ $k->eski_dosya_no ?: '—' }}</td>
                        <td data-kolon="ana_dosya" @class($hucre('ana_dosya', ['td-mono' => true]))>
                            @if ($k->anaDosya)
                                <a href="{{ route('panel.kira.detay', $k->ana_dosya_id) }}" class="kr-dosya-link td-mono">{{ $k->anaDosya->dosya_no }}</a>
                            @else — @endif
                        </td>
                        <td data-kolon="alt_dosya_sayisi" @class($hucre('alt_dosya_sayisi', ['td-mono' => true, 'is-sag' => true]))>
                            @if ($k->alt_dosyalar_count)
                                <a href="{{ route('panel.kira.index', ['dosya_no' => $k->dosya_no]) }}" class="kr-dosya-link">{{ $k->alt_dosyalar_count }}</a>
                            @else — @endif
                        </td>
                        <td data-kolon="kiraci" @class($hucre('kiraci'))>{{ $ana?->ad_unvan ?: '—' }}</td>
                        <td data-kolon="alt_kiraci" @class($hucre('alt_kiraci'))>{{ $altlar->pluck('ad_unvan')->implode(', ') ?: '—' }}</td>
                        <td data-kolon="tc_vergi" @class($hucre('tc_vergi', ['td-mono' => true]))>{{ $ana?->tc_vergi_no ?: '—' }}</td>
                        <td data-kolon="telefon" @class($hucre('telefon', ['td-mono' => true]))>{{ $ana?->telefon ?: '—' }}</td>
                        <td data-kolon="isyeri" @class($hucre('isyeri'))>{{ $k->isyeri_adi ?: '—' }}</td>
                        <td data-kolon="is_kolu" @class($hucre('is_kolu'))>{{ $k->isKolu?->ad ?: '—' }}</td>
                        <td data-kolon="ilce" @class($hucre('ilce'))>{{ $t?->ilce?->ad ?: '—' }}</td>
                        <td data-kolon="mahalle" @class($hucre('mahalle'))>{{ $t?->mahalle?->ad ?: '—' }}</td>
                        <td data-kolon="ada_parsel" @class($hucre('ada_parsel', ['td-mono' => true]))>{{ $t?->adaParsel() ?: '—' }}</td>
                        <td data-kolon="adres" @class($hucre('adres', ['kr-td-adres' => true]))>{{ $t?->adres ?: '—' }}</td>
                        <td data-kolon="yuzolcum" @class($hucre('yuzolcum', ['td-mono' => true, 'is-sag' => true]))>{{ $t?->yuzolcum !== null ? $tl($t->yuzolcum) : '—' }}</td>
                        <td data-kolon="imar" @class($hucre('imar'))>{{ $t?->imarDurumu?->ad ?: '—' }}</td>
                        <td data-kolon="arac_kapasitesi" @class($hucre('arac_kapasitesi', ['td-mono' => true, 'is-sag' => true]))>{{ $k->arac_kapasitesi ?: '—' }}</td>
                        <td data-kolon="kira_donemi" @class($hucre('kira_donemi', ['td-mono' => true]))>
                            {{ $s ? $s->baslangic_tarihi?->format('d.m.Y').' – '.$s->bitis_tarihi?->format('d.m.Y') : '—' }}
                        </td>
                        <td data-kolon="kalan_gun" @class($hucre('kalan_gun'))>
                            @if ($kalan !== null)
                                <span class="kr-kalan {{ $kalan < 0 ? 'is-gecmis' : ($kalan <= 90 ? 'is-yakin' : '') }}">
                                    {{ $kalan < 0 ? abs($kalan).' gün geçti' : $kalan.' gün' }}
                                </span>
                            @else — @endif
                        </td>
                        <td data-kolon="sure" @class($hucre('sure', ['td-mono' => true]))>{{ $s?->sureYil() ? $s->sureYil().' yıl' : '—' }}</td>
                        <td data-kolon="yillik_bedel" @class($hucre('yillik_bedel', ['td-mono' => true, 'is-sag' => true]))>{{ $bedel?->yillik_bedel !== null ? $tl($bedel->yillik_bedel) : '—' }}</td>
                        <td data-kolon="aylik_bedel" @class($hucre('aylik_bedel', ['td-mono' => true, 'is-sag' => true]))>{{ $bedel?->aylik() !== null ? $tl($bedel->aylik()) : '—' }}</td>
                        <td data-kolon="alt_bedel_toplam" @class($hucre('alt_bedel_toplam', ['td-mono' => true, 'is-sag' => true]))>{{ $k->alt_bedel_toplam !== null ? $tl($k->alt_bedel_toplam) : '—' }}</td>
                        <td data-kolon="taksit" @class($hucre('taksit', ['td-mono' => true, 'is-sag' => true]))>{{ $s?->taksit_sayisi ?: '—' }}</td>
                        <td data-kolon="odeme_sekli" @class($hucre('odeme_sekli'))>{{ $s?->odeme_sekli ?: '—' }}</td>
                        <td data-kolon="sozlesme_no" @class($hucre('sozlesme_no', ['td-mono' => true]))>{{ $s?->sozlesme_no ?: '—' }}</td>
                        <td data-kolon="sozlesme_tarihi" @class($hucre('sozlesme_tarihi', ['td-mono' => true]))>{{ $s?->sozlesme_tarihi?->format('d.m.Y') ?: '—' }}</td>
                        <td data-kolon="dolu_bos" @class($hucre('dolu_bos'))>
                            <span class="kr-durum" style="--kr-renk:{{ $k->dolu_bos->renk() }}">{{ $k->dolu_bos->etiket() }}</span>
                        </td>
                        <td data-kolon="ihale" @class($hucre('ihale'))>{{ $k->ihale_turu->etiket() }}</td>
                        <td data-kolon="hukuki_durum" @class($hucre('hukuki_durum'))>{{ $k->hukukiDurum?->ad ?: '—' }}</td>
                        <td data-kolon="kiracilik_durum" @class($hucre('kiracilik_durum'))>{{ $k->kiracilikDurum?->ad ?: '—' }}</td>
                        <td data-kolon="tahliye" @class($hucre('tahliye'))>
                            @if ($k->tahliye_ihbarnamesi_mi)<span class="kr-etiket is-tehlike">Çekildi</span>@else — @endif
                        </td>
                        <td data-kolon="muhasebe" @class($hucre('muhasebe'))>{{ $k->muhasebeNiteligi?->ad ?: '—' }}</td>
                        <td data-kolon="sorumlu" @class($hucre('sorumlu'))>{{ $k->sorumluPersonel ? $k->sorumluPersonel->ad.' '.$k->sorumluPersonel->soyad : '—' }}</td>
                        <td data-kolon="uyari" @class($hucre('uyari'))>
                            @if ($k->uyari)<span class="kr-etiket is-tehlike">{{ $k->uyari }}</span>@else — @endif
                        </td>
                        <td data-kolon="aciklama" @class($hucre('aciklama', ['kr-td-adres' => true]))>{{ $k->aciklama ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="kr-sayfalama">
        <form method="get" action="{{ route('panel.kira.index') }}" class="kr-sayfa-boyut">
            @foreach (collect(request()->query())->except(['per_page', 'page']) as $ad => $deger)
                @if (is_array($deger))
                    @foreach ($deger as $altAd => $alt)
                        @if (is_scalar($alt))
                            <input type="hidden" name="{{ $ad }}[{{ $altAd }}]" value="{{ $alt }}">
                        @endif
                    @endforeach
                @elseif (is_scalar($deger))
                    <input type="hidden" name="{{ $ad }}" value="{{ $deger }}">
                @endif
            @endforeach
            <label for="kr-sayfa-boyu">Tabloda</label>
            <input id="kr-sayfa-boyu" type="number" name="per_page" min="10" max="200" step="1" value="{{ $sayfaBoyu }}" inputmode="numeric" title="10 ile 200 arası">
            <span>kayıt</span>
            <button type="submit">Uygula</button>
        </form>
        <p class="kr-sayfa-bilgi">{{ $sayi($kayitlar->firstItem()) }}–{{ $sayi($kayitlar->lastItem()) }} / {{ $sayi($kayitlar->total()) }} kayıt</p>
        <div class="tl-pagination">{{ $kayitlar->onEachSide(1)->links('panel.kira._sayfalama') }}</div>
    </div>
    </div>
@endif

<script>
(function () {
    const dugme = document.querySelector('[data-detay-ac]');
    const kutu = document.getElementById('kiraDetay');
    dugme.addEventListener('click', function () {
        const acik = kutu.hidden;
        kutu.hidden = !acik;
        dugme.setAttribute('aria-expanded', acik ? 'true' : 'false');
    });
})();

/* ---------- Kolon seçimi ---------- */
(function () {
    const tetik = document.getElementById('kp-tetik');
    if (!tetik) return;
    const backdrop = document.getElementById('kp-backdrop');
    /* Hero kartında backdrop-filter var; fixed modal oraya hapsolmasın. */
    backdrop.classList.add('kr-kp');
    document.body.appendChild(backdrop);
    const liste = document.getElementById('kp-liste');
    const ara = document.getElementById('kp-ara');
    const araTemizle = document.getElementById('kp-ara-temizle');
    const bos = document.getElementById('kp-bos');
    const durum = document.getElementById('kp-durum');
    const kutular = Array.from(liste.querySelectorAll('.kp-cb'));
    const varsayilan = @json($varsayilanKolonlar);
    const kaydetUrl = @json(route('panel.kolon-tercihi.kaydet'));
    const csrf = @json(csrf_token());
    let kaydetZamanlayici = null;

    const secili = () => kutular.filter(k => k.checked).map(k => k.value);

    function tabloyaUygula() {
        const acik = new Set(secili());
        document.querySelectorAll('#kiraTablo [data-kolon]').forEach(h => {
            h.classList.toggle('is-gizli', !acik.has(h.dataset.kolon));
        });
        document.querySelectorAll('[data-kp-sayac]').forEach(e => {
            // Tetikteki sayaç "<n>/<toplam>" biçiminde; yalnızca sayıyı değiştir
            const em = e.querySelector('em');
            e.textContent = acik.size;
            if (em) e.appendChild(em);
        });
        liste.querySelectorAll('[data-kp-grup]').forEach(g => {
            const gk = Array.from(g.querySelectorAll('.kp-cb'));
            const sec = gk.filter(k => k.checked).length;
            g.querySelector('[data-kp-grup-sayac]').textContent = sec + '/' + gk.length;
            g.classList.toggle('is-hepsi', gk.length > 0 && sec === gk.length);
            g.classList.toggle('is-hicbiri', sec === 0);
        });
    }

    function kaydet() {
        clearTimeout(kaydetZamanlayici);
        kaydetZamanlayici = setTimeout(async function () {
            const kolonlar = secili();
            if (!kolonlar.length) return;
            durum.hidden = false;
            durum.className = 'kp-alt-durum is-info';
            durum.textContent = 'Kaydediliyor…';
            try {
                const r = await fetch(kaydetUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ tablo: 'kira', kolonlar: kolonlar }),
                });
                durum.className = 'kp-alt-durum ' + (r.ok ? 'is-ok' : 'is-hata');
                durum.textContent = r.ok ? 'Kaydedildi' : 'Kaydedilemedi';
            } catch (e) {
                durum.className = 'kp-alt-durum is-hata';
                durum.textContent = 'Kaydedilemedi';
            }
            setTimeout(() => { durum.hidden = true; }, 1800);
        }, 500);
    }

    function modalKapat() { backdrop.hidden = true; }
    tetik.addEventListener('click', () => { backdrop.hidden = false; ara.focus(); });
    document.getElementById('kp-kapat').addEventListener('click', modalKapat);
    document.getElementById('kp-vazgec').addEventListener('click', modalKapat);
    backdrop.addEventListener('click', e => { if (e.target === backdrop) modalKapat(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !backdrop.hidden) modalKapat(); });

    kutular.forEach(k => k.addEventListener('change', () => { tabloyaUygula(); kaydet(); }));

    liste.querySelectorAll('[data-kp-grup-toggle]').forEach(b => b.addEventListener('click', function () {
        const grup = this.closest('[data-kp-grup]');
        const gk = Array.from(grup.querySelectorAll('.kp-cb'));
        const hepsi = gk.every(k => k.checked);
        gk.forEach(k => { k.checked = !hepsi; });
        tabloyaUygula(); kaydet();
    }));

    document.querySelectorAll('[data-kp-eylem]').forEach(b => b.addEventListener('click', function () {
        const e = this.dataset.kpEylem;
        kutular.forEach(k => {
            k.checked = e === 'tumu' ? true : (e === 'hicbiri' ? false : varsayilan.includes(k.value));
        });
        tabloyaUygula(); kaydet();
    }));

    ara.addEventListener('input', function () {
        const q = this.value.trim().toLocaleLowerCase('tr');
        araTemizle.hidden = q === '';
        let gorunen = 0;
        liste.querySelectorAll('.kp-secim').forEach(s => {
            const uyar = s.querySelector('.kp-secim-ad').textContent.toLocaleLowerCase('tr').includes(q);
            s.hidden = !uyar;
            if (uyar) gorunen++;
        });
        liste.querySelectorAll('[data-kp-grup]').forEach(g => {
            g.hidden = g.querySelectorAll('.kp-secim:not([hidden])').length === 0;
        });
        bos.hidden = gorunen > 0;
    });
    araTemizle.addEventListener('click', () => { ara.value = ''; ara.dispatchEvent(new Event('input')); ara.focus(); });

    tabloyaUygula();
})();

/* ---------- Kolon filtreleri: yazdıkça bu sayfadaki satırlar süzülür ---------- */
(function () {
    const tablo = document.getElementById('kiraTablo');
    const pop = document.getElementById('tl-flt-pop');
    if (!tablo || !pop) return;
    const govde = tablo.querySelector('tbody');
    const popBaslik = document.getElementById('tl-flt-pop-baslik');
    const popInput = document.getElementById('tl-flt-pop-input');
    const popSonuc = document.getElementById('tl-flt-pop-sonuc');
    const bar = document.getElementById('tl-flt-aktif-bar');
    const barListe = document.getElementById('tl-flt-chip-liste');
    const sayac = document.getElementById('tl-flt-sayac');
    const filtreler = {};
    let aktifKolon = null;
    let aktifEtiket = null;

    const norm = s => (s || '').toLocaleLowerCase('tr').replace(/\s+/g, ' ').trim();
    const kacis = s => s.replace(/[<>&"]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' }[c]));

    function filtrele() {
        const anahtarlar = Object.keys(filtreler);
        let gorunen = 0;
        govde.querySelectorAll('tr:not(#tl-flt-bos)').forEach(tr => {
            const uyar = anahtarlar.every(a => {
                const td = tr.querySelector('[data-kolon="' + a + '"]');
                return norm(td ? td.textContent : '').includes(filtreler[a].deger);
            });
            tr.classList.toggle('is-flt-gizli', !uyar);
            if (uyar) gorunen++;
        });

        let bos = govde.querySelector('#tl-flt-bos');
        if (gorunen === 0 && anahtarlar.length > 0) {
            if (!bos) {
                bos = document.createElement('tr');
                bos.id = 'tl-flt-bos';
                const kolon = tablo.querySelectorAll('thead th').length;
                bos.innerHTML = '<td colspan="' + kolon + '" class="tl-ara-bos-hucre">Bu sayfada kolon filtresine uyan kayıt yok.</td>';
                govde.appendChild(bos);
            }
            bos.classList.remove('is-flt-gizli');
        } else if (bos) {
            bos.remove();
        }

        bar.hidden = anahtarlar.length === 0;
        barListe.innerHTML = '';
        anahtarlar.forEach(a => {
            const f = filtreler[a];
            const chip = document.createElement('span');
            chip.className = 'tl-flt-chip';
            chip.innerHTML = '<strong>' + kacis(f.etiket) + '</strong> <em>' + kacis(f.deger) + '</em>'
                + '<button type="button" data-flt-sil="' + kacis(a) + '" aria-label="Kaldır">'
                + '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>'
                + '</button>';
            barListe.appendChild(chip);
        });
        const toplam = govde.querySelectorAll('tr:not(#tl-flt-bos)').length;
        const metin = anahtarlar.length === 0
            ? 'Yazdıkça bu sayfadaki satırlar süzülür.'
            : (gorunen === 0 ? 'Eşleşen kayıt yok' : gorunen + ' / ' + toplam + ' kayıt');
        sayac.textContent = anahtarlar.length === 0 ? '' : metin;
        popSonuc.textContent = metin;
        popSonuc.classList.toggle('is-bos', anahtarlar.length > 0 && gorunen === 0);

        tablo.querySelectorAll('.tl-th-flt').forEach(b => {
            b.classList.toggle('is-aktif', !!filtreler[b.dataset.tlFlt]);
        });
    }

    function yazildikca() {
        if (!aktifKolon) return;
        const deger = norm(popInput.value);
        if (deger === '') delete filtreler[aktifKolon];
        else filtreler[aktifKolon] = { deger: deger, etiket: aktifEtiket };
        filtrele();
    }

    function popKonumla(btn) {
        const r = btn.getBoundingClientRect();
        pop.hidden = false;
        const genislik = pop.offsetWidth || 280;
        const sol = Math.min(Math.max(8, r.left), window.innerWidth - genislik - 8);
        pop.style.left = sol + 'px';
        pop.style.top = (r.bottom + 8) + 'px';
    }

    function popAc(btn) {
        aktifKolon = btn.dataset.tlFlt;
        aktifEtiket = btn.dataset.tlFltEtiket || aktifKolon;
        popBaslik.textContent = aktifEtiket;
        popInput.value = filtreler[aktifKolon]?.deger || '';
        popKonumla(btn);
        filtrele();
        popInput.focus();
    }

    function popKapat() {
        pop.hidden = true;
        aktifKolon = null;
    }

    tablo.querySelectorAll('.tl-th-flt').forEach(b => b.addEventListener('click', function (e) {
        e.stopPropagation();
        if (aktifKolon === this.dataset.tlFlt && !pop.hidden) { popKapat(); return; }
        popAc(this);
    }));

    popInput.addEventListener('input', yazildikca);
    popInput.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') popKapat();
    });
    document.getElementById('tl-flt-pop-sil').addEventListener('click', function () {
        if (aktifKolon) delete filtreler[aktifKolon];
        popInput.value = '';
        filtrele();
        popInput.focus();
    });
    document.addEventListener('click', function (e) {
        if (pop.hidden) return;
        if (pop.contains(e.target) || e.target.closest('.tl-th-flt')) return;
        popKapat();
    });
    barListe.addEventListener('click', function (e) {
        const b = e.target.closest('[data-flt-sil]');
        if (!b) return;
        delete filtreler[b.dataset.fltSil];
        if (aktifKolon === b.dataset.fltSil) popInput.value = '';
        filtrele();
    });
    document.getElementById('tl-flt-temizle-hepsi').addEventListener('click', function () {
        Object.keys(filtreler).forEach(a => delete filtreler[a]);
        popInput.value = '';
        filtrele();
    });
    function popTazele() {
        if (pop.hidden || !aktifKolon) return;
        const btn = tablo.querySelector('.tl-th-flt[data-tl-flt="' + aktifKolon + '"]');
        if (btn) popKonumla(btn);
    }
    window.addEventListener('resize', popTazele);
    window.addEventListener('scroll', popTazele, true);
})();

/* ---------- Satır işlem menüsü ----------
   Menü body'ye taşınır: tablo yatay/dikey kaydığı ve İşlem sütunu yapışık
   olduğu için menü hücrenin içinde kalırsa kırpılıyor. */
(function () {
    const menuler = [];
    document.querySelectorAll('.kr-liste .tl-dd').forEach(function (dd) {
        const menu = dd.querySelector('.tl-dd-menu');
        if (!menu) return;
        document.body.appendChild(menu);
        dd._menu = menu;
        menuler.push(dd);
    });
    if (!menuler.length) return;

    function hepsiniKapat(haric) {
        menuler.forEach(function (d) {
            if (d === haric) return;
            d.classList.remove('is-acik');
            d._menu.classList.remove('is-acik');
        });
    }

    function konumla(dd) {
        const btn = dd.querySelector('[data-dd-toggle]');
        const menu = dd._menu;
        const r = btn.getBoundingClientRect();
        menu.style.position = 'fixed';
        menu.style.top = (r.bottom + 4) + 'px';
        menu.style.left = r.left + 'px';
        menu.style.right = 'auto';
        if (r.left + menu.offsetWidth + 12 > window.innerWidth) {
            menu.style.left = 'auto';
            menu.style.right = (window.innerWidth - r.right) + 'px';
        }
        if (r.bottom + menu.offsetHeight + 12 > window.innerHeight) {
            menu.style.top = (r.top - menu.offsetHeight - 4) + 'px';
        }
    }

    menuler.forEach(function (dd) {
        dd.querySelector('[data-dd-toggle]').addEventListener('click', function (e) {
            e.stopPropagation();
            const acikti = dd.classList.contains('is-acik');
            hepsiniKapat(acikti ? null : dd);
            dd.classList.toggle('is-acik', !acikti);
            dd._menu.classList.toggle('is-acik', !acikti);
            if (!acikti) requestAnimationFrame(function () { konumla(dd); });
        });
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('.tl-dd-menu')) return;
        hepsiniKapat();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') hepsiniKapat();
    });
    ['scroll', 'resize'].forEach(function (olay) {
        window.addEventListener(olay, function () {
            menuler.forEach(function (dd) { if (dd.classList.contains('is-acik')) konumla(dd); });
        }, { passive: true, capture: olay === 'scroll' });
    });
})();
</script>
@endsection
