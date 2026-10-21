@extends('layouts.panel')

@section('title', 'Kira Alanları')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=5">
    <link rel="stylesheet" href="{{ asset('css/kira.css') }}?v=6">
@endpush

@php
    $tl = fn ($n) => $n === null ? '—' : number_format((float) $n, 2, ',', '.');
    $sayi = fn ($n) => number_format((float) $n, 0, ',', '.');
    $sorguDurumsuz = collect(request()->query())->except(['dolu_bos', 'page'])->all();
    $filtreVar = collect($filtre)->filter(fn ($v) => filled($v))->isNotEmpty();
@endphp

@section('content')
@if (session('basari'))<div class="flash-success" role="status">{{ session('basari') }}</div>@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Kira İşlemleri</p>
        <h2>Kira Alanları</h2>
        <small><strong>{{ $sayi($sayac['tumu']) }}</strong> alan · bir dosyada birden çok yer kiraya verilebilir</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.kira.alanlar.excel', request()->query()) }}" class="btn-cancel">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            Excel
        </a>
        @can('kira.olustur')
            <a href="{{ route('panel.kira.alanlar.olustur') }}" class="btn-submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Yeni Alan
            </a>
        @endcan
    </div>
</section>

<div class="dg-ozet kr-ozet">
    <div class="dg-ozet-kutu"><span>Toplam kiraya verilen</span><strong>{{ $tl($ozet['toplam_alan']) }} <em>m²</em></strong></div>
    <div class="dg-ozet-kutu"><span>Yıllık bedel toplamı</span><strong>{{ $tl($ozet['yillik_toplam']) }} <em>TL</em></strong></div>
    <div class="dg-ozet-kutu {{ $ozet['bir_ay'] ? 'is-uyari' : '' }}"><span>Son 1 ayı kalan</span><strong>{{ $ozet['bir_ay'] }} <em>alan</em></strong></div>
    <div class="dg-ozet-kutu {{ $ozet['dolmus'] ? 'is-uyari' : '' }}"><span>Süresi dolmuş</span><strong>{{ $ozet['dolmus'] }} <em>alan</em></strong></div>
</div>

<div class="hsl-kisayol-bar" role="tablist" aria-label="Doluluk durumu">
    <a href="{{ route('panel.kira.alanlar.index', $sorguDurumsuz) }}"
       class="hsl-kisayol {{ $filtre['dolu_bos'] === null ? 'is-aktif' : '' }}" role="tab" aria-selected="{{ $filtre['dolu_bos'] === null ? 'true' : 'false' }}">
        Tümü <span class="say">{{ $sayac['tumu'] }}</span>
    </a>
    <a href="{{ route('panel.kira.alanlar.index', $sorguDurumsuz + ['dolu_bos' => 'dolu']) }}"
       class="hsl-kisayol {{ $filtre['dolu_bos'] === 'dolu' ? 'is-aktif' : '' }}" role="tab" aria-selected="{{ $filtre['dolu_bos'] === 'dolu' ? 'true' : 'false' }}">
        <span class="kr-nokta" style="background:#dc2626"></span> Dolu <span class="say">{{ $sayac['dolu'] }}</span>
    </a>
    <a href="{{ route('panel.kira.alanlar.index', $sorguDurumsuz + ['dolu_bos' => 'bos']) }}"
       class="hsl-kisayol {{ $filtre['dolu_bos'] === 'bos' ? 'is-aktif' : '' }}" role="tab" aria-selected="{{ $filtre['dolu_bos'] === 'bos' ? 'true' : 'false' }}">
        <span class="kr-nokta" style="background:#16a34a"></span> Boş <span class="say">{{ $sayac['bos'] }}</span>
    </a>
</div>

<form method="GET" action="{{ route('panel.kira.alanlar.index') }}" class="kr-sorgu">
    @if ($filtre['dolu_bos'])<input type="hidden" name="dolu_bos" value="{{ $filtre['dolu_bos'] }}">@endif

    <div class="hsl-ara kr-ara">
        <svg class="hsl-ara-ikon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="search" name="q" value="{{ $filtre['q'] }}" placeholder="Alan adı, kiracı, faaliyet, adres, dosya no…" autocomplete="off">
        <button type="submit" class="hsl-btn">Sorgula</button>
        <button type="button" class="hsl-btn is-ghost" data-detay-ac aria-expanded="{{ $filtreVar ? 'true' : 'false' }}">Detaylı Sorgu</button>
        @if ($filtreVar)<a href="{{ route('panel.kira.alanlar.index') }}" class="hsl-btn is-tehlike">Temizle</a>@endif
    </div>

    <div class="kr-detay" id="kiraDetay" @unless ($filtreVar) hidden @endunless>
        <div class="kr-detay-izgara">
            <label class="kr-alan"><span>Dosya No</span><input type="text" name="dosya_no" value="{{ $filtre['dosya_no'] }}"></label>
            <label class="kr-alan"><span>Kiracı</span><input type="text" name="kiraci" value="{{ $filtre['kiraci'] }}"></label>
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
            <label class="kr-alan">
                <span>Durum</span>
                <select name="durum">
                    <option value="">Tümü</option>
                    <option value="aktif" @selected($filtre['durum'] === 'aktif')>Aktif</option>
                    <option value="pasif" @selected($filtre['durum'] === 'pasif')>Pasif</option>
                    <option value="dolmus" @selected($filtre['durum'] === 'dolmus')>Süresi dolmuş</option>
                </select>
            </label>
            <div class="kr-alan kr-ikili">
                <span>Kira Başlangıç / Bitiş</span>
                <div>
                    <input type="date" name="baslangic" value="{{ $filtre['baslangic'] }}" aria-label="Başlangıçtan sonra">
                    <input type="date" name="bitis" value="{{ $filtre['bitis'] }}" aria-label="Bitişten önce">
                </div>
            </div>
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
            Henüz kira alanı yok. Bir dosyanın detayından ya da <strong>Yeni Alan</strong> ile ekleyebilirsiniz.
        @else
            Sorgunuza uyan kira alanı bulunamadı.
        @endif
    </div>
