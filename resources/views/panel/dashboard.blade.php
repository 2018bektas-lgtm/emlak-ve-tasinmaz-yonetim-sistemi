@extends('layouts.panel')

@section('title', 'Özet')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/ozet.css') }}?v=1">
@endpush

@php
    $saat = (int) now()->format('H');
    $selam = $saat < 6 ? 'İyi geceler' : ($saat < 12 ? 'Günaydın' : ($saat < 18 ? 'İyi günler' : 'İyi akşamlar'));
    $sayi = fn ($n) => number_format((int) $n, 0, ',', '.');
    $lojmanRenk = ['bos' => '#10b981', 'dolu' => '#3b82f6', 'isgalde' => '#ef4444', 'bakimda' => '#f59e0b', 'kullanim-disi' => '#94a3b8'];
@endphp

@section('content')
<section class="page-hero oz-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">{{ now()->translatedFormat('d F Y, l') }}</p>
        <h2>{{ $selam }}, {{ auth()->user()->ad }}.</h2>
        <small>Taşınmaz, hisse satışı, ecrimisil ve lojman işlemlerinin güncel durumu.</small>
    </div>
    <div class="page-hero-actions oz-hizli">
        @can('tasinmaz.olustur')
            <a href="{{ route('panel.tasinmazlar.olustur') }}" class="btn-cancel">+ Taşınmaz</a>
        @endcan
        @can('ecrimisil.olustur')
            <a href="{{ route('panel.ecrimisil.olustur') }}" class="btn-cancel">+ İşgal Kaydı</a>
        @endcan
        @can('lojman.olustur')
            <a href="{{ route('panel.lojman.basvuru.olustur') }}" class="btn-cancel">+ Lojman Başvurusu</a>
        @endcan
        @can('hisse-satisi.goruntule')
            <a href="{{ route('panel.hisse-satisi.index') }}" class="btn-submit">Hisse Satışı</a>
        @endcan
    </div>
</section>

@if (empty($ozet))
    <div class="hs-bos-kart">Rolünüzde görüntüleme izni olan bir modül yok. Yetki için sistem yöneticisine başvurun.</div>
