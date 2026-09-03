@extends('layouts.panel')

@section('title', $mudurluk->exists ? 'Müdürlük Düzenle' : 'Yeni Müdürlük')

@section('content')
@if ($errors->any())
    <div class="flash-error" role="alert">Lütfen aşağıdaki alanları kontrol edin.</div>
@endif

<form method="POST" action="{{ $mudurluk->exists ? route('panel.mudurlukler.guncelle', $mudurluk) : route('panel.mudurlukler.store') }}" class="form-shell">
    @csrf
    @if ($mudurluk->exists) @method('PUT') @endif

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">01</span>
            <div class="form-section-head-text">
                <h3>{{ $mudurluk->exists ? 'Müdürlüğü düzenle' : 'Yeni müdürlük' }}</h3>
                <small>Kod benzersiz olmalıdır.</small>
            </div>
        </div>

        <div class="form-row form-row-3">
            <div class="form-field {{ $errors->has('ad') ? 'has-error' : '' }}">
                <label for="ad">Ad <span class="required">*</span></label>
                <input id="ad" name="ad" type="text" class="form-input" value="{{ old('ad', $mudurluk->ad) }}" required maxlength="150">
                @error('ad')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field {{ $errors->has('kod') ? 'has-error' : '' }}">
                <label for="kod">Kod <span class="required">*</span></label>
                <input id="kod" name="kod" type="text" class="form-input" value="{{ old('kod', $mudurluk->kod) }}" required maxlength="40" placeholder="emlak-istimlak">
                @error('kod')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="sira">Sıra</label>
                <input id="sira" name="sira" type="number" class="form-input" value="{{ old('sira', $mudurluk->sira ?? 0) }}" min="0">
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label for="aciklama">Açıklama</label>
                <textarea id="aciklama" name="aciklama" class="form-textarea" rows="2">{{ old('aciklama', $mudurluk->aciklama) }}</textarea>
            </div>
        </div>
        <div class="form-row">
            <label class="form-check">
                <input type="checkbox" name="aktif_mi" value="1" @checked(old('aktif_mi', $mudurluk->aktif_mi ?? true))>
                <span>Aktif</span>
            </label>
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ route('panel.mudurlukler.index') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">Kaydet</button>
    </div>
</form>
@endsection
