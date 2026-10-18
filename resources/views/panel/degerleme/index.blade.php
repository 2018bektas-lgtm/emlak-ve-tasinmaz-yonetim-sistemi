@extends('layouts.panel')

@section('title', 'Taşınmaz Değerleme')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=3">
@endpush

@php
    $tl = fn ($n) => number_format((float) $n, 2, ',', '.');
    $sorguTipsiz = collect(request()->query())->except(['tip', 'page'])->all();
    $sorguDonemsiz = collect(request()->query())->except(['donem', 'page'])->all();
    $sekmeler = [
        ['ad' => 'Tümü', 'k' => null, 'say' => $sayac['tumu']],
        ['ad' => 'Arsa', 'k' => 'arsa', 'say' => $sayac['arsa']],
        ['ad' => 'Bağımsız Bölüm', 'k' => 'bb', 'say' => $sayac['bb']],
    ];
    $filtreVar = collect($filtre)->filter(fn ($v) => filled($v))->isNotEmpty();
@endphp

@section('content')
@if (session('basari'))<div class="flash-success" role="status">{{ session('basari') }}</div>@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Değerleme</p>
        <h2>Taşınmaz Değerleme</h2>
        <small><strong>{{ number_format($sayac['tumu'], 0, ',', '.') }}</strong> rapor · arsa ve bağımsız bölüm rayiç değer tespitleri</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.degerleme.harita') }}" class="btn-cancel">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4L3 6v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14M15 6v14"/></svg>
            Harita
        </a>
        <a href="{{ route('panel.degerleme.excel', request()->query()) }}" class="btn-cancel">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            Excel
        </a>
        @can('degerleme.olustur')
            <a href="{{ route('panel.degerleme.ice-aktar') }}" class="btn-cancel">İçe Aktar</a>
            <a href="{{ route('panel.degerleme.yeni') }}" class="btn-submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Yeni Değerleme
            </a>
        @endcan
    </div>
</section>

<div class="dg-ozet">
    <div class="dg-ozet-kutu"><span>Listelenen toplam değer</span><strong>{{ $tl($ozet['toplam_bedel']) }} <em>TL</em></strong></div>
    <div class="dg-ozet-kutu"><span>Bu ay girilen</span><strong>{{ $ozet['bu_ay'] }} <em>rapor</em></strong></div>
    <div class="dg-ozet-kutu {{ $ozet['evraksiz'] ? 'is-uyari' : '' }}"><span>Rapor PDF’i eksik</span><strong>{{ $ozet['evraksiz'] }} <em>kayıt</em></strong></div>
</div>

<div class="hsl-kisayol-bar" role="tablist" aria-label="Değerleme türü">
    @foreach ($sekmeler as $s)
        <a href="{{ route('panel.degerleme.index', $s['k'] ? $sorguTipsiz + ['tip' => $s['k']] : $sorguTipsiz) }}"
           class="hsl-kisayol {{ $filtre['tip'] === $s['k'] ? 'is-aktif' : '' }}" role="tab" aria-selected="{{ $filtre['tip'] === $s['k'] ? 'true' : 'false' }}">
            @if ($s['k'])<span class="dg-tur-nokta is-{{ $s['k'] }}"></span>@endif
            {{ $s['ad'] }} <span class="say">{{ $s['say'] }}</span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('panel.degerleme.index') }}">
    @if ($filtre['tip'])<input type="hidden" name="tip" value="{{ $filtre['tip'] }}">@endif
    @if ($filtre['donem'])<input type="hidden" name="donem" value="{{ $filtre['donem'] }}">@endif
    <div class="hsl-toolbar">
        <div class="hsl-ara">
            <svg class="hsl-ara-ikon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="search" name="q" value="{{ $filtre['q'] }}" placeholder="Ada/parsel (1234/5), ilçe, mahalle, rapor no, BB no…" autocomplete="off">
        </div>
        <div class="dg-segment" role="group" aria-label="Kayıt dönemi">
            @foreach ([null => 'Tümü', 'bugun' => 'Bugün', 'hafta' => 'Bu Hafta', 'ay' => 'Bu Ay'] as $k => $ad)
                <a href="{{ route('panel.degerleme.index', $k ? $sorguDonemsiz + ['donem' => $k] : $sorguDonemsiz) }}" class="{{ ($filtre['donem'] ?? '') === (string) $k ? 'is-aktif' : '' }}">{{ $ad }}</a>
            @endforeach
        </div>
        <button type="submit" class="hsl-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>Ara</button>
        @if ($filtreVar)
            <a href="{{ route('panel.degerleme.index') }}" class="hsl-btn is-tehlike">Temizle</a>
        @endif
    </div>
