{{--
    Harita bilgi paneli: parsele tıklanınca sağdan açılır (bkz. harita-esri.js, tsbAc).
    Detay sayfasıyla (goster.blade.php) aynı ilişkileri gösterir; bölümler
    SSS gibi açılır-kapanır. Kayıtlar yalnızca okunur, yönetim ilgili modülde.
--}}
@php
    $kullanici = auth()->user();
    $izin = fn (string $kod) => (bool) $kullanici?->izinVarMi($kod);
    $sayi = fn ($v, int $basamak = 2) => $v === null ? null : number_format((float) $v, $basamak, ',', '.');
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
    $tkgmUrl = $tasinmaz->tkgmSorguUrl();

    $bayrak = fn (string $alan) => ($ekbilgi && (bool) $ekbilgi->{$alan}) || $yapilar->contains(fn ($y) => (bool) $y->{$alan});
    $binaVar = (bool) $tasinmaz->uzeri_bina_var_mi || $yapilar->isNotEmpty();

    $niteligiArsa = $tasinmaz->nitelik
        ? (bool) preg_match('/arsa|arazi|tarla|bağ|bahçe|çayır|otlak|mera|boş/iu', $tasinmaz->nitelik)
        : null;
    $tapuFiiliUyumsuz = $niteligiArsa !== null && (($niteligiArsa && $binaVar) || (! $niteligiArsa && ! $binaVar));

    $isgalEtiket = match ($ekbilgi?->isgal_durumu) {
        'yok', 'bos' => 'Yok',
        'var', 'isgal' => 'İşgalli',
        'kismi', 'kismen' => 'Kısmen İşgalli',
        default => $ekbilgi?->isgal_durumu ?: null,
    };

    $lojmanlar = $tasinmaz->lojmanlar;
    $ecrimisiller = $tasinmaz->ecrimisiller;
    $suruyorIsgal = $ecrimisiller->filter(fn ($e) => $e->durum() === 'devam-eden');
    $degerlemeler = $tasinmaz->degerlemeler->sortByDesc('rapor_tarihi')->values();
    $sonDeg = $degerlemeler->first();
    $hisseDosyalari = $tasinmaz->hisseBasvurulari->groupBy('grup_no');
    $meclisKararlari = $tasinmaz->meclisKararlari->sortByDesc('karar_tarihi')->values();
    $kayitTipi = $tasinmaz->kayit_tipi instanceof \App\Enums\KayitTipi ? $tasinmaz->kayit_tipi : null;

    $isgalVar = in_array($ekbilgi?->isgal_durumu, ['var', 'kismi', 'isgal', 'kismen'], true) || $suruyorIsgal->isNotEmpty();
    // Alanı olan dosya, alan kartında zaten görünür; ayrıca listelenmez.
    $alansizDosyalar = $kiraDosyalari->reject(fn ($d) => $kiraAlanlari->contains('kira_dosya_id', $d->id));
    $kiraSayisi = $kiraAlanlari->count() + $alansizDosyalar->count();
    $aciklamalar = collect([$ekbilgi?->aciklama])->merge($yapilar->pluck('aciklama'))->filter()->unique()->values();
@endphp

