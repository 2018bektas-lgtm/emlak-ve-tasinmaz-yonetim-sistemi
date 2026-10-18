@extends('layouts.panel')

@section('title', $kullanici->exists ? 'Kullanıcı Düzenle' : 'Yeni Kullanıcı')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/yonetim.css') }}?v=2">
@endpush

@php
    $ben = auth()->user();
    $kendisi = $kullanici->exists && $kullanici->id === $ben->id;
    $seciliRol = (string) old('rol_id', $kullanici->rol_id);
@endphp

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Sistem Yönetimi · {{ $kullanici->exists ? 'Kullanıcı #'.$kullanici->id : 'Yeni Kullanıcı' }}</p>
        <h2>{{ $kullanici->exists ? $kullanici->getAdSoyad() : 'Yeni Kullanıcı' }}</h2>
        <small>{{ $kullanici->exists ? 'Kimlik, şifre ve rol bilgilerini düzenleyin.' : 'Hesap bilgilerini girin ve bir rol seçin; yetkiler rolden gelir.' }}</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.kullanicilar.index') }}" class="btn-cancel">← Kullanıcılar</a>
    </div>
</section>

@if ($errors->any())
    <div class="flash-error" role="alert"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ $kullanici->exists ? route('panel.kullanicilar.guncelle', $kullanici) : route('panel.kullanicilar.store') }}" class="form-shell ym-form" autocomplete="off">
    @csrf
    @if ($kullanici->exists) @method('PUT') @endif

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">1</span>
            <div class="form-section-head-text">
                <h3>Kimlik</h3>
                <small>Giriş e-posta ya da kullanıcı adıyla yapılır; ikisi de benzersiz olmalı.</small>
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field">
                <label for="ad">Ad <span class="required">*</span></label>
                <input id="ad" name="ad" type="text" class="form-input" value="{{ old('ad', $kullanici->ad) }}" required maxlength="100">
                @error('ad')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="soyad">Soyad <span class="required">*</span></label>
                <input id="soyad" name="soyad" type="text" class="form-input" value="{{ old('soyad', $kullanici->soyad) }}" required maxlength="100">
                @error('soyad')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field">
                <label for="mail">E-posta <span class="required">*</span></label>
                <input id="mail" name="mail" type="email" class="form-input" value="{{ old('mail', $kullanici->mail) }}" required maxlength="150">
                @error('mail')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="kullanici_adi">Kullanıcı Adı <span class="required">*</span></label>
                <div class="ym-onek">
                    <span>@</span>
                    <input id="kullanici_adi" name="kullanici_adi" type="text" class="form-input" value="{{ old('kullanici_adi', $kullanici->kullanici_adi) }}" required maxlength="60">
                </div>
                @error('kullanici_adi')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">2</span>
            <div class="form-section-head-text">
                <h3>Şifre</h3>
                <small>{{ $kullanici->exists ? 'Değiştirmek istemiyorsanız boş bırakın.' : 'En az 8 karakter.' }}</small>
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field">
                <label for="sifre">{{ $kullanici->exists ? 'Yeni Şifre' : 'Şifre' }} @if (! $kullanici->exists)<span class="required">*</span>@endif</label>
                <div class="ym-sifre">
                    <input id="sifre" name="sifre" type="password" class="form-input" {{ $kullanici->exists ? '' : 'required' }} minlength="8" autocomplete="new-password">
                    <button type="button" class="ym-sifre-btn" data-sifre-goster aria-label="Şifreyi göster">Göster</button>
                    <button type="button" class="ym-sifre-btn" data-sifre-uret title="Rastgele güçlü şifre üret">Üret</button>
                </div>
                <div class="ym-guc" aria-hidden="true"><span id="ym-guc-cubuk"></span></div>
                <span class="hint" id="ym-guc-yazi">{{ $kullanici->exists ? 'Boş bırakılırsa şifre değişmez.' : 'Harf, rakam ve sembol karıştırın.' }}</span>
                @error('sifre')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">3</span>
            <div class="form-section-head-text">
                <h3>Rol ve Durum</h3>
                <small>Kullanıcının görebileceği ve yapabileceği her şey seçilen rolün izinleriyle belirlenir.</small>
            </div>
        </div>
        <div class="ym-rol-secim" role="radiogroup" aria-label="Rol">
            @foreach ($roller as $rol)
                @if ($rol->kod === 'admin' && ! $ben->adminMi()) @continue @endif
                <label class="ym-rol-kart is-{{ $rol->kod }}">
                    <input type="radio" name="rol_id" value="{{ $rol->id }}" @checked($seciliRol === (string) $rol->id) required>
                    <span class="ym-rol-kart-govde">
                        <span class="ym-rol-kart-ust">
                            <strong>{{ $rol->ad }}</strong>
                            <em>{{ $rol->kod === 'admin' ? 'Tüm izinler' : $rol->izinler_count.' izin' }}</em>
                        </span>
                        <small>{{ $rol->aciklama ?: 'Açıklama girilmemiş.' }}</small>
                    </span>
                </label>
            @endforeach
        </div>
        @error('rol_id')<span class="error">{{ $message }}</span>@enderror

        <label class="ym-anahtar {{ $kendisi ? 'is-kilitli' : '' }}">
            <input type="checkbox" name="aktif_mi" value="1" @checked(old('aktif_mi', $kullanici->aktif_mi ?? true)) @disabled($kendisi)>
            <span class="ym-anahtar-ray"></span>
            <span class="ym-anahtar-metin">
                <strong>Hesap aktif</strong>
                <small>{{ $kendisi ? 'Kendi hesabınızı pasifleştiremezsiniz.' : 'Pasif hesaplar sisteme giriş yapamaz.' }}</small>
            </span>
        </label>
    </section>

    <div class="form-actions">
        <a href="{{ route('panel.kullanicilar.index') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            {{ $kullanici->exists ? 'Değişiklikleri Kaydet' : 'Kullanıcıyı Oluştur' }}
        </button>
    </div>
