@extends('layouts.panel')

@section('title', 'Kira Dosyası · '.$dosya->dosya_no)

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=5">
    <link rel="stylesheet" href="{{ asset('css/kira.css') }}?v=3">
@endpush

@php
    $tl = fn ($n) => $n === null ? '—' : number_format((float) $n, 2, ',', '.');
    $gun = fn ($t) => $t?->format('d.m.Y') ?: '—';
    $t = $dosya->tasinmaz;
    $s = $aktifSozlesme;
    $kalan = $s?->kalanGun();
    $ana = $dosya->kiracilar->firstWhere('tip', \App\Enums\KiraciTipi::Ana);
    $altlar = $dosya->kiracilar->where('tip', \App\Enums\KiraciTipi::Alt);
    $bedel = $s?->guncelBedel();
@endphp

@section('content')
@if (session('basari'))<div class="flash-success" role="status">{{ session('basari') }}</div>@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Kira İşlemleri · Dosya</p>
        <h2>{{ $dosya->dosya_no }}@if ($dosya->isyeri_adi) <span class="kr-hero-alt">{{ $dosya->isyeri_adi }}</span>@endif</h2>
        <small>
            <span class="kr-durum" style="--kr-renk:{{ $dosya->dolu_bos->renk() }}">{{ $dosya->dolu_bos->etiket() }}</span>
            · {{ $dosya->ihale_turu->etiket() }}
            @if ($dosya->isKolu) · {{ $dosya->isKolu->ad }}@endif
            @if ($dosya->anaDosya) · ana dosya: <a href="{{ route('panel.kira.detay', $dosya->anaDosya->id) }}"><strong>{{ $dosya->anaDosya->dosya_no }}</strong></a>@endif
        </small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.kira.index') }}" class="btn-cancel">Listeye dön</a>
        @if ($dosya->koordinat)
            <a href="{{ route('panel.kira.harita', ['merkez' => $dosya->koordinat->merkez_lat.','.$dosya->koordinat->merkez_lng, 'zoom' => 17]) }}" class="btn-cancel">Haritada Göster</a>
        @endif
    </div>
</section>

@if ($dosya->uyari)
    <div class="flash-error" role="alert"><strong>Uyarı:</strong> {{ $dosya->uyari }}</div>
@endif

{{-- Özet şeridi --}}
<div class="dg-ozet kr-ozet">
    <div class="dg-ozet-kutu"><span>Kira dönemi</span><strong class="kr-ozet-kucuk">{{ $s ? $gun($s->baslangic_tarihi).' – '.$gun($s->bitis_tarihi) : '—' }}</strong></div>
    <div class="dg-ozet-kutu {{ $kalan !== null && $kalan <= 90 ? 'is-uyari' : '' }}">
        <span>Kalan süre</span>
        <strong>{{ $kalan === null ? '—' : ($kalan < 0 ? abs($kalan).' gün geçti' : $kalan) }} @if ($kalan !== null && $kalan >= 0)<em>gün</em>@endif</strong>
    </div>
    <div class="dg-ozet-kutu"><span>Yürürlükteki yıllık bedel</span><strong>{{ $tl($bedel?->yillik_bedel) }} <em>TL</em></strong></div>
    <div class="dg-ozet-kutu"><span>Yüzölçüm</span><strong>{{ $tl($t?->yuzolcum) }} <em>m²</em></strong></div>
</div>

