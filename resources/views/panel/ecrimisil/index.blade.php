@extends('layouts.panel')

@section('title', 'Ecrimisil — İşgal Kayıtları')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/ecrimisil.css') }}?v=1">
@endpush

@section('content')
@if (session('basari'))
    <div class="flash-success">{{ session('basari') }}</div>
@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Ecrimisil</p>
        <h2>İşgal Kayıtları</h2>
        <small><strong>{{ number_format($sayac['tumu'], 0, ',', '.') }}</strong> kayıt · izinsiz kullanım tespitleri, tutanakları ve raporları</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.ecrimisil.harita') }}" class="btn-cancel">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4L3 6v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14M15 6v14"/></svg>
            Harita
        </a>
        @if (auth()->user()->izinVarMi('ecrimisil.olustur'))
            <a href="{{ route('panel.ecrimisil.olustur') }}" class="btn-submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Yeni Kayıt
            </a>
        @endif
    </div>
</section>

@php
    $aktifDurum = $filtre['durum'] ?? '';
    $detayFiltreAktif = collect($filtre)->except(['q', 'durum'])->filter(fn ($v) => filled($v))->isNotEmpty();
    $sekmeler = [
        ['ad' => 'Tümü', 'k' => '', 'renk' => '', 'say' => $sayac['tumu']],
        ['ad' => 'İşgal Devam Ediyor', 'k' => 'devam-eden', 'renk' => 'is-kirmizi', 'say' => $sayac['devam-eden']],
        ['ad' => 'Tutanak Yok', 'k' => 'yeni', 'renk' => 'is-mor', 'say' => $sayac['yeni']],
        ['ad' => 'İşgal Sonlandı', 'k' => 'sonlanmis', 'renk' => 'is-yesil', 'say' => $sayac['sonlanmis']],
    ];
    $sorguDurumsuz = collect(request()->query())->except(['durum', 'page'])->all();
@endphp

<div class="hsl-kisayol-bar" role="tablist" aria-label="Duruma göre">
    @foreach ($sekmeler as $s)
        <a href="{{ route('panel.ecrimisil.index', $s['k'] === '' ? $sorguDurumsuz : $sorguDurumsuz + ['durum' => $s['k']]) }}"
           class="hsl-kisayol {{ $s['renk'] }} {{ $aktifDurum === $s['k'] ? 'is-aktif' : '' }}"
           role="tab" aria-selected="{{ $aktifDurum === $s['k'] ? 'true' : 'false' }}">
            @if ($s['k'] !== '')<span class="ec-nokta is-{{ $s['k'] }}"></span>@endif
            {{ $s['ad'] }}
            <span class="say">{{ $s['say'] }}</span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('panel.ecrimisil.index') }}" id="ec-form">
    @if ($aktifDurum)
        <input type="hidden" name="durum" value="{{ $aktifDurum }}">
    @endif
    <div class="hsl-toolbar">
        <div class="hsl-ara">
            <svg class="hsl-ara-ikon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="search" name="q" value="{{ $filtre['q'] ?? '' }}" placeholder="Ad soyad / ünvan, TC / vergi no, nitelik, ada veya parsel...">
        </div>
        <button type="submit" class="hsl-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>Ara</button>
        <button type="button" class="hsl-btn is-ghost" id="ec-filtre-toggle" aria-expanded="{{ $detayFiltreAktif ? 'true' : 'false' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54z"/></svg>
            Detaylı Filtre
        </button>
        @if (collect($filtre)->filter(fn ($v) => filled($v))->isNotEmpty())
            <a href="{{ route('panel.ecrimisil.index') }}" class="hsl-btn is-tehlike">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                Temizle
            </a>
        @endif
    </div>

    <div class="hsl-filtre" id="ec-filtre" {{ $detayFiltreAktif ? '' : 'hidden' }}>
        <div class="hsl-filtre-baslik">Konum & Sorumlu</div>
        <div class="hsl-filtre-grid">
            <div class="hsl-alan"><label>İl</label>
                <select name="il_id" id="ec-f-il">
                    <option value="">— Tümü —</option>
                    @foreach ($iller as $il)<option value="{{ $il->id }}" @selected(($filtre['ilId'] ?? '') == $il->id)>{{ $il->ad }}</option>@endforeach
                </select>
            </div>
            <div class="hsl-alan"><label>İlçe</label>
                <select name="ilce_id" id="ec-f-ilce" @disabled(! ($filtre['ilId'] ?? null))>
                    <option value="">— Tümü —</option>
                    @foreach ($ilceler as $x)<option value="{{ $x->id }}" @selected(($filtre['ilceId'] ?? '') == $x->id)>{{ $x->ad }}</option>@endforeach
                </select>
            </div>
            <div class="hsl-alan"><label>Mahalle</label>
                <select name="mahalle_id" id="ec-f-mahalle" @disabled(! ($filtre['ilceId'] ?? null))>
                    <option value="">— Tümü —</option>
                    @foreach ($mahalleler as $x)<option value="{{ $x->id }}" @selected(($filtre['mahalleId'] ?? '') == $x->id)>{{ $x->ad }}</option>@endforeach
                </select>
            </div>
            <div class="hsl-alan"><label>Sorumlu Kullanıcı</label>
                <select name="kullanici_id">
                    <option value="">— Tümü —</option>
                    @foreach ($kullanicilar as $k)<option value="{{ $k->id }}" @selected(($filtre['kullaniciId'] ?? '') == $k->id)>{{ $k->ad }} {{ $k->soyad }}</option>@endforeach
                </select>
            </div>
            <div class="hsl-alan"><label>Ada</label><input type="text" name="ada" value="{{ $filtre['ada'] ?? '' }}" autocomplete="off"></div>
            <div class="hsl-alan"><label>Parsel</label><input type="text" name="parsel" value="{{ $filtre['parsel'] ?? '' }}" autocomplete="off"></div>
        </div>
        <div class="hs-ara-aksiyon">
            <button type="submit" class="hsl-btn">Uygula</button>
        </div>
    </div>
