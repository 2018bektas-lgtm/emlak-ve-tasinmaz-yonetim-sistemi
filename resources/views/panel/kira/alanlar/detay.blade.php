@extends('layouts.panel')

@section('title', 'Kira Alanı · '.($alan->ad ?: '#'.$alan->id))

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=5">
    <link rel="stylesheet" href="https://js.arcgis.com/4.30/esri/themes/light/main.css">
    <link rel="stylesheet" href="{{ asset('css/kira.css') }}?v=12">
@endpush

@push('scripts')
    <script src="https://js.arcgis.com/4.30/"></script>
    <script src="{{ asset('js/kira-harita-kutu.js') }}?v=3"></script>
@endpush

@php
    $tl = fn ($n) => $n === null ? '—' : number_format((float) $n, 2, ',', '.');
    $gun = fn ($t) => $t?->format('d.m.Y') ?: '—';
    $d = $alan->dosya;
    $t = $d?->tasinmaz;
    $p = $alan->poligon;
    $bolumNo = 0;
    $no = function () use (&$bolumNo) { return ++$bolumNo; };

    // Kiracı, dönem ve bedel dosyanın yürürlükteki döneminden gelir
    // (alan kaydedilirken eşitlenir). Dosyası olmayan alan henüz kirada değil.
    $s = $d?->aktifSozlesme;
    $baslangic = $s?->baslangic_tarihi;
    $bitis = $s?->bitis_tarihi;
    $bedel = $s?->guncelBedel()?->yillik_bedel;
    $kalan = $d?->sozlesme_aktif_mi ? $s?->kalanGun() : null;

    // Konum: dosyanın tapu bilgisi, dosya yoksa sınırın parseli
    $ilce = $t?->ilce?->ad ?? $p?->mahalle?->ilce?->ad;
    $mahalle = $t?->mahalle?->ad ?? $p?->mahalle?->ad;
    $adaParsel = $t?->adaParsel() ?? $p?->adaParsel();
@endphp

@section('content')
@if (session('basari'))<div class="flash-success" role="status">{{ session('basari') }}</div>@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Kira İşlemleri · Kira Alanı</p>
        <h2>
            @if ($d)
                <a href="{{ route('panel.kira.detay', $d->id) }}" class="kr-ust-dosya">{{ $d->dosya_no }}</a>
                <span class="kr-ust-ayrac">›</span>
            @endif
            {{ $alan->ad ?: 'Adsız alan' }}
        </h2>
        <small>
            <span class="kr-durum" style="--kr-renk:{{ $alan->dolu_bos->renk() }}">{{ $alan->dolu_bos->etiket() }}</span>
            @if ($alan->isKolu) · {{ $alan->isKolu->ad }}@endif
            @unless ($alan->aktif_mi) · <strong>Pasif</strong>@endunless
        </small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.kira.alanlar.index') }}" class="btn-cancel">Alan listesi</a>
        @if ($p)
            <a href="{{ route('panel.kira.harita', ['merkez' => $p->merkez_lat.','.$p->merkez_lng, 'zoom' => 18]) }}" class="btn-cancel">Haritada Göster</a>
        @endif
        @can('kira.duzenle')
            <a href="{{ route('panel.kira.alanlar.duzenle', $alan->id) }}" class="{{ $d ? 'btn-submit' : 'btn-cancel' }}">Düzenle</a>
            @unless ($d)
                {{-- Boş alan: dosya numarası bağlanınca kiraya verilmiş olur --}}
                <a href="{{ route('panel.kira.alanlar.duzenle', ['id' => $alan->id, 'kirala' => 1]) }}" class="btn-submit">Kiraya Ver</a>
            @endunless
        @endcan
    </div>
</section>

{{-- İki sayfanın farkı: burası kiraya verilen yer, sözleşmenin kendisi dosyada --}}
<div class="kr-kunye">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7l8-4 8 4-2 12-6 2-6-2z"/></svg>
    <div>
        <strong>Bu sayfa kiraya verilen <em>yeri</em> anlatır:</strong> sınırı, kaç m² olduğu, hangi iş kolunda kullanıldığı ve dolu mu boş mu olduğu.
        <br>
        @if ($d)
            Sözleşme numarası, yıl yıl kira bedelleri, ihale ve encümen kararları
            <a href="{{ route('panel.kira.detay', $d->id) }}">{{ $d->dosya_no }} numaralı kira dosyasında</a>.
            @if ($dosyaAlanSayisi > 1)
                Bu dosyada <strong>{{ $dosyaAlanSayisi }} ayrı yer</strong> kiraya verilmiş.
            @endif
        @else
            Bu alan henüz bir kira dosyasına bağlı değil. <strong>Kiraya Ver</strong> ile dosya numarası bağlandığında kiracı ve sözleşme bilgileri buraya gelir.
        @endif
    </div>