</form>

@if ($kayitlar->isEmpty())
    <div class="hs-bos-kart">
        @if ($sayac['tumu'] === 0 && ! $filtreVar)
            Henüz değerleme yok. <strong>Yeni Değerleme</strong> ile bir taşınmaz seçip başlayın ya da mevcut raporları <strong>İçe Aktar</strong> ile Excel’den yükleyin.
        @else
            Filtreye uygun değerleme bulunamadı.
        @endif
    </div>
@else
    <div class="tl-tablo-wrap">
        <table class="tl-tablo dg-tablo">
            <thead>
                <tr>
                    <th>Taşınmaz</th>
                    <th>Tür</th>
                    <th class="is-sag">Alan (m²)</th>
                    <th class="is-sag">Birim (TL/m²)</th>
                    <th class="is-sag">Toplam Değer (TL)</th>
                    <th>Rapor</th>
                    <th>Hazırlayan</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kayitlar as $k)
                    @php $t = $k->tasinmaz; $alan = $k->degerlenenAlan(); @endphp
                    <tr>
                        <td>
                            <a href="{{ route('panel.degerleme.detay', $k->id) }}" class="dg-tasinmaz">
                                <strong class="td-mono">{{ $t?->ada }}/{{ $t?->parsel }}</strong>
                                <small>{{ $t?->ilce?->ad }} · {{ $t?->mahalle?->ad }}</small>
                            </a>
                        </td>
                        <td>
                            <span class="dg-tur is-{{ $k->tip }}">{{ $k->tip === 'bb' ? 'Bağımsız Bölüm' : 'Arsa' }}</span>
                            @if ($k->tip === 'bb' && $k->bolumEtiketi())<small class="dg-alt">{{ $k->bolumEtiketi() }}</small>@endif
                        </td>
                        <td class="td-mono is-sag">{{ $alan !== null ? $tl($alan) : '—' }}</td>
                        <td class="td-mono is-sag">{{ $k->birim_fiyat !== null ? $tl($k->birim_fiyat) : '—' }}</td>
                        <td class="td-mono is-sag"><strong>{{ $tl($k->toplam_bedel) }}</strong></td>
                        <td>
                            <span class="td-mono">{{ $k->rapor_no ?: '—' }}</span>
                            <small class="dg-alt">{{ $k->rapor_tarihi?->format('d.m.Y') }}</small>
                        </td>
                        <td>{{ $k->hazirlayan ? $k->hazirlayan->ad.' '.$k->hazirlayan->soyad : '—' }}</td>
                        <td class="dg-islem">
                            @if ($k->evrak)
                                <a href="{{ asset('storage/'.$k->evrak) }}" target="_blank" rel="noopener" class="dg-ikon-btn is-pdf" title="Rapor PDF">PDF</a>
                            @else
                                <span class="dg-ikon-btn is-yok" title="Rapor PDF’i yüklenmemiş">PDF</span>
                            @endif
                            <a href="{{ route('panel.degerleme.rapor', $k->id) }}" target="_blank" class="dg-ikon-btn" title="Yazdırılabilir rapor">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/></svg>
                            </a>
                            <a href="{{ route('panel.degerleme.detay', $k->id) }}" class="btn-cancel hs-mini-btn">Detay</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="tl-pagination">{{ $kayitlar->links() }}</div>
@endif
@endsection
