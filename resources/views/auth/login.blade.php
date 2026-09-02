<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giriş · Emlak ve Taşınmaz Yönetim Sistemi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/corporate.css') }}?v=15">
</head>
<body class="auth-body">
    <aside class="auth-visual" aria-hidden="true">
        <img class="auth-hero-img" src="{{ asset('images/login-poster.jpg') }}" alt="">
        <div class="auth-hero-overlay"></div>

        <div class="auth-hero-content">
            <a class="brand brand-light" href="{{ route('login') }}">
                <span class="logo-text">
                    <strong>ETYS</strong>
                    <em>Emlak &amp; Taşınmaz<br>Yönetim Sistemi</em>
                </span>
            </a>

            <div class="auth-hero-lead">
                <p class="eyebrow">Belediye · Emlak ve Taşınmaz Yönetim Sistemi</p>
                <h1>Ada, parsel ve mesken<br>işleri tek yerde.</h1>
                <p>İmar planları, parselasyon, tahsis, tapu ve envanter — hepsi tek platformda, güvenli ve izlenebilir.</p>
            </div>

            <ul class="auth-hero-facts">
                <li><span>01</span> Ada / Parsel</li>
                <li><span>02</span> İmar &amp; Parselasyon</li>
                <li><span>03</span> Tapu &amp; Tahsis</li>
                <li><span>04</span> Envanter</li>
            </ul>
        </div>
    </aside>

    <main class="auth-form-panel">
        <div class="auth-form-wrap">
            <header class="auth-form-head">
                <p class="eyebrow dark">Giriş</p>
                <h2 id="login-title">Hesabınıza giriş yapın</h2>
                <p class="auth-sub">Kurumsal e-posta ve şifrenizle devam edin.</p>
            </header>

            @if ($errors->any())
                <div class="alert-error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" novalidate aria-labelledby="login-title">
                @csrf

                <label class="field">
                    <span>E-posta veya Kullanıcı Adı</span>
                    <span class="field-control">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="9" r="3.2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M4.5 20c1.4-3.4 4.3-5.2 7.5-5.2s6.1 1.8 7.5 5.2" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
                        <input type="text" name="giris" value="{{ old('giris') }}" required autofocus autocomplete="username" placeholder="admin  ·  ad.soyad@belediye.gov.tr">
                    </span>
                </label>

                <label class="field">
                    <span>Şifre</span>
                    <span class="field-control">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="11" width="12" height="9" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M9 11V8.5a3 3 0 0 1 6 0V11" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
                        <input id="sifre" type="password" name="sifre" required autocomplete="current-password" placeholder="••••••••">
                        <button class="toggle-pass" type="button" data-target="sifre" aria-label="Şifreyi göster">Göster</button>
                    </span>
                </label>

                <label class="check">
                    <input type="checkbox" name="beni_hatirla" value="1">
                    <span>Oturumu açık tut</span>
                </label>

                <button class="btn btn-primary" type="submit">Giriş Yap</button>
            </form>

            @if (config('app.debug'))
                <p class="demo-hint">Demo · admin (veya admin@etys.local) / password</p>
            @endif
        </div>

        <footer class="auth-form-footer">
            <span>© {{ date('Y') }} Emlak ve Taşınmaz Yönetim Sistemi</span>
            <span>Güvenli oturum · KVKK uyumlu</span>
        </footer>
    </main>

    <script>
        document.querySelectorAll('.toggle-pass').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.target);
                const hidden = input.type === 'password';
                input.type = hidden ? 'text' : 'password';
                button.textContent = hidden ? 'Gizle' : 'Göster';
            });
        });
    </script>
</body>
</html>
