@extends('layouts.panel')

@section('title', $alan ? 'Kira Alanı Düzenle' : 'Yeni Kira Alanı')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=5">
    <link rel="stylesheet" href="https://js.arcgis.com/4.30/esri/themes/light/main.css">
    <link rel="stylesheet" href="{{ asset('css/kira.css') }}?v=10">
@endpush

@push('scripts')
    <script src="https://js.arcgis.com/4.30/"></script>
    <script src="{{ asset('js/kira-harita-kutu.js') }}?v=2"></script>
@endpush

@php
    $d = fn (string $alanAd, $varsayilan = null) => old($alanAd, $alan?->{$alanAd} ?? $varsayilan);
    $seciliPoligon = old('poligon_id', $alan?->poligon_id ?? ($seciliPoligonId ?? null));
@endphp

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Kira İşlemleri · Alan</p>
        <h2>{{ $alan ? 'Kira Alanı Düzenle' : 'Yeni Kira Alanı' }}</h2>
        <small>Bir dosyada birden çok yer kiraya verilebilir; her alanın kendi kiracısı, süresi ve sınırı olur.</small>
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

<form method="POST" action="{{ $alan ? route('panel.kira.alanlar.guncelle', $alan->id) : route('panel.kira.alanlar.kaydet') }}" class="form-shell">
    @csrf
    @if ($alan)@method('PUT')@endif

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">1</span>
            <div class="form-section-head-text">
                <h3>Dosya ve Alan</h3>
                <small>Alan hangi kira dosyasına ait?</small>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="kira_dosya_id">Kira Dosyası <span class="required">*</span></label>
                <select id="kira_dosya_id" name="kira_dosya_id" class="form-select" required>
                    <option value="">Seçin</option>
                    @foreach ($dosyalar as $dd)
                        <option value="{{ $dd->id }}" @selected((string) $d('kira_dosya_id', $dosya?->id) === (string) $dd->id)>
                            {{ $dd->dosya_no }}@if ($dd->isyeri_adi) — {{ $dd->isyeri_adi }}@endif
                        </option>
                    @endforeach
                </select>
                @error('kira_dosya_id')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="ad">Alan Adı</label>
                <input id="ad" name="ad" class="form-input" value="{{ $d('ad') }}" maxlength="200" placeholder="Örn. Büfe, Tenis kortu, Otopark">
            </div>
            <div class="form-field">
                <label for="alan">Kiraya Verilen Alan (m²)</label>
                <input id="alan" name="alan" type="number" class="form-input td-mono" value="{{ $d('alan') }}" step="0.01" min="0">
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="is_kolu_id">İş Kolu</label>
                <select id="is_kolu_id" name="is_kolu_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($isKollari as $i)
                        <option value="{{ $i->id }}" @selected((string) $d('is_kolu_id') === (string) $i->id)>{{ $i->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="faaliyet_konusu">Faaliyet Konusu</label>
                <input id="faaliyet_konusu" name="faaliyet_konusu" class="form-input" value="{{ $d('faaliyet_konusu') }}" maxlength="200">
            </div>
            <div class="form-field">
                <label for="kiralama_amaci">Kiralama Amacı</label>
                <input id="kiralama_amaci" name="kiralama_amaci" class="form-input" value="{{ $d('kiralama_amaci') }}" maxlength="200">
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label for="adres">Kiraya Verilen Yerin Adresi</label>
                <textarea id="adres" name="adres" class="form-textarea" rows="2" maxlength="1000">{{ $d('adres') }}</textarea>
            </div>
        </div>
    </section>

    {{-- 2 · Poligon seçimi: aynı sınır birden çok kayıtta kullanılabilir --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">2</span>
            <div class="form-section-head-text">
                <h3>Sınır (Poligon)</h3>
                <small>Aynı poligon birden çok kayıtta kullanılabilir — yeniden çizmek yerine mevcut olanı seçin.</small>
            </div>
        </div>

        @if ($poligonlar->isEmpty())
            <p class="hint">Bu dosyanın parselinde kayıtlı poligon yok. Haritadan kayıt açtığınızda poligon otomatik oluşur.</p>
            <input type="hidden" name="poligon_id" value="">
        @else
            <div class="kr-poligon-liste" role="radiogroup" aria-label="Poligon seçimi">
                <label class="kr-poligon-kart {{ ! $seciliPoligon ? 'is-secili' : '' }}">
                    <input type="radio" name="poligon_id" value="" @checked(! $seciliPoligon)>
                    <span class="kr-poligon-ad">Sınır bağlama</span>
                    <small>Bu alan haritada görünmez</small>
                </label>
                @foreach ($poligonlar as $p)
                    <label class="kr-poligon-kart {{ (string) $seciliPoligon === (string) $p->id ? 'is-secili' : '' }}">
                        <input type="radio" name="poligon_id" value="{{ $p->id }}" @checked((string) $seciliPoligon === (string) $p->id)>
                        <span class="kr-poligon-ad">#{{ $p->id }} · {{ $p->ad ?: 'Adsız poligon' }}</span>
                        <small>
                            @if ($p->adaParsel()) {{ $p->adaParsel() }} · @endif
                            {{ $p->alan !== null ? number_format((float) $p->alan, 2, ',', '.').' m²' : 'alan yok' }}
                            @if ($p->alanlar_count) · <strong>{{ $p->alanlar_count }} kayıt kullanıyor</strong>@endif
                        </small>
                    </label>
                @endforeach
            </div>

            {{-- Seçilen sınır haritada: seçim değiştikçe öne çıkan poligon değişir.
                 Her poligon ayrı bir kutuda değil, tek haritada gösterilir. --}}
            @php
                $kmhAyar = [
                    'duzenlenir' => false,
                    'geometri' => $poligonlar->firstWhere('id', (int) $seciliPoligon)?->geoJson(),
                    'ekler' => $poligonlar
                        ->filter(fn ($p) => (string) $p->id !== (string) $seciliPoligon)
                        ->map(fn ($p) => ['geometri' => $p->geoJson(), 'renk' => '#f59e0b', 'ad' => $p->ad])
                        ->filter(fn ($e) => $e['geometri'] !== null)
                        ->values(),
                    'buyukHarita' => route('panel.kira.harita'),
                ];
            @endphp
            <div class="kmh is-kisa kr-poligon-harita" data-kmh="{{ json_encode($kmhAyar) }}"></div>
            <span class="hint">
                Yeni bir sınır çizmek için <a href="{{ route('panel.kira.harita') }}">Kira Haritası</a> →
                <strong>Kayıt</strong> → <strong>Yeni Kayıt</strong> adımlarını kullanın.
            </span>
        @endif
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">3</span>
            <div class="form-section-head-text">
                <h3>Kiracı ve Kira</h3>
                <small>Boş bırakılırsa dosyanın ana kiracısı geçerli sayılır.</small>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="kiraci_ad_unvan">Kiracı Ad / Unvan</label>
                <input id="kiraci_ad_unvan" name="kiraci_ad_unvan" class="form-input" value="{{ $d('kiraci_ad_unvan') }}" maxlength="250">
            </div>
            <div class="form-field">
                <label for="alt_kiraci">Alt Kiracı</label>
                <input id="alt_kiraci" name="alt_kiraci" class="form-input" value="{{ $d('alt_kiraci') }}" maxlength="250">
            </div>
            <div class="form-field">
                <label>Doluluk <span class="required">*</span></label>
                <div class="kr-secim">
                    <label class="kr-secim-kart" style="--kr-renk:#dc2626">
                        <input type="radio" name="dolu_bos" value="dolu" @checked($d('dolu_bos', 'dolu') === 'dolu' || ($alan?->dolu_bos?->value === 'dolu'))>
                        <span>Dolu</span>
                    </label>
                    <label class="kr-secim-kart" style="--kr-renk:#16a34a">
                        <input type="radio" name="dolu_bos" value="bos" @checked(old('dolu_bos', $alan?->dolu_bos?->value) === 'bos')>
                        <span>Boş</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="yillik_bedel">Yıllık Bedel (₺)</label>
                <input id="yillik_bedel" name="yillik_bedel" type="number" class="form-input td-mono" value="{{ $d('yillik_bedel') }}" step="0.01" min="0">
            </div>
            <div class="form-field">
                <label for="taksit_sayisi">Taksit Sayısı</label>
                <input id="taksit_sayisi" name="taksit_sayisi" type="number" class="form-input td-mono" value="{{ $d('taksit_sayisi') }}" min="1" max="255">
            </div>
            <div class="form-field">
                <label for="baslangic_tarihi">Kira Başlangıç</label>
                <input id="baslangic_tarihi" name="baslangic_tarihi" type="date" class="form-input" value="{{ old('baslangic_tarihi', $alan?->baslangic_tarihi?->format('Y-m-d')) }}">
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="bitis_tarihi">Kira Bitiş</label>
                <input id="bitis_tarihi" name="bitis_tarihi" type="date" class="form-input" value="{{ old('bitis_tarihi', $alan?->bitis_tarihi?->format('Y-m-d')) }}">
                @error('bitis_tarihi')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">4</span>
            <div class="form-section-head-text"><h3>Hukuki Durum ve Notlar</h3></div>
        </div>
        <div class="form-row form-row-3">
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
                <label for="hukuki_durum_id">Hukuki Durum</label>
                <select id="hukuki_durum_id" name="hukuki_durum_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($hukukiDurumlar as $h)
                        <option value="{{ $h->id }}" @selected((string) $d('hukuki_durum_id') === (string) $h->id)>{{ $h->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field kr-onaylar">
                <label class="kr-satir-onay"><input type="checkbox" name="aktif_mi" value="1" @checked(old('aktif_mi', $alan?->aktif_mi ?? true))> Kayıt aktif</label>
                <label class="kr-satir-onay"><input type="checkbox" name="tahliye_ihbarnamesi_mi" value="1" @checked(old('tahliye_ihbarnamesi_mi', $alan?->tahliye_ihbarnamesi_mi))> Tahliye ihbarnamesi çekildi</label>
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label for="aciklama">Açıklama / Not</label>
                <textarea id="aciklama" name="aciklama" class="form-textarea" rows="3" maxlength="5000">{{ $d('aciklama') }}</textarea>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ route('panel.kira.alanlar.index') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">{{ $alan ? 'Değişiklikleri Kaydet' : 'Alanı Ekle' }}</button>
    </div>
</form>
@endsection