@else
    <div class="tl-tablo-wrap">
        <table class="tl-tablo kr-tablo">
            <thead>
                <tr>
                    <th>Alan</th>
                    <th>Dosya</th>
                    <th>Kiracı</th>
                    <th>İş Kolu / Faaliyet</th>
                    <th>Konum</th>
                    <th class="is-sag">Alan (m²)</th>
                    <th class="is-sag">Yıllık Bedel (₺)</th>
                    <th>Dönem</th>
                    <th>Durum</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kayitlar as $a)
                    @php $t = $a->dosya?->tasinmaz; $kalan = $a->kalanGun(); @endphp
                    <tr>
                        <td>
                            <a href="{{ route('panel.kira.alanlar.detay', $a->id) }}" class="kr-dosya-link">{{ $a->ad ?: 'Adsız alan' }}</a>
                            @if ($a->poligon)
                                <small class="dg-alt">
                                    Poligon #{{ $a->poligon->id }}
                                    @if ($a->poligon->alanlar_count ?? null)· {{ $a->poligon->alanlar_count }} kayıt @endif
                                </small>
                            @else
                                <small class="dg-alt">Geometri yok</small>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('panel.kira.detay', $a->kira_dosya_id) }}" class="td-mono kr-dosya-link">{{ $a->dosya?->dosya_no }}</a>
                            <small class="dg-alt">{{ $a->dosya?->isyeri_adi }}</small>
                        </td>
                        <td>
                            {{ $a->kiraci_ad_unvan ?: ($a->dosya?->anaKiraci?->ad_unvan ?: '—') }}
                            @if ($a->alt_kiraci)<small class="dg-alt">alt: {{ $a->alt_kiraci }}</small>@endif
                            @if ($a->tahliye_ihbarnamesi_mi)<small class="kr-etiket is-tehlike">Tahliye ihbarnamesi</small>@endif
                        </td>
                        <td>
                            {{ $a->isKolu?->ad ?: '—' }}
                            @if ($a->faaliyet_konusu)<small class="dg-alt">{{ $a->faaliyet_konusu }}</small>@endif
                        </td>
                        <td>
                            {{ $t?->ilce?->ad ?: '—' }}@if ($t?->mahalle) · {{ $t->mahalle->ad }}@endif
                            @if ($t?->adaParsel())<small class="dg-alt td-mono">{{ $t->adaParsel() }}</small>@endif
                        </td>
                        <td class="td-mono is-sag">{{ $tl($a->alan) }}</td>
                        <td class="td-mono is-sag">{{ $tl($a->yillik_bedel) }}</td>
                        <td>
                            @if ($a->baslangic_tarihi || $a->bitis_tarihi)
                                <span class="td-mono">{{ $a->baslangic_tarihi?->format('d.m.Y') ?: '—' }} – {{ $a->bitis_tarihi?->format('d.m.Y') ?: '—' }}</span>
                                @if ($kalan !== null)
                                    <small class="kr-kalan {{ $kalan < 0 ? 'is-gecmis' : ($kalan <= 90 ? 'is-yakin' : '') }}">
                                        {{ $kalan < 0 ? abs($kalan).' gün geçti' : $kalan.' gün' }}
                                    </small>
                                @endif
                            @else — @endif
                        </td>
                        <td>
                            <span class="kr-durum" style="--kr-renk:{{ $a->dolu_bos->renk() }}">{{ $a->dolu_bos->etiket() }}</span>
                            @unless ($a->aktif_mi)<small class="dg-alt">Pasif</small>@endunless
                        </td>
                        <td class="tl-th-islem">
                            <a href="{{ route('panel.kira.alanlar.detay', $a->id) }}" class="btn-cancel hs-mini-btn">Kart</a>
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
    if (!dugme) return;
    dugme.addEventListener('click', function () {
        const acik = kutu.hidden;
        kutu.hidden = !acik;
        dugme.setAttribute('aria-expanded', acik ? 'true' : 'false');
    });
})();
</script>
@endsection