<div class="tsb">
    {{-- ÜST: kimlik, durum, özet --}}
    <header class="tsb-hero">
        <div class="tsb-hero-ust">
            <span class="tsb-no">Taşınmaz #{{ $tasinmaz->id }}</span>
            <span class="tsb-cip is-{{ $mulkiyet === 'HİSSELİ' ? 'kirmizi' : 'yesil' }}">{{ $mulkiyet === 'HİSSELİ' ? 'Hisseli' : ($mulkiyet === 'TAM' ? 'Tam mülkiyet' : 'Mülkiyet —') }}</span>
            <span class="tsb-cip">{{ $satisOzet->etiket() }}</span>
            @if ($kayitTipi)
                <span class="tsb-cip" title="{{ $kayitTipi->aciklama() }}">{{ $kayitTipi->etiket() }}</span>
            @endif
            @if ($isgalVar)
                <span class="tsb-cip is-turuncu">İşgal</span>
            @endif
        </div>

        <div class="tsb-kimlik">
            <div class="tsb-kimlik-metin">
                <div class="tsb-adaparsel">
                    <div><small>Ada</small><b>{{ $tasinmaz->ada ?? '—' }}</b></div>
                    <i aria-hidden="true"></i>
                    <div><small>Parsel</small><b>{{ $tasinmaz->parsel ?? '—' }}</b></div>
                </div>
                <p class="tsb-konum">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-7-7-7-13a7 7 0 0 1 14 0c0 6-7 13-7 13z"/><circle cx="12" cy="9" r="2.6"/></svg>
                    {{ collect([$tasinmaz->ilce?->ad, $tasinmaz->mahalle?->ad ? $tasinmaz->mahalle->ad.' Mah.' : null])->filter()->implode(' · ') ?: 'Konum bilgisi yok' }}
                </p>
            </div>
            @if ($resimler->isNotEmpty())
                <a href="{{ $resimler->first()->url() }}" target="_blank" rel="noopener" class="tsb-kapak">
                    <img src="{{ $resimler->first()->url() }}" alt="Kapak fotoğrafı" loading="lazy">
                </a>
            @endif
        </div>
    </header>

    <div class="tsb-ozet">
        <div class="tsb-ozet-oge" style="--ikon:#1e3a8a">
            <span class="tsb-ozet-ikon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v18H3z"/><path d="M3 9h18M9 21V9"/></svg></span>
            <div><span>Yüzölçüm</span><strong>{{ $tasinmaz->alan !== null ? $sayi($tasinmaz->alan) : '—' }}<em>m²</em></strong></div>
        </div>
        <div class="tsb-ozet-oge" style="--ikon:#16a34a">
            <span class="tsb-ozet-ikon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/></svg></span>
            <div><span>Son Değer</span><strong>{{ $sonDeg ? $sayi($sonDeg->toplam_bedel, 0) : '—' }}@if ($sonDeg)<em>TL</em>@endif</strong></div>
        </div>
        <div class="tsb-ozet-oge" style="--ikon:#ea580c">
            <span class="tsb-ozet-ikon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2 8l10 5 10-5z"/><path d="m2 13 10 5 10-5"/></svg></span>
            <div><span>İmar</span><strong class="is-metin" title="{{ $imarAd }}">{{ $imarAd ?: '—' }}</strong></div>
        </div>
        <div class="tsb-ozet-oge" style="--ikon:#7c3aed">
            <span class="tsb-ozet-ikon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7" cy="7" r="1.5"/></svg></span>
            <div><span>Nitelik</span><strong class="is-metin">{{ $tasinmaz->nitelik ?: '—' }}</strong></div>
        </div>
    </div>

    <div class="tsb-eylem">
            @if ($izin('tasinmaz.goruntule') && ! isset($tasinmaz->duzenleParams()['tasinmaz']))
                <a href="{{ $tasinmaz->rota('rapor-pdf') }}" class="tsb-btn is-ana" data-tsb-pdf>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6M9 15l3 3 3-3"/></svg>
                    <span>Taşınmaz Raporu İndir (PDF)</span>
                </a>
            @endif
            @if ($izin('tasinmaz.goruntule'))
                <a href="{{ $tasinmaz->rota('detay') }}" class="tsb-btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
                    Detay Sayfası
                </a>
            @endif
            @if ($izin('tasinmaz.duzenle'))
                <a href="{{ $tasinmaz->rota('duzenle') }}" class="tsb-btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                    Düzenle
                </a>
            @endif
            <button type="button" class="tsb-btn" data-tsb-yakinlas title="Haritada bu parsele yakınlaş">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3M11 8v6M8 11h6"/></svg>
                Yakınlaş
            </button>
            @if ($tkgmUrl)
                <a href="{{ $tkgmUrl }}" target="_blank" rel="noopener noreferrer" class="tsb-btn" title="TKGM Parsel Sorgu — yeni sekmede">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>
                    TKGM
                </a>
            @endif
    </div>

    {{-- Bölümlere hızlı geçiş; çipleri harita-esri.js bölüm başlıklarından üretir --}}
    <nav class="tsb-nav" data-tsb-nav aria-label="Bölümler"></nav>

    {{-- BÖLÜMLER --}}
    <div class="tsb-bolumler">

        {{-- Kimlik & Konum --}}
        <details class="tsb-bolum" open>
            <summary>
                <span class="tsb-ikon is-lacivert"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-7-7-7-13a7 7 0 0 1 14 0c0 6-7 13-7 13z"/><circle cx="12" cy="9" r="2.6"/></svg></span>
                <span class="tsb-bolum-ad">Kimlik &amp; Konum</span>
                <span class="tsb-bolum-meta">Kadastro</span>
            </summary>
            <dl class="tsb-liste">
                <div><dt>İl / İlçe</dt><dd>{{ $tasinmaz->il?->ad ?? '—' }} / {{ $tasinmaz->ilce?->ad ?? '—' }}</dd></div>
                <div><dt>Mahalle</dt><dd>{{ $tasinmaz->mahalle?->ad ?? '—' }}</dd></div>
                <div><dt>Ada / Parsel</dt><dd class="is-mono">{{ $tasinmaz->ada ?? '—' }} / {{ $tasinmaz->parsel ?? '—' }}</dd></div>
                <div><dt>Tapu Yüzölçüm</dt><dd class="is-mono">{{ $tasinmaz->alan !== null ? $sayi($tasinmaz->alan).' m²' : '—' }}</dd></div>
                <div><dt>Nitelik</dt><dd>
                    {{ $tasinmaz->nitelik ?: '—' }}
                    @if ($tapuFiiliUyumsuz)
                        <span class="tl-pill tl-pill-warn">Tapu ≠ Fiili</span>
                    @endif
                </dd></div>
                <div><dt>Kayıt Tipi</dt><dd>{{ $kayitTipi?->etiket() ?? ($binaVar ? 'Yapı var' : '—') }}</dd></div>
                <div><dt>Mülkiyet</dt><dd>
                    @if ($mulkiyet === 'TAM') Tam mülkiyet
                    @elseif ($mulkiyet === 'HİSSELİ') Hisseli · {{ $aktifHisseler->count() }} aktif hisse
                    @else — @endif
                </dd></div>
                @if ($tasinmaz->mahalle?->tkgm_id)
                    <div><dt>Mahalle TKGM No</dt><dd class="is-mono">{{ $tasinmaz->mahalle->tkgm_id }}</dd></div>
                @endif
                @if ($koordinat && $koordinat->lat !== null)
                    <div><dt>Koordinat</dt><dd class="is-mono">{{ number_format((float) $koordinat->lat, 6, '.', '') }}, {{ number_format((float) $koordinat->lng, 6, '.', '') }}</dd></div>
                @endif
            </dl>
        </details>

        {{-- Çevresel analiz: ayrı istenir (dış servisler), gelince bu yer tutucunun yerine geçer --}}
        <details class="tsb-bolum tsb-cevre is-yukleniyor" open data-tsb-cevre="{{ route('panel.ajax.tasinmaz-cevre', $tasinmaz->id) }}">
            <summary>
                <span class="tsb-ikon is-yesil"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 12l4-3"/><path d="M12 3v2M21 12h-2M5 12H3"/></svg></span>
                <span class="tsb-bolum-ad">Çevresel Analiz</span>
                <span class="tsb-say is-bos">…</span>
            </summary>
            <div class="tsb-cevre-bekle">
                <span class="tsb-dondur" aria-hidden="true"></span>
                <div><b>Çevre verisi hazırlanıyor</b><small>Ulaşım, eğitim, sağlık, yeşil alan, ticaret ve sosyal tesisler taranıyor. İlk seferde 30 sn kadar sürebilir.</small></div>
            </div>
        </details>

        {{-- Resmî imar, risk-kısıt, altyapı-arazi: ayrı istenir (ABB servisleri) --}}
        <div class="tsb-saha-bekle" data-tsb-saha="{{ route('panel.ajax.tasinmaz-saha', $tasinmaz->id) }}">
            <span class="tsb-dondur" aria-hidden="true"></span>
            <div><b>Resmî imar, risk ve altyapı sorgulanıyor</b><small>e-İmar, ASKİ, KUDEM, itfaiye ve afet kayıtları taranıyor.</small></div>
        </div>

        {{-- Tapu --}}
        <details class="tsb-bolum">
            <summary>
                <span class="tsb-ikon is-mavi"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                <span class="tsb-bolum-ad">Tapu</span>
                <span class="tsb-bolum-meta {{ $tapu ? '' : 'is-bos' }}">{{ $tapu ? ($tapu->tapu_durumu ?: 'Kayıtlı') : 'Girilmemiş' }}</span>
            </summary>
            @if ($tapu)
                <dl class="tsb-liste">
                    <div><dt>TAKBİS Zemin No</dt><dd class="is-mono">{{ $tapu->takbis_zemin_no ?? '—' }}</dd></div>
                    <div><dt>Cilt / Sayfa</dt><dd class="is-mono">{{ $tapu->cilt_no ?? '—' }} / {{ $tapu->sayfa_no ?? '—' }}</dd></div>
                    <div><dt>Tapu Durumu</dt><dd>{{ $tapu->tapu_durumu ?? '—' }}</dd></div>
                    <div><dt>Tapu Tarihi</dt><dd>{{ $tarih($tapu->tapu_tarihi) ?? '—' }}</dd></div>
                    @if ($tapu->pdfUrl())
                        <div><dt>Tapu Kaydı</dt><dd><a href="{{ $tapu->pdfUrl() }}" target="_blank" rel="noopener" class="tsb-link">PDF görüntüle</a></dd></div>
                    @endif
                </dl>
            @else
                <p class="tsb-bos">Bu taşınmaz için tapu kaydı girilmemiş.</p>
            @endif
        </details>

        {{-- İmar --}}
        <details class="tsb-bolum">
            <summary>
                <span class="tsb-ikon is-turuncu"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2 8l10 5 10-5z"/><path d="m2 13 10 5 10-5"/><path d="m2 18 10 5 10-5"/></svg></span>
                <span class="tsb-bolum-ad">İmar Durumu</span>
                <span class="tsb-bolum-meta {{ $imarAd ? '' : 'is-bos' }}">{{ $imarAd ? \Illuminate\Support\Str::limit($imarAd, 22) : 'Tanımsız' }}</span>
            </summary>
            <dl class="tsb-liste">
                <div><dt>İmar Durumu</dt><dd>{{ $imarAd ?: '—' }}</dd></div>
                <div><dt>Emsal</dt><dd class="is-mono">{{ $imar?->emsal !== null ? $sayi($imar->emsal) : '—' }}</dd></div>
                <div><dt>Yençok / Yenaz</dt><dd class="is-mono">{{ $imar?->yenaz_yencok ?: '—' }}</dd></div>
                @if (trim((string) ($imar->imar_notu ?? '')) !== '')
                    <div class="is-uzun"><dt>İmar Notu</dt><dd>{{ $imar->imar_notu }}</dd></div>
                @endif
            </dl>
        </details>

        {{-- Kullanım --}}
        <details class="tsb-bolum">
            <summary>
                <span class="tsb-ikon is-yesil"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-5 7 5v14"/><path d="M9 21v-6h6v6"/></svg></span>
                <span class="tsb-bolum-ad">Kullanım Durumu</span>
                <span class="tsb-bolum-meta {{ $isgalVar ? 'is-uyari' : '' }}">{{ $isgalVar ? 'İşgal var' : ($isgalEtiket ? 'İşgal: '.$isgalEtiket : 'Özet') }}</span>
            </summary>
            <div class="tsb-tikler">
                <span class="tsb-tik {{ $lojmanlar->isNotEmpty() ? 'is-var' : '' }}">Lojman</span>
                <span class="tsb-tik {{ $bayrak('tahsis_var') ? 'is-var' : '' }}">Tahsis</span>
                <span class="tsb-tik {{ $bayrak('kira_var') || $kiraSayisi ? 'is-var' : '' }}">Kira</span>
                <span class="tsb-tik {{ $bayrak('ust_hakki_var') ? 'is-var' : '' }}">Üst Hakkı</span>
                <span class="tsb-tik {{ $bayrak('meclis_satis_karari_var') ? 'is-var' : '' }}">Meclis Kararı</span>
                <span class="tsb-tik {{ $isgalVar ? 'is-var is-uyari' : '' }}">İşgal</span>
                <span class="tsb-tik {{ $binaVar ? 'is-var' : '' }}">Üzerinde Yapı</span>
            </div>
            <dl class="tsb-liste">
                <div><dt>İşgal Durumu</dt><dd>{{ $isgalEtiket ?? '—' }}</dd></div>
                @if ($kategori?->mevcut_kullanim_sekli)
                    <div><dt>Mevcut Kullanım</dt><dd>{{ $kategori->mevcut_kullanim_sekli }}</dd></div>
                @endif
                @foreach ($aciklamalar as $aciklama)
                    <div class="is-uzun"><dt>{{ $loop->first ? 'Açıklama' : '' }}</dt><dd>{{ $aciklama }}</dd></div>
                @endforeach
            </dl>
        </details>

        {{-- Kira --}}
        <details class="tsb-bolum">
            <summary>
                <span class="tsb-ikon is-mor"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8 12 3l8 5-2 12H6z"/><path d="M9 13h6"/></svg></span>
                <span class="tsb-bolum-ad">Kira</span>
                <span class="tsb-say {{ $kiraSayisi ? '' : 'is-bos' }}">{{ $kiraSayisi }}</span>
            </summary>
            @forelse ($kiraAlanlari as $alan)
                @php $kalan = $alan->kalanGun(); @endphp
                <div class="tsb-kart">
                    <div class="tsb-kart-ust">
                        <strong>{{ $alan->ad ?: 'Kira alanı #'.$alan->id }}</strong>
                        @if ($alan->dolu_bos)
                            <span class="tl-pill {{ $alan->dolu_bos->value === 'dolu' ? 'tl-pill-danger' : 'tl-pill-ok' }}">{{ $alan->dolu_bos->etiket() }}</span>
                        @endif
                        @unless ($alan->aktif_mi)<span class="tl-pill tl-pill-mute">Pasif</span>@endunless
                    </div>
                    <dl class="tsb-kart-liste">
                        @if ($alan->dosya)<div><dt>Dosya</dt><dd class="is-mono">{{ $alan->dosya->dosya_no }}</dd></div>@endif
                        @if ($alan->kiraci())<div><dt>Kiracı</dt><dd>{{ $alan->kiraci() }}</dd></div>@endif
                        @if ($alan->yillik_bedel)<div><dt>Yıllık Bedel</dt><dd class="is-mono">{{ $sayi($alan->yillik_bedel) }} TL</dd></div>@endif
                        @if ($alan->bitis_tarihi)
                            <div><dt>Bitiş</dt><dd>{{ $tarih($alan->bitis_tarihi) }}
                                @if ($kalan !== null)
                                    <small class="{{ $kalan < 0 ? 'is-gecti' : ($kalan <= 90 ? 'is-yakin' : '') }}">· {{ $kalan < 0 ? abs($kalan).' gün geçti' : $kalan.' gün kaldı' }}</small>
                                @endif
                            </dd></div>
                        @endif
                    </dl>
                    @if ($izin('kira.goruntule'))
                        <a href="{{ route('panel.kira.alanlar.detay', $alan->id) }}" class="tsb-kart-link">Alan bilgi kartı →</a>
                    @endif
                </div>
            @empty
                @if ($alansizDosyalar->isEmpty())
                    <p class="tsb-bos">Bu taşınmaza bağlı kira kaydı yok.</p>
                @endif
            @endforelse
            @foreach ($alansizDosyalar as $dosya)
                <div class="tsb-satir-kart">
                    <span class="is-mono">{{ $dosya->dosya_no }}</span>
                    @if ($dosya->dolu_bos)<span class="tl-pill {{ $dosya->dolu_bos->value === 'dolu' ? 'tl-pill-danger' : 'tl-pill-ok' }}">{{ $dosya->dolu_bos->etiket() }}</span>@endif
                    @unless ($dosya->sozlesme_aktif_mi)<span class="tl-pill tl-pill-mute">Sözleşme yok</span>@endunless
                    @if ($izin('kira.goruntule'))<a href="{{ route('panel.kira.detay', $dosya->id) }}" class="tsb-link">Dosya →</a>@endif
                </div>
            @endforeach
        </details>

        {{-- Hisseler --}}
        <details class="tsb-bolum">
            <summary>
                <span class="tsb-ikon is-kirmizi"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12A9 9 0 1 1 12 3v9z"/><path d="M21 12A9 9 0 0 0 12 3"/></svg></span>
                <span class="tsb-bolum-ad">Hisseler</span>
                <span class="tsb-say {{ $hisseler->isNotEmpty() ? '' : 'is-bos' }}">{{ $hisseler->count() }}</span>
            </summary>
            @if ($hisseler->isEmpty())
                <p class="tsb-bos">Hisse kaydı bulunmuyor.</p>
            @else
                <div class="tsb-hisse-ozet">
                    <div><span>Aktif hisse toplamı</span><strong>{{ $sayi($aktifHisseToplam) }} m²</strong></div>
                    @if ($aktifOran !== null)
                        <div class="tsb-oran" style="--oran: {{ $aktifOran }}%"><span>Tapu alanının</span><strong>%{{ $sayi($aktifOran) }}</strong></div>
                    @endif
                </div>
                @foreach ($hisseler as $h)
                    @php
                        $oran = ($h->hisse_pay !== null && (float) $h->hisse_payda) ? round((float) $h->hisse_pay / (float) $h->hisse_payda * 100, 2) : null;
                    @endphp
                    <div class="tsb-satir-kart">
                        <span class="tsb-satir-sira">{{ $h->hisse_no ?: '#'.$loop->iteration }}</span>
                        <span class="is-mono">{{ $h->hisse_yuzolcum !== null ? $sayi($h->hisse_yuzolcum).' m²' : '—' }}</span>
                        @if ($oran !== null)<small>%{{ $sayi($oran) }}</small>@endif
                        <span class="tl-pill {{ $h->hisse_durum === 'aktif' ? 'tl-pill-ok' : 'tl-pill-mute' }}">{{ $h->hisse_durum === 'aktif' ? 'Aktif' : ($h->hisse_durum === 'kapali' ? 'Kapalı' : ($h->hisse_durum ?: '—')) }}</span>
                    </div>
                @endforeach
            @endif
        </details>

        {{-- Bağımsız bölümler --}}
        @if ($kayitTipi !== \App\Enums\KayitTipi::BosParsel)
            <details class="tsb-bolum">
                <summary>
                    <span class="tsb-ikon is-lacivert"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01"/></svg></span>
                    <span class="tsb-bolum-ad">Bağımsız Bölümler</span>
                    <span class="tsb-say {{ $yapilar->isNotEmpty() ? '' : 'is-bos' }}">{{ $yapilar->count() }}</span>
                </summary>
                @forelse ($yapilar as $yr)
                    <div class="tsb-satir-kart">
                        <span class="tsb-satir-sira">{{ collect([$yr->blok_no, $yr->kat_no, $yr->bagimsiz_bolum_no])->filter()->implode(' / ') ?: '#'.$loop->iteration }}</span>
                        <span>{{ $yr->nitelik ?: '—' }}</span>
                        <small class="is-mono">{{ $yr->brut_alan !== null ? $sayi($yr->brut_alan).' m² brüt' : '' }}{{ $yr->oda_sayisi ? ' · '.$yr->oda_sayisi : '' }}</small>
                    </div>
                @empty
                    <p class="tsb-bos">Bağımsız bölüm eklenmemiş.</p>
                @endforelse
            </details>
        @endif

        {{-- Değerleme --}}
        <details class="tsb-bolum">
            <summary>
                <span class="tsb-ikon is-yesil"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/></svg></span>
                <span class="tsb-bolum-ad">Değerleme</span>
                <span class="tsb-say {{ $degerlemeler->isNotEmpty() ? '' : 'is-bos' }}">{{ $degerlemeler->count() }}</span>
            </summary>
            @forelse ($degerlemeler as $d)
                <div class="tsb-satir-kart">
                    <span class="tsb-satir-sira">{{ $d->tip === 'bb' ? 'BB '.$d->bb_no : 'Arsa' }}</span>
                    <strong class="is-mono">{{ $sayi($d->toplam_bedel) }} TL</strong>
                    <small>{{ $tarih($d->rapor_tarihi) }}{{ $d->birim_fiyat ? ' · '.$sayi($d->birim_fiyat, 0).' TL/m²' : '' }}</small>
                    @if ($izin('degerleme.goruntule'))<a href="{{ route('panel.degerleme.detay', $d->id) }}" class="tsb-link">Rapor →</a>@endif
                </div>
            @empty
                <p class="tsb-bos">Değerleme raporu yok.</p>
            @endforelse
        </details>

        {{-- Ecrimisil --}}
        <details class="tsb-bolum">
            <summary>
                <span class="tsb-ikon is-turuncu"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg></span>
                <span class="tsb-bolum-ad">Ecrimisil / İşgal</span>
                <span class="tsb-say {{ $ecrimisiller->isNotEmpty() ? ($suruyorIsgal->isNotEmpty() ? 'is-uyari' : '') : 'is-bos' }}">{{ $ecrimisiller->count() }}</span>
            </summary>
            @forelse ($ecrimisiller as $ec)
                <div class="tsb-satir-kart">
                    <span>{{ $ec->ad_soyad_unvan ?: 'İşgalci #'.$ec->id }}</span>
                    <span class="tl-pill {{ $ec->durum() === 'devam-eden' ? 'tl-pill-warn' : 'tl-pill-mute' }}">{{ $ec->durumEtiketi() }}</span>
                    @if ($izin('ecrimisil.goruntule'))<a href="{{ route('panel.ecrimisil.detay', $ec->id) }}" class="tsb-link">Kayıt →</a>@endif
                </div>
            @empty
                <p class="tsb-bos">Ecrimisil kaydı yok.</p>
            @endforelse
        </details>

        {{-- Lojman --}}
        <details class="tsb-bolum">
            <summary>
                <span class="tsb-ikon is-mavi"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9v12h14V9"/><path d="M10 21v-6h4v6"/></svg></span>
                <span class="tsb-bolum-ad">Lojman</span>
                <span class="tsb-say {{ $lojmanlar->isNotEmpty() ? '' : 'is-bos' }}">{{ $lojmanlar->count() }}</span>
            </summary>
            @forelse ($lojmanlar as $lj)
                @php $tahsis = $lj->aktifTahsis(); @endphp
                <div class="tsb-satir-kart">
                    <span>{{ $lj->konumKisa() ?: $lj->ad }}</span>
                    <span class="tl-pill tl-pill-info">{{ $lj->durumEtiketi() }}</span>
                    @if ($tahsis)<small>{{ $tahsis->ad_soyad }}</small>@endif
                    @if ($izin('lojman.goruntule'))<a href="{{ route('panel.lojman.detay', $lj->id) }}" class="tsb-link">Lojman →</a>@endif
                </div>
            @empty
                <p class="tsb-bos">Lojman olarak kullanılmıyor.</p>
            @endforelse
        </details>

        {{-- Hisse satışı --}}
        <details class="tsb-bolum">
            <summary>
                <span class="tsb-ikon is-mor"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <span class="tsb-bolum-ad">Hisse Satışı</span>
                <span class="tsb-say {{ $hisseDosyalari->isNotEmpty() ? '' : 'is-bos' }}">{{ $hisseDosyalari->count() }}</span>
            </summary>
            @forelse ($hisseDosyalari as $grupNo => $basvurular)
                @php $son = $basvurular->first(); @endphp
                <div class="tsb-satir-kart">
                    <span class="tsb-satir-sira">Dosya #{{ $grupNo }}</span>
                    <small>{{ $basvurular->count() }} başvuru{{ $son?->ad_soyad ? ' · '.$son->ad_soyad : '' }}</small>
                    @if ($son?->durum)<span class="tl-pill {{ $son->durum->pill() }}">{{ $son->durum->etiket() }}</span>@endif
                    @if ($izin('hisse-satisi.goruntule'))<a href="{{ route('panel.hisse-satisi.detay', $grupNo) }}" class="tsb-link">Dosya →</a>@endif
                </div>
            @empty
                <p class="tsb-bos">Hisse satış dosyası yok.</p>
            @endforelse
        </details>

        {{-- Meclis kararları --}}
        <details class="tsb-bolum">
            <summary>
                <span class="tsb-ikon is-kirmizi"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m14 13-7.5 7.5a2.1 2.1 0 0 1-3-3L11 10"/><path d="m16 16 6-6M8 8l6-6M9 7l8 8M21 11l-8-8"/></svg></span>
                <span class="tsb-bolum-ad">Meclis Kararları</span>
                <span class="tsb-say {{ $meclisKararlari->isNotEmpty() ? '' : 'is-bos' }}">{{ $meclisKararlari->count() }}</span>
            </summary>
            @forelse ($meclisKararlari as $k)
                <div class="tsb-kart">
                    <div class="tsb-kart-ust">
                        <strong>Karar {{ $k->karar_no ?: '—' }}</strong>
                        <span class="tl-pill tl-pill-warn">{{ $k->karar_tipi === 'satis' ? 'Satış' : ucfirst((string) $k->karar_tipi) }}</span>
                    </div>
                    <dl class="tsb-kart-liste">
                        <div><dt>Tarih</dt><dd>{{ $tarih($k->karar_tarihi) ?? '—' }}</dd></div>
                        @if (trim((string) $k->karar_ozeti) !== '')<div><dt>Özet</dt><dd>{{ $k->karar_ozeti }}</dd></div>@endif
                    </dl>
                    @if ($k->karar_pdf)
                        <a href="{{ asset('storage/'.$k->karar_pdf) }}" target="_blank" rel="noopener" class="tsb-kart-link">Karar PDF →</a>
                    @endif
                </div>
            @empty
                <p class="tsb-bos">Meclis kararı yok.</p>
            @endforelse
        </details>

        {{-- Muhasebe & kategori --}}
        <details class="tsb-bolum">
            <summary>
                <span class="tsb-ikon is-gri"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7" cy="7" r="1.5"/></svg></span>
                <span class="tsb-bolum-ad">Muhasebe &amp; Kategori</span>
                <span class="tsb-bolum-meta {{ $kategori ? '' : 'is-bos' }}">{{ $kategori?->kayitTuru?->kod ?: ($kategori ? 'Tanımlı' : 'Yok') }}</span>
            </summary>
            @if ($kategori)
                <dl class="tsb-liste">
                    <div><dt>Muhasebe Kaydı</dt><dd>{{ $kategori->muhasebeKayit ? trim(($kategori->muhasebeKayit->kod ? '['.$kategori->muhasebeKayit->kod.'] ' : '').$kategori->muhasebeKayit->ad) : '—' }}</dd></div>
                    <div><dt>Kayıt Türü</dt><dd>{{ $kategori->kayitTuru ? trim(($kategori->kayitTuru->kod ? '['.$kategori->kayitTuru->kod.'] ' : '').$kategori->kayitTuru->ad) : '—' }}</dd></div>
                    <div><dt>Mevcut Kullanım</dt><dd>{{ $kategori->mevcut_kullanim_sekli ?: '—' }}</dd></div>
                </dl>
            @else
                <p class="tsb-bos">Kategori bilgisi girilmemiş.</p>
            @endif
        </details>

        {{-- Fotoğraflar --}}
        <details class="tsb-bolum" @if ($resimler->isNotEmpty()) data-tsb-foto @endif>
            <summary>
                <span class="tsb-ikon is-gri"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></span>
                <span class="tsb-bolum-ad">Fotoğraflar</span>
                <span class="tsb-say {{ $resimler->isNotEmpty() ? '' : 'is-bos' }}">{{ $resimler->count() }}</span>
            </summary>
            @if ($resimler->isEmpty())
                <p class="tsb-bos">Fotoğraf eklenmemiş.</p>
            @else
                <div class="tsb-foto">
                    @foreach ($resimler as $r)
                        <a href="{{ $r->url() }}" target="_blank" rel="noopener" class="{{ $r->kapak_mi ? 'is-kapak' : '' }}">
                            <img src="{{ $r->url() }}" alt="Taşınmaz fotoğrafı {{ $loop->iteration }}" loading="lazy">
                        </a>
                    @endforeach
                </div>
            @endif
        </details>
    </div>

    <p class="tsb-dipnot">Kayıtlar bilgi amaçlıdır; düzenleme ilgili modülde yapılır.</p>
</div>
