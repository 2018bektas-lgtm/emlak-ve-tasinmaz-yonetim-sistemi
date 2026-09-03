@extends('layouts.panel')

@section('title', 'Roller ve İzinler')

@section('content')
@if (session('basari'))
    <div class="flash-success" role="status">{{ session('basari') }}</div>
@endif
@if (session('hata'))
    <div class="flash-error" role="alert">{{ session('hata') }}</div>
@endif

<section class="page-hero">
    <div class="page-hero-text">
        <h2>Roller ve İzinler</h2>
        <small>{{ $roller->count() }} rol tanımlı. Kullanıcılar role atanır, izinler rol üzerinden gelir.</small>
    </div>
    <div class="page-hero-actions">
        @can('rol.duzenle')
            <a href="{{ route('panel.roller.olustur') }}" class="btn-submit">Yeni Rol</a>
        @endcan
    </div>
</section>

<div class="adm-kart">
    <table class="adm-tablo">
        <thead>
            <tr>
                <th>Rol</th>
                <th>Kod</th>
                <th>İzin</th>
                <th>Kullanıcı</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($roller as $rol)
                <tr>
                    <td>
                        <strong>{{ $rol->ad }}</strong>
                        @if ($rol->sistem_mi)
                            <span class="rol-rozet rol-rozet-{{ $rol->kod }}">sistem</span>
                        @endif
                        @if ($rol->aciklama)
                            <div class="adm-alt">{{ $rol->aciklama }}</div>
                        @endif
                    </td>
                    <td class="tl-td-mono">{{ $rol->kod }}</td>
                    <td>{{ $rol->kod === 'admin' ? 'Tümü' : $rol->izinler_count }}</td>
                    <td>{{ $rol->kullanicilar_count }}</td>
                    <td class="adm-islem">
                        @can('rol.duzenle')
                            <a href="{{ route('panel.roller.duzenle', $rol) }}" class="btn-cancel">Düzenle</a>
                            @if (! $rol->sistem_mi)
                                <form method="POST" action="{{ route('panel.roller.sil', $rol) }}" onsubmit="return confirm('Bu rolü silmek istediğinize emin misiniz?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-cancel btn-tehlike">Sil</button>
                                </form>
                            @endif
                        @endcan
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