</div>

<div class="dg-ozet kr-ozet">
    <div class="dg-ozet-kutu"><span>Kiraya verilen alan</span><strong>{{ $tl($alan->alan) }} <em>m²</em></strong></div>
    <div class="dg-ozet-kutu"><span>Sınır</span><strong class="kr-ozet-kucuk">{{ $p ? '#'.$p->id.($p->alanlar->count() > 1 ? ' · '.$p->alanlar->count().' kayıt paylaşıyor' : '') : 'Bağlı değil' }}</strong></div>
    <div class="dg-ozet-kutu {{ $kalan !== null && $kalan <= 90 ? 'is-uyari' : '' }}">
        <span>Kalan süre</span>
        <strong>{{ $kalan === null ? '—' : ($kalan < 0 ? abs($kalan).' gün geçti' : $kalan) }} @if ($kalan !== null && $kalan >= 0)<em>gün</em>@endif</strong>
    </div>
    <div class="dg-ozet-kutu"><span>Kira dönemi</span><strong class="kr-ozet-kucuk">{{ $gun($baslangic) }} – {{ $gun($bitis) }}</strong></div>
</div>

{{-- 1 · Alanın kendi bilgileri --}}
<section class="form-section">
    <div class="form-section-head">
        <span class="form-section-num">{{ $no() }}</span>
        <div class="form-section-head-text">
            <h3>Alan Bilgileri</h3>
            <small>Yalnızca bu yere ait olanlar</small>
        </div>
    </div>
    <dl class="dg-bilgi">
        <div><dt>Alan Adı</dt><dd>{{ $alan->ad ?: '—' }}</dd></div>
        <div><dt>Kiraya Verilen Alan</dt><dd class="td-mono">{{ $tl($alan->alan) }} m²</dd></div>
        <div><dt>İş Kolu</dt><dd>{{ $alan->isKolu?->ad ?: '—' }}</dd></div>
        <div><dt>Faaliyet Konusu</dt><dd>{{ $alan->faaliyet_konusu ?: '—' }}</dd></div>
        <div><dt>Kiralama Amacı</dt><dd>{{ $alan->kiralama_amaci ?: '—' }}</dd></div>
        <div><dt>Doluluk</dt><dd><span class="kr-durum" style="--kr-renk:{{ $alan->dolu_bos->renk() }}">{{ $alan->dolu_bos->etiket() }}</span></dd></div>
        <div><dt>Kiracılık Durumu</dt><dd>{{ $alan->kiracilikDurum?->ad ?: '—' }}</dd></div>
        <div><dt>Hukuki Durum</dt><dd>{{ $alan->hukukiDurum?->ad ?: '—' }}</dd></div>
        <div><dt>Tahliye İhbarnamesi</dt><dd>{{ $alan->tahliye_ihbarnamesi_mi ? 'Çekildi' : 'Çekilmedi' }}</dd></div>
        <div><dt>Kayıt Durumu</dt><dd>{{ $alan->aktif_mi ? 'Aktif' : 'Pasif' }}</dd></div>
    </dl>
    @if ($alan->adres)<p class="kr-aciklama">{{ $alan->adres }}</p>@endif
    @if ($alan->aciklama)<p class="kr-aciklama">{{ $alan->aciklama }}</p>@endif
</section>

