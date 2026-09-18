@extends('layouts.panel')

@section('title', 'Taşınmaz Detay · #' . $tasinmaz->id)

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/maplibre-gl@3.6.2/dist/maplibre-gl.css" crossorigin="anonymous">
@endpush

@section('content')
@if (session('basari'))
    <div class="flash-success" role="status">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12 l5 5 L20 7"/></svg>
        {{ session('basari') }}
    </div>
@endif

@php
    $koordinat = $tasinmaz->koordinat;
    $imar = $tasinmaz->imar;
    $imarAd = $imar?->imarDurumu?->ad;
    $tapu = $tasinmaz->tapu;
    $kategori = $tasinmaz->kategori;
    $ekbilgi = $tasinmaz->ekbilgi;
    $yapilar = $tasinmaz->yapilar;
    $hisseler = $tasinmaz->hisseler;
    $aktifHisseler = $hisseler->where('hisse_durum', 'aktif');
    $aktifHisseToplam = $aktifHisseler->sum('hisse_yuzolcum');
    $resimler = $tasinmaz->resimler->sortByDesc('kapak_mi')->values();
    $kapakResim = $resimler->first();
    $tkgmUrl = $tasinmaz->tkgmSorguUrl();
    $mulkiyet = $tasinmaz->mulkiyet_durumu;
    $satisOzet = $tasinmaz->satisOzeti();

    // Ekbilgi + yapı bayraklarını birleşik OR ile göster
    $bayrak = function (string $alan) use ($ekbilgi, $yapilar) {
        if ($ekbilgi && (bool) $ekbilgi->{$alan}) return true;
        return $yapilar->contains(fn ($y) => (bool) $y->{$alan});
    };
    $tahsisVar = $bayrak('tahsis_var');
    $ustHakkiVar = $bayrak('ust_hakki_var');
    $kiraVar = $bayrak('kira_var');
    $meclisVar = $bayrak('meclis_satis_karari_var');
    $binaVar = (bool) $tasinmaz->uzeri_bina_var_mi || $yapilar->isNotEmpty();
    $geometriVar = $koordinat && (! empty($koordinat->koordinat) || ($koordinat->lat !== null && $koordinat->lng !== null));

    $aciklamalar = collect([$ekbilgi?->aciklama])
        ->merge($yapilar->pluck('aciklama'))
        ->filter()
        ->values();
    $imarNotu = trim((string) ($imar->imar_notu ?? ''));

    // Aktif meclis satış kararı (en yeni ilk)
    $meclisSatisKarari = $tasinmaz->meclisKararlari
        ->where('karar_tipi', 'satis')
        ->sortByDesc('karar_tarihi')
        ->first();

    // Tapu niteliği ile fiili durum uyumsuzluğu
    // (Web Tapu'da "arsa" görünüyor ama üstünde bina varsa — veya tersi)
    $niteligiArsa = $tasinmaz->nitelik
        ? (bool) preg_match('/arsa|arazi|tarla|bağ|bahçe|çayır|otlak|mera|boş/iu', $tasinmaz->nitelik)
        : null;
    $tapuFiiliUyumsuz = $niteligiArsa !== null && (
        ($niteligiArsa && $binaVar) ||
        (! $niteligiArsa && ! $binaVar)
    );
    $uyumsuzlukMetin = $tapuFiiliUyumsuz
        ? ($niteligiArsa
            ? 'Tapuda boş nitelikli, fiilen üzerinde yapı var'
            : 'Tapuda yapı nitelikli, fiilen boş')
        : null;

    $isgalEtiket = match ($ekbilgi?->isgal_durumu) {
        'bos' => 'Boş',
        'isgal' => 'İşgalli',
        'kismen' => 'Kısmen İşgalli',
        default => $ekbilgi?->isgal_durumu ?: '—',
    };
@endphp

