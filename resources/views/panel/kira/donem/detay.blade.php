@extends('layouts.panel')

@section('title', 'Kiracılık Dönemi · '.$dosya->dosya_no)

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=5">
    <link rel="stylesheet" href="{{ asset('css/kira.css') }}?v=12">
@endpush

@php
    $tl = fn ($n) => $n === null ? '—' : number_format((float) $n, 2, ',', '.');
    $gun = fn ($t) => $t?->format('d.m.Y') ?: '—';
    $t = $dosya->tasinmaz;
    $ana = $donem->kiracilar->firstWhere('tip', \App\Enums\KiraciTipi::Ana);
    $altlar = $donem->kiracilar->where('tip', \App\Enums\KiraciTipi::Alt);
    $bedel = $donem->guncelBedel();
    $kalan = $donem->kalanGun();
    $yururlukte = $donem->aktif_mi && $dosya->sozlesme_aktif_mi;
    $bolumNo = 0;
    $no = function () use (&$bolumNo) { return ++$bolumNo; };
    // Geçmişte kaçıncı dönem: liste yeniden eskiye sıralı
    $donemSirasi = $tumDonemler->count() - ($sira === false ? 0 : $sira);
@endphp

@section('content')
@if (session('basari'))<div class="flash-success" role="status">{{ session('basari') }}</div>@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Kira İşlemleri · Kiracılık Dönemi</p>
        <h2>
            <a href="{{ route('panel.kira.detay', $dosya->id) }}" class="kr-ust-dosya">{{ $dosya->dosya_no }}</a>
            <span class="kr-ust-ayrac">›</span>
            {{ $ana?->ad_unvan ?: 'Kiracı girilmemiş' }}
        </h2>
        <small>
            {{ $donemSirasi }}. dönem / {{ $tumDonemler->count() }}
            · {{ $gun($donem->baslangic_tarihi) }} – {{ $gun($donem->bitis_tarihi) }}
            @if ($yururlukte)
                · <span class="kr-etiket is-yesil">Yürürlükte</span>
            @else
                · <span class="kr-etiket">Sona ermiş</span>
            @endif
        </small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.kira.detay', $dosya->id) }}" class="btn-cancel">Dosyaya dön</a>
        @can('kira.olustur')
            <a href="{{ route('panel.kira.donem.olustur', $dosya->id) }}" class="btn-submit">+ Yeni Kiracı</a>
        @endcan
    </div>
</section>

{{-- Dönemler arası geçiş: dosyadaki tüm kiracılar tek açılır listede --}}
@if ($tumDonemler->count() > 1)
    <div class="kr-donem-gecis">
        <label for="donemSecim">Bu dosyadaki kiracılar</label>
        <select id="donemSecim" data-donem-secim>
            @foreach ($tumDonemler as $d)
                <option value="{{ route('panel.kira.donem.detay', [$dosya->id, $d->id]) }}" @selected($d->id === $donem->id)>
                    {{ $d->anaKiraci?->ad_unvan ?: 'Kiracı girilmemiş' }}
                    ({{ $d->baslangic_tarihi?->format('d.m.Y') }} – {{ $d->bitis_tarihi?->format('d.m.Y') }})
                    @if ($d->aktif_mi) · yürürlükte @endif
                </option>
            @endforeach
        </select>
    </div>
@endif

<div class="dg-ozet kr-ozet">
    <div class="dg-ozet-kutu"><span>Yıllık bedel</span><strong>{{ $tl($bedel?->yillik_bedel) }} <em>TL</em></strong></div>
    <div class="dg-ozet-kutu"><span>Aylık</span><strong>{{ $tl($bedel?->aylik()) }} <em>TL</em></strong></div>
    <div class="dg-ozet-kutu {{ $kalan !== null && $kalan <= 90 ? 'is-uyari' : '' }}">
        <span>{{ $yururlukte ? 'Kalan süre' : 'Durum' }}</span>
        <strong>
            @if (! $yururlukte)
                Sona ermiş
            @elseif ($kalan === null)
                —
            @elseif ($kalan < 0)
                {{ abs($kalan) }} gün geçti
            @else
                {{ $kalan }} <em>gün</em>
            @endif
        </strong>
    </div>
    <div class="dg-ozet-kutu"><span>Sözleşme süresi</span><strong>{{ $donem->sureYil() ? $donem->sureYil().' ' : '—' }}<em>{{ $donem->sureYil() ? 'yıl' : '' }}</em></strong></div>
