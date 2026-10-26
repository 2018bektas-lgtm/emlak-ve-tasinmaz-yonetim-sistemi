@php
    $sayi = fn ($v, int $b = 2) => $v === null ? null : number_format((float) $v, $b, ',', '.');
    $tarih = fn ($d) => $d ? $d->format('d.m.Y') : null;

    $koordinat = $tasinmaz->koordinat;
    $imar = $tasinmaz->imar;
    $imarAd = $imar?->imarDurumu?->ad;
    $tapu = $tasinmaz->tapu;
    $kategori = $tasinmaz->kategori;
    $ekbilgi = $tasinmaz->ekbilgi;
    $yapilar = $tasinmaz->yapilar;
    $hisseler = $tasinmaz->hisseler;
    $aktifHisseler = $hisseler->where('hisse_durum', 'aktif');
    $aktifHisseToplam = (float) $aktifHisseler->sum('hisse_yuzolcum');
    $tapuAlan = $tasinmaz->alan !== null ? (float) $tasinmaz->alan : 0.0;
    $aktifOran = $tapuAlan > 0 ? min(100, round($aktifHisseToplam / $tapuAlan * 100, 2)) : null;
    $resimler = $tasinmaz->resimler->sortByDesc('kapak_mi')->values();
    $mulkiyet = $tasinmaz->mulkiyet_durumu;
    $satisOzet = $tasinmaz->satisOzeti();
    $kayitTipi = $tasinmaz->kayit_tipi instanceof \App\Enums\KayitTipi ? $tasinmaz->kayit_tipi : null;

    $bayrak = fn (string $alan) => ($ekbilgi && (bool) $ekbilgi->{$alan}) || $yapilar->contains(fn ($y) => (bool) $y->{$alan});
    $binaVar = (bool) $tasinmaz->uzeri_bina_var_mi || $yapilar->isNotEmpty();
    $isgalEtiket = match ($ekbilgi?->isgal_durumu) {
        'yok', 'bos' => 'Yok',
        'var', 'isgal' => 'İşgalli',
        'kismi', 'kismen' => 'Kısmen işgalli',
        default => $ekbilgi?->isgal_durumu ?: '—',
    };

    $lojmanlar = $tasinmaz->lojmanlar;
    $ecrimisiller = $tasinmaz->ecrimisiller;
    $suruyorIsgal = $ecrimisiller->filter(fn ($e) => $e->durum() === 'devam-eden');
    $isgalVar = in_array($ekbilgi?->isgal_durumu, ['var', 'kismi', 'isgal', 'kismen'], true) || $suruyorIsgal->isNotEmpty();
    $degerlemeler = $tasinmaz->degerlemeler->sortBy('rapor_tarihi')->values();
    $sonDeg = $degerlemeler->last();
    $hisseDosyalari = $tasinmaz->hisseBasvurulari->groupBy('grup_no');
    $meclisKararlari = $tasinmaz->meclisKararlari->sortByDesc('karar_tarihi')->values();
    $alansizDosyalar = $kiraDosyalari->reject(fn ($d) => $kiraAlanlari->contains('kira_dosya_id', $d->id));

    $adaParsel = 'Ada '.($tasinmaz->ada ?? '—').' / Parsel '.($tasinmaz->parsel ?? '—');
    $konumMetni = collect([$tasinmaz->il?->ad, $tasinmaz->ilce?->ad, $tasinmaz->mahalle?->ad ? $tasinmaz->mahalle->ad.' Mah.' : null])->filter()->implode(' · ');
    $raporNo = 'TR-'.str_pad((string) $tasinmaz->id, 6, '0', STR_PAD_LEFT).'-'.$olusturma->format('ymd');

    $sayfaSayisi = 9 + ($resimler->isNotEmpty() ? 1 : 0);
    $sayfa = 1;
    $sayfaNo = function () use (&$sayfa, $sayfaSayisi) {
        return str_pad((string) ++$sayfa, 2, '0', STR_PAD_LEFT).' / '.str_pad((string) $sayfaSayisi, 2, '0', STR_PAD_LEFT);
    };
    $kullanimlar = [
        ['Lojman', $lojmanlar->isNotEmpty()],
        ['Tahsis', $bayrak('tahsis_var')],
        ['Kira', $bayrak('kira_var') || $kiraAlanlari->isNotEmpty() || $alansizDosyalar->isNotEmpty()],
        ['Üst Hakkı', $bayrak('ust_hakki_var')],
        ['Meclis Kararı', $bayrak('meclis_satis_karari_var') || $meclisKararlari->isNotEmpty()],
        ['Üzerinde Yapı', $binaVar],
        ['İşgal', $isgalVar],
    ];
