@extends('layouts.panel')

@section('title', 'Değerleme #'.$kayit->id)

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=3">
@endpush

@php
    $tl = fn ($n) => number_format((float) $n, 2, ',', '.');
    $alan = $kayit->degerlenenAlan();
    $imar = $tasinmaz->imar;
    $kim = fn ($u) => $u ? $u->ad.' '.$u->soyad : '—';
@endphp

@section('content')
@if (session('basari'))<div class="flash-success" role="status">{{ session('basari') }}</div>@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Değerleme #{{ $kayit->id }} · {{ $kayit->tipEtiketi() }}</p>
        <h2>{{ $tasinmaz->mahalle?->ad }} {{ $tasinmaz->ada }}/{{ $tasinmaz->parsel }}@if ($kayit->tip === 'bb' && $kayit->bb_no) <span class="dg-baslik-bb">BB {{ $kayit->bb_no }}</span>@endif</h2>
        <small>{{ $tasinmaz->il?->ad }} / {{ $tasinmaz->ilce?->ad }} · Rapor {{ $kayit->rapor_no ?: 'numarasız' }}{{ $kayit->rapor_tarihi ? ' · '.$kayit->rapor_tarihi->format('d.m.Y') : '' }}</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.degerleme.index') }}" class="btn-cancel">← Liste</a>
        @if ($tasinmaz->koordinat)
            <a href="{{ route('panel.degerleme.harita', ['tasinmaz' => $tasinmaz->id]) }}" class="btn-cancel">Haritada Göster</a>
        @endif
        <a href="{{ route('panel.degerleme.rapor', $kayit->id) }}" target="_blank" class="btn-cancel">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/></svg>
            Rapor Çıktısı
        </a>
        @can('degerleme.duzenle')
            <a href="{{ route('panel.degerleme.duzenle', $kayit->id) }}" class="btn-submit">Düzenle</a>
        @endcan
    </div>
</section>

