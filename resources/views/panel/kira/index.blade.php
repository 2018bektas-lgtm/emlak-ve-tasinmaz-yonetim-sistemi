@extends('layouts.panel')

@section('title', 'Kira İşleri Sorgulama')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=5">
    <link rel="stylesheet" href="{{ asset('css/kira.css') }}?v=1">
@endpush

@php
    $tl = fn ($n) => number_format((float) $n, 2, ',', '.');
    $sayi = fn ($n) => number_format((float) $n, 0, ',', '.');
    $sorguDurumsuz = collect(request()->query())->except(['dolu_bos', 'page'])->all();
    $filtreVar = collect($filtre)->filter(fn ($v) => filled($v))->isNotEmpty();
    // Hızlı sorgu rozetleri: hangisi etkin, başlıkta gösterilir
    $etkinKisayol = $filtre['kalan'] ? $filtre['kalan'].' gün' : ($filtre['durum'] === 'dolmus' ? 'Süresi dolmuş' : null);
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
        <a href="{{ route('panel.kira.excel', request()->query()) }}" class="btn-cancel">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            Excel
        </a>
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
    <div class="tl-tablo-wrap">
        <table class="tl-tablo kr-tablo">
            <thead>
                <tr>
                    <th>Dosya No</th>
                    <th>Kiracı</th>
                    <th>İşyeri / İş Kolu</th>
                    <th>Konum</th>
                    <th class="is-sag">Yüzölçüm (m²)</th>
                    <th>Kira Dönemi</th>
                    <th>Durum</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kayitlar as $k)
                    @php
                        $t = $k->tasinmaz;
                        $s = $k->aktifSozlesme;
                        $kalan = $s?->kalanGun();
                    @endphp
                    <tr>
                        <td>
                            <span class="dg-tasinmaz">
                                <strong class="td-mono">{{ $k->dosya_no }}</strong>
                                @if ($k->eski_dosya_no)<small>eski: {{ $k->eski_dosya_no }}</small>@endif
                            </span>
                        </td>
                        <td>
                            {{ $k->anaKiraci?->ad_unvan ?: '—' }}
                            @if ($k->tahliye_ihbarnamesi_mi)<small class="kr-etiket is-tehlike">Tahliye ihbarnamesi</small>@endif
                        </td>
                        <td>
                            {{ $k->isyeri_adi ?: '—' }}
                            @if ($k->isKolu)<small class="dg-alt">{{ $k->isKolu->ad }}</small>@endif
                        </td>
                        <td>
                            @if ($t)
                                {{ $t->ilce?->ad }}@if ($t->mahalle) · {{ $t->mahalle->ad }}@endif
                                @if ($t->adaParsel())<small class="dg-alt td-mono">{{ $t->adaParsel() }}</small>@endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="td-mono is-sag">{{ $t?->yuzolcum !== null ? $tl($t->yuzolcum) : '—' }}</td>
                        <td>
                            @if ($s)
                                <span class="td-mono">{{ $s->baslangic_tarihi?->format('d.m.Y') }} – {{ $s->bitis_tarihi?->format('d.m.Y') }}</span>
                                @if ($kalan !== null)
                                    <small class="kr-kalan {{ $kalan < 0 ? 'is-gecmis' : ($kalan <= 90 ? 'is-yakin' : '') }}">
                                        {{ $kalan < 0 ? abs($kalan).' gün geçti' : $kalan.' gün kaldı' }}
                                    </small>
                                @endif
                            @else
                                <span class="dg-alt">Sözleşme yok</span>
                            @endif
                        </td>
                        <td>
                            <span class="kr-durum" style="--kr-renk:{{ $k->dolu_bos->renk() }}">{{ $k->dolu_bos->etiket() }}</span>
                            <small class="dg-alt">{{ $k->ihale_turu->etiket() }}</small>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="tl-pagination">{{ $kayitlar->links() }}</div>
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
</script>
@endsection