@else
    {{-- Modül sayaçları --}}
    <div class="oz-kpi-izgara">
        @isset($ozet['tasinmaz'])
            <a href="{{ route('panel.tasinmazlar.index') }}" class="oz-kpi" style="--renk: #3b82f6">
                <span class="oz-kpi-ikon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6"/></svg></span>
                <span class="oz-kpi-ad">Taşınmaz</span>
                <strong class="oz-kpi-deger">{{ $sayi($ozet['tasinmaz']['toplam']) }}</strong>
                <span class="oz-kpi-alt">{{ $sayi($ozet['tasinmaz']['hisseli']) }} aktif hisseli · bu ay +{{ $sayi($ozet['tasinmaz']['bu_ay']) }}</span>
            </a>
        @endisset
        @isset($ozet['hisse'])
            <a href="{{ route('panel.hisse-satisi.liste') }}" class="oz-kpi" style="--renk: #f97316">
                <span class="oz-kpi-ikon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M15 9.5c-.5-1-1.6-1.5-3-1.5-1.7 0-3 .9-3 2.2 0 3 6 1.6 6 4.6 0 1.3-1.3 2.2-3 2.2-1.4 0-2.6-.6-3-1.6M12 6v2M12 16v2"/></svg></span>
                <span class="oz-kpi-ad">Hisse Satışı</span>
                <strong class="oz-kpi-deger">{{ $sayi($ozet['hisse']['islemde']) }}</strong>
                <span class="oz-kpi-alt">dosya işlemde · {{ $sayi($ozet['hisse']['tamamlanan']) }} tamamlandı</span>
            </a>
        @endisset
        @isset($ozet['ecrimisil'])
            <a href="{{ route('panel.ecrimisil.index', ['durum' => 'devam-eden']) }}" class="oz-kpi" style="--renk: #ef4444">
                <span class="oz-kpi-ikon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4h6l1 2h3v15H5V6h3z"/><path d="M9 12h6M9 16h4"/></svg></span>
                <span class="oz-kpi-ad">Ecrimisil</span>
                <strong class="oz-kpi-deger">{{ $sayi($ozet['ecrimisil']['devam']) }}</strong>
                <span class="oz-kpi-alt">işgal devam ediyor · toplam {{ $sayi($ozet['ecrimisil']['toplam']) }}</span>
            </a>
        @endisset
        @isset($ozet['lojman'])
            @php $dolu = $ozet['lojman']['durumlar']['dolu']['sayi']; $lt = $ozet['lojman']['toplam']; @endphp
            <a href="{{ route('panel.lojman.index') }}" class="oz-kpi" style="--renk: #8b5cf6">
                <span class="oz-kpi-ikon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="1.5"/><path d="M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1M11 21v-3h2v3"/></svg></span>
                <span class="oz-kpi-ad">Lojman Doluluğu</span>
                <strong class="oz-kpi-deger">%{{ $lt ? round($dolu * 100 / $lt) : 0 }}</strong>
                <span class="oz-kpi-alt">{{ $sayi($dolu) }} / {{ $sayi($lt) }} lojman tahsisli</span>
            </a>
        @endisset
    </div>

    <div class="oz-izgara">
        <div class="oz-sutun">
            @isset($ozet['hisse'])
                @php $enCok = max(1, $ozet['hisse']['asamalar']->max('sayi')); @endphp
                <section class="oz-kart">
                    <header class="oz-kart-bas">
                        <div><h3>Hisse satışı aşamaları</h3><small>İşlemdeki dosyaların bulunduğu aşama</small></div>
                        <a href="{{ route('panel.hisse-satisi.liste') }}" class="oz-link">Tümü →</a>
                    </header>
                    <ul class="oz-cubuklar">
                        @foreach ($ozet['hisse']['asamalar'] as $a)
                            <li>
                                <a href="{{ route('panel.hisse-satisi.liste', ['durum' => $a['durum']]) }}">
                                    <span class="oz-cubuk-ad">{{ $a['ad'] }}</span>
                                    <span class="oz-cubuk"><span style="width: {{ $a['sayi'] ? max(4, round($a['sayi'] * 100 / $enCok)) : 0 }}%"></span></span>
                                    <span class="oz-cubuk-sayi">{{ $a['sayi'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endisset

            @isset($ozet['lojman'])
                <section class="oz-kart">
                    <header class="oz-kart-bas">
                        <div><h3>Lojman durumu</h3><small>{{ $sayi($ozet['lojman']['toplam']) }} lojman · {{ $sayi($ozet['lojman']['acik_basvuru']) }} açık başvuru</small></div>
                        <a href="{{ route('panel.lojman.basvurular') }}" class="oz-link">Başvurular →</a>
                    </header>
                    <div class="oz-yigin" role="img" aria-label="Lojman durum dağılımı">
                        @foreach ($ozet['lojman']['durumlar'] as $kod => $d)
                            @if ($d['sayi'])<span style="flex: {{ $d['sayi'] }}; background: {{ $lojmanRenk[$kod] }}" title="{{ $d['ad'] }}: {{ $d['sayi'] }}"></span>@endif
                        @endforeach
                        @if (! $ozet['lojman']['toplam'])<span class="is-bos"></span>@endif
                    </div>
                    <ul class="oz-lejant">
                        @foreach ($ozet['lojman']['durumlar'] as $kod => $d)
                            <li><a href="{{ route('panel.lojman.index', ['durum' => $kod]) }}"><i style="background: {{ $lojmanRenk[$kod] }}"></i>{{ $d['ad'] }} <b>{{ $d['sayi'] }}</b></a></li>
                        @endforeach
                    </ul>

                    <h4 class="oz-ara-baslik">60 gün içinde biten tahsisler</h4>
                    @forelse ($ozet['lojman']['biten_tahsis'] as $t)
                        <a href="{{ route('panel.lojman.detay', $t->lojman_id) }}" class="oz-satir">
                            <span class="oz-satir-metin"><strong>{{ $t->ad_soyad }}</strong><small>{{ $t->lojman?->ad }} {{ $t->lojman?->konumKisa() }}</small></span>
                            <span class="oz-gun {{ $t->bitis_tarihi->diffInDays(now()->startOfDay(), true) <= 15 ? 'is-yakin' : '' }}">{{ $t->bitis_tarihi->format('d.m.Y') }}</span>
                        </a>
                    @empty
                        <p class="oz-bos">Yaklaşan tahsis bitişi yok.</p>
                    @endforelse
                </section>
            @endisset

            @isset($ozet['ecrimisil'])
                <section class="oz-kart oz-uyari-kart">
                    <header class="oz-kart-bas">
                        <div><h3>Ecrimisil</h3><small>Tutanak bekleyen işgal kayıtları</small></div>
                    </header>
                    <div class="oz-mini-izgara">
                        <a href="{{ route('panel.ecrimisil.index', ['durum' => 'yeni']) }}" class="oz-mini {{ $ozet['ecrimisil']['tutanaksiz'] ? 'is-uyari' : '' }}"><b>{{ $sayi($ozet['ecrimisil']['tutanaksiz']) }}</b><span>Tutanak yok</span></a>
                        <a href="{{ route('panel.ecrimisil.index', ['durum' => 'devam-eden']) }}" class="oz-mini"><b>{{ $sayi($ozet['ecrimisil']['devam']) }}</b><span>Devam eden</span></a>
                        <a href="{{ route('panel.ecrimisil.index', ['durum' => 'sonlanmis']) }}" class="oz-mini"><b>{{ $sayi($ozet['ecrimisil']['toplam'] - $ozet['ecrimisil']['devam'] - $ozet['ecrimisil']['tutanaksiz']) }}</b><span>Sonlanan</span></a>
                    </div>
                </section>
            @endisset
        </div>

        <section class="oz-kart oz-son">
            <header class="oz-kart-bas">
                <div><h3>Son kayıtlar</h3><small>Modüllere en son eklenenler</small></div>
            </header>
            @forelse ($sonKayitlar as $s)
                <a href="{{ $s['url'] }}" class="oz-akis is-{{ $s['tur'] }}">
                    <span class="oz-akis-nokta"></span>
                    <span class="oz-akis-metin">
                        <em>{{ $s['etiket'] }}</em>
                        <strong>{{ $s['baslik'] ?: '—' }}</strong>
                        <small>{{ $s['alt'] }}</small>
                    </span>
                    <time datetime="{{ $s['tarih']->toIso8601String() }}" title="{{ $s['tarih']->format('d.m.Y H:i') }}">{{ $s['tarih']->diffForHumans(short: true) }}</time>
                </a>
            @empty
                <p class="oz-bos">Henüz kayıt yok.</p>
            @endforelse
        </section>
    </div>
@endif
@endsection