</form>

@if ($kayitlar->isEmpty())
    <div class="hs-bos-kart">
        @if ($sayac['tumu'] === 0 && collect($filtre)->filter(fn ($v) => filled($v))->isEmpty())
            Henüz işgal kaydı yok. Sağ üstteki <strong>Yeni Kayıt</strong> ile ilk tespiti girebilirsiniz.
        @else
            Filtreye uygun kayıt bulunamadı.
        @endif
    </div>
@else
    <div class="tl-tablo-wrap">
        <table class="tl-tablo ec-liste">
            <thead>
                <tr>
                    <th>İşgalci</th>
                    <th>Taşınmaz</th>
                    <th>Sorumlu</th>
                    <th>Kayıtlar</th>
                    <th>Durum</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kayitlar as $k)
                    @php $d = $k->durum(); @endphp
                    <tr>
                        <td>
                            <div class="ec-kisi">
                                <strong>{{ $k->ad_soyad_unvan }}</strong>
                                <small>
                                    @if ($k->nitelik)<span class="ec-nitelik">{{ $k->nitelik }}</span>@endif
                                    {{ $k->tc_vergi_no ?? 'TC / VKN yok' }}
                                </small>
                            </div>
                        </td>
                        <td>
                            @if ($k->tasinmaz)
                                <div class="ec-kisi">
                                    <strong class="td-mono">{{ $k->tasinmaz->ada }} / {{ $k->tasinmaz->parsel }}</strong>
                                    <small>{{ $k->tasinmaz->ilce?->ad ?? '—' }} · {{ $k->tasinmaz->mahalle?->ad ?? '—' }}</small>
                                </div>
                            @else
                                <span class="ec-soluk">Bağlı değil</span>
                            @endif
                        </td>
                        <td>{{ $k->kullanici ? $k->kullanici->ad.' '.$k->kullanici->soyad : '—' }}</td>
                        <td>
                            <div class="ec-sayilar">
                                <span title="Tutanak">{{ $k->tutanaklar_count }} tutanak</span>
                                <span title="Rapor">{{ $k->raporlar_count }} rapor</span>
                                <span title="Resim">{{ $k->resimler_count }} resim</span>
                            </div>
                        </td>
                        <td>
                            <span class="ec-durum is-{{ $d }}">{{ \App\Models\EcrimisilIsgalci::DURUMLAR[$d] }}</span>
                            @if ($d !== 'yeni')
                                <small class="ec-gun">{{ number_format($k->toplamIsgalGunu(), 0, ',', '.') }} gün</small>
                            @endif
                        </td>
                        <td class="ec-sag">
                            <a href="{{ route('panel.ecrimisil.detay', $k->id) }}" class="btn-cancel hs-mini-btn">Aç</a>
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
    const btn = document.getElementById('ec-filtre-toggle');
    btn?.addEventListener('click', () => {
        const p = document.getElementById('ec-filtre');
        p.hidden = !p.hidden;
        btn.setAttribute('aria-expanded', p.hidden ? 'false' : 'true');
    });

    const ilcelerUrl = @json(url('/panel/ajax/ilceler'));
    const mahallelerUrl = @json(url('/panel/ajax/mahalleler'));
    const il = document.getElementById('ec-f-il');
    const ilce = document.getElementById('ec-f-ilce');
    const mah = document.getElementById('ec-f-mahalle');

    il?.addEventListener('change', async () => {
        ilce.innerHTML = '<option value="">— Tümü —</option>';
        mah.innerHTML = '<option value="">— Tümü —</option>';
        mah.disabled = true;
        if (!il.value) { ilce.disabled = true; return; }
        ilce.disabled = false;
        const r = await fetch(ilcelerUrl + '/' + il.value);
        (await r.json() || []).forEach(x => ilce.appendChild(new Option(x.ad, x.id)));
    });
    ilce?.addEventListener('change', async () => {
        mah.innerHTML = '<option value="">— Tümü —</option>';
        if (!ilce.value) { mah.disabled = true; return; }
        mah.disabled = false;
        const r = await fetch(mahallelerUrl + '/' + ilce.value);
        (await r.json() || []).forEach(x => mah.appendChild(new Option(x.ad, x.id)));
    });
})();
</script>
@endsection