</form>

<script>
(function () {
    const alan = document.getElementById('sifre');
    const cubuk = document.getElementById('ym-guc-cubuk');
    const yazi = document.getElementById('ym-guc-yazi');
    const ilkYazi = yazi.textContent;
    function guc(s) {
        if (!s) return 0;
        let p = Math.min(s.length, 16) / 4;
        if (/[a-zçğıöşü]/.test(s) && /[A-ZÇĞİÖŞÜ]/.test(s)) p += 1;
        if (/\d/.test(s)) p += 1;
        if (/[^\w\sçğıöşüÇĞİÖŞÜ]/.test(s)) p += 1.5;
        return Math.min(4, Math.floor(p / 2));
    }
    alan.addEventListener('input', () => {
        const g = guc(alan.value);
        cubuk.style.width = (alan.value ? (g + 1) * 20 : 0) + '%';
        cubuk.dataset.g = g;
        yazi.textContent = alan.value ? ['Çok zayıf', 'Zayıf', 'Orta', 'İyi', 'Güçlü'][g] + (alan.value.length < 8 ? ' · en az 8 karakter' : '') : ilkYazi;
    });
    document.querySelector('[data-sifre-goster]').addEventListener('click', e => {
        const goster = alan.type === 'password';
        alan.type = goster ? 'text' : 'password';
        e.target.textContent = goster ? 'Gizle' : 'Göster';
    });
    document.querySelector('[data-sifre-uret]').addEventListener('click', () => {
        const k = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%*?';
        const d = new Uint32Array(14);
        crypto.getRandomValues(d);
        alan.value = Array.from(d, x => k[x % k.length]).join('');
        alan.type = 'text';
        document.querySelector('[data-sifre-goster]').textContent = 'Gizle';
        alan.dispatchEvent(new Event('input'));
        alan.select();
    });
})();
</script>
@endsection