{{-- 2 · Sınır --}}
<section class="form-section">
    <div class="form-section-head">
        <span class="form-section-num">{{ $no() }}</span>
        <div class="form-section-head-text">
            <h3>Sınır ve Konum</h3>
            <small>Haritada bu yeri çizen poligon</small>
        </div>
    </div>
    <dl class="dg-bilgi">
        <div><dt>İlçe</dt><dd>{{ $ilce ?: '—' }}</dd></div>
        <div><dt>Mahalle</dt><dd>{{ $mahalle ?: '—' }}</dd></div>
        <div><dt>Ada / Parsel</dt><dd class="td-mono">{{ $adaParsel ?: '—' }}</dd></div>
        <div>
            <dt>Envanter Kaydı</dt>
            <dd>
                @if ($alan->tasinmaz)
                    <span class="td-mono">#{{ $alan->tasinmaz->id }}</span>@if ($alan->tasinmaz->ada) · {{ $alan->tasinmaz->ada }}/{{ $alan->tasinmaz->parsel }}@endif
                @else
                    Sınırın içinde envanter taşınmazı yok
                @endif
            </dd>
        </div>
        <div><dt>Poligon</dt><dd>{{ $p ? '#'.$p->id.' · '.($p->ad ?: 'adsız') : 'Bağlı değil' }}</dd></div>
        <div><dt>Poligon Kaynağı</dt><dd>{{ $p ? ($p->kaynak === 'tkgm' ? 'TKGM parseli' : ($p->kaynak === 'cizim' ? 'Haritada çizim' : 'İçe aktarım')) : '—' }}</dd></div>
        <div><dt>Bu sınırı kullanan kayıt</dt><dd>{{ $p ? $p->alanlar->count() : '—' }}</dd></div>
    </dl>

    @php
        $kmhAyar = [
            'duzenlenir' => false,
            'geometri' => $p?->geoJson(),
            'buyukHarita' => $p
                ? route('panel.kira.harita', ['merkez' => $p->merkez_lat.','.$p->merkez_lng, 'zoom' => 18])
                : route('panel.kira.harita'),
        ];
    @endphp
    <div class="kmh is-kisa" data-kmh="{{ json_encode($kmhAyar) }}"></div>

    {{-- Aynı poligonu paylaşan kayıtlar --}}
    @if ($komsuKayitlar->isNotEmpty())
        <h4 class="kr-alt-baslik">Aynı Sınırdaki Diğer Kayıtlar <span>{{ $komsuKayitlar->count() }}</span></h4>
        <div class="tl-tablo-wrap">
            <table class="tl-tablo">
                <thead><tr><th>Alan</th><th>Dosya</th><th>Kiracı</th><th>Dönem</th><th class="is-sag">Alan (m²)</th><th>Durum</th><th></th></tr></thead>
                <tbody>
                    @foreach ($komsuKayitlar as $k)
                        <tr>
                            <td>{{ $k->ad ?: 'Adsız alan' }}</td>
                            <td class="td-mono">{{ $k->dosya?->dosya_no }}</td>
                            <td>{{ $k->kiraci_ad_unvan ?: '—' }}</td>
                            <td class="td-mono">{{ $gun($k->baslangic_tarihi) }} – {{ $gun($k->bitis_tarihi) }}</td>
                            <td class="td-mono is-sag">{{ $tl($k->alan) }}</td>
                            <td><span class="kr-durum" style="--kr-renk:{{ $k->dolu_bos->renk() }}">{{ $k->dolu_bos->etiket() }}</span></td>
                            <td><a href="{{ route('panel.kira.alanlar.detay', $k->id) }}" class="btn-cancel hs-mini-btn">Kart</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- Kiracı ve dönem: dosyanın yürürlükteki döneminden --}}
<section class="form-section">
    <div class="form-section-head">
        <span class="form-section-num">{{ $no() }}</span>
        <div class="form-section-head-text">
            <h3>Kiracı ve Dönem</h3>
            <small>{{ $d ? 'Dosyanın yürürlükteki sözleşme döneminden' : 'Alan boş — kiraya verilmedi' }}</small>
        </div>
        @if ($d)
            <a href="{{ route('panel.kira.detay', $d->id) }}" class="btn-cancel hs-mini-btn dg-sag">Sözleşmeye git</a>
        @endif
    </div>
    @if ($d)
        <dl class="dg-bilgi">
            <div><dt>Kiracı</dt><dd>{{ $alan->kiraci() ?: '—' }}</dd></div>
            <div><dt>Alt Kiracı</dt><dd>{{ $alan->alt_kiraci ?: '—' }}</dd></div>
            <div><dt>Kira Dönemi</dt><dd class="td-mono">{{ $gun($baslangic) }} – {{ $gun($bitis) }}</dd></div>
            <div><dt>Yıllık Bedel</dt><dd class="td-mono">{{ $tl($bedel) }} TL</dd></div>
            <div><dt>Taksit Sayısı</dt><dd class="td-mono">{{ $s?->taksit_sayisi ?: '—' }}</dd></div>
            <div><dt>İhale</dt><dd>{{ $d->ihale_turu?->etiket() ?: '—' }}</dd></div>
        </dl>
        <p class="hint">Yıl yıl bedel geçmişi, sözleşme no, noter ve yevmiye bilgileri dosya kartında. Kiracı değiştiyse dosyadan "Kiracı Değişti" ile yeni dönem açın.</p>
    @else
        <p class="hrm-panel-ipucu">
            Bu yer şu an kirada değil.
            @can('kira.duzenle')
                <a href="{{ route('panel.kira.alanlar.duzenle', ['id' => $alan->id, 'kirala' => 1]) }}">Kiraya Ver</a> ile dosya numarası, kiracı ve sözleşme bilgilerini girin.
            @endcan
        </p>
    @endif
