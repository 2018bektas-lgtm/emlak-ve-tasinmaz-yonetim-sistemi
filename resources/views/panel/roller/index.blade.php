@extends('layouts.panel')

@section('title', 'Roller ve İzinler')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/yonetim.css') }}?v=2">
@endpush

@php
    $moduller = \App\Support\YetkiModulleri::tamListe($izinSayilari->keys());
    $toplamIzin = (int) $izinSayilari->sum();
@endphp

@section('content')
@if (session('basari'))<div class="flash-success" role="status">{{ session('basari') }}</div>@endif
@if (session('hata'))<div class="flash-error" role="alert">{{ session('hata') }}</div>@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Sistem Yönetimi</p>
        <h2>Roller ve İzinler</h2>
        <small><strong>{{ $roller->count() }}</strong> rol · <strong>{{ $toplamIzin }}</strong> izin · kullanıcılar bir role atanır, izinler rolden gelir</small>
    </div>
    <div class="page-hero-actions">
        @can('kullanici.goruntule')
            <a href="{{ route('panel.kullanicilar.index') }}" class="btn-cancel">Kullanıcılar</a>
        @endcan
        @can('rol.duzenle')
            <a href="{{ route('panel.roller.olustur') }}" class="btn-submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Yeni Rol
            </a>
        @endcan
    </div>
</section>

<div class="ym-rol-izgara">
    @foreach ($roller as $rol)
        @php
            $admin = $rol->kod === 'admin';
            $grupSayilari = $rol->izinler->countBy('grup');
            $oran = $toplamIzin ? round(($admin ? $toplamIzin : $rol->izinler_count) * 100 / $toplamIzin) : 0;
        @endphp
        <article class="ym-rol-panel is-{{ $rol->kod }}">
            <header class="ym-rol-panel-ust">
                <span class="ym-rol-ikon is-{{ $rol->kod }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.4 8.6-8 10-4.6-1.4-8-5-8-10V6z"/>@if ($admin)<path d="M9 12l2 2 4-4"/>@endif</svg>
                </span>
                <div class="ym-rol-panel-baslik">
                    <h3>{{ $rol->ad }}</h3>
                    <small class="td-mono">{{ $rol->kod }}@if ($rol->sistem_mi) · <span class="ym-sistem">sistem rolü</span>@endif</small>
                </div>
                <div class="ym-rol-oran" style="--oran: {{ $oran }}" title="İzinlerin %{{ $oran }}'i">
                    <span>%{{ $oran }}</span>
                </div>
            </header>

            <p class="ym-rol-aciklama">{{ $rol->aciklama ?: 'Açıklama girilmemiş.' }}</p>

            <div class="ym-kapsam">
                @foreach ($moduller as $mod)
                    @php
                        $toplam = collect($mod['gruplar'])->keys()->sum(fn ($g) => $izinSayilari[$g] ?? 0);
                        $secili = $admin ? $toplam : collect($mod['gruplar'])->keys()->sum(fn ($g) => $grupSayilari[$g] ?? 0);
                    @endphp
                    @continue($toplam === 0)
                    <div class="ym-kapsam-satir">
                        <span class="ym-kapsam-ad">{{ $mod['ad'] }}</span>
                        <span class="ym-kapsam-cubuk"><span style="width: {{ round($secili * 100 / $toplam) }}%; background: {{ $mod['renk'] }}"></span></span>
                        <span class="ym-kapsam-sayi td-mono">{{ $secili }}/{{ $toplam }}</span>
                    </div>
                @endforeach
            </div>

            <footer class="ym-rol-panel-alt">
                @can('kullanici.goruntule')
                    <a href="{{ route('panel.kullanicilar.index', ['rol_id' => $rol->id]) }}" class="ym-rol-kullanici">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c1-4 4-6 6.5-6s5.5 2 6.5 6"/></svg>
                        {{ $rol->kullanicilar_count }} kullanıcı
                    </a>
                @else
                    <span class="ym-rol-kullanici">{{ $rol->kullanicilar_count }} kullanıcı</span>
                @endcan
                @can('rol.duzenle')
                    <span class="ym-islem">
                        <a href="{{ route('panel.roller.duzenle', $rol) }}" class="btn-cancel hs-mini-btn">{{ $admin ? 'Görüntüle' : 'Düzenle' }}</a>
                        @if (! $rol->sistem_mi)
                            <form method="POST" action="{{ route('panel.roller.sil', $rol) }}" onsubmit="return confirm('“{{ $rol->ad }}” rolü silinsin mi?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-cancel hs-mini-btn ym-sil" @disabled($rol->kullanicilar_count > 0) title="{{ $rol->kullanicilar_count > 0 ? 'Bu role atanmış kullanıcılar var' : '' }}">Sil</button>
                            </form>
                        @endif
                    </span>
                @endcan
            </footer>
        </article>
    @endforeach
</div>
@endsection
