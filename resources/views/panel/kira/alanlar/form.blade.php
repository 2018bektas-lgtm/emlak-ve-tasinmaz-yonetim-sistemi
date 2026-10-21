@extends('layouts.panel')

@section('title', $alan ? 'Kira Alanı Düzenle' : 'Yeni Kira Alanı')

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
    $d = fn (string $ad, $varsayilan = null) => old($ad, $v[$ad] ?? $varsayilan);
    $bedeller = old('bedeller', $v['bedeller']);

    // Haritaya basılacak sınır: önce formdan dönen değer (doğrulama hatası),
    // sonra seçili poligon, sonra haritadan gelen parsel geometrisi.
    $hamKoordinat = old('koordinat', $v['koordinat'] ?? '');
    $formGeometri = $hamKoordinat ? json_decode($hamKoordinat, true) : $poligon?->geoJson();

    $kmhAyar = [
        'duzenlenir' => true,
        'hedef' => '#kiraKoordinat',
        'geometri' => $formGeometri,
        'ekler' => [],
        'tkgm' => url('/panel/ajax/tkgm-parsel-adaparsel'),
        'tkgmNokta' => url('/panel/ajax/tkgm-parsel'),
        'alanlar' => ['mahalle' => '#mahalle_id', 'ada' => '#ada', 'parsel' => '#parsel', 'yuzolcum' => '#yuzolcum'],
        'buyukHarita' => route('panel.kira.harita'),
    ];

    // Dosyası olan alanın kiracı/sözleşmesi bu formda değil, dosyada değişir
    $dosyali = $alan && $dosya;
    $aktifDonem = $dosya?->aktifSozlesme;
@endphp

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Kira İşlemleri · Kira Alanı</p>
        <h2>{{ $alan ? 'Kira Alanı Düzenle' : 'Yeni Kira Alanı' }}@if ($alan?->ad) <span class="kr-hero-alt">{{ $alan->ad }}</span>@endif</h2>
        <small>Önce kiraya verilecek yer eklenir; kiraya verildiğinde dosya numarası bağlanır. Aynı yerde farklı dosya numaralı birden çok kira olabilir.</small>
    </div>
    <div class="page-hero-actions">
        @if ($alan)<a href="{{ route('panel.kira.alanlar.detay', $alan->id) }}" class="btn-cancel">Karta dön</a>@endif
        <a href="{{ route('panel.kira.alanlar.index') }}" class="btn-cancel">Listeye dön</a>
    </div>
</section>

@if ($errors->any())
    <div class="flash-error" role="alert">
        <strong>Kayıt tamamlanmadı.</strong>
        <ul>@foreach ($errors->all() as $hata)<li>{{ $hata }}</li>@endforeach</ul>
    </div>
@endif

@if (($onDolgu['koordinat'] ?? null) || ($onDolgu['ada'] ?? null))
    <div class="kr-harita-serit" role="status">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4L3 6v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14M15 6v14"/></svg>
        <span>
            Parsel haritadan alındı:
            <strong>{{ $onDolgu['ada'] ?? '—' }}/{{ $onDolgu['parsel'] ?? '—' }}</strong>
            @if (($onDolgu['ilce_ad'] ?? null)) · {{ $onDolgu['ilce_ad'] }}@if (($onDolgu['mahalle_ad'] ?? null)) · {{ $onDolgu['mahalle_ad'] }}@endif @endif
        </span>
    </div>
@endif

