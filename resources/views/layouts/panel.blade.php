<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Yönetim Paneli') · ETYS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/corporate.css') }}?v=97">
    <script src="{{ asset('js/ozel-select.js') }}?v=3" defer></script>
    @stack('head')
</head>
<body class="app-shell @yield('shellClass')">
    @php
        $currentRoute = request()->route()?->getName();
        $navGroups = [
            'Ana Menü' => [
                ['label' => 'Özet', 'route' => 'panel.dashboard', 'icon' => 'grid'],
            ],
            'Taşınmaz İşlemleri' => [
                ['label' => 'Taşınmazlar', 'icon' => 'map', 'children' => [
                    ['label' => 'Taşınmaz Listesi', 'route' => 'panel.tasinmazlar.index', 'izin' => 'tasinmaz.goruntule'],
                    ['label' => 'Taşınmaz Ekle', 'route' => 'panel.tasinmazlar.olustur', 'izin' => 'tasinmaz.olustur'],
                    ['label' => 'Taşınmaz Haritası', 'route' => 'panel.tasinmazlar.harita', 'izin' => 'harita.goruntule'],
                    ['label' => 'Toplu İşlem (Excel)', 'route' => 'panel.tasinmazlar.toplu-islem', 'izin' => 'tasinmaz.toplu-islem'],
                    ['label' => 'Sayıştay Raporları', 'children' => [
                        ['label' => 'Kayıtlı (EK-2)', 'route' => 'panel.sayistay-rapor.kayitli', 'izin' => 'rapor.goruntule'],
                        ['label' => 'Kayıtsız (EK-3)', 'route' => 'panel.sayistay-rapor.kayitsiz', 'izin' => 'rapor.goruntule'],
                        ['label' => 'Orta Malları (EK-4)', 'route' => 'panel.sayistay-rapor.ortamallari', 'izin' => 'rapor.goruntule'],
                        ['label' => 'Genel Hizmet (EK-5)', 'route' => 'panel.sayistay-rapor.genelhizmet', 'izin' => 'rapor.goruntule'],
                    ]],
                ]],
            ],
            'Satış İşlemleri' => [
                ['label' => 'Hisse Satışı', 'icon' => 'coin', 'children' => [
                    ['label' => 'Başvuru Listesi', 'route' => 'panel.hisse-satisi.liste', 'izin' => 'hisse-satisi.goruntule'],
                    ['label' => 'Yeni Başvuru', 'route' => 'panel.hisse-satisi.index', 'izin' => 'hisse-satisi.olustur'],
                    ['label' => 'Harita', 'route' => 'panel.hisse-satisi.harita', 'izin' => 'hisse-satisi.goruntule'],
                    ['label' => 'Arşiv', 'route' => 'panel.hisse-satisi.arsiv', 'izin' => 'hisse-satisi.goruntule'],
                ]],
                ['label' => 'Ecrimisil', 'icon' => 'clipboard', 'children' => [
                    ['label' => 'İşgal Kayıtları', 'route' => 'panel.ecrimisil.index', 'izin' => 'ecrimisil.goruntule'],
                    ['label' => 'Yeni Kayıt', 'route' => 'panel.ecrimisil.olustur', 'izin' => 'ecrimisil.olustur'],
                    ['label' => 'Harita', 'route' => 'panel.ecrimisil.harita', 'izin' => 'ecrimisil.goruntule'],
                ]],
                ['label' => 'Lojman', 'icon' => 'building', 'children' => [
                    ['label' => 'Lojmanlar', 'route' => 'panel.lojman.index', 'izin' => 'lojman.goruntule'],
                    ['label' => 'Yeni Lojman', 'route' => 'panel.lojman.olustur', 'izin' => 'lojman.olustur'],
                    ['label' => 'Başvurular', 'route' => 'panel.lojman.basvurular', 'izin' => 'lojman.goruntule'],
                    ['label' => 'Yeni Başvuru', 'route' => 'panel.lojman.basvuru.olustur', 'izin' => 'lojman.olustur'],
                    ['label' => 'Tahsis Excel', 'route' => 'panel.lojman.tahsis.excel', 'izin' => 'lojman.goruntule'],
                ]],
            ],
            'Sistem' => [
                ['label' => 'Kullanıcılar', 'icon' => 'users', 'children' => [
                    ['label' => 'Kullanıcı Listesi', 'route' => 'panel.kullanicilar.index', 'izin' => 'kullanici.goruntule'],
                    ['label' => 'Yeni Kullanıcı', 'route' => 'panel.kullanicilar.olustur', 'izin' => 'kullanici.olustur'],
                    ['label' => 'Roller ve İzinler', 'route' => 'panel.roller.index', 'izin' => 'rol.goruntule'],
                    ['label' => 'Müdürlükler', 'route' => 'panel.mudurlukler.index', 'izin' => 'mudurluk.goruntule'],
                ]],
            ],
        ];

        $icons = [
            'grid'   => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
            'map'    => '<path d="M9 4 L3 6 v14 l6-2 6 2 6-2 V4 l-6 2z"/><path d="M9 4 v14 M15 6 v14"/>',
            'home'   => '<path d="M3 11 L12 3 l9 8"/><path d="M5 10 v10 h14 V10"/><path d="M10 20 v-5 h4 v5"/>',
            'layers' => '<path d="M12 3 L2 8 l10 5 10-5z"/><path d="M2 13 l10 5 10-5"/><path d="M2 18 l10 5 10-5"/>',
            'plot'   => '<rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/>',
            'assign' => '<path d="M4 6 h12"/><path d="M4 12 h8"/><path d="M4 18 h12"/><circle cx="19" cy="12" r="2.5"/>',
            'deed'   => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8 h8 M8 12 h8 M8 16 h5"/>',
            'box'    => '<path d="M3 7 L12 3 l9 4 v10 L12 21 3 17 z"/><path d="M3 7 l9 4 9-4 M12 11 v10"/>',
            'chart'  => '<path d="M4 20 V10 M10 20 V4 M16 20 V13 M22 20 H2"/>',
            'users'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20 c1-4 4-6 6.5-6 s5.5 2 6.5 6"/><circle cx="17" cy="9" r="2.5"/><path d="M15 14 c3 0 5 2 6 6"/>',
            'gear'   => '<circle cx="12" cy="12" r="3"/><path d="M12 2 v3 M12 19 v3 M4.2 4.2 l2.1 2.1 M17.7 17.7 l2.1 2.1 M2 12 h3 M19 12 h3 M4.2 19.8 l2.1-2.1 M17.7 6.3 l2.1-2.1"/>',
            'coin'   => '<circle cx="12" cy="12" r="9"/><path d="M15 9.5 c-0.5-1-1.5-1.5-3-1.5 c-1.7 0-3 1-3 2 c0 2.5 6 1.5 6 4 c0 1-1.3 2-3 2 c-1.5 0-2.5-0.5-3-1.5 M12 6 v2 M12 16 v2"/>',
            'clipboard' => '<rect x="6" y="4" width="12" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M9 11 h6 M9 15 h4"/>',
            'building'  => '<path d="M3 21 h18 M5 21 V6 a2 2 0 0 1 2-2 h10 a2 2 0 0 1 2 2 v15"/><path d="M9 8 h1 M14 8 h1 M9 12 h1 M14 12 h1 M9 16 h1 M14 16 h1"/>',
        ];

        $user = auth()->user();
        $initials = strtoupper(mb_substr($user->ad, 0, 1) . mb_substr($user->soyad, 0, 1));
        $izinVar = fn (?string $izin) => ! $izin || ($user && $user->izinVarMi($izin));
    @endphp

    <aside class="app-sidebar" id="app-sidebar">
        <div class="app-brand">
            <a class="brand brand-light" href="{{ route('panel.dashboard') }}">
                <span class="logo-text">
                    <strong>ETYS</strong>
                    <em>Emlak &amp; Taşınmaz<br>Yönetim Sistemi</em>
                </span>
            </a>
            <span class="app-brand-mini" aria-hidden="true">E</span>
            <button type="button" class="app-collapse-btn" data-collapse-toggle aria-label="Menüyü daralt">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 6 L8 12 L14 18"/>
                </svg>
            </button>
            <button type="button" class="app-sidebar-close" data-sidebar-toggle aria-label="Menüyü kapat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 6 L18 18 M18 6 L6 18"/>
                </svg>
            </button>
        </div>

        <nav class="app-nav" aria-label="Ana menü">
            @foreach ($navGroups as $groupTitle => $items)
                <div class="app-nav-group">
                    <p class="app-nav-title">{{ $groupTitle }}</p>
                    @foreach ($items as $item)
                        @php
                            if (isset($item['izin']) && ! $izinVar($item['izin'])) {
                                continue;
                            }
                            $children = [];
                            foreach ($item['children'] ?? [] as $child) {
                                if (! isset($child['izin']) || $izinVar($child['izin'])) {
                                    $children[] = $child;
                                }
                            }
                            $hasChildren = $children !== [];
                            $hasRoute = isset($item['route']);
                            if (! $hasChildren && ! $hasRoute && ($item['children'] ?? []) !== []) {
                                continue;
                            }
                            $active = $hasRoute && $currentRoute === $item['route'];
                            $activeChild = false;
                            if ($hasChildren) {
                                foreach ($children as $child) {
                                    if (isset($child['route']) && $currentRoute === $child['route']) {
                                        $activeChild = true;
                                        break;
                                    }
                                }
                            }
                        @endphp

                        @if ($hasChildren)
                            <div class="app-nav-parent {{ $activeChild ? 'is-open' : '' }}" data-tooltip="{{ $item['label'] }}">
                                <button type="button" class="app-nav-item app-nav-toggle" data-dropdown-toggle aria-expanded="{{ $activeChild ? 'true' : 'false' }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        {!! $icons[$item['icon']] ?? '' !!}
                                    </svg>
                                    <span>{{ $item['label'] }}</span>
                                    <svg class="app-nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M6 9 l6 6 6-6"/>
                                    </svg>
                                </button>
                                <div class="app-nav-submenu">
                                    <div class="app-nav-submenu-inner">
                                        <p class="app-nav-flyout-title">{{ $item['label'] }}</p>
                                        @foreach ($children as $child)
                                            @php
                                                $childGrandchildren = [];
                                                foreach ($child['children'] ?? [] as $gc) {
                                                    if (! isset($gc['izin']) || $izinVar($gc['izin'])) {
                                                        $childGrandchildren[] = $gc;
                                                    }
                                                }
                                                $childHasChildren = $childGrandchildren !== [];
                                                $childHref = isset($child['route']) ? route($child['route']) : '#';
                                                $childActive = isset($child['route']) && $currentRoute === $child['route'];
                                                $childActiveGrandchild = false;
                                                if ($childHasChildren) {
                                                    foreach ($childGrandchildren as $gc) {
                                                        if (isset($gc['route']) && $currentRoute === $gc['route']) {
                                                            $childActiveGrandchild = true;
                                                            break;
                                                        }
                                                    }
                                                }
                                                $childDisabled = ! isset($child['route']) && ! $childHasChildren;
                                            @endphp
                                            @if ($childHasChildren)
                                                <div class="app-nav-subgroup {{ $childActiveGrandchild ? 'is-open' : '' }}">
                                                    <button type="button" class="app-nav-subitem app-nav-subitem-toggle" data-nested-toggle aria-expanded="{{ $childActiveGrandchild ? 'true' : 'false' }}">
                                                        <span class="app-nav-dot" aria-hidden="true"></span>
                                                        <span>{{ $child['label'] }}</span>
                                                        <svg class="app-nav-sub-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                            <path d="M6 9l6 6 6-6"/>
                                                        </svg>
                                                    </button>
                                                    <div class="app-nav-subgroup-icerik">
                                                        @foreach ($childGrandchildren as $gc)
                                                            @php
                                                                $gcHref = isset($gc['route']) ? route($gc['route']) : '#';
                                                                $gcActive = isset($gc['route']) && $currentRoute === $gc['route'];
                                                                $gcDisabled = ! isset($gc['route']);
                                                            @endphp
                                                            <a href="{{ $gcHref }}"
                                                               class="app-nav-sub-subitem {{ $gcActive ? 'is-active' : '' }} {{ $gcDisabled ? 'is-disabled' : '' }}"
                                                               @if ($gcDisabled) aria-disabled="true" title="Yakında" @endif>
                                                                <span class="app-nav-dot" aria-hidden="true"></span>
                                                                <span>{{ $gc['label'] }}</span>
                                                            </a>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @else
                                                <a href="{{ $childHref }}"
                                                   class="app-nav-subitem {{ $childActive ? 'is-active' : '' }} {{ $childDisabled ? 'is-disabled' : '' }}"
                                                   @if ($childDisabled) aria-disabled="true" title="Yakında" @endif>
                                                    <span class="app-nav-dot" aria-hidden="true"></span>
                                                    <span>{{ $child['label'] }}</span>
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @else
                            @php
                                $href = $hasRoute ? route($item['route']) : '#';
                                $disabled = ! $hasRoute;
                            @endphp
                            <a href="{{ $href }}"
                               class="app-nav-item {{ $active ? 'is-active' : '' }} {{ $disabled ? 'is-disabled' : '' }}"
                               data-tooltip="{{ $item['label'] }}"
                               @if ($disabled) aria-disabled="true" title="Yakında" @endif>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    {!! $icons[$item['icon']] ?? '' !!}
                                </svg>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </nav>

        <div class="app-user">
            <div class="app-avatar">{{ $initials }}</div>
            <div class="app-user-info">
                <strong>{{ $user->getAdSoyad() }}</strong>
                <span>{{ ($user->rol?->ad ?? 'Rol yok') . ' · @' . $user->kullanici_adi }}</span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="app-logout-btn" type="submit" title="Çıkış" aria-label="Çıkış">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 4 h3 a2 2 0 0 1 2 2 v12 a2 2 0 0 1-2 2 h-3"/>
                        <path d="M10 16 l-4-4 4-4 M14 12 H6"/>
                    </svg>
                </button>
            </form>
        </div>
    </aside>

    <div class="app-main">
        @php
            $gunler = ['Sunday'=>'Pazar','Monday'=>'Pazartesi','Tuesday'=>'Salı','Wednesday'=>'Çarşamba','Thursday'=>'Perşembe','Friday'=>'Cuma','Saturday'=>'Cumartesi'];
            $aylar = ['January'=>'Ocak','February'=>'Şubat','March'=>'Mart','April'=>'Nisan','May'=>'Mayıs','June'=>'Haziran','July'=>'Temmuz','August'=>'Ağustos','September'=>'Eylül','October'=>'Ekim','November'=>'Kasım','December'=>'Aralık'];
            $simdi = now();
            $tarihStr = $simdi->day.' '.$aylar[$simdi->format('F')].' '.$simdi->year;
            $gunStr = $gunler[$simdi->format('l')];
            $saatStr = $simdi->format('H:i');
            $env = strtoupper(config('app.env', 'local'));
            $bildirimSayisi = 3;
        @endphp

        <header class="app-header">
            <div class="app-header-strip">
                <nav class="app-breadcrumb" aria-label="Sayfa yolu">
                    <a href="{{ route('panel.dashboard') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 11 L12 3 l9 8"/><path d="M5 10 v10 h14 V10"/>
                        </svg>
                        Yönetim
                    </a>
                    <span class="app-breadcrumb-sep" aria-hidden="true">›</span>
                    <span aria-current="page">@yield('title', 'Özet')</span>
                </nav>

                <div class="app-header-meta">
                    <span class="app-header-datetime" title="{{ $tarihStr }} · {{ $gunStr }} · {{ $saatStr }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="16" rx="2"/>
                            <path d="M3 9 h18 M8 3 v4 M16 3 v4"/>
                        </svg>
                        <time datetime="{{ $simdi->toDateString() }}"><strong>{{ $tarihStr }}</strong> · {{ $gunStr }}</time>
                    </span>
                    <span class="app-env-badge app-env-{{ strtolower($env) }}">
                        <span class="app-env-dot"></span>{{ $env }}
                    </span>
                </div>
            </div>

            <div class="app-header-main">
                <button class="app-menu-toggle" type="button" aria-label="Menüyü aç" aria-expanded="false" aria-controls="app-sidebar" data-sidebar-toggle>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                        <path d="M4 7 h16 M4 12 h16 M4 17 h16"/>
                    </svg>
                </button>

                <div class="app-header-title">
                    <p>Yönetim Paneli</p>
                    <h1>@yield('title', 'Özet')</h1>
                </div>

                <div class="app-header-search" role="search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="M20 20 l-3.5-3.5"/>
                    </svg>
                    <input type="search" placeholder="Ada, parsel, kullanıcı, tahsis ara..." aria-label="Genel arama">
                    <kbd>Ctrl K</kbd>
                </div>

                <div class="app-header-actions">
                    <button class="app-icon-btn app-notif-btn" type="button" aria-label="Bildirimler">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 8 a6 6 0 0 1 12 0 c0 7 3 8 3 8 H3 s3-1 3-8"/>
                            <path d="M10 20 a2 2 0 0 0 4 0"/>
                        </svg>
                        @if ($bildirimSayisi > 0)
                            <span class="app-notif-badge" aria-label="{{ $bildirimSayisi }} okunmamış bildirim">{{ $bildirimSayisi }}</span>
                        @endif
                    </button>
                    <button class="app-icon-btn" type="button" aria-label="Yardım">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"/>
                            <path d="M9.5 9 a2.5 2.5 0 0 1 5 0 c0 2-2.5 2-2.5 4"/>
                            <path d="M12 17.5 v0.5"/>
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <main class="app-content @yield('contentClass')">
            @yield('content')
        </main>
    </div>

    <div class="app-scrim" data-sidebar-toggle></div>

    <script>
        (function () {
            const shell = document.body;
            const sidebar = document.getElementById('app-sidebar');
            const scrim = document.querySelector('.app-scrim');
            const menuToggle = document.querySelector('.app-menu-toggle');
            const isMobile = () => window.matchMedia('(max-width: 900px)').matches;

            function setNavOpen(open) {
                sidebar.classList.toggle('is-open', open);
                if (scrim) scrim.classList.toggle('is-open', open);
                shell.classList.toggle('is-nav-open', open);
                if (menuToggle) {
                    menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    menuToggle.setAttribute('aria-label', open ? 'Menüyü kapat' : 'Menüyü aç');
                }
            }

            if (!isMobile() && localStorage.getItem('etys-sidebar-collapsed') === '1') {
                shell.classList.add('is-collapsed');
            }

            document.querySelectorAll('[data-sidebar-toggle]').forEach(el => {
                el.addEventListener('click', () => {
                    setNavOpen(!sidebar.classList.contains('is-open'));
                });
            });

            document.querySelectorAll('[data-collapse-toggle]').forEach(el => {
                el.addEventListener('click', () => {
                    if (isMobile()) return;
                    const collapsed = shell.classList.toggle('is-collapsed');
                    localStorage.setItem('etys-sidebar-collapsed', collapsed ? '1' : '0');
                });
            });

            sidebar.querySelectorAll('a[href]:not(.is-disabled)').forEach(a => {
                a.addEventListener('click', () => {
                    if (isMobile()) setNavOpen(false);
                });
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
                    setNavOpen(false);
                }
            });

            window.addEventListener('resize', () => {
                if (!isMobile()) setNavOpen(false);
            });

            document.querySelectorAll('[data-dropdown-toggle]').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (shell.classList.contains('is-collapsed') && !isMobile()) return;
                    const parent = btn.closest('.app-nav-parent');
                    const isOpen = parent.classList.toggle('is-open');
                    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });
            });

            // Nested submenu toggle (Taşınmazlar → Sayıştay Raporları gibi)
            document.querySelectorAll('[data-nested-toggle]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const grup = btn.closest('.app-nav-subgroup');
                    const isOpen = grup.classList.toggle('is-open');
                    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
