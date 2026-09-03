@extends('layouts.panel')

@section('title', 'Kullanıcılar')

@section('content')
@if (session('basari'))
    <div class="flash-success" role="status">{{ session('basari') }}</div>
@endif
@if (session('hata'))
    <div class="flash-error" role="alert">{{ session('hata') }}</div>
@endif

<section class="page-hero">
    <div class="page-hero-text">
        <h2>Kullanıcılar</h2>
        <small>{{ $kullanicilar->total() }} kayıt</small>
    </div>
    <div class="page-hero-actions">
        @can('kullanici.olustur')
            <a href="{{ route('panel.kullanicilar.olustur') }}" class="btn-submit">Yeni Kullanıcı</a>
        @endcan
    </div>
</section>

<form method="GET" action="{{ route('panel.kullanicilar.index') }}" class="tl-filtre">
    <div class="tl-filtre-satir tl-filtre-ust">
        <div class="tl-filtre-alan">
            <input type="text" name="q" value="{{ $filtre['q'] }}" placeholder="Ad, soyad, e-posta, kullanıcı adı…" autocomplete="off">
        </div>
        <div class="tl-filtre-grup">
            <select name="rol_id" class="form-select">
                <option value="">Tüm roller</option>
                @foreach ($roller as $rol)
                    <option value="{{ $rol->id }}" @selected($filtre['rol_id'] == $rol->id)>{{ $rol->ad }}</option>
                @endforeach
            </select>
        </div>
        <div class="tl-filtre-grup">
            <select name="mudurluk_id" class="form-select">
                <option value="">Tüm müdürlükler</option>
                @foreach ($mudurlukler as $m)
                    <option value="{{ $m->id }}" @selected($filtre['mudurluk_id'] == $m->id)>{{ $m->ad }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-submit tl-filtre-btn">Filtrele</button>
    </div>
</form>

<div class="adm-kart">
    <table class="adm-tablo">
        <thead>
            <tr>
                <th>Kullanıcı</th>
                <th>Rol</th>
                <th>Müdürlük</th>
                <th>Durum</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($kullanicilar as $k)
                <tr>
                    <td>
                        <strong>{{ $k->getAdSoyad() }}</strong>
                        <div class="adm-alt">{{ $k->mail }} · @{{ $k->kullanici_adi }}</div>
                    </td>
                    <td>
                        @if ($k->rol)
                            <span class="rol-rozet rol-rozet-{{ $k->rol->kod }}">{{ $k->rol->ad }}</span>
                        @else
                            <span class="tl-pill tl-pill-mute">Atanmamış</span>
                        @endif
                    </td>
                    <td>{{ $k->mudurluk->ad ?? '—' }}</td>
                    <td>
                        @if ($k->aktif_mi)
                            <span class="durum-aktif">Aktif</span>
                        @else
                            <span class="durum-pasif">Pasif</span>
                        @endif
                    </td>
                    <td class="adm-islem">
                        @can('kullanici.duzenle')
                            <a href="{{ route('panel.kullanicilar.duzenle', $k) }}" class="btn-cancel">Düzenle</a>
                        @endcan
                        @can('kullanici.sil')
                            @if ($k->id !== auth()->id())
                                <form method="POST" action="{{ route('panel.kullanicilar.sil', $k) }}" onsubmit="return confirm('{{ $k->getAdSoyad() }} silinsin mi?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-cancel btn-tehlike">Sil</button>
                                </form>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Kayıt bulunamadı.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="tl-pagination">
    {{ $kullanicilar->links() }}
</div>
@endsection
