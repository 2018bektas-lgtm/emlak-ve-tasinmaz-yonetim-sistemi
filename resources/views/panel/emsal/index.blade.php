@extends('layouts.panel')

@section('title', 'Emsaller')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=3">
    <link rel="stylesheet" href="{{ asset('css/emsal.css') }}?v=1">
@endpush

@php
    $tl = fn ($n) => number_format((float) $n, 2, ',', '.');
    $sorguTipsiz = collect(request()->query())->except(['tip', 'page'])->all();
    $filtreVar = collect($filtre)->filter(fn ($v) => filled($v))->isNotEmpty();
@endphp

@section('content')
@if (session('basari'))<div class="flash-success" role="status">{{ session('basari') }}</div>@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Değerleme</p>
        <h2>Emsaller</h2>
        <small><strong>{{ number_format($sayac['tumu'], 0, ',', '.') }}</strong> emsal · encümen kararı, resmî senet ve mahkeme kararı satışları</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.emsal.excel', request()->query()) }}" class="btn-cancel">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            Excel
        </a>
        @can('emsal.olustur')
            <a href="{{ route('panel.emsal.olustur') }}" class="btn-submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Yeni Emsal
            </a>
        @endcan
    </div>
</section>

<div class="dg-ozet">
    <div class="dg-ozet-kutu"><span>Ortalama birim fiyat</span><strong>{{ $ozet['ortalama'] ? $tl($ozet['ortalama']) : '—' }} <em>TL/m²</em></strong></div>
    <div class="dg-ozet-kutu"><span>En yüksek birim fiyat</span><strong>{{ $ozet['en_yuksek'] ? $tl($ozet['en_yuksek']) : '—' }} <em>TL/m²</em></strong></div>
    <div class="dg-ozet-kutu"><span>Emsal alınan parsel</span><strong>{{ number_format($ozet['parsel'], 0, ',', '.') }} <em>parsel</em></strong></div>
    <div class="dg-ozet-kutu {{ $ozet['evraksiz'] ? 'is-uyari' : '' }}"><span>Evrakı eksik</span><strong>{{ $ozet['evraksiz'] }} <em>kayıt</em></strong></div>
</div>

<div class="hsl-kisayol-bar" role="tablist" aria-label="Emsal türü">
    <a href="{{ route('panel.emsal.index', $sorguTipsiz) }}"
       class="hsl-kisayol {{ $filtre['tip'] === null ? 'is-aktif' : '' }}" role="tab" aria-selected="{{ $filtre['tip'] === null ? 'true' : 'false' }}">
        Tümü <span class="say">{{ $sayac['tumu'] }}</span>
    </a>
    @foreach (\App\Enums\EmsalTipi::cases() as $t)
        <a href="{{ route('panel.emsal.index', $sorguTipsiz + ['tip' => $t->value]) }}"
           class="hsl-kisayol {{ $filtre['tip'] === $t->value ? 'is-aktif' : '' }}" role="tab" aria-selected="{{ $filtre['tip'] === $t->value ? 'true' : 'false' }}">
            <span class="em-tur-nokta" style="background:{{ $t->renk() }}"></span>
            {{ $t->etiket() }} <span class="say">{{ $sayac[$t->value] }}</span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('panel.emsal.index') }}">
    @if ($filtre['tip'])<input type="hidden" name="tip" value="{{ $filtre['tip'] }}">@endif
    <div class="hsl-toolbar em-toolbar">
        <div class="hsl-ara">
            <svg class="hsl-ara-ikon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="search" name="q" value="{{ $filtre['q'] }}" placeholder="Ada/parsel (1234/5), ilçe, mahalle, karar/yevmiye/esas no…" autocomplete="off">
        </div>

        <label class="em-filtre">
            <span>İlçe</span>
            <select name="ilce_id" onchange="this.form.submit()">
                <option value="">Tümü</option>
                @foreach ($ilceler as $i)
                    <option value="{{ $i->id }}" @selected((string) $filtre['ilce_id'] === (string) $i->id)>{{ $i->ad }}</option>
                @endforeach
            </select>
        </label>

        <label class="em-filtre">
            <span>Mahalle</span>
            <select name="mahalle_id" @disabled($mahalleler->isEmpty())>
                <option value="">{{ $mahalleler->isEmpty() ? 'Önce ilçe seçin' : 'Tümü' }}</option>
                @foreach ($mahalleler as $m)
                    <option value="{{ $m->id }}" @selected((string) $filtre['mahalle_id'] === (string) $m->id)>{{ $m->ad }}</option>
                @endforeach
            </select>
        </label>

        <label class="em-filtre is-tarih">
            <span>Satış tarihi</span>
            <div class="em-tarih-ikili">
                <input type="date" name="baslangic" value="{{ $filtre['baslangic'] }}" aria-label="Başlangıç tarihi">
                <i>–</i>
                <input type="date" name="bitis" value="{{ $filtre['bitis'] }}" aria-label="Bitiş tarihi">
            </div>
        </label>

        <button type="submit" class="hsl-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>Ara</button>
        @if ($filtreVar)
            <a href="{{ route('panel.emsal.index') }}" class="hsl-btn is-tehlike">Temizle</a>
        @endif
    </div>
