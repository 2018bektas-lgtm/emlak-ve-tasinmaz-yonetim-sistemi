@extends('layouts.panel')

@section('title', $rol->exists ? 'Rol Düzenle' : 'Yeni Rol')

@section('content')
@if ($errors->any())
    <div class="flash-error" role="alert">Lütfen aşağıdaki alanları kontrol edin.</div>
@endif

@php
    $grupEtiket = [
        'tasinmaz' => 'Taşınmaz',
        'hisse' => 'Hisse',
        'yapi' => 'Yapı / BBN',
        'harita' => 'Harita',
        'kullanici' => 'Kullanıcı',
        'rol' => 'Rol',
        'mudurluk' => 'Müdürlük',
        'rapor' => 'Rapor',
    ];
    $adminKilit = $rol->exists && $rol->kod === 'admin';
    $sistemKilit = $rol->exists && $rol->sistem_mi;
@endphp

<form method="POST" action="{{ $rol->exists ? route('panel.roller.guncelle', $rol) : route('panel.roller.store') }}" class="form-shell">
    @csrf
    @if ($rol->exists) @method('PUT') @endif

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">01</span>
            <div class="form-section-head-text">
                <h3>{{ $rol->exists ? 'Rolü düzenle' : 'Yeni rol' }}</h3>
                <small>Kod benzersiz olmalıdır. Sistem rolleri silinemez.</small>
            </div>
        </div>

        <div class="form-row form-row-3">
            <div class="form-field {{ $errors->has('ad') ? 'has-error' : '' }}">
                <label for="ad">Ad <span class="required">*</span></label>
                <input id="ad" name="ad" type="text" class="form-input" value="{{ old('ad', $rol->ad) }}" required maxlength="100">
                @error('ad')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field {{ $errors->has('kod') ? 'has-error' : '' }}">
                <label for="kod">Kod <span class="required">*</span></label>
                <input id="kod" name="kod" type="text" class="form-input" value="{{ old('kod', $rol->kod) }}" required maxlength="40" {{ $sistemKilit ? 'readonly' : '' }} placeholder="ornek-rol">
                @error('kod')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="sira">Sıra</label>
                <input id="sira" name="sira" type="number" class="form-input" value="{{ old('sira', $rol->sira ?? 0) }}" min="0">
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label for="aciklama">Açıklama</label>
                <textarea id="aciklama" name="aciklama" class="form-textarea" rows="2">{{ old('aciklama', $rol->aciklama) }}</textarea>
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">02</span>
            <div class="form-section-head-text">
                <h3>İzinler</h3>
                <small>
                    @if ($adminKilit)
                        Admin rolü tüm izinlere otomatik sahiptir; işaretler değiştirilemez.
                    @else
                        Bu role verilecek işlem izinlerini seçin.
                    @endif
                </small>
            </div>
        </div>

        @foreach ($izinler->groupBy('grup') as $grup => $grupIzinleri)
            <div class="izin-grup">
                <h4>{{ $grupEtiket[$grup] ?? $grup }}</h4>
                <div class="izin-grid">
                    @foreach ($grupIzinleri as $izin)
                        <label class="form-check">
                            <input type="checkbox" name="izinler[]" value="{{ $izin->id }}"
                                   @checked(in_array($izin->id, old('izinler', $seciliIzinler->all())))
                                   @disabled($adminKilit)>
                            <span>
                                {{ $izin->ad }}
                                <em>{{ $izin->kod }}</em>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>

    <div class="form-actions">
        <a href="{{ route('panel.roller.index') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">Kaydet</button>
    </div>
</form>
@endsection