</div>

{{-- 1 · Bu dönemin kiracısı --}}
<section class="form-section">
    <div class="form-section-head">
        <span class="form-section-num">{{ $no() }}</span>
        <div class="form-section-head-text">
            <h3>Kiracı</h3>
            <small>Bu döneme ait kiracı bilgileri</small>
        </div>
    </div>
    <dl class="dg-bilgi">
        <div><dt>Ad / Unvan</dt><dd>{{ $ana?->ad_unvan ?: '—' }}</dd></div>
        <div><dt>TC / Vergi No</dt><dd class="td-mono">{{ $ana?->tc_vergi_no ?: '—' }}</dd></div>
        <div><dt>Telefon</dt><dd class="td-mono">{{ $ana?->telefon ?: '—' }}</dd></div>
        <div><dt>E-posta</dt><dd>{{ $ana?->eposta ?: '—' }}</dd></div>
        <div><dt>IBAN</dt><dd class="td-mono">{{ $ana?->iban ?: '—' }}</dd></div>
        <div><dt>Avukat</dt><dd>{{ $ana?->avukat ?: '—' }}</dd></div>
        <div><dt>Alt Kiracı</dt><dd>{{ $altlar->pluck('ad_unvan')->implode(', ') ?: '—' }}</dd></div>
    </dl>
    @if ($ana?->adres)<p class="kr-aciklama">{{ $ana->adres }}</p>@endif
</section>

{{-- 2 · Sözleşme --}}
<section class="form-section">
    <div class="form-section-head">
        <span class="form-section-num">{{ $no() }}</span>
        <div class="form-section-head-text"><h3>Sözleşme</h3></div>
    </div>
    <dl class="dg-bilgi">
        <div><dt>Sözleşme No</dt><dd class="td-mono">{{ $donem->sozlesme_no ?: '—' }}</dd></div>
        <div><dt>Sözleşme Tarihi</dt><dd class="td-mono">{{ $gun($donem->sozlesme_tarihi) }}</dd></div>
        <div><dt>Kira Dönemi</dt><dd class="td-mono">{{ $gun($donem->baslangic_tarihi) }} – {{ $gun($donem->bitis_tarihi) }}</dd></div>
        <div><dt>Süre</dt><dd class="td-mono">{{ $donem->sure_ay ? $donem->sure_ay.' ay' : ($donem->sureYil() ? $donem->sureYil().' yıl' : '—') }}</dd></div>
        <div><dt>Taksit Sayısı</dt><dd class="td-mono">{{ $donem->taksit_sayisi ?: '—' }}</dd></div>
        <div><dt>Ödeme Şekli</dt><dd>{{ $donem->odeme_sekli ?: '—' }}</dd></div>
        <div><dt>Noter Tarihi</dt><dd class="td-mono">{{ $gun($donem->noter_tarihi) }}</dd></div>
        <div><dt>Yevmiye No</dt><dd class="td-mono">{{ $donem->yevmiye_no ?: '—' }}</dd></div>
        <div><dt>KDV</dt><dd>{{ $donem->kdv_orani !== null ? $tl($donem->kdv_orani).'%' : '—' }} {{ $donem->kdv_dahil_mi ? '(dahil)' : '' }}</dd></div>
        <div><dt>Artırma Yöntemi</dt><dd>{{ $donem->artirma_yontemi ?: '—' }}</dd></div>
    </dl>
    @if ($donem->aciklama)<p class="kr-aciklama">{{ $donem->aciklama }}</p>@endif
</section>

