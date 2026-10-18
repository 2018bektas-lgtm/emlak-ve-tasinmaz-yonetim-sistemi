@extends('layouts.panel')

@section('title', 'Yeni Değerleme')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=3">
@endpush

@php $tl = fn ($n) => number_format((float) $n, 2, ',', '.'); @endphp

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Değerleme · Yeni</p>
        <h2>Değerlenecek Taşınmazı Seçin</h2>
        <small>Yalnız envanterdeki taşınmazdan açılır · boş parsel → arsa, kat mülkiyetli → bağımsız bölüm, binalı parsel → ikisi de.</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.degerleme.index') }}" class="btn-cancel">← Değerlemeler</a>
    </div>
</section>

<form method="GET" action="{{ route('panel.degerleme.yeni') }}" class="form-section hs-ara-form">
    <div class="form-section-head">
        <span class="form-section-num">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
        </span>
        <div class="form-section-head-text">
            <h3>Taşınmaz Ara</h3>
            <small>İl / ilçe / mahalle / ada / parsel — istediğiniz alanları doldurup arayın.</small>
        </div>
    </div>
    <div class="form-row form-row-3">
        <div class="form-field">
            <label for="dg-il">İl</label>
            <select name="il_id" class="form-select" id="dg-il">
                <option value="">Tümü</option>
                @foreach ($iller as $il)<option value="{{ $il->id }}" @selected($filtre['il_id'] == $il->id)>{{ $il->ad }}</option>@endforeach
            </select>
        </div>
        <div class="form-field">
            <label for="dg-ilce">İlçe</label>
            <select name="ilce_id" class="form-select" id="dg-ilce">
                <option value="">Tümü</option>
                @foreach ($ilceler as $x)<option value="{{ $x->id }}" @selected($filtre['ilce_id'] == $x->id)>{{ $x->ad }}</option>@endforeach
            </select>
        </div>
        <div class="form-field">
            <label for="dg-mahalle">Mahalle</label>
            <select name="mahalle_id" class="form-select" id="dg-mahalle">
                <option value="">Tümü</option>
                @foreach ($mahalleler as $x)<option value="{{ $x->id }}" @selected($filtre['mahalle_id'] == $x->id)>{{ $x->ad }}</option>@endforeach
            </select>
        </div>
    </div>
    <div class="form-row form-row-3">
        <div class="form-field">
            <label for="dg-ada">Ada No</label>
            <input id="dg-ada" type="text" name="ada" value="{{ $filtre['ada'] }}" class="form-input" placeholder="Örn. 1234">
        </div>
        <div class="form-field">
            <label for="dg-parsel">Parsel No</label>
            <input id="dg-parsel" type="text" name="parsel" value="{{ $filtre['parsel'] }}" class="form-input" placeholder="Örn. 45">
        </div>
        <div class="form-field hs-ara-aksiyon">
            @if ($aradiMi)<a href="{{ route('panel.degerleme.yeni') }}" class="btn-cancel hs-ara-temizle">Temizle</a>@endif
            <button type="submit" class="btn-submit hs-ara-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
                Ara
            </button>
        </div>
    </div>
</form>

@if ($sonuclar !== null)
    <div class="td-section">
        <div class="td-section-head">
            <div>
                <h3>Arama Sonucu</h3>
                <small>Toplam {{ $sonuclar->total() }} taşınmaz</small>
            </div>
        </div>
        @if ($sonuclar->isEmpty())
            <p class="td-bos">Bu kriterlerle kayıtlı taşınmaz yok. Önce taşınmazı envantere ekleyin.</p>
        @else
            <div class="tl-tablo-wrap">
                <table class="tl-tablo td-tablo">
                    <thead>
                        <tr>
                            <th>İlçe / Mahalle</th>
                            <th>Ada / Parsel</th>
                            <th>Kayıt Tipi</th>
                            <th class="is-sag">Alan (m²)</th>
                            <th>Son Değerleme</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sonuclar as $t)
                            @php
                                $tipler = \App\Models\TasinmazDegerleme::tipler($t);
                                $son = $t->degerlemeler->first();
                            @endphp
                            <tr>
                                <td>{{ $t->ilce?->ad ?? '—' }}<br><small class="dg-alt">{{ $t->mahalle?->ad ?? '—' }}</small></td>
                                <td class="td-mono">
                                    <span class="tl-badge">{{ $t->ada }}</span> / <span class="tl-badge">{{ $t->parsel }}</span>
                                    @if ($t->kayit_tipi === \App\Enums\KayitTipi::KatMulkiyetli && $t->yapi?->bagimsiz_bolum_no)
                                        <small class="dg-alt">BB {{ $t->yapi->bagimsiz_bolum_no }}{{ $t->yapi->blok_no ? ' · '.$t->yapi->blok_no.' Blok' : '' }}</small>
                                    @endif
                                </td>
                                <td>
                                    {{ $t->kayit_tipi?->etiket() ?? '—' }}
                                    @if ($t->kayit_tipi === \App\Enums\KayitTipi::KatMulkiyetsizBina)<small class="dg-alt">{{ $t->yapilar_count }} bağımsız bölüm</small>@endif
                                </td>
                                <td class="td-mono is-sag">{{ $t->alan !== null ? $tl($t->alan) : '—' }}</td>
                                <td>
                                    @if ($son)
                                        <span class="td-mono">{{ $tl($son->toplam_bedel) }} TL</span>
                                        <small class="dg-alt">{{ $son->rapor_tarihi?->format('d.m.Y') }} · {{ $t->degerlemeler->count() }} rapor</small>
                                    @else
                                        <span class="dg-alt">Değerlenmemiş</span>
                                    @endif
                                </td>
                                <td class="dg-islem">
                                    @foreach ($tipler as $tip)
                                        <a href="{{ route('panel.degerleme.olustur', ['tasinmazId' => $t->id, 'tip' => $tip]) }}" class="btn-submit hs-mini-btn">
                                            + {{ $tip === 'bb' ? 'Bağımsız Bölüm' : 'Arsa' }}
                                        </a>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="tl-pagination">{{ $sonuclar->links() }}</div>
        @endif
    </div>
@else
    <div class="hs-arama-bos">
        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
        <p>Bir arama yapın — sonuçlar burada listelenecek.</p>
    </div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const il = document.getElementById('dg-il'), ilce = document.getElementById('dg-ilce'), mah = document.getElementById('dg-mahalle');
    const doldur = (sel, kayitlar) => {
        while (sel.options.length > 1) sel.remove(1);
        (kayitlar || []).forEach(k => sel.add(new Option(k.ad, k.id)));
    };
    const getir = async url => { try { const r = await fetch(url, { headers: { Accept: 'application/json' } }); return r.ok ? r.json() : []; } catch (e) { return []; } };
    il.addEventListener('change', async () => { doldur(ilce, []); doldur(mah, []); if (il.value) doldur(ilce, await getir({{ \Illuminate\Support\Js::from(url('/panel/ajax/ilceler')) }} + '/' + il.value)); });
    ilce.addEventListener('change', async () => { doldur(mah, []); if (ilce.value) doldur(mah, await getir({{ \Illuminate\Support\Js::from(url('/panel/ajax/mahalleler')) }} + '/' + ilce.value)); });
});
</script>
@endsection
