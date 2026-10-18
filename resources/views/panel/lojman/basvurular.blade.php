@extends('layouts.panel')

@section('title', 'Lojman Başvuruları')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/lojman.css') }}?v=1">
@endpush

@php
    use App\Models\Lojman;
    use App\Models\LojmanBasvuru;

    $aktifDurum = $filtre['durum'] ?? '';
    $sorguDurumsuz = collect(request()->query())->except(['durum', 'page'])->all();
    $sekmeler = array_merge(
        [['ad' => 'Tümü', 'k' => '', 'say' => $sayac['tumu']]],
        collect(['onaylandi', 'degerlendirmede', 'basvuruldu', 'tahsisedildi', 'reddedildi'])
            ->map(fn ($d) => ['ad' => LojmanBasvuru::DURUMLAR[$d], 'k' => $d, 'say' => $sayac[$d]])->all()
    );
@endphp

@section('content')
@if (session('basari'))<div class="flash-success">{{ session('basari') }}</div>@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Lojman</p>
        <h2>Başvurular</h2>
        <small><strong>{{ $sayac['onaylandi'] }}</strong> onaylı başvuru tahsis bekliyor · liste önce onaylılar, sonra başvuru tarihi sırasıyla</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.lojman.index') }}" class="btn-cancel">Lojmanlar</a>
        <a href="{{ route('panel.lojman.basvuru.excel', array_filter(['durum' => $aktifDurum])) }}" class="btn-cancel">Excel</a>
        <a href="{{ route('panel.lojman.tahsis.excel') }}" class="btn-cancel">Tahsis Excel</a>
        @if (auth()->user()->izinVarMi('lojman.olustur'))
            <a href="{{ route('panel.lojman.basvuru.olustur') }}" class="btn-submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Yeni Başvuru
            </a>
        @endif
    </div>
</section>

<div class="hsl-kisayol-bar" role="tablist" aria-label="Duruma göre">
    @foreach ($sekmeler as $s)
        <a href="{{ route('panel.lojman.basvurular', $s['k'] === '' ? $sorguDurumsuz : $sorguDurumsuz + ['durum' => $s['k']]) }}"
           class="hsl-kisayol {{ $aktifDurum === $s['k'] ? 'is-aktif' : '' }}" role="tab" aria-selected="{{ $aktifDurum === $s['k'] ? 'true' : 'false' }}">
            @if ($s['k'] !== '')<span class="lj-nokta is-b-{{ $s['k'] }}"></span>@endif
            {{ $s['ad'] }}
            <span class="say">{{ $s['say'] }}</span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('panel.lojman.basvurular') }}">
    @if ($aktifDurum)<input type="hidden" name="durum" value="{{ $aktifDurum }}">@endif
    <div class="hsl-toolbar">
        <div class="hsl-ara">
            <svg class="hsl-ara-ikon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="search" name="q" value="{{ $filtre['q'] }}" placeholder="Ad soyad, TC, sicil no ya da birim...">
        </div>
        <select name="tahsis_turu" class="lj-toolbar-secim" onchange="this.form.submit()" aria-label="Tahsis türü">
            <option value="">Tüm türler</option>
            @foreach (Lojman::TIPLER as $t)<option value="{{ $t }}" @selected($filtre['tahsis_turu'] === $t)>{{ $t }}</option>@endforeach
        </select>
        <button type="submit" class="hsl-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>Ara</button>
        @if ($filtre['q'] || $filtre['tahsis_turu'] || $aktifDurum)
            <a href="{{ route('panel.lojman.basvurular') }}" class="hsl-btn is-tehlike">Temizle</a>
        @endif
    </div>
</form>

@if ($basvurular->isEmpty())
    <div class="hs-bos-kart">{{ $sayac['tumu'] === 0 ? 'Henüz başvuru yok.' : 'Filtreye uygun başvuru bulunamadı.' }}</div>
@else
    <div class="tl-tablo-wrap">
        <table class="tl-tablo lj-liste">
            <thead><tr><th>Başvuran</th><th>Görev</th><th>Tercih</th><th>Tarih</th><th>Durum</th><th></th></tr></thead>
            <tbody>
                @foreach ($basvurular as $b)
                    <tr>
                        <td>
                            <div class="lj-hucre">
                                <strong>{{ $b->ad_soyad }}</strong>
                                <small class="td-mono">{{ $b->tc_kimlik ?? 'TC yok' }}{{ $b->sicil_no ? ' · Sicil '.$b->sicil_no : '' }}</small>
                            </div>
                        </td>
                        <td>
                            <div class="lj-hucre">
                                <span>{{ $b->unvan ?? '—' }}</span>
                                <small>{{ $b->birim }}</small>
                            </div>
                        </td>
                        <td>
                            <div class="lj-hucre">
                                @if ($b->tahsisRelation)
                                    <span>{{ $b->tahsisRelation->lojman?->ad }}</span><small>tahsis edildi</small>
                                @else
                                    <span>{{ $b->lojman?->ad ?? 'Genel' }}</span>
                                    <small>{{ $b->tahsis_turu }}</small>
                                @endif
                            </div>
                        </td>
                        <td class="td-mono">{{ $b->basvuru_tarihi?->format('d.m.Y') }}</td>
                        <td><span class="lj-bdurum is-{{ $b->durum }}">{{ LojmanBasvuru::DURUMLAR[$b->durum] ?? $b->durum }}</span></td>
                        <td class="lj-sag"><a href="{{ route('panel.lojman.basvuru-detay', $b->id) }}" class="btn-cancel hs-mini-btn">Aç</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="tl-pagination">{{ $basvurular->links() }}</div>
@endif
@endsection