{{-- 3 · Bedeller --}}
@if ($donem->bedeller->isNotEmpty())
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">{{ $no() }}</span>
            <div class="form-section-head-text">
                <h3>Kira Bedelleri</h3>
                <small>Bu dönemin yıl yıl bedelleri</small>
            </div>
        </div>
        <div class="tl-tablo-wrap">
            <table class="tl-tablo">
                <thead><tr><th>Yıl</th><th>Dönem</th><th class="is-sag">Yıllık Bedel (₺)</th><th class="is-sag">Aylık (₺)</th><th class="is-sag">Artış (%)</th></tr></thead>
                <tbody>
                    @foreach ($donem->bedeller as $b)
                        <tr>
                            <td class="td-mono">{{ $b->yil_sirasi }}</td>
                            <td class="td-mono">{{ $gun($b->donem_baslangic) }} – {{ $gun($b->donem_bitis) }}</td>
                            <td class="td-mono is-sag">{{ $tl($b->yillik_bedel) }}</td>
                            <td class="td-mono is-sag">{{ $tl($b->aylik()) }}</td>
                            <td class="td-mono is-sag">{{ $b->tufe_orani !== null ? $tl($b->tufe_orani) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

{{-- 4 · Kiralanan yer (dosyadan) --}}
<section class="form-section">
    <div class="form-section-head">
        <span class="form-section-num">{{ $no() }}</span>
        <div class="form-section-head-text">
            <h3>Kiralanan Yer</h3>
            <small>Dosyaya ait; dönem değişse de aynı kalır</small>
        </div>
        <a href="{{ route('panel.kira.detay', $dosya->id) }}" class="btn-cancel hs-mini-btn dg-sag">Dosya kartı</a>
    </div>
    <dl class="dg-bilgi">
        <div><dt>Dosya No</dt><dd class="td-mono">{{ $dosya->dosya_no }}</dd></div>
        <div><dt>İşyeri Adı</dt><dd>{{ $dosya->isyeri_adi ?: '—' }}</dd></div>
        <div><dt>İş Kolu</dt><dd>{{ $dosya->isKolu?->ad ?: '—' }}</dd></div>
        <div><dt>İlçe</dt><dd>{{ $t?->ilce?->ad ?: '—' }}</dd></div>
        <div><dt>Mahalle</dt><dd>{{ $t?->mahalle?->ad ?: '—' }}</dd></div>
        <div><dt>Ada / Parsel</dt><dd class="td-mono">{{ $t?->adaParsel() ?: '—' }}</dd></div>
        <div><dt>Yüzölçüm</dt><dd class="td-mono">{{ $tl($t?->yuzolcum) }} m²</dd></div>
    </dl>
    @if ($t?->adres)<p class="kr-aciklama">{{ $t->adres }}</p>@endif
</section>

{{-- 5 · Bu döneme ait belgeler --}}
<section class="form-section">
    <div class="form-section-head">
        <span class="form-section-num">{{ $no() }}</span>
        <div class="form-section-head-text">
            <h3>Dönem Belgeleri</h3>
            <small>Bu kiracılık dönemine eklenenler</small>
        </div>
    </div>
    @if ($donem->belgeler->isEmpty())
        <p class="hint">Bu döneme belge eklenmemiş. Dosyanın genel belgeleri <a href="{{ route('panel.kira.detay', $dosya->id) }}">dosya kartında</a>.</p>
    @else
        <div class="tl-tablo-wrap">
            <table class="tl-tablo">
                <thead><tr><th>Tür</th><th>Ad</th><th>Yükleyen</th><th>Tarih</th><th></th></tr></thead>
                <tbody>
                    @foreach ($donem->belgeler as $b)
                        <tr>
                            <td>{{ $b->tur->etiket() }}</td>
                            <td>{{ $b->ad }}</td>
                            <td>{{ $b->yukleyen ? $b->yukleyen->ad.' '.$b->yukleyen->soyad : '—' }}</td>
                            <td class="td-mono">{{ $b->created_at?->format('d.m.Y') }}</td>
                            <td><a href="{{ $b->url() }}" target="_blank" rel="noopener" class="btn-cancel hs-mini-btn">Aç</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<script>
(function () {
    const secim = document.querySelector('[data-donem-secim]');
    if (!secim) return;
    secim.addEventListener('change', function () { window.location.href = this.value; });
})();
</script>
@endsection