<div class="dg-detay-izgara">
    <section class="dg-kart dg-deger-kart">
        <span class="dg-etiket">Toplam Değer</span>
        <strong class="dg-buyuk">{{ $tl($kayit->toplam_bedel) }} <em>TL</em></strong>
        <div class="dg-deger-alt">
            <div><span>Değerlenen alan</span><b class="td-mono">{{ $alan !== null ? $tl($alan).' m²' : '—' }}</b></div>
            <div><span>Birim fiyat</span><b class="td-mono">{{ $kayit->birim_fiyat !== null ? $tl($kayit->birim_fiyat).' TL/m²' : '—' }}</b></div>
            @if ($alan && ! $kayit->birim_fiyat)
                <div><span>m² başına (hesaplanan)</span><b class="td-mono">{{ $tl($kayit->toplam_bedel / $alan) }} TL/m²</b></div>
            @endif
        </div>
    </section>

    <section class="dg-kart">
        <h3>Rapor</h3>
        <dl class="dg-bilgi is-tek">
            <div><dt>Rapor No</dt><dd class="td-mono">{{ $kayit->rapor_no ?: '—' }}</dd></div>
            <div><dt>Rapor Tarihi</dt><dd>{{ $kayit->rapor_tarihi?->format('d.m.Y') ?? '—' }}</dd></div>
            <div><dt>Hazırlayan</dt><dd>{{ $kim($kayit->hazirlayan) }}</dd></div>
            <div><dt>Rapor PDF</dt><dd>
                @if ($kayit->evrak)
                    <a href="{{ asset('storage/'.$kayit->evrak) }}" target="_blank" rel="noopener" class="td-link">PDF’i aç ↗</a>
                @else
                    <span class="dg-eksik">Yüklenmemiş</span>
                @endif
            </dd></div>
            <div><dt>Kaydı Giren</dt><dd>{{ $kim($kayit->olusturan) }} · {{ $kayit->created_at?->format('d.m.Y H:i') }}</dd></div>
        </dl>
    </section>

    <section class="dg-kart">
        <h3>Taşınmaz</h3>
        <dl class="dg-bilgi is-tek">
            <div><dt>Kayıt Tipi</dt><dd>{{ $tasinmaz->kayit_tipi?->etiket() ?? '—' }}</dd></div>
            <div><dt>Parsel Alanı</dt><dd class="td-mono">{{ $tasinmaz->alan !== null ? $tl($tasinmaz->alan).' m²' : '—' }}</dd></div>
            <div><dt>Nitelik</dt><dd>{{ $tasinmaz->nitelik ?: '—' }}</dd></div>
            <div><dt>İmar</dt><dd>{{ $imar?->imarDurumu?->ad ?: '—' }}{{ $imar?->emsal !== null ? ' · E: '.$tl($imar->emsal) : '' }}{{ $imar?->yenaz_yencok ? ' · '.$imar->yenaz_yencok : '' }}</dd></div>
            @can('tasinmaz.goruntule')
                <div><dt></dt><dd><a href="{{ $tasinmaz->rota('detay') }}" class="td-link">Taşınmaz kaydına git →</a></dd></div>
            @endcan
        </dl>
    </section>

    @if ($kayit->tip === 'bb')
        <section class="dg-kart">
            <h3>Bağımsız Bölüm</h3>
            <dl class="dg-bilgi is-tek">
                <div><dt>BB No</dt><dd class="td-mono">{{ $kayit->bb_no ?: '—' }}</dd></div>
                <div><dt>Kat</dt><dd>{{ $kayit->kat ?: '—' }}</dd></div>
                <div><dt>Nitelik</dt><dd>{{ $kayit->bb_nitelik ?: '—' }}</dd></div>
                <div><dt>Oda Sayısı</dt><dd>{{ $kayit->oda_sayisi ?: '—' }}</dd></div>
                <div><dt>Brüt Alan</dt><dd class="td-mono">{{ $kayit->brut_alan !== null ? $tl($kayit->brut_alan).' m²' : '—' }}</dd></div>
                <div><dt>Yapı Kaydı</dt><dd>{{ $kayit->yapi_id ? 'Bağlı' : 'Elle girildi' }}</dd></div>
            </dl>
        </section>
    @endif

    @if ($kayit->aciklama)
        <section class="dg-kart dg-genis">
            <h3>Açıklama</h3>
            <p class="dg-aciklama">{{ $kayit->aciklama }}</p>
        </section>
    @endif

    <section class="dg-kart dg-genis">
        <h3>Bu taşınmazın değer geçmişi</h3>
        @if ($gecmis->count() < 2)
            <p class="dg-alt">Bu taşınmaz için başka değerleme yok.</p>
        @else
            <div class="tl-tablo-wrap dg-ic-tablo">
                <table class="tl-tablo">
                    <thead><tr><th>Rapor</th><th>Tür</th><th class="is-sag">Alan (m²)</th><th class="is-sag">Birim (TL/m²)</th><th class="is-sag">Toplam (TL)</th><th>Hazırlayan</th></tr></thead>
                    <tbody>
                        @foreach ($gecmis as $g)
                            <tr class="{{ $g->id === $kayit->id ? 'dg-bu' : '' }}">
                                <td><a href="{{ route('panel.degerleme.detay', $g->id) }}" class="td-link td-mono">{{ $g->rapor_no ?: '#'.$g->id }}</a> <small class="dg-alt">{{ $g->rapor_tarihi?->format('d.m.Y') }}</small></td>
                                <td>{{ $g->tip === 'bb' ? 'BB '.($g->bb_no ?? '') : 'Arsa' }}</td>
                                <td class="td-mono is-sag">{{ ($a = $g->degerlenenAlan()) !== null ? $tl($a) : '—' }}</td>
                                <td class="td-mono is-sag">{{ $g->birim_fiyat !== null ? $tl($g->birim_fiyat) : '—' }}</td>
                                <td class="td-mono is-sag"><strong>{{ $tl($g->toplam_bedel) }}</strong></td>
                                <td>{{ $kim($g->hazirlayan) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

@can('degerleme.sil')
    <form method="POST" action="{{ route('panel.degerleme.sil', $kayit->id) }}" class="dg-sil-form" onsubmit="return confirm('Bu değerleme raporu silinsin mi? Bu işlem geri alınamaz.');">
        @csrf @method('DELETE')
        <button type="submit" class="btn-cancel hs-mini-btn hs-mini-btn-tehlike">Değerlemeyi Sil</button>
    </form>
@endcan
@endsection
