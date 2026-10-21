@extends('layouts.panel')

@section('title', 'Kira Dosyası · '.$dosya->dosya_no)

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=5">
    <link rel="stylesheet" href="https://js.arcgis.com/4.30/esri/themes/light/main.css">
    <link rel="stylesheet" href="{{ asset('css/kira.css') }}?v=12">
    {{-- Dosya kartının kendi düzeni: sol künye rayı + sekmeler --}}
    <link rel="stylesheet" href="{{ asset('css/kira-detay.css') }}?v=1">
@endpush

@push('scripts')
    <script src="https://js.arcgis.com/4.30/"></script>
    <script src="{{ asset('js/kira-harita-kutu.js') }}?v=3"></script>
@endpush

@php
    $tl = fn ($n) => $n === null ? '—' : number_format((float) $n, 2, ',', '.');
    $gun = fn ($t) => $t?->format('d.m.Y') ?: '—';
    $t = $dosya->tasinmaz;
    $s = $aktifSozlesme;
    $kalan = $s?->kalanGun();
    $ana = $dosya->anaKiraci;
    $altlar = $dosya->kiracilar->where('tip', \App\Enums\KiraciTipi::Alt);
    $bedel = $s?->guncelBedel();
    $isletme = $dosya->isletmeBilgisi;

    // Sözleşmenin ne kadarı geçti — rayda ilerleme çubuğu
    $ilerleme = null;
    if ($s?->baslangic_tarihi && $s->bitis_tarihi) {
        $toplam = $s->baslangic_tarihi->diffInDays($s->bitis_tarihi);
        $gecen = $s->baslangic_tarihi->diffInDays(now(), false);
        $ilerleme = $toplam > 0 ? max(0, min(100, round($gecen / $toplam * 100))) : null;
    }

    // Haritada soluk çizilecekler: birincil sınır dışındaki alanların sınırları
    $digerPoligonlar = $dosya->alanlar
        ->map(fn ($a) => $a->poligon)
        ->filter(fn ($p) => $p && $p->id !== $birincilPoligon?->id)
        ->unique('id')
        ->map(fn ($p) => ['geometri' => $p->geoJson(), 'renk' => '#f59e0b', 'ad' => $p->ad])
        ->filter(fn ($e) => $e['geometri'] !== null)
        ->values();

    $eskiSozlesmeler = $dosya->sozlesmeler
        ->reject(fn ($sz) => $s && $sz->id === $s->id)
        ->sortByDesc(fn ($sz) => $sz->bitis_tarihi?->timestamp ?? 0);

    // Sekmeler: boş olanlar hiç basılmaz
    $sekmeler = collect([
        ['id' => 'ozet', 'ad' => 'Özet', 'sayi' => null],
        ['id' => 'kiracilar', 'ad' => 'Kiracılar', 'sayi' => $kiraciGecmisi->count()],
        ['id' => 'sozlesme', 'ad' => 'Sözleşme & Bedel', 'sayi' => $s?->bedeller->count()],
        ['id' => 'yer', 'ad' => 'Yer & Harita', 'sayi' => $dosya->alanlar->count() ?: null],
        ['id' => 'ihale', 'ad' => 'İhale & Kararlar', 'sayi' => $dosya->ihaleler->count() + $dosya->kararlar->count() + $dosya->surecOlaylari->count() ?: null],
        ['id' => 'belgeler', 'ad' => 'Belgeler', 'sayi' => $dosya->belgeler->count() ?: null],
    ])->when($isletme, fn ($c) => $c->push(['id' => 'isletme', 'ad' => 'İşletme', 'sayi' => null]));
@endphp

@section('content')
@if (session('basari'))<div class="flash-success" role="status">{{ session('basari') }}</div>@endif

