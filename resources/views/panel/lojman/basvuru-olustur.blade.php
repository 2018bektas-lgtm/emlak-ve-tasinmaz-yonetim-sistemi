@extends('layouts.panel')

@section('title', 'Yeni Lojman Başvurusu')

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <h2>Yeni Başvuru</h2>
        <small>Personel bilgileri ve lojman tercihi.</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.lojman.basvurular') }}" class="btn-cancel">Vazgeç</a>
    </div>
</section>

@if ($errors->any())<div class="flash-error"><ul style="margin:0;padding-left:20px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ route('panel.lojman.basvuru.kaydet') }}">
    @csrf
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">1</span>
            <div class="form-section-head-text"><h3>Personel Bilgileri</h3></div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field"><label>Ad Soyad *</label><input type="text" name="ad_soyad" class="form-input" value="{{ old('ad_soyad') }}" required maxlength="200"></div>
            <div class="form-field"><label>TC Kimlik No</label><input type="text" name="tc_kimlik" class="form-input" value="{{ old('tc_kimlik') }}" maxlength="11" placeholder="11 hane"></div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field"><label>Sicil No</label><input type="text" name="sicil_no" class="form-input" value="{{ old('sicil_no') }}" maxlength="50"></div>
            <div class="form-field"><label>Ünvan</label><input type="text" name="unvan" class="form-input" value="{{ old('unvan') }}" maxlength="150"></div>
            <div class="form-field"><label>Birim</label><input type="text" name="birim" class="form-input" value="{{ old('birim') }}" maxlength="200"></div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">2</span>
            <div class="form-section-head-text"><h3>Başvuru Detayı</h3></div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label>Lojman (opsiyonel)</label>
                <select name="lojman_id" class="form-select">
                    <option value="">— Herhangi bir lojman —</option>
                    @foreach ($lojmanlar as $l)
                        <option value="{{ $l->id }}" @selected(old('lojman_id', $lojman?->id) == $l->id)>{{ $l->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label>Tahsis Türü</label>
                <select name="tahsis_turu" class="form-select">
                    <option value="">— Seçin —</option>
                    @foreach (['Sıra', 'Görev', 'Hizmet', 'Temsil'] as $t)
                        <option value="{{ $t }}" @selected(old('tahsis_turu') === $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field"><label>Başvuru Tarihi *</label><input type="date" name="basvuru_tarihi" class="form-input" value="{{ old('basvuru_tarihi', now()->toDateString()) }}" required></div>
        </div>
        <div class="form-field">
            <label>Durum *</label>
            <select name="durum" class="form-select" required>
                @foreach (['basvuruldu'=>'Başvuruldu','degerlendirmede'=>'Değerlendirmede','onaylandi'=>'Onaylandı','reddedildi'=>'Reddedildi'] as $k => $v)
                    <option value="{{ $k }}" @selected(old('durum', 'basvuruldu') === $k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-field"><label>Açıklama</label><textarea name="aciklama" class="form-input" rows="3" maxlength="5000">{{ old('aciklama') }}</textarea></div>
    </section>

    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
        <a href="{{ route('panel.lojman.basvurular') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">Başvuruyu Kaydet</button>
    </div>
</form>
@endsection
