@extends('layouts.panel')

@section('title', 'Lojmanlar')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/lojman.css') }}?v=1">
@endpush

@php
    use App\Models\Lojman;

    $aktifDurum = $filtre['durum'] ?? '';
    $detayFiltreAktif = collect($filtre)->except(['q', 'durum'])->filter(fn ($v) => filled($v))->isNotEmpty();
    $sorguDurumsuz = collect(request()->query())->except(['durum', 'page'])->all();
    $sekmeler = [
        ['ad' => 'Tümü', 'k' => '', 'say' => $sayilar['toplam']],
        ['ad' => 'Boş', 'k' => 'bos', 'say' => $sayilar['bos']],
        ['ad' => 'Dolu', 'k' => 'dolu', 'say' => $sayilar['dolu']],
        ['ad' => 'İşgalde', 'k' => 'isgalde', 'say' => $sayilar['isgalde']],
        ['ad' => 'Bakımda', 'k' => 'bakimda', 'say' => $sayilar['bakimda']],
        ['ad' => 'Kullanım Dışı', 'k' => 'kullanim-disi', 'say' => $sayilar['kullanim-disi']],
    ];
    $doluluk = $sayilar['toplam'] - $sayilar['kullanim-disi'] > 0
        ? round($sayilar['dolu'] * 100 / ($sayilar['toplam'] - $sayilar['kullanim-disi']))
        : 0;
@endphp

@section('content')
@if (session('basari'))<div class="flash-success">{{ session('basari') }}</div>@endif
@if ($errors->any())<div class="flash-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Lojman</p>
        <h2>Lojmanlar</h2>
        <small><strong>{{ $sayilar['toplam'] }}</strong> lojman · doluluk <strong>%{{ $doluluk }}</strong> · durum aktif tahsislerden hesaplanır</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.lojman.basvurular') }}" class="btn-cancel">Başvurular</a>
        <a href="{{ route('panel.lojman.excel', request()->query()) }}" class="btn-cancel">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
            Excel
        </a>
        @if (auth()->user()->izinVarMi('lojman.olustur'))
            <button type="button" class="btn-cancel" id="lj-toplu-ac" aria-expanded="{{ $errors->has('excel_file') ? 'true' : 'false' }}">Toplu Yükle</button>
            <a href="{{ route('panel.lojman.olustur') }}" class="btn-submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Yeni Lojman
            </a>
        @endif
    </div>
</section>

@if (auth()->user()->izinVarMi('lojman.olustur'))
    <section class="lj-toplu" id="lj-toplu" @if (! $errors->has('excel_file')) hidden @endif>
        <div class="lj-toplu-metin">
            <strong>Excel ile toplu lojman yükleme</strong>
            <small>Sütunlar: Ad, İl, İlçe, Mahalle, Ada, Parsel, Blok, Daire No, Kat, Oda, Alan m², Tip, Durum, Açıklama. İl/ilçe/mahalle adları kayıtlarla aynı yazılmalı. Her satır taşınmaz envanterindeki bir binanın birimine eşlenir; eşleşmeyen, boş parsel, satılmış ya da zaten lojman olan satırlar atlanır.</small>
        </div>
        <form method="POST" action="{{ route('panel.lojman.excel-yukle') }}" enctype="multipart/form-data" class="lj-toplu-form">
            @csrf
            <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required class="form-input">
            <button type="submit" class="btn-submit">Yükle</button>
            <a href="{{ route('panel.lojman.excel-sablon') }}" class="btn-cancel">Şablon</a>
        </form>
    </section>
@endif

