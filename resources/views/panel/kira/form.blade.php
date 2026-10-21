@extends('layouts.panel')

@section('title', 'Kira Dosyası Düzenle')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=5">
    <link rel="stylesheet" href="{{ asset('css/kira.css') }}?v=12">
@endpush

@php
    // Bedel satırları: doğrulama hatasında kullanıcının girdiği satırlar korunur
    $bedeller = old('bedeller', $v['bedeller'] ?? [['yillik_bedel' => '', 'donem_baslangic' => '', 'donem_bitis' => '', 'tufe_orani' => '']]);
@endphp

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Kira İşlemleri</p>
        <h2>Kira Dosyası Düzenle <span class="kr-hero-alt">{{ $dosya->dosya_no }}</span></h2>
        <small>Dosya, kiracı ve yürürlükteki sözleşme birlikte güncellenir. Kiralanan yerler (alanlar) dosya detayından yönetilir.</small>
    </div>
    <div class="page-hero-actions">
        {{-- Kiracı değiştiyse bu formda değil, yeni dönem açarak kaydedilir --}}
        @can('kira.olustur')
            <a href="{{ route('panel.kira.donem.olustur', $dosya->id) }}" class="btn-cancel">Kiracı Değişti</a>
        @endcan
        <a href="{{ route('panel.kira.detay', $dosya->id) }}" class="btn-cancel">Detaya dön</a>
        <a href="{{ route('panel.kira.index') }}" class="btn-cancel">Listeye dön</a>
    </div>
</section>