<form method="POST" action="{{ $alan ? route('panel.kira.alanlar.guncelle', $alan->id) : route('panel.kira.alanlar.kaydet') }}" class="form-shell" id="alanForm">
    @csrf
    @if ($alan)@method('PUT')@endif
    <input type="hidden" id="kiraKoordinat" name="koordinat" value="{{ $hamKoordinat }}">
    <input type="hidden" id="poligonId" name="poligon_id" value="{{ old('poligon_id', $v['poligon_id']) }}">

    {{-- 1 · Yer --}}
    @include('panel.kira._konum', [
        'no' => 1,
        'baslik' => 'Yer ve Sınır',
        'aciklama' => 'Sınırı çizin, ada/parselden getirin ya da haritadan seçin. Aynı parselde açılmış sınır varsa yeniden kullanılır.',
        'envanterIpucu' => 'Boş bırakılırsa sınırın içindeki envanter taşınmazı otomatik bağlanır.',
        'haritaIpucu' => $poligon
            ? 'Seçili sınır: <strong>#'.$poligon->id.'</strong>'.($poligon->ad ? ' · '.e($poligon->ad) : '')
                .($komsular->isNotEmpty() ? ' · bu sınırda <strong>'.$komsular->count().'</strong> kayıt daha var' : '')
                .'. Yeni çizerseniz bu kayıt yeni sınıra bağlanır.'
            : null,
    ])

    @if ($komsular->isNotEmpty())
        <div class="kr-komsu-serit" role="note">
            <span>Bu sınırdaki diğer kayıtlar:</span>
            @foreach ($komsular as $k)
                <a href="{{ route('panel.kira.alanlar.detay', $k->id) }}">{{ $k->dosya?->dosya_no ?? 'dosyasız' }} · {{ $k->ad ?: 'adsız' }}</a>
            @endforeach
        </div>
    @endif

    {{-- 2 · Alan --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">2</span>
            <div class="form-section-head-text">
                <h3>Kira Alanı</h3>
                <small>Kiraya verilen (ya da verilecek) yerin kendisi.</small>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="ad">Alan Adı</label>
                <input id="ad" name="ad" class="form-input" value="{{ $d('ad') }}" maxlength="200" placeholder="Örn. Büfe, Tenis kortu, Otopark">
            </div>
            <div class="form-field">
                <label for="alan">Kiraya Verilen Alan (m²)</label>
                <input id="alan" name="alan" type="number" class="form-input td-mono" value="{{ $d('alan') }}" step="0.01" min="0">
            </div>
            <div class="form-field">
                <label for="is_kolu_id">İş Kolu</label>
                <select id="is_kolu_id" name="is_kolu_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($isKollari as $i)
                        <option value="{{ $i->id }}" @selected((string) $d('is_kolu_id') === (string) $i->id)>{{ $i->ad }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="faaliyet_konusu">Faaliyet Konusu</label>
                <input id="faaliyet_konusu" name="faaliyet_konusu" class="form-input" value="{{ $d('faaliyet_konusu') }}" maxlength="200">
            </div>
            <div class="form-field">
                <label for="kiralama_amaci">Kiralama Amacı</label>
                <input id="kiralama_amaci" name="kiralama_amaci" class="form-input" value="{{ $d('kiralama_amaci') }}" maxlength="200">
            </div>
            <div class="form-field">
                <label for="alt_kiraci">Alt Kiracı</label>
                <input id="alt_kiraci" name="alt_kiraci" class="form-input" value="{{ $d('alt_kiraci') }}" maxlength="250">
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label>Doluluk <span class="required">*</span></label>
                <div class="kr-secim">
                    <label class="kr-secim-kart" style="--kr-renk:#16a34a">
                        <input type="radio" name="dolu_bos" value="bos" @checked($d('dolu_bos') === 'bos')>
                        <span>Boş</span>
                    </label>
                    <label class="kr-secim-kart" style="--kr-renk:#dc2626">
                        <input type="radio" name="dolu_bos" value="dolu" @checked($d('dolu_bos') === 'dolu')>
                        <span>Dolu (kirada)</span>
                    </label>
                </div>
                <span class="hint">Dolu seçilince kiralama bilgileri istenir.</span>
            </div>
            @if ($alan)
                <div class="form-field kr-onaylar">
                    <label class="kr-satir-onay"><input type="checkbox" name="aktif_mi" value="1" @checked(old('aktif_mi', $v['aktif_mi']))> Kayıt aktif</label>
                </div>
            @endif
        </div>
    </section>

    {{-- Kiralama: yalnız doluyken --}}
    <div id="kiralamaBolum" data-kira-grup>
        @if ($dosyali)
            {{-- Dosyası olan alan: kira bilgisi dosyadan gelir, burada değiştirilmez --}}
            <section class="form-section">
                <div class="form-section-head">
                    <span class="form-section-num">3</span>
                    <div class="form-section-head-text">
                        <h3>Kiralama</h3>
                        <small>Bu alan bir dosyaya bağlı; kiracı, sözleşme ve bedel dosyada tutulur.</small>
                    </div>
                </div>
                <div class="kr-dosya-ozet">
                    <div>
                        <span>Dosya No</span>
                        <a href="{{ route('panel.kira.detay', $dosya->id) }}" class="td-mono"><strong>{{ $dosya->dosya_no }}</strong></a>
                    </div>
                    <div><span>Kiracı</span><strong>{{ $dosya->guncelKiraci()?->ad_unvan ?? '—' }}</strong></div>
                    <div><span>Sözleşme</span><strong>{{ $aktifDonem?->baslangic_tarihi?->format('d.m.Y') ?? '—' }} – {{ $aktifDonem?->bitis_tarihi?->format('d.m.Y') ?? '—' }}</strong></div>
                    <div class="kr-dosya-ozet-eylem">
                        @can('kira.duzenle')<a href="{{ route('panel.kira.duzenle', $dosya->id) }}" class="btn-cancel">Dosyayı düzenle</a>@endcan
                        @can('kira.olustur')<a href="{{ route('panel.kira.donem.olustur', $dosya->id) }}" class="btn-cancel">Kiracı değişti</a>@endcan
                    </div>
                </div>
            </section>
        @else
            {{-- 3 · Dosya numarası: girilince sistem yeni / bu yerde var / başka yerde var der --}}
            <section class="form-section">
                <div class="form-section-head">
                    <span class="form-section-num">3</span>
                    <div class="form-section-head-text">
                        <h3>Dosya Numarası</h3>
                        <small>Yeni numara dosya açar. Bu yerde zaten olan numara yeni kiracılık dönemi olur, eskisi geçmişe düşer. Başka yerdeki numara bu alanı o dosyaya ek yer olarak bağlar.</small>
                    </div>
                </div>
                <div class="form-row form-row-3">
                    <div class="form-field">
                        <label for="dosya_no">Dosya No <span class="required">*</span></label>
                        <input id="dosya_no" name="dosya_no" class="form-input td-mono" value="{{ $d('dosya_no') }}" maxlength="50" autocomplete="off">
                        @error('dosya_no')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-field kr-dosya-durum-alan">
                        <div class="kr-dosya-durum" id="dosyaDurum" hidden></div>
                    </div>
                </div>
                <div class="form-row" id="devamOnayBolum" @if (! $errors->has('devam_onayi')) hidden @endif>
                    <div class="form-field">
                        <label class="kr-satir-onay kr-uyari-onay">
                            <input type="checkbox" name="devam_onayi" value="1" @checked(old('devam_onayi'))>
                            <span id="devamOnayMetin">{{ $errors->first('devam_onayi') ?: 'Süresi dolmamış kira erken kapatılsın ve yeni dönem başlasın.' }}</span>
                        </label>
                    </div>
                </div>
            </section>

            {{-- Yalnız yeni dosyada: dosyanın kendi bilgileri --}}
            <div data-kira-grup="yeni">
                <section class="form-section">
                    <div class="form-section-head">
                        <span class="form-section-num">4</span>
                        <div class="form-section-head-text">
                            <h3>Dosya Bilgileri</h3>
                            <small>Yeni dosya açılırken sorulur; işyeri adı boşsa alan adı kullanılır.</small>
                        </div>
                    </div>
                    <div class="form-row form-row-3">
                        <div class="form-field">
                            <label for="eski_dosya_no">Eski Dosya No</label>
                            <input id="eski_dosya_no" name="eski_dosya_no" class="form-input td-mono" value="{{ $d('eski_dosya_no') }}" maxlength="50">
                        </div>
                        <div class="form-field">
                            <label for="isyeri_adi">İşyeri Adı</label>
                            <input id="isyeri_adi" name="isyeri_adi" class="form-input" value="{{ $d('isyeri_adi') }}" maxlength="200">
                        </div>
                        <div class="form-field">
                            <label for="ana_dosya_id">Ana Dosya</label>
                            <select id="ana_dosya_id" name="ana_dosya_id" class="form-select">
                                <option value="">Yok (bağımsız dosya)</option>
                                @foreach ($anaDosyalar as $a)
                                    <option value="{{ $a->id }}" @selected((string) $d('ana_dosya_id') === (string) $a->id)>{{ $a->dosya_no }}@if ($a->isyeri_adi) — {{ $a->isyeri_adi }}@endif</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-row form-row-3">
                        <div class="form-field">
                            <label>İhale Durumu <span class="required">*</span></label>
                            <div class="kr-secim">
                                <label class="kr-secim-kart" style="--kr-renk:#6366f1">
                                    <input type="radio" name="ihale_turu" value="ihaleli" @checked($d('ihale_turu') === 'ihaleli')>
                                    <span>İhaleli</span>
                                </label>
                                <label class="kr-secim-kart" style="--kr-renk:#64748b">
                                    <input type="radio" name="ihale_turu" value="ihalesiz" @checked($d('ihale_turu') === 'ihalesiz')>
                                    <span>İhalesiz</span>
                                </label>
                            </div>
                        </div>
                        <div class="form-field">
                            <label for="muhasebe_niteligi_id">Muhasebe Niteliği</label>
                            <select id="muhasebe_niteligi_id" name="muhasebe_niteligi_id" class="form-select">
                                <option value="">Seçin</option>
                                @foreach ($muhasebeNitelikleri as $m)
                                    <option value="{{ $m->id }}" @selected((string) $d('muhasebe_niteligi_id') === (string) $m->id)>{{ $m->ad }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="arac_kapasitesi">Araç Kapasitesi</label>
                            <input id="arac_kapasitesi" name="arac_kapasitesi" type="number" class="form-input td-mono" value="{{ $d('arac_kapasitesi') }}" min="0">
                            <span class="hint">Otopark ve durak dosyalarında.</span>
                        </div>
                    </div>
                    <div class="form-row form-row-3">
                        <div class="form-field">
                            <label for="hukuki_durum_id">Hukuki Durum</label>
                            <select id="hukuki_durum_id" name="hukuki_durum_id" class="form-select">
                                <option value="">Seçin</option>
                                @foreach ($hukukiDurumlar as $h)
                                    <option value="{{ $h->id }}" @selected((string) $d('hukuki_durum_id') === (string) $h->id)>{{ $h->ad }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="kiracilik_durum_id">Kiracılık Durumu</label>
                            <select id="kiracilik_durum_id" name="kiracilik_durum_id" class="form-select">
                                <option value="">Seçin</option>
                                @foreach ($kiracilikDurumlar as $k)
                                    <option value="{{ $k->id }}" @selected((string) $d('kiracilik_durum_id') === (string) $k->id)>{{ $k->ad }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="uyari">Uyarı</label>
                            <input id="uyari" name="uyari" class="form-input" value="{{ $d('uyari') }}" maxlength="300">
                            <span class="hint">Listede kırmızı etiket olarak görünür.</span>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-field kr-onaylar">
                            <label class="kr-satir-onay"><input type="checkbox" name="sozlesme_aktif_mi" value="1" @checked($d('sozlesme_aktif_mi'))> Sözleşme yürürlükte</label>
                            <label class="kr-satir-onay"><input type="checkbox" name="tahliye_ihbarnamesi_mi" value="1" @checked($d('tahliye_ihbarnamesi_mi'))> Tahliye ihbarnamesi çekildi</label>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Yeni dosyada da aynı yerde yeni dönemde de: kiracı, sözleşme, bedel --}}
            <div data-kira-grup="donem">
                @include('panel.kira._kiraci', ['no' => 5, 'baslik' => 'Kiracı'])
                @include('panel.kira._sozlesme', ['no' => 6])
                @include('panel.kira._bedeller', ['no' => 7])
            </div>
        @endif
    </div>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">8</span>
            <div class="form-section-head-text"><h3>Not</h3></div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label for="aciklama">Açıklama / Not</label>
                <textarea id="aciklama" name="aciklama" class="form-textarea" rows="3" maxlength="5000">{{ $d('aciklama') }}</textarea>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ $alan ? route('panel.kira.alanlar.detay', $alan->id) : route('panel.kira.alanlar.index') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit" id="alanKaydet">{{ $alan ? 'Değişiklikleri Kaydet' : 'Kira Alanını Ekle' }}</button>
    </div>
</form>

@include('panel.kira._form-betik')

<script>
(function () {
    const form = document.getElementById('alanForm');
    const kiralama = document.getElementById('kiralamaBolum');
    const dosyaNo = document.getElementById('dosya_no');
    const durumKutu = document.getElementById('dosyaDurum');
    const devamBolum = document.getElementById('devamOnayBolum');
    const poligonId = document.getElementById('poligonId');
    const koordinat = document.getElementById('kiraKoordinat');
    const kaydetBtn = document.getElementById('alanKaydet');
    const KONTROL = @json(route('panel.kira.alanlar.dosya-kontrol'));
    const ALAN_ID = @json($alan?->id);
    const YENI_ALAN = @json(! $alan);
    let durum = null;

    /** Gizli grup gönderilmesin ve zorunlu alanları formu kilitlemesin. */
    function grup(el, acik) {
        if (!el) return;
        el.hidden = !acik;
        el.querySelectorAll('input, select, textarea, button').forEach(function (g) {
            if (g.type === 'button') return;
            g.disabled = !acik;
        });
    }

    /** Görünen bölümler 1, 2, 3… diye sırayla numaralanır. */
    function numaralandir() {
        let n = 0;
        form.querySelectorAll('.form-section').forEach(function (s) {
            if (s.closest('[hidden]')) return;
            const no = s.querySelector('.form-section-num');
            if (no) no.textContent = ++n;
        });
    }

    function tazele() {
        const dolu = form.querySelector('[name="dolu_bos"]:checked')?.value === 'dolu';
        grup(kiralama, dolu);
        if (dolu && dosyaNo) {
            // Durum bilinmiyorsa yeni dosya varsayılır: bütün alanlar görünür
            grup(form.querySelector('[data-kira-grup="yeni"]'), durum === null || durum === 'yeni');
            grup(form.querySelector('[data-kira-grup="donem"]'), durum !== 'baska_yer');
        }
        if (kaydetBtn) {
            kaydetBtn.textContent = !dolu
                ? (YENI_ALAN ? 'Kira Alanını Ekle' : 'Değişiklikleri Kaydet')
                : ({ yeni: 'Ekle ve Dosyayı Aç', ayni_yer: 'Yeni Dönemi Kaydet', baska_yer: 'Ekle ve Dosyaya Bağla' }[durum] || 'Kaydet');
        }
        numaralandir();
    }

    function durumBas(veri) {
        durum = veri.durum;
        const d = veri.dosya;
        let html = '';
        if (durum === 'yeni') {
            html = '<strong>Yeni dosya</strong><span>Bu numarayla yeni bir kira dosyası açılacak.</span>';
        } else if (durum === 'ayni_yer') {
            html = '<strong>Bu yerde kayıtlı: ' + kacis(d.dosya_no) + '</strong>' +
                '<span>Mevcut kiracı: ' + kacis(d.kiraci || '—') + (d.bitis ? ' · bitiş ' + kacis(d.bitis) : '') +
                '. Kaydedince yeni kiracılık dönemi açılır, önceki dönem geçmişe düşer.</span>';
        } else if (durum === 'baska_yer') {
            html = '<strong>Başka yerde kayıtlı: ' + kacis(d.dosya_no) + '</strong>' +
                '<span>' + kacis(d.isyeri || '') + (d.kiraci ? ' · ' + kacis(d.kiraci) : '') + ' · ' + d.alan_sayisi + ' yer. ' +
                'Bu alan dosyaya ek yer olarak bağlanır; kira bilgileri dosyadan gelir.</span>' +
                '<a href="' + kacis(d.detay) + '" target="_blank" rel="noopener">Dosyayı aç</a>';
        }
        durumKutu.className = 'kr-dosya-durum is-' + (durum || 'bos');
        durumKutu.innerHTML = html;
        durumKutu.hidden = !html;

        const devamVar = durum === 'ayni_yer' && veri.devam_eden;
        if (devamBolum) {
            devamBolum.hidden = !devamVar;
            if (devamVar) document.getElementById('devamOnayMetin').textContent = veri.devam_eden;
        }
        tazele();
    }

    function kacis(d) {
        return String(d === null || d === undefined ? '' : d).replace(/[&<>"']/g, function (k) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[k];
        });
    }

    let zamanlayici = null;
    let istek = null;
    function kontrolEt() {
        if (!dosyaNo) return;
        clearTimeout(zamanlayici);
        const no = dosyaNo.value.trim();
        if (!no) { durumBas({ durum: null }); return; }
        zamanlayici = setTimeout(async function () {
            if (istek) istek.abort();
            istek = new AbortController();
            const q = new URLSearchParams({ dosya_no: no });
            if (poligonId.value) q.set('poligon_id', poligonId.value);
            if (ALAN_ID) q.set('alan_id', ALAN_ID);
            try {
                const yanit = await fetch(KONTROL + '?' + q, { headers: { Accept: 'application/json' }, signal: istek.signal });
                if (yanit.ok) durumBas(await yanit.json());
            } catch (e) { /* ağ hatası: sunucu kaydederken yine karar verir */ }
        }, 350);
    }

    form.querySelectorAll('[name="dolu_bos"]').forEach(function (r) { r.addEventListener('change', tazele); });
    if (dosyaNo) {
        dosyaNo.addEventListener('input', kontrolEt);
        if (dosyaNo.value.trim()) kontrolEt();
    }

    // Kullanıcı yeni sınır çizerse seçili poligon bırakılır; "bu yer" değiştiği için numara yeniden sorulur
    koordinat.addEventListener('change', function () {
        if (koordinat.value) poligonId.value = '';
        kontrolEt();
    });

    tazele();
})();
</script>
@endsection