</section>

{{-- Belgeler --}}
<section class="form-section">
    <div class="form-section-head">
        <span class="form-section-num">{{ $no() }}</span>
        <div class="form-section-head-text">
            <h3>Alan Belgeleri</h3>
            <small>Yalnızca bu yere eklenenler · dosyanın genel belgeleri dosya kartında</small>
        </div>
        @if ($d)
            @can('kira.duzenle')
                <button type="button" class="btn-cancel hs-mini-btn dg-sag" data-belge-ac>+ Belge Yükle</button>
            @endcan
        @endif
    </div>

    @if ($d)
    @can('kira.duzenle')
        <form method="POST" action="{{ route('panel.kira.belge.yukle') }}" enctype="multipart/form-data" class="kr-belge-form" id="belgeForm" hidden>
            @csrf
            <input type="hidden" name="kira_dosya_id" value="{{ $alan->kira_dosya_id }}">
            <input type="hidden" name="kira_alan_id" value="{{ $alan->id }}">
            <div class="form-row form-row-3">
                <div class="form-field">
                    <label for="belge_tur">Tür</label>
                    <select id="belge_tur" name="tur" class="form-select">
                        @foreach (\App\Enums\KiraBelgeTuru::cases() as $tur)
                            <option value="{{ $tur->value }}">{{ $tur->etiket() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label for="belge_ad">Belge Adı</label>
                    <input id="belge_ad" name="ad" class="form-input" maxlength="250" placeholder="Boşsa dosya adı kullanılır">
                </div>
                <div class="form-field">
                    <label for="belge_dosya">Dosya <span class="required">*</span></label>
                    <input id="belge_dosya" name="dosya" type="file" class="form-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" data-belge-kapat>Vazgeç</button>
                <button type="submit" class="btn-submit">Yükle</button>
            </div>
        </form>
    @endcan
    @endif

    @if ($alan->belgeler->isEmpty())
        <p class="hint">Bu alana henüz belge eklenmemiş.</p>
    @else
        <div class="tl-tablo-wrap">
            <table class="tl-tablo">
                <thead><tr><th>Tür</th><th>Ad</th><th>Yükleyen</th><th>Tarih</th><th class="is-sag">Boyut</th><th></th></tr></thead>
                <tbody>
                    @foreach ($alan->belgeler as $b)
                        <tr>
                            <td>{{ $b->tur->etiket() }}</td>
                            <td>{{ $b->ad }}</td>
                            <td>{{ $b->yukleyen ? $b->yukleyen->ad.' '.$b->yukleyen->soyad : '—' }}</td>
                            <td class="td-mono">{{ $b->created_at?->format('d.m.Y') }}</td>
                            <td class="td-mono is-sag">{{ $b->boyut ? number_format($b->boyut / 1024, 0, ',', '.').' KB' : '—' }}</td>
                            <td class="dg-islem">
                                <a href="{{ $b->url() }}" target="_blank" rel="noopener" class="btn-cancel hs-mini-btn">Aç</a>
                                @can('kira.sil')
                                    <form method="POST" action="{{ route('panel.kira.belge.sil', $b->id) }}" class="dg-sil-form"
                                          onsubmit="return confirm('Bu belge silinsin mi?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="dg-ikon-btn is-sil" title="Belgeyi sil">
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
    @endif
</section>

<script>
(function () {
    const ac = document.querySelector('[data-belge-ac]');
    const form = document.getElementById('belgeForm');
    if (!ac || !form) return;
    ac.addEventListener('click', () => { form.hidden = !form.hidden; if (!form.hidden) form.querySelector('select').focus(); });
    form.querySelector('[data-belge-kapat]').addEventListener('click', () => { form.hidden = true; });
})();
</script>
@endsection