<div class="kd">
    {{-- ---------- SOL RAY: dosyanın künyesi ---------- --}}
    <aside class="kd-ray">
        <div class="kd-kimlik">
            <p class="kd-eyebrow">Kira Dosyası</p>
            <h2 class="kd-no">{{ $dosya->dosya_no }}</h2>
            @if ($dosya->isyeri_adi)<p class="kd-isyeri">{{ $dosya->isyeri_adi }}</p>@endif

            <div class="kd-rozetler">
                <span class="kr-durum" style="--kr-renk:{{ $dosya->dolu_bos->renk() }}">{{ $dosya->dolu_bos->etiket() }}</span>
                <span class="kr-etiket">{{ $dosya->ihale_turu->etiket() }}</span>
                @if ($dosya->sozlesme_aktif_mi)
                    <span class="kr-etiket is-yesil">Yürürlükte</span>
                @else
                    <span class="kr-etiket">Yürürlükte değil</span>
                @endif
                @if ($dosya->tahliye_ihbarnamesi_mi)<span class="kr-etiket is-tehlike">Tahliye ihbarı</span>@endif
            </div>
        </div>

        @if ($dosya->uyari)
            <div class="kd-uyari">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                <span>{{ $dosya->uyari }}</span>
            </div>
        @endif

        {{-- Güncel kiracı: sayfanın en çok aranan bilgisi --}}
        <div class="kd-kiraci">
            <span class="kd-etiket">Güncel kiracı</span>
            <strong>{{ $ana?->ad_unvan ?: 'Kiracı girilmemiş' }}</strong>
            <div class="kd-kiraci-alt">
                @if ($ana?->telefon)<a href="tel:{{ $ana->telefon }}" class="td-mono">{{ $ana->telefon }}</a>@endif
                @if ($ana?->tc_vergi_no)<span class="td-mono">{{ $ana->tc_vergi_no }}</span>@endif
            </div>
            @if ($kiraciGecmisi->count() > 1)
                <a href="#kiracilar" class="kd-kiraci-gecmis" data-sekme-git="kiracilar">
                    {{ $kiraciGecmisi->count() }} kiracılık dönemi →
                </a>
            @endif
        </div>

        {{-- Sözleşme süresi --}}
        <div class="kd-sure {{ $kalan !== null && $kalan < 0 ? 'is-gecmis' : ($kalan !== null && $kalan <= 90 ? 'is-yakin' : '') }}">
            <div class="kd-sure-ust">
                <span class="kd-etiket">Sözleşme süresi</span>
                <strong>
                    @if ($kalan === null) —
                    @elseif ($kalan < 0) {{ abs($kalan) }} gün geçti
                    @else {{ $kalan }} gün kaldı @endif
                </strong>
            </div>
            @if ($ilerleme !== null)
                <div class="kd-cubuk"><span style="width:{{ $ilerleme }}%"></span></div>
            @endif
            <div class="kd-sure-alt td-mono">{{ $gun($s?->baslangic_tarihi) }} – {{ $gun($s?->bitis_tarihi) }}</div>
        </div>

        {{-- Rakamlar --}}
        <dl class="kd-rakam">
            <div>
                <dt>Yıllık bedel</dt>
                <dd class="td-mono">{{ $tl($bedel?->yillik_bedel) }} <em>TL</em></dd>
            </div>
            <div>
                <dt>Aylık</dt>
                <dd class="td-mono">{{ $tl($bedel?->aylik()) }} <em>TL</em></dd>
            </div>
            <div>
                <dt>Yüzölçüm</dt>
                <dd class="td-mono">{{ $tl($t?->yuzolcum) }} <em>m²</em></dd>
            </div>
            <div>
                <dt>Taksit</dt>
                <dd class="td-mono">{{ $s?->taksit_sayisi ?: '—' }}</dd>
            </div>
        </dl>

        {{-- Konum --}}
        <div class="kd-konum">
            <span class="kd-etiket">Konum</span>
            <p>{{ collect([$t?->ilce?->ad, $t?->mahalle?->ad])->filter()->implode(' · ') ?: 'Konum girilmemiş' }}</p>
            @if ($t?->adaParsel())<p class="td-mono kd-adaparsel">Ada/Parsel {{ $t->adaParsel() }}</p>@endif
            @php
                $kmhRay = [
                    'duzenlenir' => false,
                    'geometri' => $birincilPoligon?->geoJson(),
                    'ekler' => $digerPoligonlar,
                    'buyukHarita' => $birincilPoligon
                        ? route('panel.kira.harita', ['merkez' => $birincilPoligon->merkez_lat.','.$birincilPoligon->merkez_lng, 'zoom' => 17])
                        : route('panel.kira.harita'),
                ];
            @endphp
            <div class="kmh kd-ray-harita" data-kmh="{{ json_encode($kmhRay) }}"></div>
        </div>

        {{-- Eylemler — bu sayfa salt görüntülemedir: düzenleme/ekleme yok,
             yalnızca gezinme bağlantıları bulunur. --}}
        <div class="kd-eylem">
            <a href="{{ route('panel.kira.index') }}" class="kd-btn is-ana">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10 16l-4-4 4-4M14 12H6"/><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/></svg>
                Listeye Dön
            </a>
            @if ($birincilPoligon)
                <a href="{{ route('panel.kira.harita', ['merkez' => $birincilPoligon->merkez_lat.','.$birincilPoligon->merkez_lng, 'zoom' => 17]) }}" class="kd-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.4"/></svg>
                    Haritada Göster
                </a>
            @endif
        </div>

        @if ($dosya->anaDosya || $dosya->altDosyalar->isNotEmpty())
            <div class="kd-bagli">
                <span class="kd-etiket">Bağlı dosyalar</span>
                @if ($dosya->anaDosya)
                    <a href="{{ route('panel.kira.detay', $dosya->anaDosya->id) }}">
                        <span class="kr-etiket">Ana</span> <strong class="td-mono">{{ $dosya->anaDosya->dosya_no }}</strong>
                    </a>
                @endif
                @foreach ($dosya->altDosyalar as $alt)
                    <a href="{{ route('panel.kira.detay', $alt->id) }}">
                        <span class="kr-etiket">Alt</span> <strong class="td-mono">{{ $alt->dosya_no }}</strong> {{ $alt->isyeri_adi }}
                    </a>
                @endforeach
            </div>
        @endif
    </aside>

    {{-- ---------- SAĞ: sekmeli içerik ---------- --}}
    <div class="kd-ana">
        <nav class="kd-sekmeler" role="tablist" aria-label="Dosya bölümleri">
            @foreach ($sekmeler as $i => $sek)
                <button type="button" role="tab" class="kd-sekme {{ $i === 0 ? 'is-aktif' : '' }}"
                        data-sekme="{{ $sek['id'] }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}"
                        aria-controls="pnl-{{ $sek['id'] }}">
                    {{ $sek['ad'] }}
                    @if ($sek['sayi'])<span class="kd-sayi">{{ $sek['sayi'] }}</span>@endif
                </button>
            @endforeach
        </nav>

        {{-- ===== ÖZET ===== --}}
        <section class="kd-panel is-acik" id="pnl-ozet" role="tabpanel">
            <div class="kd-kart-izgara">
                <div class="kd-kart">
                    <h4>Dosya</h4>
                    <dl class="kd-liste">
                        <div><dt>Dosya No</dt><dd class="td-mono">{{ $dosya->dosya_no }}</dd></div>
                        <div><dt>Eski Dosya No</dt><dd class="td-mono">{{ $dosya->eski_dosya_no ?: '—' }}</dd></div>
                        <div><dt>İşyeri Adı</dt><dd>{{ $dosya->isyeri_adi ?: '—' }}</dd></div>
                        <div><dt>İş Kolu</dt><dd>{{ $dosya->isKolu?->ad ?: '—' }}</dd></div>
                        <div><dt>Muhasebe Niteliği</dt><dd>{{ $dosya->muhasebeNiteligi?->ad ?: '—' }}</dd></div>
                        <div><dt>Sorumlu Personel</dt><dd>{{ $dosya->sorumluPersonel ? $dosya->sorumluPersonel->ad.' '.$dosya->sorumluPersonel->soyad : '—' }}</dd></div>
                        <div><dt>Araç Kapasitesi</dt><dd class="td-mono">{{ $dosya->arac_kapasitesi ?: '—' }}</dd></div>
                    </dl>
                </div>

                <div class="kd-kart">
                    <h4>Kiracı</h4>
                    <dl class="kd-liste">
                        <div><dt>Ana Kiracı</dt><dd>{{ $ana?->ad_unvan ?: '—' }}</dd></div>
                        <div><dt>TC / Vergi No</dt><dd class="td-mono">{{ $ana?->tc_vergi_no ?: '—' }}</dd></div>
                        <div><dt>Telefon</dt><dd class="td-mono">{{ $ana?->telefon ?: '—' }}</dd></div>
                        <div><dt>E-posta</dt><dd>{{ $ana?->eposta ?: '—' }}</dd></div>
                        <div><dt>IBAN</dt><dd class="td-mono">{{ $ana?->iban ?: '—' }}</dd></div>
                        <div><dt>Avukat</dt><dd>{{ $ana?->avukat ?: '—' }}</dd></div>
                        <div><dt>Alt Kiracı</dt><dd>{{ $altlar->pluck('ad_unvan')->implode(', ') ?: '—' }}</dd></div>
                    </dl>
                </div>

                <div class="kd-kart">
                    <h4>Hukuki Durum</h4>
                    <dl class="kd-liste">
                        <div><dt>Hukuki Durum</dt><dd>{{ $dosya->hukukiDurum?->ad ?: '—' }}</dd></div>
                        <div><dt>Kiracılık Durumu</dt><dd>{{ $dosya->kiracilikDurum?->ad ?: '—' }}</dd></div>
                        <div><dt>Sözleşme</dt><dd>{{ $dosya->sozlesme_aktif_mi ? 'Yürürlükte' : 'Yürürlükte değil' }}</dd></div>
                        <div><dt>Tahliye İhbarnamesi</dt><dd>{{ $dosya->tahliye_ihbarnamesi_mi ? 'Çekildi' : 'Çekilmedi' }}</dd></div>
                    </dl>
                </div>
            </div>

            @if ($dosya->aciklama)
                <div class="kd-not">
                    <span class="kd-etiket">Açıklama</span>
                    <p>{{ $dosya->aciklama }}</p>
                </div>
            @endif
        </section>

        {{-- ===== KİRACILAR ===== --}}
        <section class="kd-panel" id="pnl-kiracilar" role="tabpanel" hidden>
            <div class="kd-panel-ust">
                <div>
                    <h3>Kiracılık Dönemleri</h3>
                    <p>{{ $dosya->dosya_no }} numaralı dosyada
                        @if ($kiraciGecmisi->count() <= 1) tek bir dönem var
                        @else <strong>{{ $kiraciGecmisi->count() }} dönem</strong> · en yeniden eskiye @endif
                    </p>
                </div>
            </div>

            @if ($kiraciGecmisi->isEmpty())
                <p class="kd-bos">Bu dosyada kayıtlı kiracılık dönemi yok.</p>
            @else
                <div class="tl-tablo-wrap">
                    <table class="tl-tablo kr-donem-tablo">
                        <thead>
                            <tr>
                                <th>#</th><th>Kiracı</th><th>Sözleşme No</th><th>Dönem</th>
                                <th class="is-sag">Süre</th><th class="is-sag">Yıllık Bedel (₺)</th><th>Durum</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($kiraciGecmisi as $i => $g)
                                <tr class="{{ $g['yururlukte'] ? 'is-yururlukte' : '' }}">
                                    <td class="td-mono">{{ $kiraciGecmisi->count() - $i }}</td>
                                    <td><strong>{{ $g['kiraci'] ?: 'Kiracı girilmemiş' }}</strong></td>
                                    <td class="td-mono">{{ $g['sozlesme_no'] ?: '—' }}</td>
                                    <td class="td-mono">{{ $gun($g['baslangic']) }} – {{ $gun($g['bitis']) }}</td>
                                    <td class="td-mono is-sag">{{ $g['sure'] ? $g['sure'].' yıl' : '—' }}</td>
                                    <td class="td-mono is-sag">{{ $tl($g['yillik_bedel']) }}</td>
                                    <td>
                                        @if ($g['yururlukte'])
                                            <span class="kr-etiket is-yesil">Yürürlükte</span>
                                        @else
                                            <span class="kr-etiket">Sona ermiş</span>
                                        @endif
                                    </td>
                                    <td><a href="{{ $g['bag'] }}" class="btn-cancel hs-mini-btn">Dönem kartı</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($ayniSinirdakiler->isNotEmpty())
                <div class="kd-panel-ust kd-ayirac">
                    <div>
                        <h3>Aynı Sınırdaki Diğer Dosyalar</h3>
                        <p>Bu parselde {{ $ayniSinirdakiler->count() + 1 }} ayrı kira dosyası var</p>
                    </div>
                    @if ($birincilPoligon)
                        <a href="{{ route('panel.kira.harita', ['merkez' => $birincilPoligon->merkez_lat.','.$birincilPoligon->merkez_lng, 'zoom' => 18]) }}" class="kd-btn">Haritada göster</a>
                    @endif
                </div>
                <div class="tl-tablo-wrap">
                    <table class="tl-tablo">
                        <thead><tr><th>Dosya No</th><th>Yer</th><th>Kiracı</th><th>İş Kolu</th><th class="is-sag">m²</th><th>Dönem</th><th>Durum</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($ayniSinirdakiler as $k)
                                <tr>
                                    <td class="td-mono"><strong>{{ $k['dosya_no'] }}</strong></td>
                                    <td>{{ $k['ad'] ?: '—' }}</td>
                                    <td>{{ $k['kiraci'] ?: '—' }}</td>
                                    <td>{{ $k['is_kolu'] ?: '—' }}</td>
                                    <td class="td-mono is-sag">{{ $tl($k['alan']) }}</td>
                                    <td class="td-mono">{{ $gun($k['baslangic']) }} – {{ $gun($k['bitis']) }}</td>
                                    <td><span class="kr-durum" style="--kr-renk:{{ $k['dolu_bos']->renk() }}">{{ $k['dolu_bos']->etiket() }}</span></td>
                                    <td>@if ($k['bag'])<a href="{{ $k['bag'] }}" class="btn-cancel hs-mini-btn">Dosyayı aç</a>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- ===== SÖZLEŞME & BEDEL ===== --}}
        <section class="kd-panel" id="pnl-sozlesme" role="tabpanel" hidden>
            @if (! $s)
                <p class="kd-bos">Bu dosyada sözleşme kaydı yok.</p>
            @else
                <div class="kd-kart-izgara">
                    <div class="kd-kart">
                        <h4>Yürürlükteki Sözleşme</h4>
                        <dl class="kd-liste">
                            <div><dt>Sözleşme No</dt><dd class="td-mono">{{ $s->sozlesme_no ?: '—' }}</dd></div>
                            <div><dt>Sözleşme Tarihi</dt><dd class="td-mono">{{ $gun($s->sozlesme_tarihi) }}</dd></div>
                            <div><dt>Kira Dönemi</dt><dd class="td-mono">{{ $gun($s->baslangic_tarihi) }} – {{ $gun($s->bitis_tarihi) }}</dd></div>
                            <div><dt>Süre</dt><dd class="td-mono">{{ $s->sure_ay ? $s->sure_ay.' ay' : ($s->sureYil() ? $s->sureYil().' yıl' : '—') }}</dd></div>
                            <div><dt>Taksit Sayısı</dt><dd class="td-mono">{{ $s->taksit_sayisi ?: '—' }}</dd></div>
                            <div><dt>Ödeme Şekli</dt><dd>{{ $s->odeme_sekli ?: '—' }}</dd></div>
                        </dl>
                    </div>
                    <div class="kd-kart">
                        <h4>Noter / Mali</h4>
                        <dl class="kd-liste">
                            <div><dt>Noter Tarihi</dt><dd class="td-mono">{{ $gun($s->noter_tarihi) }}</dd></div>
                            <div><dt>Yevmiye No</dt><dd class="td-mono">{{ $s->yevmiye_no ?: '—' }}</dd></div>
                            <div><dt>KDV</dt><dd>{{ $s->kdv_orani !== null ? $tl($s->kdv_orani).'%' : '—' }} {{ $s->kdv_dahil_mi ? '(dahil)' : '' }}</dd></div>
                            <div><dt>Artırma Yöntemi</dt><dd>{{ $s->artirma_yontemi ?: '—' }}</dd></div>
                        </dl>
                    </div>
                </div>

                @if ($s->bedeller->isNotEmpty())
                    <div class="kd-panel-ust kd-ayirac">
                        <div>
                            <h3>Kira Bedelleri</h3>
                            <p>{{ $gun($s->baslangic_tarihi) }} – {{ $gun($s->bitis_tarihi) }} sözleşmesi · yıl yıl</p>
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
                @endif

                @if ($eskiSozlesmeler->isNotEmpty())
                    <div class="kd-panel-ust kd-ayirac">
                        <div>
                            <h3>Süresi Dolmuş Sözleşmeler</h3>
                            <p>{{ $eskiSozlesmeler->count() }} dönem</p>
                        </div>
                    </div>
                    <div class="tl-tablo-wrap">
                        <table class="tl-tablo">
                            <thead><tr><th>Sözleşme No</th><th>Dönem</th><th>Süre</th><th class="is-sag">Son yıllık bedel</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($eskiSozlesmeler as $eski)
                                    <tr>
                                        <td class="td-mono">{{ $eski->sozlesme_no ?: '—' }}</td>
                                        <td class="td-mono">{{ $gun($eski->baslangic_tarihi) }} – {{ $gun($eski->bitis_tarihi) }}</td>
                                        <td class="td-mono">{{ $eski->sureYil() ? $eski->sureYil().' yıl' : '—' }}</td>
                                        <td class="td-mono is-sag">{{ $tl($eski->bedeller->last()?->yillik_bedel) }}</td>
                                        <td><a href="{{ route('panel.kira.donem.detay', [$dosya->id, $eski->id]) }}" class="btn-cancel hs-mini-btn">Dönem kartı</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </section>

        {{-- ===== YER & HARİTA ===== --}}
        <section class="kd-panel" id="pnl-yer" role="tabpanel" hidden>
            <div class="kd-yer-izgara">
                <div class="kd-kart">
                    <h4>Adres / Tapu</h4>
                    <dl class="kd-liste">
                        <div><dt>İl</dt><dd>{{ $t?->il?->ad ?: '—' }}</dd></div>
                        <div><dt>İlçe</dt><dd>{{ $t?->ilce?->ad ?: '—' }}</dd></div>
                        <div><dt>Mahalle</dt><dd>{{ $t?->mahalle?->ad ?: '—' }}</dd></div>
                        <div><dt>Ada / Parsel</dt><dd class="td-mono">{{ $t?->adaParsel() ?: '—' }}</dd></div>
                        <div><dt>Yüzölçüm</dt><dd class="td-mono">{{ $tl($t?->yuzolcum) }} m²</dd></div>
                        <div><dt>İmar Durumu</dt><dd>{{ $t?->imarDurumu?->ad ?: '—' }}</dd></div>
                        <div><dt>Envanter Kaydı</dt><dd>{{ $t?->tasinmaz ? '#'.$t->tasinmaz->id.' · '.$t->tasinmaz->ada.'/'.$t->tasinmaz->parsel : 'Bağlı değil' }}</dd></div>
                        <div><dt>Sınır</dt><dd>{{ $birincilPoligon ? '#'.$birincilPoligon->id.($birincilPoligon->kayitSayisi() > 1 ? ' · '.$birincilPoligon->kayitSayisi().' kayıt paylaşıyor' : '') : 'Yok' }}</dd></div>
                    </dl>
                    @if ($t?->adres)<p class="kd-adres">{{ $t->adres }}</p>@endif
                </div>

                @php
                    $kmhGenis = [
                        'duzenlenir' => false,
                        'geometri' => $birincilPoligon?->geoJson(),
                        'ekler' => $digerPoligonlar,
                        'buyukHarita' => $birincilPoligon
                            ? route('panel.kira.harita', ['merkez' => $birincilPoligon->merkez_lat.','.$birincilPoligon->merkez_lng, 'zoom' => 17])
                            : route('panel.kira.harita'),
                    ];
                @endphp
                <div class="kmh" data-kmh="{{ json_encode($kmhGenis) }}"></div>
            </div>

            <div class="kd-panel-ust kd-ayirac">
                <div>
                    <h3>Kiraya Verilen Yerler</h3>
                    <p>
                        {{ $dosya->alanlar->count() }} yer
                        @if ($dosya->alanlar->count() === 1) · bu dosyada tek bir yer kiraya verilmiş @endif
                    </p>
                </div>
            </div>

            @if ($dosya->alanlar->isEmpty())
                <p class="kd-bos">Bu dosyada kiraya verilmiş yer tanımlı değil.</p>
            @else
                <div class="tl-tablo-wrap">
                    <table class="tl-tablo">
                        <thead><tr><th>Yer</th><th>Faaliyet</th><th>İş Kolu</th><th class="is-sag">m²</th><th class="is-sag">Yıllık Bedel</th><th>Dönem</th><th>Sınır</th><th>Durum</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($dosya->alanlar as $a)
                                <tr>
                                    <td><strong>{{ $a->ad ?: '—' }}</strong></td>
                                    <td>{{ $a->faaliyet_konusu ?: '—' }}</td>
                                    <td>{{ $a->isKolu?->ad ?: '—' }}</td>
                                    <td class="td-mono is-sag">{{ $tl($a->alan) }}</td>
                                    <td class="td-mono is-sag">{{ $tl($a->yillik_bedel) }}</td>
                                    <td class="td-mono">{{ $gun($a->baslangic_tarihi) }} – {{ $gun($a->bitis_tarihi) }}</td>
                                    <td class="td-mono">{{ $a->poligon_id ? '#'.$a->poligon_id : '—' }}</td>
                                    <td><span class="kr-durum" style="--kr-renk:{{ $a->dolu_bos->renk() }}">{{ $a->dolu_bos->etiket() }}</span></td>
                                    <td><a href="{{ route('panel.kira.alanlar.detay', $a->id) }}" class="btn-cancel hs-mini-btn">Kart</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- ===== İHALE & KARARLAR ===== --}}
        <section class="kd-panel" id="pnl-ihale" role="tabpanel" hidden>
            @if ($dosya->ihaleler->isEmpty() && $dosya->kararlar->isEmpty() && $dosya->surecOlaylari->isEmpty())
                <p class="kd-bos">Bu dosyada ihale, karar veya süreç kaydı yok.</p>
            @endif

            @foreach ($dosya->ihaleler as $ih)
                <div class="kd-kart">
                    <h4>İhale · {{ $ih->usul?->etiket() ?: 'usul girilmemiş' }}</h4>
                    <dl class="kd-liste kd-liste-genis">
                        <div><dt>İhale Tarihi</dt><dd class="td-mono">{{ $ih->ihale_tarihi?->format('d.m.Y H:i') ?: '—' }}</dd></div>
                        <div><dt>Muhammen Bedel</dt><dd class="td-mono">{{ $tl($ih->muhammen_bedel) }}</dd></div>
                        <div><dt>İhale Bedeli</dt><dd class="td-mono">{{ $tl($ih->ihale_bedeli) }}</dd></div>
                        <div><dt>Geçici Teminat</dt><dd class="td-mono">{{ $tl($ih->gecici_teminat) }}</dd></div>
                        <div><dt>Kati Teminat</dt><dd class="td-mono">{{ $tl($ih->kati_teminat) }}</dd></div>
                        <div><dt>Durum</dt><dd>{{ $ih->iptalMi() ? 'İptal' : 'Geçerli' }}</dd></div>
                        <div><dt>Kazanan</dt><dd>{{ $ih->katilimcilar->firstWhere('kazandi_mi', true)?->ad_unvan ?: '—' }}</dd></div>
                    </dl>
                    @if ($ih->komisyon->isNotEmpty())
                        <p class="kd-adres"><strong>Komisyon:</strong>
                            {{ $ih->komisyon->map(fn ($u) => $u->ad_soyad.($u->gorev === 'baskan' ? ' (Başkan)' : ''))->implode(', ') }}
                        </p>
                    @endif
                </div>
            @endforeach

            @if ($dosya->kararlar->isNotEmpty())
                <div class="kd-panel-ust kd-ayirac"><div><h3>Kararlar</h3><p>{{ $dosya->kararlar->count() }} karar</p></div></div>
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
                <div class="kd-panel-ust kd-ayirac"><div><h3>Süreç</h3><p>{{ $dosya->surecOlaylari->count() }} olay</p></div></div>
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

        {{-- ===== BELGELER ===== --}}
        <section class="kd-panel" id="pnl-belgeler" role="tabpanel" hidden>
            <div class="kd-panel-ust">
                <div>
                    <h3>Belgeler</h3>
                    <p>Encümen kararı, sözleşme, yazışmalar</p>
                </div>
            </div>

            @if ($dosya->belgeler->isEmpty())
                <p class="kd-bos">Bu dosyaya henüz belge eklenmemiş.</p>
            @else
                <div class="tl-tablo-wrap">
                    <table class="tl-tablo">
                        <thead><tr><th>Tür</th><th>Ad</th><th>Yer</th><th>Yükleyen</th><th>Tarih</th><th class="is-sag">Boyut</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($dosya->belgeler as $b)
                                <tr>
                                    <td>{{ $b->tur->etiket() }}</td>
                                    <td>{{ $b->ad }}</td>
                                    <td>
                                        @if ($b->kira_alan_id)
                                            <a href="{{ route('panel.kira.alanlar.detay', $b->kira_alan_id) }}">{{ $b->alan?->ad ?: 'Alan #'.$b->kira_alan_id }}</a>
                                        @else — @endif
                                    </td>
                                    <td>{{ $b->yukleyen ? $b->yukleyen->ad.' '.$b->yukleyen->soyad : '—' }}</td>
                                    <td class="td-mono">{{ $b->created_at?->format('d.m.Y') }}</td>
                                    <td class="td-mono is-sag">{{ $b->boyut ? number_format($b->boyut / 1024, 0, ',', '.').' KB' : '—' }}</td>
                                    <td class="dg-islem">
                                        <a href="{{ $b->url() }}" target="_blank" rel="noopener" class="btn-cancel hs-mini-btn">Aç</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- ===== İŞLETME ===== --}}
        @if ($isletme)
            <section class="kd-panel" id="pnl-isletme" role="tabpanel" hidden>
                <div class="kd-kart">
                    <h4>Abonelik ve Denetim</h4>
                    <dl class="kd-liste kd-liste-genis">
                        <div><dt>Su Abone No</dt><dd class="td-mono">{{ $isletme->su_abone_no ?: '—' }}</dd></div>
                        <div><dt>Elektrik Abone No</dt><dd class="td-mono">{{ $isletme->elektrik_abone_no ?: '—' }}</dd></div>
                        <div><dt>Doğalgaz Abone No</dt><dd class="td-mono">{{ $isletme->dogalgaz_abone_no ?: '—' }}</dd></div>
                        <div><dt>İşyeri Teslim</dt><dd class="td-mono">{{ $gun($isletme->isyeri_teslim_tarihi) }}</dd></div>
                        <div><dt>Son Denetim</dt><dd class="td-mono">{{ $gun($isletme->son_denetim_tarihi) }}</dd></div>
                    </dl>
                    @if ($isletme->abone_aciklama)<p class="kd-adres">{{ $isletme->abone_aciklama }}</p>@endif
                    @if ($isletme->demirbaslar)<p class="kd-adres"><strong>Demirbaşlar:</strong> {{ $isletme->demirbaslar }}</p>@endif
                    @if ($isletme->denetim_notu)<p class="kd-adres"><strong>Denetim notu:</strong> {{ $isletme->denetim_notu }}</p>@endif
                </div>
            </section>
        @endif
    </div>