<section class="page-hero td-hero">
    <div class="page-hero-text">
        <div class="td-hero-baslik">
            <a href="{{ route('panel.tasinmazlar.index') }}" class="td-hero-back" title="Listeye dön">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <h2>Taşınmaz #{{ $tasinmaz->id }}</h2>
            <span class="tl-pill {{ $satisOzet->pill() }}">{{ $satisOzet->etiket() }}</span>
            @if ($mulkiyet === 'TAM')
                <span class="tl-pill tl-pill-ok">TAM</span>
            @elseif ($mulkiyet === 'HİSSELİ')
                <span class="tl-pill tl-pill-danger">HİSSELİ</span>
            @endif
            @if ($tasinmaz->kayit_tipi instanceof \App\Enums\KayitTipi)
                <span class="tl-pill {{ $tasinmaz->kayit_tipi === \App\Enums\KayitTipi::BosParsel ? 'tl-pill-mute' : ($tasinmaz->kayit_tipi === \App\Enums\KayitTipi::KatMulkiyetli ? 'tl-pill-ok' : 'tl-pill-info') }}"
                      title="{{ $tasinmaz->kayit_tipi->aciklama() }}">
                    {{ $tasinmaz->kayit_tipi->etiket() }}
                </span>
            @elseif ($binaVar)
                <span class="tl-pill tl-pill-info" title="Fiili durum: üzerinde yapı mevcut">Yapı Var</span>
            @endif
            @if ($tapuFiiliUyumsuz)
                <span class="tl-pill tl-pill-warn" title="{{ $uyumsuzlukMetin }}">⚠ Tapu ≠ Fiili</span>
            @endif
        </div>
        <small>
            {{ $tasinmaz->il->ad ?? '—' }} / {{ $tasinmaz->ilce->ad ?? '—' }} · {{ $tasinmaz->mahalle->ad ?? '—' }}
            · Ada <strong>{{ $tasinmaz->ada ?? '—' }}</strong> · Parsel <strong>{{ $tasinmaz->parsel ?? '—' }}</strong>
            @if ($tasinmaz->mudurluk?->ad)
                · {{ $tasinmaz->mudurluk->ad }}
            @endif
        </small>
    </div>
    <div class="page-hero-actions">
        @if ($tkgmUrl)
            <a href="{{ $tkgmUrl }}" target="_blank" rel="noopener noreferrer" class="btn-cancel td-hero-btn" title="TKGM Parsel Sorgu — yeni sekmede aç">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>
                TKGM
            </a>
        @endif
        <button type="button" class="btn-cancel td-hero-btn" onclick="window.print()" title="Sayfayı yazdır">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/></svg>
            Yazdır
        </button>
        @can('tasinmaz.duzenle')
            <a href="{{ $tasinmaz->rota('duzenle') }}" class="btn-submit td-hero-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5 a2.121 2.121 0 0 1 3 3 L7 19 l-4 1 1-4z"/></svg>
                Düzenle
            </a>
        @endcan
    </div>
</section>

{{-- Haritalar --}}
<div class="td-map-grid">
    <div class="td-card td-map-card">
        <div class="td-card-head">
            <span class="td-card-ikon td-card-ikon-navy">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4 L3 6 v14 l6-2 6 2 6-2 V4 l-6 2z"/><path d="M9 4 v14 M15 6 v14"/></svg>
            </span>
            <div class="td-card-baslik">
                <h3>Uzak Görünüm</h3>
                <small>Uydu · Geniş açı</small>
            </div>
        </div>
        <div class="td-map" id="td-map-uzak" data-rol="uzak"></div>
        @if (! $geometriVar)
            <p class="td-map-uyari">Bu taşınmaz için henüz koordinat kaydı bulunmuyor.</p>
        @endif
    </div>

    <div class="td-card td-map-card">
        <div class="td-card-head">
            <span class="td-card-ikon td-card-ikon-warn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12 h4 v-4 h4 v4 h4 v-4 h4 v4 h2 M3 12 v6 h18 v-6"/></svg>
            </span>
            <div class="td-card-baslik">
                <h3>İmar Durumu</h3>
                <small>{{ $imarAd ?: 'İmar bilgisi tanımlı değil' }}</small>
            </div>
        </div>
        <div class="td-map" id="td-map-imar" data-rol="imar"></div>
        @if (! $geometriVar)
            <p class="td-map-uyari">Konum yoksa imar katmanı gösterilemez.</p>
        @endif
    </div>