<div class="hsl-kisayol-bar" role="tablist" aria-label="Duruma göre">
    @foreach ($sekmeler as $s)
        <a href="{{ route('panel.lojman.index', $s['k'] === '' ? $sorguDurumsuz : $sorguDurumsuz + ['durum' => $s['k']]) }}"
           class="hsl-kisayol {{ $aktifDurum === $s['k'] ? 'is-aktif' : '' }}" role="tab" aria-selected="{{ $aktifDurum === $s['k'] ? 'true' : 'false' }}">
            @if ($s['k'] !== '')<span class="lj-nokta is-{{ $s['k'] }}"></span>@endif
            {{ $s['ad'] }}
            <span class="say">{{ $s['say'] }}</span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('panel.lojman.index') }}">
    @if ($aktifDurum)<input type="hidden" name="durum" value="{{ $aktifDurum }}">@endif
    <div class="hsl-toolbar">
        <div class="hsl-ara">
            <svg class="hsl-ara-ikon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="search" name="q" value="{{ $filtre['q'] ?? '' }}" placeholder="Lojman adı, blok, daire, ada/parsel ya da oturan kişi...">
        </div>
        <button type="submit" class="hsl-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>Ara</button>
        <button type="button" class="hsl-btn is-ghost" id="lj-filtre-toggle" aria-expanded="{{ $detayFiltreAktif ? 'true' : 'false' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54z"/></svg>
            Detaylı Filtre
        </button>
        @if (collect($filtre)->filter(fn ($v) => filled($v))->isNotEmpty())
            <a href="{{ route('panel.lojman.index') }}" class="hsl-btn is-tehlike">Temizle</a>
        @endif
    </div>

    <div class="hsl-filtre" id="lj-filtre" {{ $detayFiltreAktif ? '' : 'hidden' }}>
        <div class="hsl-filtre-grid">
            <div class="hsl-alan"><label>İl</label>
                <select name="il_id" data-il>
                    <option value="">— Tümü —</option>
                    @foreach ($iller as $il)<option value="{{ $il->id }}" @selected(($filtre['ilId'] ?? '') == $il->id)>{{ $il->ad }}</option>@endforeach
                </select>
            </div>
            <div class="hsl-alan"><label>İlçe</label>
                <select name="ilce_id" data-ilce @disabled(! ($filtre['ilId'] ?? null))>
                    <option value="">— Tümü —</option>
                    @foreach ($ilceler as $x)<option value="{{ $x->id }}" @selected(($filtre['ilceId'] ?? '') == $x->id)>{{ $x->ad }}</option>@endforeach
                </select>
            </div>
            <div class="hsl-alan"><label>Mahalle</label>
                <select name="mahalle_id" data-mahalle @disabled(! ($filtre['ilceId'] ?? null))>
                    <option value="">— Tümü —</option>
                    @foreach ($mahalleler as $x)<option value="{{ $x->id }}" @selected(($filtre['mahalleId'] ?? '') == $x->id)>{{ $x->ad }}</option>@endforeach
                </select>
            </div>
            <div class="hsl-alan"><label>Tahsis Türü</label>
                <select name="tip">
                    <option value="">— Tümü —</option>
                    @foreach (Lojman::TIPLER as $t)<option value="{{ $t }}" @selected(($filtre['tip'] ?? '') === $t)>{{ $t }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="hs-ara-aksiyon"><button type="submit" class="hsl-btn">Uygula</button></div>
    </div>
</form>

@if ($lojmanlar->isEmpty())
    <div class="hs-bos-kart">
        @if ($sayilar['toplam'] === 0 && collect($filtre)->filter(fn ($v) => filled($v))->isEmpty())
            Henüz lojman kaydı yok. <strong>Yeni Lojman</strong> ile tek tek ya da <strong>Toplu Yükle</strong> ile Excel'den ekleyebilirsiniz.
        @else
            Filtreye uygun lojman bulunamadı.
        @endif
    </div>
@else
    <div class="tl-tablo-wrap">
        <table class="tl-tablo lj-liste">
            <thead>
                <tr><th>Lojman</th><th>Konum</th><th>Özellik</th><th>Oturan</th><th>Durum</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($lojmanlar as $l)
                    @php $d = $l->efektifDurum(); $aktif = $l->aktifTahsis(); @endphp
                    <tr>
                        <td>
                            <div class="lj-hucre">
                                <strong>{{ $l->ad }}</strong>
                                <small>{{ $l->konumKisa() ?: '—' }}</small>
                            </div>
                        </td>
                        <td>
                            <div class="lj-hucre">
                                <span>{{ $l->ilce?->ad ?? '—' }} · {{ $l->mahalle?->ad ?? '—' }}</span>
                                <small class="td-mono">Ada {{ $l->ada }} / Parsel {{ $l->parsel }}@if (! $l->tasinmaz_id) · <em class="lj-uyari-yazi" title="Taşınmaz kaydıyla eşleşmedi">bağlı değil</em>@endif</small>
                            </div>
                        </td>
                        <td>
                            <div class="lj-etiketler">
                                @if ($l->oda_sayisi !== null)<span>{{ $l->oda_sayisi }}+1</span>@endif
                                @if ($l->alan_m2)<span>{{ number_format((float) $l->alan_m2, 0, ',', '.') }} m²</span>@endif
                                @if ($l->tip)<span class="is-tip">{{ $l->tip }}</span>@endif
                            </div>
                        </td>
                        <td>
                            @if ($aktif)
                                <div class="lj-hucre">
                                    <strong>{{ $aktif->ad_soyad }}</strong>
                                    <small>{{ $aktif->baslamadiMi() ? $aktif->baslangic_tarihi->format('d.m.Y').'’de başlayacak' : $aktif->baslangic_tarihi->format('d.m.Y').'’den beri' }}@if ($aktif->bitis_tarihi) · bitiş {{ $aktif->bitis_tarihi->format('d.m.Y') }}@endif</small>
                                </div>
                            @else
                                <span class="lj-soluk">—</span>
                            @endif
                        </td>
                        <td><span class="lj-durum is-{{ $d }}">{{ Lojman::DURUMLAR[$d] }}</span></td>
                        <td class="lj-sag"><a href="{{ route('panel.lojman.detay', $l->id) }}" class="btn-cancel hs-mini-btn">Aç</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="tl-pagination">{{ $lojmanlar->links() }}</div>
@endif

<script>
(function () {
    const ac = (btnId, panelId) => {
        const b = document.getElementById(btnId), p = document.getElementById(panelId);
        b?.addEventListener('click', () => { p.hidden = !p.hidden; b.setAttribute('aria-expanded', p.hidden ? 'false' : 'true'); });
    };
    ac('lj-filtre-toggle', 'lj-filtre');
    ac('lj-toplu-ac', 'lj-toplu');

    const ilceUrl = @json(url('/panel/ajax/ilceler'));
    const mahUrl = @json(url('/panel/ajax/mahalleler'));
    const il = document.querySelector('[data-il]'), ilce = document.querySelector('[data-ilce]'), mah = document.querySelector('[data-mahalle]');
    const doldur = async (sel, url) => {
        sel.innerHTML = '<option value="">— Tümü —</option>';
        sel.disabled = !url;
        if (!url) return;
        (await (await fetch(url)).json() || []).forEach(x => sel.appendChild(new Option(x.ad, x.id)));
    };
    il?.addEventListener('change', () => { doldur(ilce, il.value ? ilceUrl + '/' + il.value : null); doldur(mah, null); });
    ilce?.addEventListener('change', () => doldur(mah, ilce.value ? mahUrl + '/' + ilce.value : null));
})();
</script>
@endsection