</div>

<script>
/* ---------- Sekmeler ---------- */
(function () {
    const dugmeler = Array.from(document.querySelectorAll('[data-sekme]'));
    const panoler = Array.from(document.querySelectorAll('.kd-panel'));
    if (!dugmeler.length) return;

    function ac(id, adresiYaz) {
        const hedef = dugmeler.find(d => d.dataset.sekme === id);
        if (!hedef) return;
        dugmeler.forEach(d => {
            const aktif = d === hedef;
            d.classList.toggle('is-aktif', aktif);
            d.setAttribute('aria-selected', aktif ? 'true' : 'false');
        });
        panoler.forEach(p => {
            const aktif = p.id === 'pnl-' + id;
            p.hidden = !aktif;
            p.classList.toggle('is-acik', aktif);
        });
        if (adresiYaz) history.replaceState(null, '', '#' + id);
        // Gömülü haritalar gizliyken 0 boyutla kurulur; görününce yeniden ölçülsün
        window.dispatchEvent(new Event('resize'));
    }

    dugmeler.forEach(d => d.addEventListener('click', () => ac(d.dataset.sekme, true)));

    // Raydaki "N kiracılık dönemi →" bağlantısı sekmeyi açar
    document.querySelectorAll('[data-sekme-git]').forEach(b => {
        b.addEventListener('click', e => { e.preventDefault(); ac(b.dataset.sekmeGit, true); });
    });

    // Adres satırındaki #kiracilar gibi bağlantılarla doğrudan açılış
    const baslangic = (location.hash || '').replace('#', '');
    if (baslangic) ac(baslangic, false);

    // Klavye: sol/sağ ok sekmeler arasında gezer
    document.querySelector('.kd-sekmeler').addEventListener('keydown', e => {
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
        const simdiki = dugmeler.findIndex(d => d.classList.contains('is-aktif'));
        const yon = e.key === 'ArrowRight' ? 1 : -1;
        const yeni = (simdiki + yon + dugmeler.length) % dugmeler.length;
        ac(dugmeler[yeni].dataset.sekme, true);
        dugmeler[yeni].focus();
    });
})();

</script>
@endsection