</form>

@if ($kayitlar->isEmpty())
    <div class="hs-bos-kart">
        @if ($sayac['tumu'] === 0 && ! $filtreVar)
            Henüz emsal yok. <strong>Yeni Emsal</strong> ile encümen kararı, resmî senet ya da mahkeme kararı satışı ekleyin.
        @else
            Filtreye uygun emsal bulunamadı.
        @endif
    </div>
@else
    <div class="tl-tablo-wrap">
        <table class="tl-tablo em-tablo">
            <thead>
                <tr>
                    <th>Parsel</th>
                    <th>Tür</th>
                    <th>Belge No</th>
                    <th>Tarih</th>
                    <th class="is-sag">Birim Fiyat (TL/m²)</th>
                    <th class="is-sag">Alan (m²)</th>
                    <th>İmar Durumu</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kayitlar as $k)
                    @php $p = $k->parsel; @endphp
                    <tr>
                        <td>
                            <span class="dg-tasinmaz">
                                <strong class="td-mono">{{ $p?->ada }}/{{ $p?->parsel }}</strong>
                                <small>{{ $p?->ilce?->ad }} · {{ $p?->mahalle?->ad }}</small>
                            </span>
                        </td>
                        <td>
                            <span class="em-tur" style="--em-renk:{{ $k->tip->renk() }}">{{ $k->tip->etiket() }}</span>
                            @if ($k->satilan_hisse)<small class="dg-alt">Hisse: {{ $k->satilan_hisse }}</small>@endif
                        </td>
                        <td>
                            <span class="td-mono">{{ $k->belgeNo() ?: '—' }}</span>
                            @if ($k->tip === \App\Enums\EmsalTipi::Mahkeme && $k->karar_no)<small class="dg-alt">Karar: {{ $k->karar_no }}</small>@endif
                        </td>
                        <td class="td-mono">{{ $k->tarih?->format('d.m.Y') ?: '—' }}</td>
                        <td class="td-mono is-sag"><strong>{{ $k->birim_fiyat !== null ? $tl($k->birim_fiyat) : '—' }}</strong></td>
                        <td class="td-mono is-sag">{{ $p?->alan !== null ? $tl($p->alan) : '—' }}</td>
                        <td>{{ $p?->imarDurumu?->ad ?: '—' }}</td>
                        <td class="dg-islem">
                            @if ($k->evrak)
                                <a href="{{ asset('storage/'.$k->evrak) }}" target="_blank" rel="noopener" class="dg-ikon-btn is-pdf" title="Emsal evrakı">PDF</a>
                            @else
                                <span class="dg-ikon-btn is-yok" title="Evrak yüklenmemiş">PDF</span>
                            @endif
                            @can('emsal.duzenle')
                                <a href="{{ route('panel.emsal.duzenle', $k->id) }}" class="btn-cancel hs-mini-btn">Düzenle</a>
                            @endcan
                            @can('emsal.sil')
                                <form method="POST" action="{{ route('panel.emsal.sil', $k->id) }}" class="dg-sil-form"
                                      onsubmit="return confirm('Bu emsal kaydı silinsin mi? Bu işlem geri alınamaz.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="dg-ikon-btn is-sil" title="Emsali sil">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="tl-pagination">{{ $kayitlar->links() }}</div>
@endif
@endsection
