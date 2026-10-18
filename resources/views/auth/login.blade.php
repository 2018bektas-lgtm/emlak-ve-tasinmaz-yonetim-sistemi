<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b1020">
    <title>Giriş · Emlak ve Taşınmaz Yönetim Sistemi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <link rel="preload" as="image" href="{{ asset('images/login-bg.jpg') }}">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v=1">
</head>
<body class="lg-body">
    {{-- Liquid glass kırılma filtresi (Chromium'da backdrop-filter ile kullanılır) --}}
    <svg class="lg-defs" aria-hidden="true" focusable="false">
        <filter id="lg-refract" x="0%" y="0%" width="100%" height="100%" color-interpolation-filters="sRGB">
            <feTurbulence type="fractalNoise" baseFrequency="0.008 0.012" numOctaves="2" seed="7" result="noise"/>
            <feGaussianBlur in="noise" stdDeviation="2" result="soft"/>
            <feDisplacementMap in="SourceGraphic" in2="soft" scale="38" xChannelSelector="R" yChannelSelector="G"/>
        </filter>
    </svg>

    <div class="lg-scene" aria-hidden="true">
        <div class="lg-photo" style="background-image: url('{{ asset('images/login-bg.jpg') }}')"></div>
        <div class="lg-shade"></div>
        <span class="lg-orb lg-orb-a"></span>
        <span class="lg-orb lg-orb-b"></span>
        <span class="lg-orb lg-orb-c"></span>
        <div class="lg-grain"></div>
    </div>

    <header class="lg-topbar">
        <a class="lg-pill lg-brand glass" href="{{ route('login') }}">
            <span class="lg-mark" aria-hidden="true">
                <svg viewBox="0 0 32 32"><path d="M5 26V12l11-7 11 7v14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/><path d="M11 26v-8h10v8M5 26h22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/></svg>
            </span>
            <span class="lg-brand-text">
                <strong>ETYS</strong>
                <em>Emlak &amp; Taşınmaz Yönetimi</em>
            </span>
        </a>

        <div class="lg-pill lg-status glass" role="status">
            <span class="lg-dot" aria-hidden="true"></span>
            <span>Sistem çevrimiçi</span>
            <span class="lg-sep" aria-hidden="true"></span>
            <time id="lg-clock" class="lg-clock">--:--</time>
        </div>
    </header>

    <div class="lg-layout">
        <section class="lg-hero" aria-label="Sistem tanıtımı">
            <p class="lg-eyebrow"><span></span>Belediye Kurumsal Platformu</p>
            <h1>Şehrin her parseli,<br><em>tek bir</em> ekranda.</h1>
            <p class="lg-lead">Taşınmaz envanteri, hisse satışı, ecrimisil, lojman ve Sayıştay raporları; güvenli, izlenebilir ve her zaman güncel.</p>

            <ul class="lg-modules">
                <li class="glass glass-chip">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V9l8-5 8 5v11M9 20v-6h6v6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                    Taşınmaz
                </li>
                <li class="glass glass-chip">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v18M5 8h11a3 3 0 0 1 0 6H8a3 3 0 0 0 0 6h11" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    Hisse Satışı
                </li>
                <li class="glass glass-chip">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 6v6c0 4.5 3.4 7.9 8 9 4.6-1.1 8-4.5 8-9V6l-8-3Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                    Ecrimisil
                </li>
                <li class="glass glass-chip">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M10 21v-4h4v4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    Lojman
                </li>
                <li class="glass glass-chip">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h16M7 16V10M12 16V6M17 16v-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    Sayıştay Raporu
                </li>
            </ul>
        </section>

        <main class="lg-card-wrap">
            <div class="lg-card glass glass-strong" id="lg-card">
                <span class="glass-refract" aria-hidden="true"></span>
                <span class="glass-sheen" aria-hidden="true"></span>

                <div class="lg-card-inner">
                    <header class="lg-card-head">
                        <span class="lg-lock glass" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><rect x="5.5" y="10.5" width="13" height="9.5" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="12" cy="15.2" r="1.3" fill="currentColor"/></svg>
                        </span>
                        <h2 id="login-title">Tekrar hoş geldiniz</h2>
                        <p>Kurumsal hesabınızla oturum açın.</p>
                    </header>

                    @if ($errors->any())
                        <div class="lg-alert" role="alert">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M12 7.5v5.5M12 16.3h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" novalidate aria-labelledby="login-title" id="lg-form">
                        @csrf

                        <div class="lg-field">
                            <label for="giris">E-posta veya kullanıcı adı</label>
                            <div class="lg-control @error('giris') is-invalid @enderror">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8.5" r="3.5" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M4.5 20c1.3-3.5 4.2-5.4 7.5-5.4s6.2 1.9 7.5 5.4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                                <input id="giris" type="text" name="giris" value="{{ old('giris') }}" required autofocus autocomplete="username" spellcheck="false" autocapitalize="off" placeholder="ad.soyad@belediye.gov.tr">
                            </div>
                        </div>

                        <div class="lg-field">
                            <label for="sifre">Şifre</label>
                            <div class="lg-control @error('sifre') is-invalid @enderror">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5.5" y="10.5" width="13" height="9.5" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                                <input id="sifre" type="password" name="sifre" required autocomplete="current-password" placeholder="••••••••">
                                <button class="lg-eye" type="button" data-target="sifre" aria-label="Şifreyi göster" aria-pressed="false">
                                    <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.7"/></svg>
                                    <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 5.6A9.7 9.7 0 0 1 12 5.5C18 5.5 21.5 12 21.5 12a17 17 0 0 1-3.1 3.9M6.6 6.6C3.9 8.4 2.5 12 2.5 12S6 18.5 12 18.5c1.6 0 3-.4 4.3-1.1M9.9 9.9a3 3 0 0 0 4.2 4.2" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                                </button>
                            </div>
                            <p class="lg-caps" id="lg-caps" hidden>Caps Lock açık</p>
                        </div>

                        <label class="lg-switch">
                            <input type="checkbox" name="beni_hatirla" value="1" @checked(old('beni_hatirla'))>
                            <span class="lg-track" aria-hidden="true"><span class="lg-thumb"></span></span>
                            <span>Oturumu açık tut</span>
                        </label>

                        <button class="lg-submit" type="submit">
                            <span class="lg-submit-label">Giriş yap</span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </form>

                    @if (config('app.debug'))
                        <p class="lg-demo"><span>Demo</span> admin · password</p>
                    @endif

                    <footer class="lg-card-foot">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.2 7.5 9.5 4.3-1.3 7.5-4.9 7.5-9.5V6L12 3Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m8.8 12 2.2 2.2 4.2-4.4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Uçtan uca şifreli oturum · KVKK uyumlu
                    </footer>
                </div>
            </div>
        </main>
    </div>

    <footer class="lg-footer">
        <span>© {{ date('Y') }} Emlak ve Taşınmaz Yönetim Sistemi</span>
        <span>Yetkisiz erişim girişimleri kayıt altına alınır.</span>
    </footer>

    <script>
        (() => {
            const root = document.documentElement;
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            // SVG kırılma filtresi yalnızca Chromium'da backdrop-filter ile güvenilir çalışır.
            const brands = navigator.userAgentData?.brands || [];
            if (brands.some((b) => /Chromium/i.test(b.brand))) {
                root.classList.add('lg-refract-on');
            }

            // Saat
            const clock = document.getElementById('lg-clock');
            const tick = () => {
                clock.textContent = new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
            };
            tick();
            setInterval(tick, 15000);

            // Şifre göster / gizle
            document.querySelectorAll('.lg-eye').forEach((button) => {
                button.addEventListener('click', () => {
                    const input = document.getElementById(button.dataset.target);
                    const show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    button.setAttribute('aria-pressed', String(show));
                    button.setAttribute('aria-label', show ? 'Şifreyi gizle' : 'Şifreyi göster');
                    input.focus();
                });
            });

            // Caps Lock uyarısı
            const caps = document.getElementById('lg-caps');
            const sifre = document.getElementById('sifre');
            const capsCheck = (e) => {
                if (typeof e.getModifierState === 'function') caps.hidden = !e.getModifierState('CapsLock');
            };
            sifre.addEventListener('keydown', capsCheck);
            sifre.addEventListener('keyup', capsCheck);
            sifre.addEventListener('blur', () => { caps.hidden = true; });

            // Gönderimde yükleniyor durumu
            const form = document.getElementById('lg-form');
            form.addEventListener('submit', () => {
                const btn = form.querySelector('.lg-submit');
                btn.classList.add('is-loading');
                btn.setAttribute('aria-busy', 'true');
                btn.querySelector('.lg-submit-label').textContent = 'Giriş yapılıyor…';
            });

            // Kartta imleci izleyen ışık yansıması ve hafif eğim
            const card = document.getElementById('lg-card');
            if (!reduceMotion && window.matchMedia('(pointer: fine)').matches) {
                let raf = 0;
                card.addEventListener('pointermove', (e) => {
                    cancelAnimationFrame(raf);
                    raf = requestAnimationFrame(() => {
                        const r = card.getBoundingClientRect();
                        const x = (e.clientX - r.left) / r.width;
                        const y = (e.clientY - r.top) / r.height;
                        card.style.setProperty('--mx', `${(x * 100).toFixed(1)}%`);
                        card.style.setProperty('--my', `${(y * 100).toFixed(1)}%`);
                        card.style.setProperty('--rx', `${((0.5 - y) * 4).toFixed(2)}deg`);
                        card.style.setProperty('--ry', `${((x - 0.5) * 5).toFixed(2)}deg`);
                    });
                });
                card.addEventListener('pointerleave', () => {
                    card.style.setProperty('--rx', '0deg');
                    card.style.setProperty('--ry', '0deg');
                });
            }
        })();
    </script>
</body>
</html>
