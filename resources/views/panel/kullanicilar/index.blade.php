@extends('layouts.panel')

@section('title', 'Kullanıcılar')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/yonetim.css') }}?v=2">
@endpush

@php
    $aktifRol = (string) ($filtre['rol_id'] ?? '');
    $sorguRolsuz = collect(request()->query())->except(['rol_id', 'page'])->all();
    $sorguDurumsuz = collect(request()->query())->except(['durum', 'page'])->all();
    $ben = auth()->user();
@endphp

@section('content')
@if (session('basari'))<div class="flash-success" role="status">{{ session('basari') }}</div>@endif
@if (session('hata'))<div class="flash-error" role="alert">{{ session('hata') }}</div>@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Sistem Yönetimi</p>
        <h2>Kullanıcılar</h2>
        <small><strong>{{ $sayac['toplam'] }}</strong> kullanıcı · {{ $sayac['aktif'] }} aktif{{ $sayac['pasif'] ? ' · '.$sayac['pasif'].' pasif' : '' }} · yetkiler kullanıcının rolünden gelir</small>
    </div>
    <div class="page-hero-actions">
        @can('rol.goruntule')
            <a href="{{ route('panel.roller.index') }}" class="btn-cancel">Roller ve İzinler</a>
        @endcan
        @can('kullanici.olustur')
            <a href="{{ route('panel.kullanicilar.olustur') }}" class="btn-submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Yeni Kullanıcı
            </a>
        @endcan
    </div>
</section>

<div class="hsl-kisayol-bar" role="tablist" aria-label="Role göre">
    <a href="{{ route('panel.kullanicilar.index', $sorguRolsuz) }}" class="hsl-kisayol {{ $aktifRol === '' ? 'is-aktif' : '' }}">
        Tüm Roller <span class="say">{{ $sayac['toplam'] }}</span>
    </a>
    @foreach ($roller as $rol)
        <a href="{{ route('panel.kullanicilar.index', $sorguRolsuz + ['rol_id' => $rol->id]) }}" class="hsl-kisayol {{ $aktifRol === (string) $rol->id ? 'is-aktif' : '' }}">
            <span class="ym-rol-nokta is-{{ $rol->kod }}"></span>
            {{ $rol->ad }} <span class="say">{{ $rol->kullanicilar_count }}</span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('panel.kullanicilar.index') }}">
    @if ($aktifRol !== '')<input type="hidden" name="rol_id" value="{{ $aktifRol }}">@endif
    <div class="hsl-toolbar">
        <div class="hsl-ara">
            <svg class="hsl-ara-ikon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="search" name="q" value="{{ $filtre['q'] }}" placeholder="Ad, soyad, e-posta ya da kullanıcı adı…" autocomplete="off">
        </div>
        <div class="ym-segment" role="group" aria-label="Durum">
            <a href="{{ route('panel.kullanicilar.index', $sorguDurumsuz) }}" class="{{ ! $filtre['durum'] ? 'is-aktif' : '' }}">Tümü</a>
            <a href="{{ route('panel.kullanicilar.index', $sorguDurumsuz + ['durum' => 'aktif']) }}" class="{{ $filtre['durum'] === 'aktif' ? 'is-aktif' : '' }}">Aktif</a>
            <a href="{{ route('panel.kullanicilar.index', $sorguDurumsuz + ['durum' => 'pasif']) }}" class="{{ $filtre['durum'] === 'pasif' ? 'is-aktif' : '' }}">Pasif</a>
        </div>
        <button type="submit" class="hsl-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>Ara</button>
        @if ($filtre['q'] || $aktifRol !== '' || $filtre['durum'])
            <a href="{{ route('panel.kullanicilar.index') }}" class="hsl-btn is-tehlike">Temizle</a>
        @endif
    </div>
</form>

@if ($kullanicilar->isEmpty())
    <div class="hs-bos-kart">Filtreye uygun kullanıcı bulunamadı.</div>
@else
    <div class="tl-tablo-wrap">
        <table class="tl-tablo ym-tablo">
            <thead><tr><th>Kullanıcı</th><th>Kullanıcı Adı</th><th>Rol</th><th>Durum</th><th>Kayıt</th><th></th></tr></thead>
            <tbody>
                @foreach ($kullanicilar as $k)
                    @php
                        $hedefAdmin = $k->rol?->kod === 'admin';
                        $yonetebilir = ! $hedefAdmin || $ben->adminMi();
                    @endphp
                    <tr class="{{ $k->aktif_mi ? '' : 'is-pasif' }}">
                        <td>
                            <div class="ym-kisi">
                                <span class="ym-avatar is-{{ $k->rol?->kod ?? 'yok' }}">{{ mb_strtoupper(mb_substr($k->ad, 0, 1).mb_substr($k->soyad, 0, 1)) }}</span>
                                <span class="ym-kisi-metin">
                                    <strong>{{ $k->getAdSoyad() }}@if ($k->id === $ben->id) <em class="ym-sen">siz</em>@endif</strong>
                                    <small>{{ $k->mail }}</small>
                                </span>
                            </div>
                        </td>
                        <td class="td-mono">{{ '@'.$k->kullanici_adi }}</td>
                        <td>
                            @if ($k->rol)
                                <span class="ym-rol is-{{ $k->rol->kod }}">{{ $k->rol->ad }}</span>
                            @else
                                <span class="ym-rol is-yok">Rol atanmamış</span>
                            @endif
                        </td>
                        <td><span class="ym-durum {{ $k->aktif_mi ? 'is-aktif' : 'is-pasif' }}">{{ $k->aktif_mi ? 'Aktif' : 'Pasif' }}</span></td>
                        <td class="td-mono ym-soluk">{{ $k->created_at?->format('d.m.Y') }}</td>
                        <td class="ym-islem">
                            @if ($yonetebilir)
                                @can('kullanici.duzenle')
                                    <a href="{{ route('panel.kullanicilar.duzenle', $k) }}" class="btn-cancel hs-mini-btn">Düzenle</a>
                                @endcan
                                @can('kullanici.sil')
                                    @if ($k->id !== $ben->id)
                                        <form method="POST" action="{{ route('panel.kullanicilar.sil', $k) }}" onsubmit="return confirm('{{ $k->getAdSoyad() }} silinsin mi? Bu işlem geri alınamaz.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-cancel hs-mini-btn ym-sil">Sil</button>
                                        </form>
                                    @endif
                                @endcan
                            @else
                                <span class="ym-soluk" title="Admin hesabını yalnızca admin düzenleyebilir">Kilitli</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="tl-pagination">{{ $kullanicilar->links() }}</div>
@endif
@endsection