<div class="kr-detay-izgara-ust">
    {{-- Sözleşmesel bilgiler --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">1</span>
            <div class="form-section-head-text"><h3>Sözleşmesel Bilgiler</h3></div>
        </div>
        <dl class="dg-bilgi">
            <div><dt>Dosya No</dt><dd class="td-mono">{{ $dosya->dosya_no }}</dd></div>
            <div><dt>Eski Dosya No</dt><dd class="td-mono">{{ $dosya->eski_dosya_no ?: '—' }}</dd></div>
            <div><dt>İşyeri Adı</dt><dd>{{ $dosya->isyeri_adi ?: '—' }}</dd></div>
            <div><dt>İş Kolu</dt><dd>{{ $dosya->isKolu?->ad ?: '—' }}</dd></div>
            <div><dt>Ana Kiracı</dt><dd>{{ $ana?->ad_unvan ?: '—' }}</dd></div>
            <div><dt>TC / Vergi No</dt><dd class="td-mono">{{ $ana?->tc_vergi_no ?: '—' }}</dd></div>
            <div><dt>Telefon</dt><dd class="td-mono">{{ $ana?->telefon ?: '—' }}</dd></div>
            <div><dt>Alt Kiracı</dt><dd>{{ $altlar->pluck('ad_unvan')->implode(', ') ?: '—' }}</dd></div>
            <div><dt>Araç Kapasitesi</dt><dd class="td-mono">{{ $dosya->arac_kapasitesi ?: '—' }}</dd></div>
            <div><dt>Muhasebe Niteliği</dt><dd>{{ $dosya->muhasebeNiteligi?->ad ?: '—' }}</dd></div>
            <div><dt>Sorumlu Personel</dt><dd>{{ $dosya->sorumluPersonel ? $dosya->sorumluPersonel->ad.' '.$dosya->sorumluPersonel->soyad : '—' }}</dd></div>
            <div><dt>Taksit Sayısı</dt><dd class="td-mono">{{ $s?->taksit_sayisi ?: '—' }}</dd></div>
        </dl>
        @if ($dosya->aciklama)
            <p class="kr-aciklama">{{ $dosya->aciklama }}</p>
        @endif
    </section>

    {{-- Adres / tapu --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">2</span>
            <div class="form-section-head-text"><h3>Adres / Tapu Bilgileri</h3></div>
        </div>
        <dl class="dg-bilgi">
            <div><dt>İl</dt><dd>{{ $t?->il?->ad ?: '—' }}</dd></div>
            <div><dt>İlçe</dt><dd>{{ $t?->ilce?->ad ?: '—' }}</dd></div>
            <div><dt>Mahalle</dt><dd>{{ $t?->mahalle?->ad ?: '—' }}</dd></div>
            <div><dt>Ada / Parsel</dt><dd class="td-mono">{{ $t?->adaParsel() ?: '—' }}</dd></div>
            <div><dt>Yüzölçüm</dt><dd class="td-mono">{{ $tl($t?->yuzolcum) }} m²</dd></div>
            <div><dt>İmar Durumu</dt><dd>{{ $t?->imarDurumu?->ad ?: '—' }}</dd></div>
            <div><dt>Envanter Kaydı</dt><dd>{{ $t?->tasinmaz ? '#'.$t->tasinmaz->id.' · '.$t->tasinmaz->ada.'/'.$t->tasinmaz->parsel : 'Bağlı değil' }}</dd></div>
            <div><dt>Geometri</dt><dd>{{ $dosya->koordinat ? 'Var' : 'Yok' }}</dd></div>
        </dl>
        @if ($t?->adres)
            <p class="kr-aciklama">{{ $t->adres }}</p>
        @endif
    </section>

    {{-- Hukuki --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">3</span>
            <div class="form-section-head-text"><h3>Hukuki Bilgiler</h3></div>
        </div>
        <dl class="dg-bilgi">
            <div><dt>Hukuki Durum</dt><dd>{{ $dosya->hukukiDurum?->ad ?: '—' }}</dd></div>
            <div><dt>Kiracılık Durumu</dt><dd>{{ $dosya->kiracilikDurum?->ad ?: '—' }}</dd></div>
            <div><dt>Kiracının Avukatı</dt><dd>{{ $ana?->avukat ?: '—' }}</dd></div>
            <div><dt>Sözleşme</dt><dd>{{ $dosya->sozlesme_aktif_mi ? 'Yürürlükte' : 'Yürürlükte değil' }}</dd></div>
            <div><dt>Tahliye İhbarnamesi</dt><dd>{{ $dosya->tahliye_ihbarnamesi_mi ? 'Çekildi' : 'Çekilmedi' }}</dd></div>
        </dl>
    </section>
</div>

{{-- Bedel geçmişi --}}
@if ($s && $s->bedeller->isNotEmpty())
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">4</span>
            <div class="form-section-head-text">
                <h3>Kira Bedelleri</h3>
                <small>{{ $s->baslangic_tarihi?->format('d.m.Y') }} – {{ $s->bitis_tarihi?->format('d.m.Y') }} sözleşmesi</small>
            </div>
        </div>
        <div class="tl-tablo-wrap">
            <table class="tl-tablo">
                <thead><tr><th>Yıl</th><th>Dönem</th><th class="is-sag">Yıllık Bedel (₺)</th><th class="is-sag">Aylık (₺)</th><th class="is-sag">Artış (%)</th></tr></thead>
                <tbody>
                    @foreach ($s->bedeller as $b)
                        <tr @class(['kr-bedel-aktif' => $bedel && $b->id === $bedel->id])>
                            <td class="td-mono">{{ $b->yil_sirasi }}</td>
                            <td class="td-mono">{{ $gun($b->donem_baslangic) }} – {{ $gun($b->donem_bitis) }}</td>
                            <td class="td-mono is-sag"><strong>{{ $tl($b->yillik_bedel) }}</strong></td>
                            <td class="td-mono is-sag">{{ $tl($b->aylik()) }}</td>
                            <td class="td-mono is-sag">{{ $b->tufe_orani !== null ? $tl($b->tufe_orani) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

{{-- Kiralanan alanlar --}}
@if ($dosya->alanlar->isNotEmpty())
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">5</span>
            <div class="form-section-head-text"><h3>Kiralanan Alanlar</h3><small>{{ $dosya->alanlar->count() }} alan</small></div>
        </div>
        <div class="tl-tablo-wrap">
            <table class="tl-tablo">
                <thead><tr><th>Alan</th><th>Faaliyet</th><th>İş Kolu</th><th class="is-sag">m²</th><th class="is-sag">Yıllık Bedel</th><th>Dönem</th></tr></thead>
                <tbody>
                    @foreach ($dosya->alanlar as $a)
                        <tr>
                            <td>{{ $a->ad ?: '—' }}</td>
                            <td>{{ $a->faaliyet_konusu ?: '—' }}</td>
                            <td>{{ $a->isKolu?->ad ?: '—' }}</td>
                            <td class="td-mono is-sag">{{ $tl($a->alan) }}</td>
                            <td class="td-mono is-sag">{{ $tl($a->yillik_bedel) }}</td>
                            <td class="td-mono">{{ $gun($a->baslangic_tarihi) }} – {{ $gun($a->bitis_tarihi) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

{{-- İhale --}}
@if ($dosya->ihaleler->isNotEmpty())
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">6</span>
            <div class="form-section-head-text"><h3>İhale</h3></div>
        </div>
        @foreach ($dosya->ihaleler as $ih)
            <dl class="dg-bilgi">
                <div><dt>Usul</dt><dd>{{ $ih->usul?->etiket() ?: '—' }}</dd></div>
                <div><dt>İhale Tarihi</dt><dd class="td-mono">{{ $ih->ihale_tarihi?->format('d.m.Y H:i') ?: '—' }}</dd></div>
                <div><dt>Muhammen Bedel</dt><dd class="td-mono">{{ $tl($ih->muhammen_bedel) }}</dd></div>
                <div><dt>İhale Bedeli</dt><dd class="td-mono">{{ $tl($ih->ihale_bedeli) }}</dd></div>
                <div><dt>Geçici Teminat</dt><dd class="td-mono">{{ $tl($ih->gecici_teminat) }}</dd></div>
                <div><dt>Kati Teminat</dt><dd class="td-mono">{{ $tl($ih->kati_teminat) }}</dd></div>
                <div><dt>Durum</dt><dd>{{ $ih->iptalMi() ? 'İptal' : 'Geçerli' }}</dd></div>
                <div><dt>Kazanan</dt><dd>{{ $ih->katilimcilar->firstWhere('kazandi_mi', true)?->ad_unvan ?: '—' }}</dd></div>
            </dl>
            @if ($ih->komisyon->isNotEmpty())
                <p class="kr-aciklama"><strong>Komisyon:</strong>
                    {{ $ih->komisyon->map(fn ($u) => $u->ad_soyad.($u->gorev === 'baskan' ? ' (Başkan)' : ''))->implode(', ') }}
                </p>
            @endif
        @endforeach
    </section>
@endif

{{-- Kararlar ve süreç --}}
@if ($dosya->kararlar->isNotEmpty() || $dosya->surecOlaylari->isNotEmpty())
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">7</span>
            <div class="form-section-head-text"><h3>Kararlar ve Süreç</h3></div>
        </div>
        @if ($dosya->kararlar->isNotEmpty())
            <div class="tl-tablo-wrap">
                <table class="tl-tablo">
                    <thead><tr><th>Tür</th><th>Karar No</th><th>Tarih</th><th>Kurum</th><th>Açıklama</th></tr></thead>
                    <tbody>
                        @foreach ($dosya->kararlar as $kr)
                            <tr>
                                <td>{{ $kr->tur->etiket() }}</td>
                                <td class="td-mono">{{ $kr->karar_no ?: '—' }}</td>
                                <td class="td-mono">{{ $gun($kr->karar_tarihi) }}</td>
                                <td>{{ $kr->ilgili_kurum ?: '—' }}</td>
                                <td>{{ $kr->aciklama ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if ($dosya->surecOlaylari->isNotEmpty())
            <ol class="kr-zaman">
                @foreach ($dosya->surecOlaylari->sortByDesc('olay_tarihi') as $o)
                    <li style="--kr-renk:{{ $o->tur->renk() }}">
                        <span class="kr-zaman-tur">{{ $o->tur->etiket() }}</span>
                        <span class="kr-zaman-tarih td-mono">{{ $gun($o->olay_tarihi) }}</span>
                        @if ($o->ilgili_kisi)<span class="kr-zaman-kisi">{{ $o->ilgili_kisi }}</span>@endif
                        @if ($o->aciklama)<p>{{ $o->aciklama }}</p>@endif
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
@endif

{{-- Eski sözleşmeler: yürürlükteki dönem yukarıda; süresi bitenler ayrı --}}
@php
    $eskiSozlesmeler = $dosya->sozlesmeler
        ->reject(fn ($sz) => $s && $sz->id === $s->id)
        ->sortByDesc(fn ($sz) => $sz->bitis_tarihi?->timestamp ?? 0);
@endphp
@if ($eskiSozlesmeler->isNotEmpty())
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">8</span>
            <div class="form-section-head-text">
                <h3>Süresi Dolmuş Sözleşmeler</h3>
                <small>{{ $eskiSozlesmeler->count() }} dönem</small>
            </div>
        </div>
        <div class="tl-tablo-wrap">
            <table class="tl-tablo">
                <thead><tr><th>Sözleşme No</th><th>Dönem</th><th>Süre</th><th class="is-sag">Son yıllık bedel</th><th>Durum</th></tr></thead>
                <tbody>
                    @foreach ($eskiSozlesmeler as $eski)
                        @php $eskiBedel = $eski->bedeller->last(); @endphp
                        <tr>
                            <td class="td-mono">{{ $eski->sozlesme_no ?: '—' }}</td>
                            <td class="td-mono">{{ $gun($eski->baslangic_tarihi) }} – {{ $gun($eski->bitis_tarihi) }}</td>
                            <td class="td-mono">{{ $eski->sureYil() ? $eski->sureYil().' yıl' : '—' }}</td>
                            <td class="td-mono is-sag">{{ $tl($eskiBedel?->yillik_bedel) }}</td>
                            <td>{{ $eski->aktif_mi ? 'Yürürlükte' : 'Süresi doldu' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

{{-- Abonelik, demirbaş ve denetim --}}
@if ($dosya->isletmeBilgisi)
    @php $isletme = $dosya->isletmeBilgisi; @endphp
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">9</span>
            <div class="form-section-head-text"><h3>İşletme Bilgileri</h3><small>Abonelik, demirbaş, denetim</small></div>
        </div>
        <dl class="dg-bilgi">
            <div><dt>Su Abone No</dt><dd class="td-mono">{{ $isletme->su_abone_no ?: '—' }}</dd></div>
            <div><dt>Elektrik Abone No</dt><dd class="td-mono">{{ $isletme->elektrik_abone_no ?: '—' }}</dd></div>
            <div><dt>Doğalgaz Abone No</dt><dd class="td-mono">{{ $isletme->dogalgaz_abone_no ?: '—' }}</dd></div>
            <div><dt>İşyeri Teslim</dt><dd class="td-mono">{{ $gun($isletme->isyeri_teslim_tarihi) }}</dd></div>
            <div><dt>Son Denetim</dt><dd class="td-mono">{{ $gun($isletme->son_denetim_tarihi) }}</dd></div>
        </dl>
        @if ($isletme->abone_aciklama)
            <p class="kr-aciklama">{{ $isletme->abone_aciklama }}</p>
        @endif
        @if ($isletme->demirbaslar)
            <p class="kr-aciklama"><strong>Demirbaşlar:</strong> {{ $isletme->demirbaslar }}</p>
        @endif
        @if ($isletme->denetim_notu)
            <p class="kr-aciklama"><strong>Denetim notu:</strong> {{ $isletme->denetim_notu }}</p>
        @endif
    </section>
@endif

{{-- Belgeler --}}
<section class="form-section">
    <div class="form-section-head">
        <span class="form-section-num">10</span>
        <div class="form-section-head-text">
            <h3>Belgeler</h3>
            <small>Encümen kararı, sözleşme, yazışmalar</small>
        </div>
    </div>
    @if ($dosya->belgeler->isEmpty())
        <p class="hint">Bu dosyaya henüz belge eklenmemiş.</p>
    @else
        <div class="tl-tablo-wrap">
            <table class="tl-tablo">
                <thead><tr><th>Tür</th><th>Ad</th><th>Yükleyen</th><th>Tarih</th><th></th></tr></thead>
                <tbody>
                    @foreach ($dosya->belgeler as $b)
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

{{-- Alt dosyalar --}}
@if ($dosya->altDosyalar->isNotEmpty())
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">11</span>
            <div class="form-section-head-text"><h3>Alt Dosyalar</h3><small>{{ $dosya->altDosyalar->count() }} dosya</small></div>
        </div>
        <ul class="kr-alt-dosyalar">
            @foreach ($dosya->altDosyalar as $alt)
                <li><a href="{{ route('panel.kira.detay', $alt->id) }}"><strong class="td-mono">{{ $alt->dosya_no }}</strong> {{ $alt->isyeri_adi }}</a></li>
            @endforeach
        </ul>
    </section>
@endif
@endsection