</div>

{{-- Bilgi kartları --}}
<div class="td-card-grid">
    {{-- Kimlik & Konum --}}
    <div class="td-card">
        <div class="td-card-head">
            <span class="td-card-ikon td-card-ikon-navy">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-7-7-7-13a7 7 0 0 1 14 0c0 6-7 13-7 13z"/><circle cx="12" cy="9" r="2.6"/></svg>
            </span>
            <div class="td-card-baslik">
                <h3>Kimlik &amp; Konum</h3>
                <small>Kadastral bilgi</small>
            </div>
        </div>
        <dl class="td-list">
            <div><dt>İl</dt><dd>{{ $tasinmaz->il->ad ?? '—' }}</dd></div>
            <div><dt>İlçe</dt><dd>{{ $tasinmaz->ilce->ad ?? '—' }}</dd></div>
            <div><dt>Mahalle</dt><dd>{{ $tasinmaz->mahalle->ad ?? '—' }}</dd></div>
            <div><dt>Müdürlük</dt><dd>{{ $tasinmaz->mudurluk->ad ?? '—' }}</dd></div>
            <div><dt>Ada / Parsel</dt><dd class="td-mono"><span class="tl-badge">{{ $tasinmaz->ada ?? '—' }}</span> / <span class="tl-badge">{{ $tasinmaz->parsel ?? '—' }}</span></dd></div>
            <div><dt>Tapu Yüzölçüm</dt><dd class="td-mono">{{ $tasinmaz->alan !== null ? number_format((float) $tasinmaz->alan, 2, ',', '.').' m²' : '—' }}</dd></div>
            <div><dt>Nitelik (Tapu)</dt><dd>
                {{ $tasinmaz->nitelik ?? '—' }}
                @if ($tapuFiiliUyumsuz)
                    <span class="tl-pill tl-pill-warn" title="{{ $uyumsuzlukMetin }}">⚠ Fiili ≠ Tapu</span>
                @endif
            </dd></div>
            <div><dt>Mülkiyet</dt><dd>
                @if ($mulkiyet === 'TAM')
                    <span class="tl-pill tl-pill-ok">TAM</span>
                @elseif ($mulkiyet === 'HİSSELİ')
                    <span class="tl-pill tl-pill-danger">HİSSELİ ({{ $aktifHisseler->count() }} aktif)</span>
                @else
                    <span class="tl-pill tl-pill-mute">—</span>
                @endif
            </dd></div>
        </dl>
    </div>

    {{-- Tapu --}}
    <div class="td-card">
        <div class="td-card-head">
            <span class="td-card-ikon td-card-ikon-info">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8 h8 M8 12 h8 M8 16 h5"/></svg>
            </span>
            <div class="td-card-baslik">
                <h3>Tapu Bilgisi</h3>
                <small>Sicil kayıt bilgileri</small>
            </div>
        </div>
        @if ($tapu)
            <dl class="td-list">
                <div><dt>TAKBİS Zemin No</dt><dd class="td-mono">{{ $tapu->takbis_zemin_no ?? '—' }}</dd></div>
                <div><dt>Cilt / Sayfa</dt><dd class="td-mono">{{ $tapu->cilt_no ?? '—' }} / {{ $tapu->sayfa_no ?? '—' }}</dd></div>
                <div><dt>Tapu Durumu</dt><dd>{{ $tapu->tapu_durumu ?? '—' }}</dd></div>
                <div><dt>Tapu Tarihi</dt><dd>{{ optional($tapu->tapu_tarihi)->format('d.m.Y') ?: '—' }}</dd></div>
                <div><dt>Tapu Kaydı</dt><dd>
                    @if ($tapu->pdfUrl())
                        <a href="{{ $tapu->pdfUrl() }}" target="_blank" rel="noopener" class="td-link">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                            PDF görüntüle
                        </a>
                    @else
                        <span class="tl-pill tl-pill-mute">Yok</span>
                    @endif
                </dd></div>
            </dl>
        @else
            <p class="td-bos">Bu taşınmaz için tapu kaydı girilmemiş.</p>
        @endif
    </div>

    {{-- İmar --}}
    <div class="td-card">
        <div class="td-card-head">
            <span class="td-card-ikon td-card-ikon-warn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 L2 8 l10 5 10-5z"/><path d="M2 13 l10 5 10-5"/><path d="M2 18 l10 5 10-5"/></svg>
            </span>
            <div class="td-card-baslik">
                <h3>İmar Durumu</h3>
                <small>Plan bilgileri</small>
            </div>
        </div>
        <dl class="td-list">
            <div><dt>İmar Durumu</dt><dd>{{ $imarAd ?: '—' }}</dd></div>
            <div><dt>Emsal</dt><dd class="td-mono">{{ $imar?->emsal !== null ? number_format((float) $imar->emsal, 2, ',', '.') : '—' }}</dd></div>
            <div><dt>Yençok / Yenaz</dt><dd class="td-mono">{{ $imar?->yenaz_yencok ?: '—' }}</dd></div>
            <div><dt>Geometri</dt><dd>
                @if ($geometriVar)
                    <span class="tl-pill tl-pill-ok">Var</span>
                @else
                    <span class="tl-pill tl-pill-mute">Yok</span>
                @endif
            </dd></div>
            @if ($imarNotu !== '')
                <div class="td-list-notlar"><dt>İmar Notu</dt><dd>{{ $imarNotu }}</dd></div>
            @endif
        </dl>
    </div>

