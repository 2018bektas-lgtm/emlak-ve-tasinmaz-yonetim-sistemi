@extends('layouts.panel')

@section('title', $kullanici->exists ? 'Kullanıcı Düzenle' : 'Yeni Kullanıcı')

@section('content')
@if ($errors->any())
    <div class="flash-error" role="alert">Lütfen aşağıdaki alanları kontrol edin.</div>
@endif

<form method="POST" action="{{ $kullanici->exists ? route('panel.kullanicilar.guncelle', $kullanici) : route('panel.kullanicilar.store') }}" class="form-shell">
    @csrf
    @if ($kullanici->exists) @method('PUT') @endif

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">01</span>
            <div class="form-section-head-text">
                <h3>Kimlik</h3>
                <small>Giriş e-posta veya kullanıcı adı ile yapılır.</small>
            </div>
        </div>

        <div class="form-row form-row-2">
            <div class="form-field {{ $errors->has('ad') ? 'has-error' : '' }}">
                <label for="ad">Ad <span class="required">*</span></label>
                <input id="ad" name="ad" type="text" class="form-input" value="{{ old('ad', $kullanici->ad) }}" required>
                @error('ad')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field {{ $errors->has('soyad') ? 'has-error' : '' }}">
                <label for="soyad">Soyad <span class="required">*</span></label>
                <input id="soyad" name="soyad" type="text" class="form-input" value="{{ old('soyad', $kullanici->soyad) }}" required>
                @error('soyad')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field {{ $errors->has('mail') ? 'has-error' : '' }}">
                <label for="mail">E-posta <span class="required">*</span></label>
                <input id="mail" name="mail" type="email" class="form-input" value="{{ old('mail', $kullanici->mail) }}" required>
                @error('mail')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field {{ $errors->has('kullanici_adi') ? 'has-error' : '' }}">
                <label for="kullanici_adi">Kullanıcı adı <span class="required">*</span></label>
                <input id="kullanici_adi" name="kullanici_adi" type="text" class="form-input" value="{{ old('kullanici_adi', $kullanici->kullanici_adi) }}" required>
                @error('kullanici_adi')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field {{ $errors->has('sifre') ? 'has-error' : '' }}">
                <label for="sifre">Şifre @if (! $kullanici->exists)<span class="required">*</span>@endif</label>
                <input id="sifre" name="sifre" type="password" class="form-input" {{ $kullanici->exists ? '' : 'required' }} minlength="8" autocomplete="new-password">
                @if ($kullanici->exists)<span class="hint">Boş bırakılırsa şifre değişmez.</span>@endif
                @error('sifre')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">02</span>
            <div class="form-section-head-text">
                <h3>Yetki ve kapsam</h3>
                <small>Rol izinleri belirler. Müdürlük, taşınmaz listesini sınırlar.</small>
            </div>
        </div>

        <div class="form-row form-row-3">
            <div class="form-field {{ $errors->has('rol_id') ? 'has-error' : '' }}">
                <label for="rol_id">Rol <span class="required">*</span></label>
                <select id="rol_id" name="rol_id" class="form-select" required>
                    <option value="">— Seçin —</option>
                    @foreach ($roller as $rol)
                        @if ($rol->kod === 'admin' && ! auth()->user()->adminMi())
                            @continue
                        @endif
                        <option value="{{ $rol->id }}" @selected(old('rol_id', $kullanici->rol_id) == $rol->id)>{{ $rol->ad }}</option>
                    @endforeach
                </select>
                @error('rol_id')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="mudurluk_id">Müdürlük</label>
                <select id="mudurluk_id" name="mudurluk_id" class="form-select">
                    <option value="">— Atanmamış —</option>
                    @foreach ($mudurlukler as $m)
                        <option value="{{ $m->id }}" @selected(old('mudurluk_id', $kullanici->mudurluk_id) == $m->id)>{{ $m->ad }}</option>
                    @endforeach
                </select>
                <span class="hint">Admin ve tümünü-gör izni olanlar müdürlüksüz kalabilir.</span>
            </div>
            <div class="form-field">
                <label>&nbsp;</label>
                <label class="form-check">
                    <input type="checkbox" name="aktif_mi" value="1" @checked(old('aktif_mi', $kullanici->aktif_mi ?? true))
                           @disabled($kullanici->exists && $kullanici->id === auth()->id())>
                    <span>Aktif hesap</span>
                </label>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ route('panel.kullanicilar.index') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">Kaydet</button>
    </div>
</form>
@endsection