@endphp
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Taşınmaz Raporu · {{ $adaParsel }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @if ($pdfModu ?? false)
        {{-- Sunucuda PDF'e basılırken sayfa dosyadan açılır; stil gömülü gelir. --}}
        <style>{!! file_get_contents(public_path('css/tasinmaz-rapor.css')) !!}</style>
    @else
        <link rel="stylesheet" href="{{ asset('css/tasinmaz-rapor.css') }}?v=19">
    @endif
</head>
<body>

@unless ($pdfModu ?? false)
{{-- Ekran araç çubuğu (baskıda gizli) --}}
<div class="arac" id="arac">
    <div class="arac-sol">
        <span class="arac-logo">ETYS</span>
        <div>
            <strong>Taşınmaz Raporu</strong>
            <small>{{ $adaParsel }} · {{ $sayfaSayisi }} sayfa</small>
        </div>
    </div>
    <div class="arac-durum" id="arac-durum">
        <span class="arac-yukleme"><i style="width:0%" id="arac-cubuk"></i></span>
        <span id="arac-metin">Görseller hazırlanıyor…</span>
    </div>
    <div class="arac-sag">
        <a href="{{ $tasinmaz->rota('detay') }}" class="arac-btn">Detay sayfası</a>
        @if ($pdfIndirilir ?? false)
            <a href="{{ $tasinmaz->rota('rapor-pdf') }}" class="arac-btn is-ana" id="arac-indir" data-pdf-indir>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
                <span>PDF olarak indir</span>
            </a>
        @else
            <button type="button" class="arac-btn is-ana" id="arac-indir" onclick="window.print()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
                Yazdır / PDF
            </button>
        @endif
    </div>
</div>
@unless ($pdfIndirilir ?? false)
    <p class="arac-ipucu">Yazdırma penceresinde hedef olarak <b>“PDF olarak kaydet”</b> seçin. Kenar boşluğu: <b>Yok</b>, <b>Arka plan grafikleri</b> açık.</p>
@endunless
@endunless

<main class="belge">

{{-- ============ 1 · KAPAK ============ --}}
<section class="sayfa kapak">
    @include('panel.tasinmazlar._rapor-harita', ['g' => $gorseller['kapak'], 'sinif' => 'kapak-harita', 'olcekGizle' => true])
    <div class="kapak-ortu"></div>

    <div class="kapak-icerik">
        <div class="kapak-marka">
            <span class="marka-isaret">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9v12h14V9"/><path d="M9 21v-6h6v6"/></svg>
            </span>
            <div>
                <strong>Emlak ve Taşınmaz Yönetim Sistemi</strong>
                <small>Taşınmaz Envanter Raporu</small>
            </div>
        </div>

        <div class="kapak-ana">
            <span class="kapak-ust">Taşınmaz Raporu</span>
            <h1 class="kapak-baslik">
                <span><small>Ada</small>{{ $tasinmaz->ada ?? '—' }}</span>
                <i></i>
                <span><small>Parsel</small>{{ $tasinmaz->parsel ?? '—' }}</span>
            </h1>
            <p class="kapak-konum">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-7-7-7-13a7 7 0 0 1 14 0c0 6-7 13-7 13z"/><circle cx="12" cy="9" r="2.6"/></svg>
                {{ $konumMetni ?: 'Konum bilgisi yok' }}
            </p>
            <div class="kapak-cipler">
                <span class="cip is-{{ $mulkiyet === 'HİSSELİ' ? 'kirmizi' : 'yesil' }}">{{ $mulkiyet === 'HİSSELİ' ? 'Hisseli mülkiyet' : ($mulkiyet === 'TAM' ? 'Tam mülkiyet' : 'Mülkiyet belirsiz') }}</span>
                <span class="cip">{{ $satisOzet->etiket() }}</span>
                @if ($kayitTipi)<span class="cip">{{ $kayitTipi->etiket() }}</span>@endif
                @if ($isgalVar)<span class="cip is-turuncu">İşgal var</span>@endif
            </div>
        </div>

        <div class="kapak-rakamlar">
            <div><small>Yüzölçüm</small><b>{{ $tasinmaz->alan !== null ? $sayi($tasinmaz->alan) : '—' }}<em>m²</em></b></div>
            <div><small>İmar Durumu</small><b class="is-metin {{ mb_strlen((string) $imarAd) > 40 ? 'is-uzun' : '' }}">{{ $imarAd ?: '—' }}</b></div>
            <div><small>Son Değerleme</small><b>{{ $sonDeg ? $sayi($sonDeg->toplam_bedel, 0) : '—' }}@if ($sonDeg)<em>TL</em>@endif</b></div>
        </div>

        <div class="kapak-alt">
            <div><small>Rapor No</small><b>{{ $raporNo }}</b></div>
            <div><small>Düzenlenme</small><b>{{ $olusturma->format('d.m.Y H:i') }}</b></div>
            <div><small>Hazırlayan</small><b>{{ trim(($hazirlayan?->ad ?? '').' '.($hazirlayan?->soyad ?? '')) ?: ($hazirlayan?->kullanici_adi ?? '—') }}</b></div>
            <div><small>Kayıt</small><b>#{{ $tasinmaz->id }}</b></div>
        </div>
    </div>
</section>

{{-- ============ 2 · GENEL BAKIŞ ============ --}}
<section class="sayfa ic">
    @include('panel.tasinmazlar._rapor-yan', [
        'no' => '01', 'baslik' => 'Genel Bakış', 'alt' => 'Kimlik, tapu ve konum bilgileri',
        'vurgular' => [
            ['Tapu Yüzölçümü', $tasinmaz->alan !== null ? $sayi($tasinmaz->alan) : '—', 'm²'],
            ['Son Değerleme', $sonDeg ? $sayi($sonDeg->toplam_bedel, 0) : '—', $sonDeg ? 'TL' : null],
            ['Birim Değer', $sonDeg?->birim_fiyat ? $sayi($sonDeg->birim_fiyat, 0) : '—', $sonDeg?->birim_fiyat ? 'TL/m²' : null],
            ['Mülkiyet', $mulkiyet === 'TAM' ? 'Tam' : ($mulkiyet === 'HİSSELİ' ? 'Hisseli' : '—'), null],
        ],
        'kunye' => $adaParsel, 'sayfaNo' => $sayfaNo(),
    ])

    <div class="icerik genel">
        <div class="genel-sol">
            <div class="kart kart-kimlik">
                <div class="kart-bas"><span class="ikon" style="--renk:#1e3a8a"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-7-7-7-13a7 7 0 0 1 14 0c0 6-7 13-7 13z"/><circle cx="12" cy="9" r="2.6"/></svg></span><h3>Kimlik &amp; Konum</h3></div>
                <div class="kimlik-buyuk">
                    <div><small>Ada</small><b>{{ $tasinmaz->ada ?? '—' }}</b></div>
                    <div><small>Parsel</small><b>{{ $tasinmaz->parsel ?? '—' }}</b></div>
                    <div><small>Nitelik</small><b class="is-metin">{{ $tasinmaz->nitelik ?: '—' }}</b></div>
                </div>
                <dl class="izgara">
                    <div><dt>İl</dt><dd>{{ $tasinmaz->il?->ad ?? '—' }}</dd></div>
                    <div><dt>İlçe</dt><dd>{{ $tasinmaz->ilce?->ad ?? '—' }}</dd></div>
                    <div><dt>Mahalle</dt><dd>{{ $tasinmaz->mahalle?->ad ?? '—' }}</dd></div>
                    <div><dt>TKGM No</dt><dd>{{ $tasinmaz->mahalle?->tkgm_id ?? '—' }}</dd></div>
                    <div><dt>Kayıt Tipi</dt><dd>{{ $kayitTipi?->etiket() ?? '—' }}</dd></div>
                    <div><dt>Satış Durumu</dt><dd>{{ $satisOzet->etiket() }}</dd></div>
                </dl>
            </div>

            {{-- Tapu ve muhasebe tek geniş kartta: ayrı ayrı dar kalıp etiketleri bölüyordu --}}
            <div class="kart tapu-muhasebe">
                <div class="tm-kolon">
                    <div class="kart-bas"><span class="ikon" style="--renk:#2563eb"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span><h3>Tapu</h3></div>
                    @if ($tapu)
                        <dl class="liste-dl">
                            <div><dt>TAKBİS Zemin No</dt><dd>{{ $tapu->takbis_zemin_no ?? '—' }}</dd></div>
                            <div><dt>Cilt / Sayfa</dt><dd>{{ $tapu->cilt_no ?? '—' }} / {{ $tapu->sayfa_no ?? '—' }}</dd></div>
                            <div><dt>Tapu Durumu</dt><dd>{{ $tapu->tapu_durumu ?? '—' }}</dd></div>
                            <div><dt>Tapu Tarihi</dt><dd>{{ $tarih($tapu->tapu_tarihi) ?? '—' }}</dd></div>
                        </dl>
                    @else
                        <p class="bos">Tapu kaydı girilmemiş.</p>
                    @endif
                </div>
                <div class="tm-kolon">
                    <div class="kart-bas"><span class="ikon" style="--renk:#475569"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7" cy="7" r="1.5"/></svg></span><h3>Muhasebe &amp; Kategori</h3></div>
                    <dl class="liste-dl">
                        <div><dt>Muhasebe Kaydı</dt><dd>{{ $kategori?->muhasebeKayit ? trim(($kategori->muhasebeKayit->kod ? $kategori->muhasebeKayit->kod.' · ' : '').$kategori->muhasebeKayit->ad) : '—' }}</dd></div>
                        <div><dt>Kayıt Türü</dt><dd>{{ $kategori?->kayitTuru ? trim(($kategori->kayitTuru->kod ? $kategori->kayitTuru->kod.' · ' : '').$kategori->kayitTuru->ad) : '—' }}</dd></div>
                        <div><dt>Mevcut Kullanım</dt><dd>{{ $kategori?->mevcut_kullanim_sekli ?: '—' }}</dd></div>
                    </dl>
                </div>
            </div>

            <div class="kart kullanim">
                <div class="kart-bas"><span class="ikon" style="--renk:#16a34a"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-5 7 5v14"/><path d="M9 21v-6h6v6"/></svg></span><h3>Kullanım Durumu</h3><span class="kart-not">İşgal: {{ $isgalEtiket }}</span></div>
                <div class="tikler">
                    @foreach ($kullanimlar as [$ad, $var])
                        <span class="tik {{ $var ? ($ad === 'İşgal' ? 'is-uyari' : 'is-var') : '' }}">{{ $ad }}</span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Sağ sütunu baştan sona kaplayan dikey konum haritası --}}
        <div class="konum-kart">
            @include('panel.tasinmazlar._rapor-harita', ['g' => $gorseller['konum'], 'sinif' => 'tam', 'noktaMi' => true])
            <div class="konum-etiket">
                <b>{{ $tasinmaz->mahalle?->ad ? $tasinmaz->mahalle->ad.' Mah.' : '—' }}</b>
                <span>{{ collect([$tasinmaz->ilce?->ad, $tasinmaz->il?->ad])->filter()->implode(' / ') }}</span>
                @if ($koordinat && $koordinat->lat !== null)
                    <small>{{ number_format((float) $koordinat->lat, 6, '.', '') }}° K &nbsp; {{ number_format((float) $koordinat->lng, 6, '.', '') }}° D</small>
                @endif
            </div>
        </div>
    </div>
</section>

{{-- ============ 3 · UYDU GÖRÜNTÜLERİ ============ --}}
<section class="sayfa ic">
    @include('panel.tasinmazlar._rapor-yan', [
        'no' => '02', 'baslik' => 'Uydu Görüntüleri', 'alt' => 'Parsel ve yakın çevresi',
        'vurgular' => [
            ['Enlem', $koordinat && $koordinat->lat !== null ? number_format((float) $koordinat->lat, 6, '.', '').'°' : '—', null],
            ['Boylam', $koordinat && $koordinat->lng !== null ? number_format((float) $koordinat->lng, 6, '.', '').'°' : '—', null],
            ['Yüzölçüm', $tasinmaz->alan !== null ? $sayi($tasinmaz->alan) : '—', 'm²'],
            ['Geometri', $koordinat ? ($noktaMi ? 'Nokta' : 'Poligon') : 'Yok', null],
        ],
        'kunye' => $adaParsel, 'sayfaNo' => $sayfaNo(),
    ])

    <div class="icerik uydu">
        <figure class="uydu-ana">
            @include('panel.tasinmazlar._rapor-harita', ['g' => $gorseller['yakin'], 'sinif' => 'tam'])
            <figcaption class="cam-etiket"><b>Yakın Görünüm</b><span>Parsel sınırı turuncu ile işaretlidir</span></figcaption>
        </figure>
        <div class="uydu-yan">
            <figure class="uydu-cevre">
                @include('panel.tasinmazlar._rapor-harita', ['g' => $gorseller['cevre'], 'sinif' => 'tam'])
                <figcaption class="cam-etiket"><b>Çevre</b><span>Yerleşim bağlamı</span></figcaption>
            </figure>
            <div class="kart uydu-bilgi">
                <span class="ust-yazi">Konum</span>
                <b class="buyuk">{{ $tasinmaz->mahalle?->ad ? $tasinmaz->mahalle->ad.' Mah.' : '—' }}</b>
                <span class="gri">{{ collect([$tasinmaz->ilce?->ad, $tasinmaz->il?->ad])->filter()->implode(' / ') }}</span>
                <hr>
                <dl class="liste-dl">
                    <div><dt>Ada / Parsel</dt><dd>{{ $tasinmaz->ada ?? '—' }} / {{ $tasinmaz->parsel ?? '—' }}</dd></div>
                    <div><dt>Yüzölçüm</dt><dd>{{ $tasinmaz->alan !== null ? $sayi($tasinmaz->alan).' m²' : '—' }}</dd></div>
                    <div><dt>Merkeze Uzaklık</dt><dd>{{ $cevre['merkezUzaklik'] ?? null ? $sayi($cevre['merkezUzaklik'] / 1000, 1).' km (Kızılay)' : '—' }}</dd></div>
                    <div><dt>Rakım</dt><dd>{{ isset($cevre['rakim']) ? $sayi($cevre['rakim'], 0).' m' : '—' }}</dd></div>
                    <div><dt>Görüntü</dt><dd>Google Uydu · {{ $olusturma->format('d.m.Y') }}</dd></div>
                </dl>
            </div>
        </div>
    </div>
</section>

{{-- ============ 4 · İMAR DURUMU ============ --}}
@php
    // Resmî e-İmar değerleri öncelikli; yoksa sistemdeki kayıt kullanılır.
    $resmi = $saha['eimar']['veri'] ?? null;
    $eimarDurum = $saha['eimar']['durum'] ?? null;
    $sayiCoz = fn ($s) => is_numeric($n = str_replace(',', '.', (string) $s)) ? (float) $n : null;
    $kaks = $resmi ? $sayiCoz($resmi['kaks']) : null;
    $kaksKaynak = $kaks !== null ? 'resmî' : 'kayıt';
    $kaks ??= $imar?->emsal !== null ? (float) $imar->emsal : null;
    $insaat = $kaks !== null && $tapuAlan > 0 ? $tapuAlan * $kaks : null;
    $buyukHarf = fn ($s) => mb_strtoupper(strtr((string) $s, ['i' => 'İ', 'ı' => 'I']), 'UTF-8');
    $imarUyusmuyor = $resmi && $resmi['kullanim'] && $imarAd && $buyukHarf($resmi['kullanim']) !== $buyukHarf($imarAd);
    // "2 kat" ve "2 Kat" Hmax'i aynı bilgiyse bir kez yazılır.
    $katHmax = $resmi
        ? (collect([
            $resmi['kat_adedi'] ? $resmi['kat_adedi'].' kat' : null,
            $resmi['hmax'] && $buyukHarf($resmi['hmax']) !== $buyukHarf($resmi['kat_adedi'].' kat') ? $resmi['hmax'] : null,
        ])->filter()->join(' · ') ?: '—')
        : ($imar?->yenaz_yencok ?: '—');
@endphp
<section class="sayfa ic">
    @include('panel.tasinmazlar._rapor-yan', [
        'no' => '03', 'baslik' => 'İmar Durumu', 'alt' => 'Yürürlükteki uygulama imar planı',
        'vurgular' => [
            ['KAKS / Emsal'.($kaksKaynak === 'resmî' ? '' : ' (kayıt)'), $kaks !== null ? $sayi($kaks) : '—', null],
            ['TAKS', $resmi['taks'] ?? '—', null],
            ['Kat / Hmax', $katHmax, null],
            ['Olası İnşaat Alanı', $insaat !== null ? $sayi($insaat, 0) : '—', $insaat !== null ? 'm²' : null],
        ],
        'kunye' => $adaParsel, 'sayfaNo' => $sayfaNo(),
    ])

    <div class="icerik tasma">
        @include('panel.tasinmazlar._rapor-harita', ['g' => $gorseller['imar'], 'sinif' => 'tam imar-tam'])
        <div class="imar-kart">
            <span class="ust-yazi">{{ $resmi ? 'Yürürlükteki Plan · e-İmar' : 'Plan Kararı · Sistem Kaydı' }}</span>
            <b class="imar-karar">{{ $resmi['kullanim'] ?? ($imarAd ?: 'İmar bilgisi tanımlı değil') }}</b>
            @if ($resmi && $resmi['alt_kullanim'])<span class="imar-alt">{{ $resmi['alt_kullanim'] }}</span>@endif

            @if ($imarUyusmuyor)
                <p class="imar-uyari">Sistem kaydı <b>{{ $imarAd }}</b> olarak görünüyor; yürürlükteki planla uyuşmuyor. Kaydın güncellenmesi önerilir.</p>
            @elseif (! $resmi)
                <p class="imar-uyari is-bilgi">{{ match ($eimarDurum) {
                    404 => 'Bu noktada yürürlükte uygulama imar planı yok (plan dışı alan); sistem kaydı gösteriliyor.',
                    422 => 'Parsel Ankara BB e-İmar kapsamı dışında; sistem kaydı gösteriliyor.',
                    default => 'e-İmar servisine ulaşılamadı; sistem kaydı gösteriliyor.',
                } }}</p>
            @endif

            @if ($resmi)
                <dl class="imar-param">
                    <div><dt>KAKS</dt><dd>{{ $resmi['kaks'] ?? '—' }}</dd></div>
                    <div><dt>TAKS</dt><dd>{{ $resmi['taks'] ?? '—' }}</dd></div>
                    <div><dt>Kat</dt><dd>{{ $resmi['kat_adedi'] ?? '—' }}</dd></div>
                    <div><dt>Hmax</dt><dd>{{ $resmi['hmax'] ?? '—' }}</dd></div>
                    <div class="genis"><dt>Yapı düzeni</dt><dd>{{ $resmi['yapi_duzeni'] ?? '—' }}</dd></div>
                    <div class="genis"><dt>Çekme (ön/yan/arka)</dt><dd>{{ $resmi['cekme'] ?? '—' }}</dd></div>
                </dl>
            @elseif (trim((string) ($imar->imar_notu ?? '')) !== '')
                <p>{{ \Illuminate\Support\Str::limit($imar->imar_notu, 260) }}</p>
            @endif

            <small>Kaynak: Ankara BB e-İmar ve uygulama imar planı. Olası inşaat alanı = tapu alanı × KAKS; çekme mesafeleri ve plan notları dahil değildir. Resmî imar durum belgesi belediyeden alınmalıdır.</small>
        </div>
    </div>
</section>

{{-- ============ 5 · ÇEVRESEL ANALİZ ============ --}}
@php
    $cevreTamam = $cevre && ! isset($cevre['hata']);
    $mesafeYazi = fn ($m) => $m >= 1000 ? number_format($m / 1000, 1, ',', '.').' km' : $m.' m';
    $renkPuan = fn ($p) => $p === null ? '#a3adbd' : ($p >= 75 ? '#16a34a' : ($p >= 55 ? '#65a30d' : ($p >= 35 ? '#d97706' : '#dc2626')));
    $ga = $gorseller['analiz'];
@endphp
<section class="sayfa ic">
    @include('panel.tasinmazlar._rapor-yan', [
        'no' => '04', 'baslik' => 'Çevresel Analiz', 'alt' => 'Olanaklara erişim · 1 km yarıçap',
        'vurgular' => [
            ['Konum Endeksi', $cevreTamam ? (string) $cevre['genel'] : '—', $cevreTamam ? '/ 100' : null],
            ['Merkeze Uzaklık', $cevre ? $mesafeYazi((int) round($cevre['merkezUzaklik'] ?? 0)) : '—', null],
            ['Rakım', $cevreTamam && $cevre['rakim'] !== null ? $sayi($cevre['rakim'], 0) : '—', $cevreTamam && $cevre['rakim'] !== null ? 'm' : null],
            ['1 km\'de Taşınmazımız', $cevre ? $sayi($cevre['portfoy'] ?? 0, 0) : '—', null],
        ],
        'kunye' => $adaParsel, 'sayfaNo' => $sayfaNo(),
    ])

    @if (! $cevreTamam)
        <div class="icerik cevre-yok">
            <div class="kart">
                <span class="ust-yazi">Çevresel Analiz</span>
                <b class="buyuk">{{ $cevre['hata'] ?? 'Konum kaydı olmadığı için çevre analizi yapılamadı.' }}</b>
                <p class="gri">Analiz, taşınmazın koordinatı çevresindeki 1 km'lik alanda Ankara BB ve OpenStreetMap verileriyle yapılır.</p>
            </div>
        </div>
    @else
        <div class="icerik cevre">
            <div class="cevre-sol">
                <figure class="cevre-harita-kap">
                    <div class="rh tam cevre-harita">
                        @foreach ($ga['katmanlar'] as $ad => $karolar)
                            <div class="rh-katman rh-{{ $ad }}">
                                @foreach ($karolar as $k)
                                    <img src="{{ $k['url'] }}" alt="" data-rapor-gorsel onerror="this.classList.add('is-hata')"
                                         style="left:{{ $k['sol'] }}%;top:{{ $k['ust'] }}%;width:{{ $k['en'] }}%;height:{{ $k['boy'] }}%">
                                @endforeach
                            </div>
                        @endforeach
                        <svg class="rh-cizim" viewBox="0 0 {{ $ga['w'] }} {{ $ga['h'] }}" preserveAspectRatio="none" aria-hidden="true">
                            @php [$mx, $my] = $ga['merkez']; @endphp
                            <circle cx="{{ $mx }}" cy="{{ $my }}" r="{{ $ga['halkalar'][1000] }}" class="halka-1000"/>
                            <circle cx="{{ $mx }}" cy="{{ $my }}" r="{{ $ga['halkalar'][500] }}" class="halka-500"/>
                            {{-- Etiketler halkaların sağ kenarında: üstte endeks kartının altında kalıyordu --}}
                            <text x="{{ $mx + $ga['halkalar'][1000] - 6 }}" y="{{ $my + 4 }}" class="halka-yazi is-sag">1 km</text>
                            <text x="{{ $mx + $ga['halkalar'][500] - 6 }}" y="{{ $my + 4 }}" class="halka-yazi is-sag">500 m</text>
                            @foreach ($ga['tesisler'] as $t)
                                <circle cx="{{ $t['x'] }}" cy="{{ $t['y'] }}" r="5" fill="{{ $t['renk'] }}" class="tesis"/>
                            @endforeach
                            <circle cx="{{ $mx }}" cy="{{ $my }}" r="13" class="merkez-hale"/>
                            <circle cx="{{ $mx }}" cy="{{ $my }}" r="6.5" class="merkez"/>
                        </svg>
                    </div>
                    {{-- Nokta renklerinin açıklaması haritanın alt kenarında --}}
                    <div class="lejant harita-lejant">
                        @foreach ($cevre['kategoriler'] as $kod => $k)
                            <span style="--kat: {{ $k['renk'] }}"><i></i>{{ $k['ad'] }}</span>
                        @endforeach
                    </div>
                    <div class="endeks-kart">
                        @php $uz = 2 * M_PI * 26; @endphp
                        <svg viewBox="0 0 64 64" class="endeks-halka" aria-hidden="true">
                            <circle cx="32" cy="32" r="26" class="zemin"/>
                            <circle cx="32" cy="32" r="26" class="dolu" style="stroke: {{ $renkPuan($cevre['genel']) }}" stroke-dasharray="{{ round($uz * $cevre['genel'] / 100, 1) }} {{ round($uz, 1) }}"/>
                        </svg>
                        <b class="endeks-sayi">{{ $cevre['genel'] }}</b>
                        <div>
                            <small>Konum Endeksi</small>
                            <strong style="color: {{ $renkPuan($cevre['genel']) }}">{{ $cevre['seviye'] }}</strong>
                        </div>
                    </div>
                </figure>

                <div class="kart cevre-ozet">
                    <span class="ust-yazi">Değerlendirme</span>
                    <p class="cevre-cumle">{{ $cevre['ozet'] }}</p>
                    @php
                        $oneCikan = collect($cevre['kategoriler'])->filter(fn ($k) => $k['enYakin'])
                            ->map(fn ($k) => ['renk' => $k['renk'], 'kat' => $k['ad']] + $k['enYakin'])
                            ->sortBy('mesafe')->take(3);
                    @endphp
                    @if ($oneCikan->isNotEmpty())
                        <ul class="one-cikan">
                            @foreach ($oneCikan as $n)
                                <li style="--kat: {{ $n['renk'] }}"><i></i><span>{{ $n['kat'] }}</span><b>{{ $n['ad'] }}</b><em>{{ $mesafeYazi($n['mesafe']) }}</em></li>
                            @endforeach
                        </ul>
                    @endif
                    <p class="cevre-kaynak">
                        Erişilebilirlik göstergesidir; değer tahmini değildir. Kaynak: Ankara BB (EGO durakları, kent rehberi){{ $cevre['kaynaklar']['osm'] ? ', © OpenStreetMap katkıcıları' : '' }} · {{ \Illuminate\Support\Carbon::parse($cevre['tarih'])->format('d.m.Y') }}
                    </p>
                </div>
            </div>

            <div class="cevre-kategoriler">
                @foreach ($cevre['kategoriler'] as $kod => $k)
                    <div class="kart kat {{ $k['veriVar'] ? '' : 'is-yok' }}" style="--kat: {{ $k['renk'] }}">
                        <span class="kat-ikon">@include('panel.tasinmazlar._cevre-ikon', ['kod' => $kod, 'boy' => 18])</span>
                        <div class="kat-govde">
                            <div class="kat-ust">
                                <b>{{ $k['ad'] }}</b>
                                <span class="kat-puan">{{ $k['veriVar'] ? $k['puan'] : '—' }}<small>{{ $k['veriVar'] ? '/100' : '' }}</small></span>
                            </div>
                            <span class="kat-cubuk"><i style="width: {{ $k['puan'] ?? 0 }}%"></i></span>
                            @if (! $k['veriVar'])
                                <p class="kat-yakin">Veri kaynağına şu an ulaşılamadı; endekse katılmadı.</p>
                            @elseif ($k['enYakin'])
                                <p class="kat-yakin"><b>{{ $mesafeYazi($k['enYakin']['mesafe']) }}</b> · {{ $k['enYakin']['ad'] }}</p>
                                <p class="kat-sayi">500 m'de {{ $k['sayi500'] }} · 1 km'de {{ $k['sayi1000'] }}</p>
                            @else
                                <p class="kat-yakin">1 km içinde kayıtlı tesis yok.</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</section>

{{-- ============ 6 · RİSK VE KISITLAR ============ --}}
@php
    $durumAd = ['risk' => 'Risk', 'dikkat' => 'Dikkat', 'iyi' => 'Uygun', 'bilgi' => 'Bilgi', 'veri-yok' => 'Veri yok'];
    $riskMaddeleri = $saha ? collect($saha['risk']['maddeler'])->reject(fn ($m) => $m['kod'] === 'deprem')->merge($saha['kisitlar'])->values() : collect();
    $deprem = $saha['risk']['deprem'] ?? null;
    $toplanma = $saha ? collect($saha['risk']['maddeler'])->firstWhere('kod', 'toplanma') : null;
@endphp
<section class="sayfa ic">
    @include('panel.tasinmazlar._rapor-yan', [
        'no' => '05', 'baslik' => 'Risk ve Kısıtlar', 'alt' => 'Afet, güvenlik ve koruma durumu',
        'vurgular' => [
            ['Risk', $saha ? (string) $saha['sayac']['risk'] : '—', $saha ? 'unsur' : null],
            ['Dikkat', $saha ? (string) $saha['sayac']['dikkat'] : '—', $saha ? 'unsur' : null],
            ['En Büyük Deprem', $deprem && $deprem['enBuyuk'] ? 'M'.number_format($deprem['enBuyuk']['buyukluk'], 1, ',', '') : '—', $deprem && $deprem['enBuyuk'] ? '· '.$deprem['enBuyuk']['mesafe'].' km' : null],
            ['Toplanma Alanı', $toplanma ? \Illuminate\Support\Str::before($toplanma['deger'], ' ·') : '—', null],
        ],
        'kunye' => $adaParsel, 'sayfaNo' => $sayfaNo(),
    ])

    @if (! $saha)
        <div class="icerik cevre-yok"><div class="kart"><span class="ust-yazi">Risk ve Kısıtlar</span><b class="buyuk">Konum kaydı olmadığı için analiz yapılamadı.</b></div></div>
    @else
        <div class="icerik risk">
            <div class="kart deprem-kart">
                <div class="deprem-sol">
                    <span class="deprem-buyukluk">{{ $deprem && $deprem['enBuyuk'] ? number_format($deprem['enBuyuk']['buyukluk'], 1, ',', '') : '—' }}</span>
                    <small>en büyük</small>
                </div>
                <div class="deprem-govde">
                    <span class="ust-yazi">Deprem Geçmişi · 100 km · 2000'den bu yana</span>
                    @if ($deprem)
                        <b class="buyuk">{{ $deprem['sayi'] }} deprem (M3,5 ve üstü)<em> · {{ $deprem['sayi4'] }} tanesi M4+</em></b>
                        <p class="gri">
                            @if ($deprem['enBuyuk'])En büyüğü {{ \Illuminate\Support\Carbon::parse($deprem['enBuyuk']['tarih'])->format('d.m.Y') }} · {{ $deprem['enBuyuk']['mesafe'] }} km uzakta ({{ $deprem['enBuyuk']['yer'] }}).@endif
                            @if ($deprem['enYakin']) En yakın M4+: {{ $deprem['enYakin']['mesafe'] }} km · M{{ number_format($deprem['enYakin']['buyukluk'], 1, ',', '') }}.@endif
                        </p>
                    @else
                        <b class="buyuk">Deprem kataloğuna ulaşılamadı</b>
                    @endif
                </div>
                <span class="deprem-not">Türkiye'nin tamamı deprem bölgesidir; yapılaşmada güncel Deprem Yönetmeliği esastır.</span>
            </div>

            <div class="madde-izgara">
                @foreach ($riskMaddeleri as $m)
                    <div class="kart madde is-{{ $m['durum'] }}">
                        <div class="madde-ust">
                            <b>{{ $m['ad'] }}</b>
                            <span class="madde-durum">{{ $durumAd[$m['durum']] ?? $m['durum'] }}</span>
                        </div>
                        <strong>{{ $m['deger'] }}</strong>
                        <p>{{ $m['aciklama'] }}</p>
                    </div>
                @endforeach
            </div>
            <p class="kaynak-not">Kaynak: Ankara BB (afet su baskın noktaları, jeolojik etüt, kaya düşmesi, itfaiye, acil toplanma alanları, KUDEM koruma alanları, orman meşceresi) ve USGS deprem kataloğu · {{ \Illuminate\Support\Carbon::parse($saha['tarih'])->format('d.m.Y') }}. "Veri yok", ilgili kaydın bu bölgeyi kapsamadığını gösterir; risk olmadığı anlamına gelmez.</p>
        </div>
    @endif
</section>

{{-- ============ 7 · ALTYAPI VE ARAZİ ============ --}}
@php
    $yolMadde = $saha ? collect($saha['altyapi'])->firstWhere('kod', 'yol') : null;
    $hatlar = $saha ? collect($saha['altyapi'])->reject(fn ($m) => $m['kod'] === 'yol')->values() : collect();
    $arazi = $saha['arazi'] ?? null;
    $hatIkon = [
        'icme' => '<path d="M12 2.7S6 9.3 6 14a6 6 0 0 0 12 0c0-4.7-6-11.3-6-11.3z"/>',
        'kanal' => '<path d="M3 7h18M3 12h18M3 17h18"/>',
        'yagmur' => '<path d="M20 15.6A5 5 0 0 0 18 6h-1.3A8 8 0 1 0 4 15.3"/><path d="M8 19v2M12 17v4M16 19v2"/>',
    ];
@endphp
<section class="sayfa ic">
    @include('panel.tasinmazlar._rapor-yan', [
        'no' => '06', 'baslik' => 'Altyapı ve Arazi', 'alt' => 'Yol, su, kanalizasyon ve arazi yapısı',
        'vurgular' => [
            ['Yol (plana göre)', $yolMadde && str_starts_with($yolMadde['deger'], 'Plana') ? \Illuminate\Support\Str::after($yolMadde['deger'], 'göre ') : '12 m altı', null],
            ['Eğim', $arazi ? '%'.number_format($arazi['egim'], 1, ',', '') : '—', $arazi ? '· '.$arazi['egimSinifi'] : null],
            ['Bakı', $arazi['baki'] ?? '—', null],
            ['Rakım', $arazi ? (string) $arazi['rakim'] : '—', $arazi ? 'm' : null],
        ],
        'kunye' => $adaParsel, 'sayfaNo' => $sayfaNo(),
    ])

    @if (! $saha)
        <div class="icerik cevre-yok"><div class="kart"><span class="ust-yazi">Altyapı ve Arazi</span><b class="buyuk">Konum kaydı olmadığı için analiz yapılamadı.</b></div></div>
    @else
        <div class="icerik altyapi">
            <div class="altyapi-sol">
                <div class="kart yol-kart is-{{ $yolMadde['durum'] ?? 'veri-yok' }}">
                    <span class="yol-ikon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21 8 3M20 21 16 3M12 4v3M12 11v3M12 18v2"/></svg></span>
                    <div>
                        <span class="ust-yazi">Yol Erişimi · İmar planına göre</span>
                        <b class="buyuk">{{ $yolMadde['deger'] ?? '—' }}</b>
                        <p class="gri">{{ $yolMadde['aciklama'] ?? '' }}</p>
                    </div>
                </div>
                @foreach ($hatlar as $h)
                    <div class="kart hat is-{{ $h['durum'] }}">
                        <span class="hat-ikon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $hatIkon[$h['kod']] ?? '' !!}</svg></span>
                        <div class="hat-govde">
                            <div class="madde-ust"><b>{{ $h['ad'] }}</b><span class="madde-durum">{{ $durumAd[$h['durum']] ?? $h['durum'] }}</span></div>
                            <strong>{{ $h['deger'] }}</strong>
                            <p>{{ $h['aciklama'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="kart arazi-kart">
                <span class="ust-yazi">Arazi Yapısı</span>
                @if ($arazi)
                    <div class="arazi-ust">
                        <div class="pusula">
                            <span class="k">K</span><span class="d">D</span><span class="g">G</span><span class="b">B</span>
                            @if ($arazi['bakiAci'] !== null)
                                <i style="transform: rotate({{ $arazi['bakiAci'] }}deg)"></i>
                            @else
                                <em>düz</em>
                            @endif
                        </div>
                        <div>
                            <b class="egim-deger">%{{ number_format($arazi['egim'], 1, ',', '') }}</b>
                            <span class="egim-sinif">{{ $arazi['egimSinifi'] }} · {{ $arazi['baki'] === 'Yön yok' ? 'belirgin bakı yok' : $arazi['baki'].'e bakıyor' }}</span>
                            @php
                                // Ölçek dört eşit dilim: düz 0-2, hafif 2-6, orta 6-12, dik 12-25 (%).
                                $e = $arazi['egim'];
                                $konum = match (true) {
                                    $e < 2 => $e / 2 * 25,
                                    $e < 6 => 25 + ($e - 2) / 4 * 25,
                                    $e < 12 => 50 + ($e - 6) / 6 * 25,
                                    default => 75 + min(1, ($e - 12) / 13) * 25,
                                };
                            @endphp
                            <span class="egim-cubuk"><i style="width: {{ round($konum, 1) }}%"></i></span>
                            <span class="egim-olcek"><span>Düz</span><span>Hafif</span><span>Orta</span><span>Dik</span></span>
                        </div>
                    </div>

                    @php
                        $izg = $arazi['izgara'];
                        $zMin = min(array_map('min', $izg));
                        $zMax = max(array_map('max', $izg));
                    @endphp
                    <span class="ust-yazi izgara-baslik">Yükseklik Izgarası · çevre {{ $zMin }}–{{ $zMax }} m</span>
                    <div class="yukseklik-izgara">
                        @foreach ($izg as $satir)
                            @foreach ($satir as $z)
                                @php $t = $zMax > $zMin ? ($z - $zMin) / ($zMax - $zMin) : 0.5; @endphp
                                <span class="{{ $t > 0.5 ? 'is-koyu' : '' }}" style="--t: {{ round($t, 2) }}">{{ $z }}</span>
                            @endforeach
                        @endforeach
                    </div>
                    <p class="kaynak-not">Kuzey yukarıda. Open-Meteo yükseklik modeli (90 m çözünürlük); eğim ve bakı parsel çevresindeki 25 noktadan hesaplanan yaklaşık değerlerdir.</p>
                @else
                    <p class="gri">Yükseklik verisine ulaşılamadı.</p>
                @endif
            </div>
        </div>
    @endif
</section>

{{-- ============ 8 · MÜLKİYET ============ --}}
<section class="sayfa ic">
    @include('panel.tasinmazlar._rapor-yan', [
        'no' => '07', 'baslik' => 'Mülkiyet', 'alt' => 'Hisse yapısı ve bağımsız bölümler',
        'vurgular' => [
            ['Aktif Hisse Oranı', $aktifOran !== null ? '%'.$sayi($aktifOran, 1) : '—', null],
            ['Aktif Hisse Alanı', $sayi($aktifHisseToplam), 'm²'],
            ['Hisse Kaydı', $hisseler->count().' / '.$aktifHisseler->count().' aktif', null],
            ['Bağımsız Bölüm', (string) $yapilar->count(), null],
        ],
        'kunye' => $adaParsel, 'sayfaNo' => $sayfaNo(),
    ])

    <div class="icerik mulkiyet">
        <div class="kart halka-kart">
            @php $o = $aktifOran ?? 0; $cevre = 2 * M_PI * 70; @endphp
            <div class="halka-kap">
                <svg viewBox="0 0 180 180" class="halka">
                    <defs><linearGradient id="halka-renk" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fb923c"/><stop offset="1" stop-color="#ea580c"/></linearGradient></defs>
                    <circle cx="90" cy="90" r="70" class="halka-zemin"/>
                    <circle cx="90" cy="90" r="70" class="halka-dolu" stroke="url(#halka-renk)" stroke-dasharray="{{ round($cevre * $o / 100, 2) }} {{ round($cevre, 2) }}"/>
                </svg>
                <div class="halka-orta"><b>%{{ $aktifOran !== null ? $sayi($aktifOran, 1) : '—' }}</b><small>aktif hisse</small></div>
            </div>
            <div class="halka-lejant">
                <div><i class="is-turuncu"></i><span>Aktif hisseler</span><b>{{ $sayi($aktifHisseToplam) }} m²</b></div>
                <div><i></i><span>Tapu alanı</span><b>{{ $sayi($tapuAlan) }} m²</b></div>
            </div>
        </div>

        <div class="mulkiyet-sag">
            <div class="kart">
                <div class="kart-bas"><span class="ikon" style="--renk:#dc2626"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12A9 9 0 1 1 12 3v9z"/></svg></span><h3>Hisseler</h3><span class="kart-not">{{ $hisseler->count() }} kayıt</span></div>
                @if ($hisseler->isEmpty())
                    <p class="bos">Hisse kaydı bulunmuyor.</p>
                @else
                    <table class="liste">
                        <thead><tr><th>Hisse</th><th>Pay/Payda</th><th class="sag">Alan</th><th>Oran</th><th>Edinme</th><th>Durum</th></tr></thead>
                        <tbody>
                        @foreach ($hisseler->take(7) as $h)
                            @php $oran = ($h->hisse_pay !== null && (float) $h->hisse_payda) ? round((float) $h->hisse_pay / (float) $h->hisse_payda * 100, 2) : null; @endphp
                            <tr>
                                <td><b>{{ $h->hisse_no ?: '#'.$loop->iteration }}</b></td>
                                <td>{{ $h->hisse_pay !== null ? rtrim(rtrim(number_format((float) $h->hisse_pay, 4, ',', '.'), '0'), ',') : '—' }} / {{ $h->hisse_payda !== null ? rtrim(rtrim(number_format((float) $h->hisse_payda, 4, ',', '.'), '0'), ',') : '—' }}</td>
                                <td class="sag">{{ $h->hisse_yuzolcum !== null ? $sayi($h->hisse_yuzolcum).' m²' : '—' }}</td>
                                <td>
                                    @if ($oran !== null)
                                        <span class="cubuk"><i style="width: {{ min(100, $oran) }}%"></i></span>%{{ $sayi($oran) }}
                                    @else — @endif
                                </td>
                                <td>{{ $h->edinme_sekli ?: '—' }}@if ($h->edinme_tarihi)<small class="alt-satir">{{ $h->edinme_tarihi->format('d.m.Y') }}</small>@endif</td>
                                <td><span class="rozet {{ $h->hisse_durum === 'aktif' ? 'is-yesil' : '' }}">{{ $h->hisse_durum === 'aktif' ? 'Aktif' : ($h->hisse_durum === 'kapali' ? 'Kapalı' : ($h->hisse_durum ?: '—')) }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @if ($hisseler->count() > 7)<p class="devam">+ {{ $hisseler->count() - 7 }} hisse daha · tam liste detay sayfasında</p>@endif
                @endif
            </div>

            <div class="kart">
                <div class="kart-bas"><span class="ikon" style="--renk:#1e3a8a"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01"/></svg></span><h3>Bağımsız Bölümler</h3><span class="kart-not">{{ $kayitTipi?->etiket() }}</span></div>
                @if ($yapilar->isEmpty())
                    <p class="bos">{{ $kayitTipi === \App\Enums\KayitTipi::BosParsel ? 'Boş parsel; bağımsız bölüm yok.' : 'Bağımsız bölüm eklenmemiş.' }}</p>
                @else
                    <table class="liste">
                        <thead><tr><th>Blok / Kat / BB</th><th>Nitelik</th><th class="sag">Brüt</th><th class="sag">Net</th><th>Oda</th><th>Cephe</th></tr></thead>
                        <tbody>
                        @foreach ($yapilar->take(4) as $yr)
                            <tr>
                                <td><b>{{ collect([$yr->blok_no, $yr->kat_no, $yr->bagimsiz_bolum_no])->filter()->implode(' / ') ?: '#'.$loop->iteration }}</b></td>
                                <td>{{ $yr->nitelik ?: '—' }}</td>
                                <td class="sag">{{ $yr->brut_alan !== null ? $sayi($yr->brut_alan).' m²' : '—' }}</td>
                                <td class="sag">{{ $yr->net_alan !== null ? $sayi($yr->net_alan).' m²' : '—' }}</td>
                                <td>{{ $yr->oda_sayisi ?: '—' }}</td>
                                <td>{{ $yr->cephe ?: '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @if ($yapilar->count() > 4)<p class="devam">+ {{ $yapilar->count() - 4 }} bölüm daha</p>@endif
                @endif
            </div>
        </div>
    </div>
</section>

{{-- ============ 9 · KULLANIM VE KAYITLAR ============ --}}
@php $kiraSayisi = $kiraAlanlari->count() + $alansizDosyalar->count(); @endphp
<section class="sayfa ic">
    @include('panel.tasinmazlar._rapor-yan', [
        'no' => '08', 'baslik' => 'Kullanım ve Kayıtlar', 'alt' => 'Modüllerdeki ilişkili kayıtlar',
        'vurgular' => [
            ['Kira', $kiraSayisi.' kayıt', null],
            ['Değerleme', $degerlemeler->count().' rapor', null],
            ['Ecrimisil', $ecrimisiller->count().' kayıt', null],
            ['Hisse Satışı', $hisseDosyalari->count().' dosya', null],
        ],
        'kunye' => $adaParsel, 'sayfaNo' => $sayfaNo(),
    ])

    <div class="icerik kayitlar">
        <div class="kart deger" style="--renk:#16a34a">
            <div class="kart-bas"><span class="ikon" style="--renk:#16a34a"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/></svg></span><h3>Değerleme</h3><span class="say">{{ $degerlemeler->count() }}</span></div>
            @if ($degerlemeler->isEmpty())
                <p class="bos">Değerleme raporu yok.</p>
            @else
                @php $enBuyuk = max(1, (float) $degerlemeler->max('toplam_bedel')); @endphp
                <div class="deger-ust"><small>Son değer</small><b>{{ $sayi($sonDeg->toplam_bedel, 0) }}<em>TL</em></b><span>{{ $tarih($sonDeg->rapor_tarihi) }}{{ $sonDeg->birim_fiyat ? ' · '.$sayi($sonDeg->birim_fiyat, 0).' TL/m²' : '' }}</span></div>
                <div class="grafik">
                    @foreach ($degerlemeler->take(-6) as $d)
                        <div class="grafik-sutun">
                            <span class="grafik-cubuk" style="height: {{ max(8, round((float) $d->toplam_bedel / $enBuyuk * 100)) }}%"></span>
                            <small>{{ $d->rapor_tarihi?->format('m.Y') ?? '—' }}</small>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="kart" style="--renk:#7c3aed">
            <div class="kart-bas"><span class="ikon" style="--renk:#7c3aed"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8 12 3l8 5-2 12H6z"/><path d="M9 13h6"/></svg></span><h3>Kira</h3><span class="say">{{ $kiraSayisi }}</span></div>
            @forelse ($kiraAlanlari->take(2) as $alan)
                @php $kalan = $alan->kalanGun(); @endphp
                <div class="kayit">
                    <div class="kayit-ust"><b>{{ $alan->ad ?: 'Kira alanı #'.$alan->id }}</b>@if ($alan->dolu_bos)<span class="rozet {{ $alan->dolu_bos->value === 'dolu' ? 'is-kirmizi' : 'is-yesil' }}">{{ $alan->dolu_bos->etiket() }}</span>@endif</div>
                    <dl class="kayit-dl">
                        <div><dt>Kiracı</dt><dd>{{ $alan->kiraci() ?: '—' }}</dd></div>
                        <div><dt>Yıllık</dt><dd>{{ $alan->yillik_bedel ? $sayi($alan->yillik_bedel, 0).' TL' : '—' }}</dd></div>
                        <div><dt>Bitiş</dt><dd class="{{ $kalan !== null && $kalan < 0 ? 'is-kirmizi' : '' }}">{{ $tarih($alan->bitis_tarihi) ?? '—' }}</dd></div>
                    </dl>
                </div>
            @empty
                @if ($alansizDosyalar->isEmpty())<p class="bos">Kira kaydı yok.</p>@endif
            @endforelse
            @foreach ($alansizDosyalar->take(2) as $dosya)
                <div class="kayit"><div class="kayit-ust"><b>{{ $dosya->dosya_no }}</b><span class="rozet">{{ $dosya->dolu_bos?->etiket() }}</span></div></div>
            @endforeach
        </div>

        <div class="kart" style="--renk:#ea580c">
            <div class="kart-bas"><span class="ikon" style="--renk:#ea580c"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg></span><h3>Ecrimisil / İşgal</h3><span class="say">{{ $ecrimisiller->count() }}</span></div>
            @forelse ($ecrimisiller->take(3) as $ec)
                <div class="kayit"><div class="kayit-ust"><b>{{ $ec->ad_soyad_unvan ?: 'İşgalci #'.$ec->id }}</b><span class="rozet {{ $ec->durum() === 'devam-eden' ? 'is-turuncu' : '' }}">{{ $ec->durumEtiketi() }}</span></div></div>
            @empty
                <p class="bos">Ecrimisil kaydı yok.</p>
            @endforelse
        </div>

        <div class="kart" style="--renk:#2563eb">
            <div class="kart-bas"><span class="ikon" style="--renk:#2563eb"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9v12h14V9"/><path d="M10 21v-6h4v6"/></svg></span><h3>Lojman</h3><span class="say">{{ $lojmanlar->count() }}</span></div>
            @forelse ($lojmanlar->take(3) as $lj)
                @php $tahsis = $lj->aktifTahsis(); @endphp
                <div class="kayit"><div class="kayit-ust"><b>{{ $lj->konumKisa() ?: $lj->ad }}</b><span class="rozet">{{ $lj->durumEtiketi() }}</span></div>@if ($tahsis)<small>{{ $tahsis->ad_soyad }}</small>@endif</div>
            @empty
                <p class="bos">Lojman olarak kullanılmıyor.</p>
            @endforelse
        </div>

        <div class="kart" style="--renk:#7c3aed">
            <div class="kart-bas"><span class="ikon" style="--renk:#7c3aed"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><h3>Hisse Satışı</h3><span class="say">{{ $hisseDosyalari->count() }}</span></div>
            @forelse ($hisseDosyalari->take(3) as $grupNo => $basvurular)
                @php $son = $basvurular->first(); @endphp
                <div class="kayit"><div class="kayit-ust"><b>Dosya #{{ $grupNo }}</b>@if ($son?->durum)<span class="rozet">{{ $son->durum->etiket() }}</span>@endif</div><small>{{ $basvurular->count() }} başvuru{{ $son?->ad_soyad ? ' · '.$son->ad_soyad : '' }}</small></div>
            @empty
                <p class="bos">Hisse satış dosyası yok.</p>
            @endforelse
        </div>

        <div class="kart" style="--renk:#dc2626">
            <div class="kart-bas"><span class="ikon" style="--renk:#dc2626"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m14 13-7.5 7.5a2.1 2.1 0 0 1-3-3L11 10"/><path d="m16 16 6-6M8 8l6-6M9 7l8 8M21 11l-8-8"/></svg></span><h3>Meclis Kararları</h3><span class="say">{{ $meclisKararlari->count() }}</span></div>
            @forelse ($meclisKararlari->take(2) as $k)
                <div class="kayit"><div class="kayit-ust"><b>Karar {{ $k->karar_no ?: '—' }}</b><span class="rozet is-turuncu">{{ $k->karar_tipi === 'satis' ? 'Satış' : ucfirst((string) $k->karar_tipi) }}</span></div><small>{{ $tarih($k->karar_tarihi) }}{{ trim((string) $k->karar_ozeti) !== '' ? ' · '.\Illuminate\Support\Str::limit($k->karar_ozeti, 60) : '' }}</small></div>
            @empty
                <p class="bos">Meclis kararı yok.</p>
            @endforelse
        </div>

        <p class="yasal">Bu rapor ETYS kayıtlarından {{ $olusturma->format('d.m.Y H:i') }} tarihinde otomatik üretilmiştir; bilgi amaçlıdır ve resmî belge yerine geçmez. Uydu ve harita görüntüleri © Google; imar planı ve parselasyon © Ankara Büyükşehir Belediyesi.</p>
    </div>
</section>

{{-- ============ 10 · FOTOĞRAFLAR ============ --}}
@if ($resimler->isNotEmpty())
<section class="sayfa ic">
    @include('panel.tasinmazlar._rapor-yan', [
        'no' => '09', 'baslik' => 'Fotoğraflar', 'alt' => 'Sahadan kayıtlı görseller',
        'vurgular' => [['Fotoğraf', (string) $resimler->count(), null]],
        'kunye' => $adaParsel, 'sayfaNo' => $sayfaNo(),
    ])
    <div class="icerik galeri sayi-{{ min(6, $resimler->count()) }}">
        @foreach ($resimler->take(6) as $r)
            <figure><img src="{{ $r->url() }}" alt="Fotoğraf {{ $loop->iteration }}" data-rapor-gorsel onerror="this.classList.add('is-hata')"></figure>
        @endforeach
    </div>
</section>
@endif

</main>

@unless ($pdfModu ?? false)
<script>
    // PDF sunucuda üretilir (10-20 sn); bu sürede düğme meşgul görünür,
    // ikinci tıklama aynı işi yeniden başlatmasın.
    (function () {
        const indir = document.querySelector('[data-pdf-indir]');
        if (!indir) return;
        indir.addEventListener('click', function (e) {
            if (indir.classList.contains('is-mesgul')) { e.preventDefault(); return; }
            indir.classList.add('is-mesgul');
            indir.querySelector('span').textContent = 'PDF hazırlanıyor…';
            setTimeout(function () {
                indir.classList.remove('is-mesgul');
                indir.querySelector('span').textContent = 'PDF olarak indir';
            }, 25000);
        });
    })();

    // Görseller yüklenince "PDF olarak indir" etkinleşir; ?yazdir=1 ile
    // gelindiyse yazdırma penceresi kendiliğinden açılır. Bir görsel hiç
    // gelmezse rapor yine de 20 sn sonra hazır sayılır.
    (function () {
        const gorseller = Array.prototype.slice.call(document.querySelectorAll('[data-rapor-gorsel]'));
        const cubuk = document.getElementById('arac-cubuk');
        const metin = document.getElementById('arac-metin');
        const durum = document.getElementById('arac-durum');
        let biten = 0;
        let hazir = false;

        function ilerle() {
            biten++;
            cubuk.style.width = Math.round(biten / gorseller.length * 100) + '%';
            metin.textContent = 'Görseller hazırlanıyor… ' + biten + ' / ' + gorseller.length;
            if (biten >= gorseller.length) bitir();
        }

        function bitir() {
            if (hazir) return;
            hazir = true;
            durum.classList.add('is-hazir');
            metin.textContent = 'Rapor hazır';
            if (new URLSearchParams(location.search).get('yazdir') === '1') {
                setTimeout(function () { window.print(); }, 400);
            }
        }

        if (!gorseller.length) { bitir(); return; }
        gorseller.forEach(function (img) {
            if (img.complete) { ilerle(); return; }
            img.addEventListener('load', ilerle, { once: true });
            img.addEventListener('error', ilerle, { once: true });
        });
        setTimeout(bitir, 20000);
    })();
</script>
@endunless
</body>
</html>