@if ($errors->any())
    <div class="flash-error" role="alert">
        <strong>Kayıt tamamlanmadı.</strong>
        <ul>@foreach ($errors->all() as $hata)<li>{{ $hata }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ route('panel.kira.guncelle', $dosya->id) }}" class="form-shell" id="kiraForm">
    @csrf
    @method('PUT')

    {{-- 1 · Dosya --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">1</span>
            <div class="form-section-head-text">
                <h3>Dosya Bilgileri</h3>
                <small>Dosya numarası kurum içinde benzersizdir; alt dosya ise ana dosyasını seçin.</small>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="dosya_no">Dosya No <span class="required">*</span></label>
                <input id="dosya_no" name="dosya_no" class="form-input td-mono" value="{{ old('dosya_no', $v['dosya_no'] ?? null) }}" maxlength="50" required>
                @error('dosya_no')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="eski_dosya_no">Eski Dosya No</label>
                <input id="eski_dosya_no" name="eski_dosya_no" class="form-input td-mono" value="{{ old('eski_dosya_no', $v['eski_dosya_no'] ?? null) }}" maxlength="50">
            </div>
            <div class="form-field">
                <label for="ana_dosya_id">Ana Dosya</label>
                <select id="ana_dosya_id" name="ana_dosya_id" class="form-select">
                    <option value="">Yok (bağımsız dosya)</option>
                    @foreach ($anaDosyalar as $a)
                        <option value="{{ $a->id }}" @selected((string) old('ana_dosya_id', $v['ana_dosya_id'] ?? null) === (string) $a->id)>{{ $a->dosya_no }}@if ($a->isyeri_adi) — {{ $a->isyeri_adi }}@endif</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="isyeri_adi">İşyeri Adı</label>
                <input id="isyeri_adi" name="isyeri_adi" class="form-input" value="{{ old('isyeri_adi', $v['isyeri_adi'] ?? null) }}" maxlength="200">
            </div>
            <div class="form-field">
                <label for="is_kolu_id">İş Kolu</label>
                <select id="is_kolu_id" name="is_kolu_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($isKollari as $i)
                        <option value="{{ $i->id }}" @selected((string) old('is_kolu_id', $v['is_kolu_id'] ?? null) === (string) $i->id)>{{ $i->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="arac_kapasitesi">Araç Kapasitesi</label>
                <input id="arac_kapasitesi" name="arac_kapasitesi" type="number" class="form-input td-mono" value="{{ old('arac_kapasitesi', $v['arac_kapasitesi'] ?? null) }}" min="0">
                <span class="hint">Otopark ve durak dosyalarında.</span>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label>Doluluk <span class="required">*</span></label>
                <div class="kr-secim">
                    <label class="kr-secim-kart" style="--kr-renk:#dc2626">
                        <input type="radio" name="dolu_bos" value="dolu" @checked(old('dolu_bos', $v['dolu_bos'] ?? 'dolu') === 'dolu')>
                        <span>Dolu</span>
                    </label>
                    <label class="kr-secim-kart" style="--kr-renk:#16a34a">
                        <input type="radio" name="dolu_bos" value="bos" @checked(old('dolu_bos', $v['dolu_bos'] ?? null) === 'bos')>
                        <span>Boş</span>
                    </label>
                </div>
            </div>
            <div class="form-field">
                <label>İhale Durumu <span class="required">*</span></label>
                <div class="kr-secim">
                    <label class="kr-secim-kart" style="--kr-renk:#6366f1">
                        <input type="radio" name="ihale_turu" value="ihaleli" @checked(old('ihale_turu', $v['ihale_turu'] ?? null) === 'ihaleli')>
                        <span>İhaleli</span>
                    </label>
                    <label class="kr-secim-kart" style="--kr-renk:#64748b">
                        <input type="radio" name="ihale_turu" value="ihalesiz" @checked(old('ihale_turu', $v['ihale_turu'] ?? 'ihalesiz') === 'ihalesiz')>
                        <span>İhalesiz</span>
                    </label>
                </div>
            </div>
            <div class="form-field">
                <label for="muhasebe_niteligi_id">Muhasebe Niteliği</label>
                <select id="muhasebe_niteligi_id" name="muhasebe_niteligi_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($muhasebeNitelikleri as $m)
                        <option value="{{ $m->id }}" @selected((string) old('muhasebe_niteligi_id', $v['muhasebe_niteligi_id'] ?? null) === (string) $m->id)>{{ $m->ad }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </section>

    @include('panel.kira._kiraci', ['no' => 2, 'aciklama' => 'Yürürlükteki dönemin kiracısı. Kiracı değiştiyse "Kiracı Değişti" ile yeni dönem açın.'])

    @include('panel.kira._konum', [
        'no' => 3,
        'baslik' => 'Kiralanan Taşınmaz',
        'aciklama' => 'Dosyanın tapu/konum bilgisi. Haritadaki sınırlar kira alanlarında tutulur.',
        'kmhAyar' => null,
    ])

    @include('panel.kira._sozlesme', ['no' => 4])

    @include('panel.kira._bedeller', ['no' => 5])

    {{-- 6 · Durum --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">6</span>
            <div class="form-section-head-text">
                <h3>Durum ve Notlar</h3>
                <small>Hukuki süreç ve uyarılar; listede bu bilgilerle süzülür.</small>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="hukuki_durum_id">Hukuki Durum</label>
                <select id="hukuki_durum_id" name="hukuki_durum_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($hukukiDurumlar as $h)
                        <option value="{{ $h->id }}" @selected((string) old('hukuki_durum_id', $v['hukuki_durum_id'] ?? null) === (string) $h->id)>{{ $h->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="kiracilik_durum_id">Kiracılık Durumu</label>
                <select id="kiracilik_durum_id" name="kiracilik_durum_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($kiracilikDurumlar as $k)
                        <option value="{{ $k->id }}" @selected((string) old('kiracilik_durum_id', $v['kiracilik_durum_id'] ?? null) === (string) $k->id)>{{ $k->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="uyari">Uyarı</label>
                <input id="uyari" name="uyari" class="form-input" value="{{ old('uyari', $v['uyari'] ?? null) }}" maxlength="300">
                <span class="hint">Listede kırmızı etiket olarak görünür.</span>
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field kr-onaylar">
                <label class="kr-satir-onay"><input type="checkbox" name="sozlesme_aktif_mi" value="1" @checked(old('sozlesme_aktif_mi', $v['sozlesme_aktif_mi'] ?? true))> Sözleşme yürürlükte</label>
                <label class="kr-satir-onay"><input type="checkbox" name="tahliye_ihbarnamesi_mi" value="1" @checked(old('tahliye_ihbarnamesi_mi', $v['tahliye_ihbarnamesi_mi'] ?? null))> Tahliye ihbarnamesi çekildi</label>
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label for="aciklama">Açıklama / Not</label>
                <textarea id="aciklama" name="aciklama" class="form-textarea" rows="3" maxlength="5000">{{ old('aciklama', $v['aciklama'] ?? null) }}</textarea>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ route('panel.kira.detay', $dosya->id) }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">Değişiklikleri Kaydet</button>
    </div>
</form>

@include('panel.kira._form-betik')

<script>
(function () {
    const form = document.getElementById('kiraForm');
    // Boş yerde kiracı istenmez; bölüm soluklaşır
    function kiraciBolumuTazele() {
        const bos = form.querySelector('[name="dolu_bos"]:checked').value === 'bos';
        document.getElementById('kiraciBolum').classList.toggle('is-pasif', bos);
    }
    form.querySelectorAll('[name="dolu_bos"]').forEach(function (r) { r.addEventListener('change', kiraciBolumuTazele); });
    kiraciBolumuTazele();
})();
</script>
@endsection