</div>

{{-- Meclis Satış Kararı (varsa) --}}
@if ($meclisSatisKarari)
<div class="td-section">
    <div class="td-section-head">
        <div>
            <h3>Meclis Satış Kararı</h3>
            <small>Karar {{ $meclisSatisKarari->karar_no }} · {{ optional($meclisSatisKarari->karar_tarihi)->format('d.m.Y') ?: '—' }}</small>
        </div>
    </div>
    <dl class="td-list td-list-2col">
        <div><dt>Karar No</dt><dd class="td-mono"><span class="tl-badge">{{ $meclisSatisKarari->karar_no }}</span></dd></div>
        <div><dt>Karar Tarihi</dt><dd class="td-mono">{{ optional($meclisSatisKarari->karar_tarihi)->format('d.m.Y') ?: '—' }}</dd></div>
        <div><dt>Karar PDF</dt><dd>
            @if ($meclisSatisKarari->karar_pdf)
                <a href="{{ asset('storage/'.$meclisSatisKarari->karar_pdf) }}" target="_blank" rel="noopener" class="td-link">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                    PDF görüntüle
                </a>
            @else
                <span class="tl-pill tl-pill-mute">Yok</span>
            @endif
        </dd></div>
        <div><dt>Karar Tipi</dt><dd><span class="tl-pill tl-pill-warn">Satış</span></dd></div>
        @if (trim((string) $meclisSatisKarari->karar_ozeti) !== '')
            <div class="td-list-notlar"><dt>Karar Özeti</dt><dd>{{ $meclisSatisKarari->karar_ozeti }}</dd></div>
        @endif
    </dl>
</div>
@endif

{{-- Hisseler --}}
@php
    $tapuAlan = $tasinmaz->alan !== null ? (float) $tasinmaz->alan : 0.0;
    $aktifOran = $tapuAlan > 0 ? min(100, round(($aktifHisseToplam / $tapuAlan) * 100, 2)) : null;
