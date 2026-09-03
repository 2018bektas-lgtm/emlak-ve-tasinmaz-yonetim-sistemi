@extends('layouts.panel')

@section('title', 'Müdürlükler')

@section('content')
@if (session('basari'))
    <div class="flash-success" role="status">{{ session('basari') }}</div>
@endif
@if (session('hata'))
    <div class="flash-error" role="alert">{{ session('hata') }}</div>
@endif

<section class="page-hero">
    <div class="page-hero-text">
        <h2>Müdürlükler</h2>
        <small>Taşınmaz kayıtları müdürlük kapsamına göre filtrelenir.</small>
    </div>
    <div class="page-hero-actions">
        @can('mudurluk.olustur')
            <a href="{{ route('panel.mudurlukler.olustur') }}" class="btn-submit">Yeni Müdürlük</a>
        @endcan
    </div>
</section>

<div class="adm-kart">
    <table class="adm-tablo">
        <thead>
            <tr>
                <th>Müdürlük</th>
                <th>Kod</th>
                <th>Kullanıcı</th>
                <th>Taşınmaz</th>
                <th>Durum</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($mudurlukler as $m)
                <tr>
                    <td>
                        <strong>{{ $m->ad }}</strong>
                        @if ($m->aciklama)
                            <div class="adm-alt">{{ $m->aciklama }}</div>
                        @endif
                    </td>
                    <td class="tl-td-mono">{{ $m->kod }}</td>
                    <td>{{ $m->kullanicilar_count }}</td>
                    <td>{{ $m->tasinmazlar_count }}</td>
                    <td>
                        @if ($m->aktif_mi)
                            <span class="durum-aktif">Aktif</span>
                        @else
                            <span class="durum-pasif">Pasif</span>
                        @endif
                    </td>
                    <td class="adm-islem">
                        @can('mudurluk.duzenle')
                            <a href="{{ route('panel.mudurlukler.duzenle', $m) }}" class="btn-cancel">Düzenle</a>
                        @endcan
                        @can('mudurluk.sil')
                            <form method="POST" action="{{ route('panel.mudurlukler.sil', $m) }}" onsubmit="return confirm('{{ $m->ad }} silinsin mi?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-cancel btn-tehlike">Sil</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