@endphp
<div class="td-section">
    <div class="td-section-head td-section-head-hisse">
        <div>
            <h3>Hisseler</h3>
            <small>Toplam {{ $hisseler->count() }} kayıt · Aktif {{ $aktifHisseler->count() }}</small>
        </div>
        <div class="td-hisse-ozet">
            <div class="td-hisse-ozet-oge">
                <span class="td-hisse-ozet-etiket">Tapu Yüzölçüm</span>
                <span class="td-hisse-ozet-deger">{{ number_format($tapuAlan, 2, ',', '.') }} <em>m²</em></span>
            </div>
            <div class="td-hisse-ozet-oge td-hisse-ozet-vurgu">
                <span class="td-hisse-ozet-etiket">Aktif Hisse Toplamı</span>
                <span class="td-hisse-ozet-deger">{{ number_format($aktifHisseToplam, 2, ',', '.') }} <em>m²</em></span>
            </div>
            @if ($aktifOran !== null)
                <div class="td-hisse-ozet-oge">
                    <span class="td-hisse-ozet-etiket">Aktif Oran</span>
                    <span class="td-hisse-ozet-deger">%{{ number_format($aktifOran, 2, ',', '.') }}</span>
                </div>
            @endif
        </div>
    </div>
    @if ($hisseler->isEmpty())
        <p class="td-bos td-bos-block">Hisse kaydı bulunmuyor.</p>
    @else
        <div class="td-hisse-grid">
            @foreach ($hisseler as $h)
                @php
                    $pay = $h->hisse_pay !== null ? (float) $h->hisse_pay : null;
                    $payda = $h->hisse_payda !== null ? (float) $h->hisse_payda : null;
                    $oran = ($pay !== null && $payda !== null && $payda != 0.0)
                        ? round(($pay / $payda) * 100, 2) : null;
                    $m2 = $h->hisse_yuzolcum !== null ? (float) $h->hisse_yuzolcum : null;
                    $aktif = $h->hisse_durum === 'aktif';
                @endphp
                <div class="td-hisse-kart {{ $aktif ? 'is-aktif' : '' }}">
                    <div class="td-hisse-kart-ust">
                        <div class="td-hisse-kart-no">
                            <span class="td-hisse-kart-sira">#{{ $loop->iteration }}</span>
                            @if ($h->hisse_no)
                                <span class="td-hisse-kart-hn">Hisse No: <strong>{{ $h->hisse_no }}</strong></span>
                            @endif
                        </div>
                        <span class="tl-pill {{ $aktif ? 'tl-pill-ok' : ($h->hisse_durum === 'kapali' ? 'tl-pill-mute' : 'tl-pill-mute') }}">
                            {{ $aktif ? 'Aktif' : ($h->hisse_durum === 'kapali' ? 'Kapalı' : ($h->hisse_durum ?: '—')) }}
                        </span>
                    </div>

                    <div class="td-hisse-kart-m2">
                        @if ($m2 !== null)
                            <span class="td-hisse-kart-m2-deger">{{ number_format($m2, 2, ',', '.') }}</span>
                            <span class="td-hisse-kart-m2-birim">m²</span>
                        @else
                            <span class="td-hisse-kart-m2-deger">—</span>
                        @endif
                        @if ($oran !== null)
                            <span class="td-hisse-kart-oran">%{{ number_format($oran, 2, ',', '.') }}</span>
                        @endif
                    </div>

                    <dl class="td-hisse-kart-detay">
                        <div>
                            <dt>Pay / Payda</dt>
                            <dd class="td-mono">
                                {{ $pay !== null ? rtrim(rtrim(number_format($pay, 4, ',', '.'), '0'), ',') : '—' }}
                                / {{ $payda !== null ? rtrim(rtrim(number_format($payda, 4, ',', '.'), '0'), ',') : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt>Edinme</dt>
                            <dd>
                                {{ $h->edinme_sekli ?: '—' }}
                                @if ($h->edinme_tarihi) <span class="td-alt">· {{ $h->edinme_tarihi->format('d.m.Y') }}</span> @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Yevmiye No</dt>
                            <dd class="td-mono">{{ $h->yevmiye_no ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt>Rayiç Bedel</dt>
                            <dd class="td-mono">
                                {{ $h->rayic_bedel !== null ? number_format((float) $h->rayic_bedel, 2, ',', '.').' ₺' : '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            @endforeach
        </div>

        <div class="td-hisse-toplam">
            <span class="td-hisse-toplam-etiket">Aktif hisselerin toplam yüzölçümü</span>
            <div class="td-hisse-toplam-deger">
                <strong>{{ number_format($aktifHisseToplam, 2, ',', '.') }}</strong>
                <em>m²</em>
                @if ($aktifOran !== null)
                    <span class="td-hisse-toplam-oran">Tapu alanının %{{ number_format($aktifOran, 2, ',', '.') }}'ı</span>
                @endif
            </div>
        </div>
    @endif
</div>

{{-- Bağımsız Bölüm Bilgisi — kayıt tipine göre --}}
@php
    $kayitTipi = $tasinmaz->kayit_tipi;
    $coklu = $kayitTipi instanceof \App\Enums\KayitTipi
        ? $kayitTipi->bbnCoklu()
        : ($tasinmaz->yapilar->count() > 1);
    $y = $yapilar->first();
@endphp
@if ($kayitTipi instanceof \App\Enums\KayitTipi && $kayitTipi === \App\Enums\KayitTipi::BosParsel)
    {{-- Boş parsel — BBN bölümü gösterilmez --}}
@elseif ($coklu)
    <div class="td-section">
        <div class="td-section-head">
            <div>
                <h3>Bağımsız Bölümler <span class="tl-pill tl-pill-mute">Kat Mülkiyetsiz</span></h3>
                <small>{{ $yapilar->count() }} bağımsız bölüm · Tapuda "arsa", üzerinde bina var. Muhasebe ve durum bilgileri arsanın tümü için tek girişle yönetilir.</small>
            </div>
        </div>
        @if ($yapilar->isEmpty())
            <p class="td-bos td-bos-block">Bağımsız bölüm eklenmemiş.</p>
        @else
            <div class="tl-tablo-wrap">
                <table class="tl-tablo td-tablo">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Blok</th>
                            <th>Kat</th>
                            <th>B.B. No</th>
                            <th>Nitelik</th>
                            <th>Brüt m²</th>
                            <th>Net m²</th>
                            <th>Oda</th>
                            <th>Cephe</th>
                            <th>Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($yapilar as $yr)
                            <tr>
                                <td class="td-mono">{{ $loop->iteration }}</td>
                                <td class="td-mono">{{ $yr->blok_no ?: '—' }}</td>
                                <td class="td-mono">{{ $yr->kat_no ?: '—' }}</td>
                                <td class="td-mono">{{ $yr->bagimsiz_bolum_no ?: '—' }}</td>
                                <td>{{ $yr->nitelik ?: '—' }}</td>
                                <td class="td-mono">{{ $yr->brut_alan !== null ? number_format((float) $yr->brut_alan, 2, ',', '.') : '—' }}</td>
                                <td class="td-mono">{{ $yr->net_alan !== null ? number_format((float) $yr->net_alan, 2, ',', '.') : '—' }}</td>
                                <td class="td-mono">{{ $yr->oda_sayisi ?: '—' }}</td>
                                <td>{{ $yr->cephe ?: '—' }}</td>
                                <td class="td-mono">{{ $yr->aciklama ? \Illuminate\Support\Str::limit($yr->aciklama, 60) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@else
    <div class="td-section">
        <div class="td-section-head">
            <div>
                <h3>Bağımsız Bölüm Bilgisi <span class="tl-pill tl-pill-ok">Kat Mülkiyetli</span></h3>
                <small>Fiziksel/kadastral bilgiler. Kendi TAKBİS zemin nosuyla ayrı tapu kaydı var.</small>
            </div>
        </div>
        @if (! $y)
            <p class="td-bos td-bos-block">Bu taşınmaz için bağımsız bölüm bilgisi girilmemiş.</p>
        @else
            <dl class="td-list td-list-2col">
                <div><dt>Blok / Kat / B.B. No</dt><dd class="td-mono">
                    <span class="tl-badge">{{ $y->blok_no ?: '—' }}</span> /
                    <span class="tl-badge">{{ $y->kat_no ?: '—' }}</span> /
                    <span class="tl-badge">{{ $y->bagimsiz_bolum_no ?: '—' }}</span>
                </dd></div>
                <div><dt>Bölüm Niteliği</dt><dd>{{ $y->nitelik ?: '—' }}</dd></div>
                <div><dt>Brüt Alan</dt><dd class="td-mono">{{ $y->brut_alan !== null ? number_format((float) $y->brut_alan, 2, ',', '.').' m²' : '—' }}</dd></div>
                <div><dt>Net Alan</dt><dd class="td-mono">{{ $y->net_alan !== null ? number_format((float) $y->net_alan, 2, ',', '.').' m²' : '—' }}</dd></div>
                <div><dt>Oda</dt><dd class="td-mono">{{ $y->oda_sayisi ?: '—' }}</dd></div>
                <div><dt>Cephe</dt><dd>{{ $y->cephe ?: '—' }}</dd></div>
                @if ($y->aciklama)
                    <div class="td-list-notlar"><dt>Bölüm Açıklaması</dt><dd>{{ $y->aciklama }}</dd></div>
                @endif
            </dl>
        @endif
    </div>
@endif

<script src="https://cdn.jsdelivr.net/npm/maplibre-gl@3.6.2/dist/maplibre-gl.js" crossorigin="anonymous"></script>
<script>
(function () {
    const koordinat = @json($koordinat?->koordinat);
    const lat = @json($koordinat?->lat !== null ? (float) $koordinat->lat : null);
    const lng = @json($koordinat?->lng !== null ? (float) $koordinat->lng : null);
    const adaNo = @json($tasinmaz->ada ?? '');
    const parselNo = @json($tasinmaz->parsel ?? '');

    // Poligon halkasını GeoJSON polygon veya nokta olarak normalize et
    function normalize() {
        if (koordinat && typeof koordinat === 'object' && koordinat.type && koordinat.coordinates) {
            return koordinat;
        }
        if (Array.isArray(koordinat) && koordinat.length > 0 && Array.isArray(koordinat[0]) && typeof koordinat[0][0] === 'number') {
            const halka = koordinat.slice();
            const ilk = halka[0], son = halka[halka.length - 1];
            if (ilk[0] !== son[0] || ilk[1] !== son[1]) halka.push(ilk);
            return { type: 'Polygon', coordinates: [halka] };
        }
        if (lat !== null && lng !== null) {
            return { type: 'Point', coordinates: [lng, lat] };
        }
        return null;
    }

    // Ankara BB · Uygulama İmar Planı (uipSade)
    const IMAR_TILE = 'https://planaski.ankara.bel.tr/webgis/rest/services/mobilServis/uipSade/MapServer/export'
        + '?bbox={bbox-epsg-3857}&bboxSR=3857&imageSR=3857'
        + '&size=256,256&dpi=96&format=png32&transparent=true&f=image';


    function haritaOlustur(container, geom, opts) {
        const zoom = opts.zoom || 16;

        // Nokta merkezi hesabı (poligon için centroid tahmini)
        let merkez = [32.85411, 39.92077]; // Ankara varsayılan
        if (geom) {
            if (geom.type === 'Point') {
                merkez = geom.coordinates;
            } else if (geom.type === 'Polygon') {
                const halka = geom.coordinates[0];
                let sx = 0, sy = 0;
                halka.forEach(p => { sx += p[0]; sy += p[1]; });
                merkez = [sx / halka.length, sy / halka.length];
            }
        }

        // Stil kaynak/katmanları — imar altlığı istenirse eklenir
        const kaynaklar = {
            'uydu': {
                type: 'raster',
                tiles: ['https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}'],
                tileSize: 256,
                attribution: '© Google'
            }
        };
        const katmanlar = [
            { id: 'uydu-katman', type: 'raster', source: 'uydu' }
        ];
        if (opts.imarAltlik) {
            kaynaklar['imar'] = {
                type: 'raster',
                tiles: [IMAR_TILE],
                tileSize: 256,
                minzoom: 0,
                maxzoom: 22,
                attribution: 'İmar Planı · Ankara BB'
            };
            katmanlar.push({
                id: 'imar-katman',
                type: 'raster',
                source: 'imar',
                minzoom: 0,
                maxzoom: 24,
                paint: { 'raster-opacity': 0.7 }
            });
        }

        const harita = new maplibregl.Map({
            container: container,
            style: { version: 8, sources: kaynaklar, layers: katmanlar },
            center: merkez,
            zoom: zoom,
            attributionControl: false
        });
        harita.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

        if (!geom) return harita;

        harita.on('load', function () {
            if (geom.type === 'Point') {
                new maplibregl.Marker({ color: '#0ea5e9' })
                    .setLngLat(geom.coordinates)
                    .addTo(harita);
                harita.flyTo({ center: geom.coordinates, zoom: opts.zoom || 17 });
                return;
            }

            harita.addSource('parsel', {
                type: 'geojson',
                data: { type: 'Feature', geometry: geom, properties: {} }
            });
            harita.addLayer({
                id: 'parsel-fill',
                type: 'fill',
                source: 'parsel',
                paint: { 'fill-color': opts.fill || 'rgba(216,188,140,0.35)' }
            });
            harita.addLayer({
                id: 'parsel-cizgi',
                type: 'line',
                source: 'parsel',
                paint: { 'line-color': opts.stroke || '#ffffff', 'line-width': 2.5 }
            });

            // Ada/Parsel etiketi — sadece imar haritasında parselin merkezine
            if (opts.parselEtiket && (adaNo || parselNo) && geom.type === 'Polygon') {
                const halka = geom.coordinates[0];
                let sx = 0, sy = 0;
                halka.forEach(p => { sx += p[0]; sy += p[1]; });
                const merkezNokta = [sx / halka.length, sy / halka.length];

                const etiket = document.createElement('div');
                etiket.className = 'td-parsel-etiket';
                etiket.innerHTML =
                    '<span class="td-parsel-etiket-satir"><em>Ada</em> ' + (adaNo || '—') + '</span>' +
                    '<span class="td-parsel-etiket-satir"><em>Parsel</em> ' + (parselNo || '—') + '</span>';
                new maplibregl.Marker({ element: etiket, anchor: 'center' })
                    .setLngLat(merkezNokta)
                    .addTo(harita);
            }

            const halka = geom.coordinates[0];
            const bounds = halka.reduce(
                (b, c) => b.extend(c),
                new maplibregl.LngLatBounds(halka[0], halka[0])
            );
            harita.fitBounds(bounds, {
                padding: opts.padding || 40,
                maxZoom: opts.maxZoom || 18,
                animate: false
            });
        });

        return harita;
    }

    const geom = normalize();

    haritaOlustur('td-map-uzak', geom, {
        zoom: 14,
        padding: 160,
        maxZoom: 16,
        fill: 'rgba(59,130,246,0.28)',
        stroke: '#ffffff'
    });

    haritaOlustur('td-map-imar', geom, {
        zoom: 18,
        padding: 30,
        maxZoom: 20,
        fill: 'rgba(216,188,140,0.35)',
        stroke: '#0e1726',
        imarAltlik: true,
        parselEtiket: true
    });
})();
</script>
@endsection
