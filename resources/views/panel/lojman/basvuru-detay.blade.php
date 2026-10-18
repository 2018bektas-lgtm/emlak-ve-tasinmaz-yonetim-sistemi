@extends('layouts.panel')

@section('title', 'Başvuru — '.$basvuru->ad_soyad)

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/lojman.css') }}?v=1">
@endpush

@php
    use App\Models\LojmanBasvuru;

    $u = auth()->user();
    $duzenleyebilir = $u->izinVarMi('lojman.duzenle');
    $t = $basvuru->tahsis();
    $tarih = fn ($d) => $d ? $d->format('d.m.Y') : '—';
    $lojman = null; // _basvuru-form ön seçimi
    // Tahsis için uygun lojmanlar: başvuru belirli bir lojmana ise yalnız o (boşsa)
    $tahsisLojmanlari = $basvuru->lojman_id ? $bosLojmanlar->where('id', $basvuru->lojman_id) : $bosLojmanlar;
@endphp

@section('content')
@if (session('basari'))<div class="flash-success">{{ session('basari') }}</div>@endif
@if ($errors->any())<div class="flash-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Lojman Başvurusu · #{{ $basvuru->id }}</p>
        <h2>{{ $basvuru->ad_soyad }}</h2>
        <small class="lj-hero-alt">
            <span class="lj-bdurum is-{{ $basvuru->durum }}">{{ LojmanBasvuru::DURUMLAR[$basvuru->durum] }}</span>
            @if ($sira)<span class="lj-tip">Bekleme sırası {{ $sira }}</span>@endif
            <span>{{ $tarih($basvuru->basvuru_tarihi) }} · {{ $basvuru->lojman?->ad ?? 'Genel başvuru' }}</span>
        </small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.lojman.basvurular') }}" class="btn-cancel">← Başvurular</a>
        @if ($u->izinVarMi('lojman.sil') && ! $t)
            <button type="button" class="btn-cancel lj-btn-sil" data-sil="{{ route('panel.lojman.basvuru.sil', $basvuru->id) }}" data-sonra="{{ route('panel.lojman.basvurular') }}" data-soru="Başvuru ve evrakları silinsin mi?">Sil</button>
        @endif
    </div>
</section>

<div class="lj-detay-grid is-genis-sol">
    <section class="lj-kart">
        <h3 class="lj-kart-baslik">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Başvuru Bilgileri
        </h3>
        @if ($duzenleyebilir)
            <form method="POST" action="{{ route('panel.lojman.basvuru.guncelle', $basvuru->id) }}" class="lj-kart-form">
                @csrf @method('PUT')
                @include('panel.lojman._basvuru-form', ['kompakt' => true])
                <div class="lj-ekle-alt"><button type="submit" class="btn-submit">Değişiklikleri Kaydet</button></div>
            </form>
        @else
            <dl class="lj-bilgi">
                <div><dt>T.C. Kimlik</dt><dd class="td-mono">{{ $basvuru->tc_kimlik ?? '—' }}</dd></div>
                <div><dt>Sicil / Ünvan</dt><dd>{{ $basvuru->sicil_no ?? '—' }} · {{ $basvuru->unvan ?? '—' }}</dd></div>
                <div><dt>Birim</dt><dd>{{ $basvuru->birim ?? '—' }}</dd></div>
                <div><dt>Tahsis Türü</dt><dd>{{ $basvuru->tahsis_turu ?? '—' }}</dd></div>
                @if ($basvuru->aciklama)<div><dt>Açıklama</dt><dd class="lj-metin">{{ $basvuru->aciklama }}</dd></div>@endif
            </dl>
        @endif
    </section>

    <div class="lj-ana">
        <section class="lj-kart" id="tahsis">
            <h3 class="lj-kart-baslik">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V6a2 2 0 012-2h10a2 2 0 012 2v15"/><path d="M10 21v-4h4v4"/></svg>
                Tahsis
            </h3>
            @if ($t)
                <article class="lj-oge {{ $t->aktifMi() ? 'is-aktif' : '' }}">
                    <div class="lj-oge-ust">
                        <div>
                            <strong><a class="lj-link" href="{{ route('panel.lojman.detay', $t->lojman_id) }}">{{ $t->lojman?->ad }}</a></strong>
                            <small>{{ $tarih($t->baslangic_tarihi) }} – {{ $t->bitis_tarihi ? $tarih($t->bitis_tarihi) : 'süresiz' }}</small>
                        </div>
                        <span class="lj-rozet {{ $t->aktifMi() ? 'is-aktif' : '' }}">{{ $t->baslamadiMi() ? 'Başlamadı' : ($t->aktifMi() ? 'Oturuyor · '.$t->gunSayisi().' gün' : 'Sona erdi · '.$t->gunSayisi().' gün') }}</span>
                    </div>
                    <div class="lj-oge-grid">
                        <div><span>Karar No</span><b>{{ $t->karar_no ?? '—' }}</b></div>
                        <div><span>Karar Tarihi</span><b>{{ $tarih($t->karar_tarihi) }}</b></div>
                        <div><span>Sicil</span><b>{{ $t->sicil_no ?? '—' }}</b></div>
                    </div>
                </article>
            @elseif (! $basvuru->acikMi())
                <div class="lj-bos-kucuk">Başvuru reddedildi; tahsis yapılamaz. Yeniden değerlendirmek için durumunu değiştirin.</div>
            @elseif ($duzenleyebilir)
                @if ($basvuru->durum !== 'onaylandi')
                    <div class="lj-bilgi-kutu">Başvuru henüz onaylanmadı. Tahsisten önce değerlendirmeyi “Onaylandı” yapmanız önerilir.</div>
                @endif
                @if ($tahsisLojmanlari->isEmpty())
                    <div class="lj-bos-kucuk">
                        {{ $basvuru->lojman_id ? 'Tercih edilen lojman şu an boş değil.' : 'Şu an boş lojman yok.' }}
                    </div>
                @else
                    <form method="POST" action="{{ route('panel.lojman.basvuru.tahsis', $basvuru->id) }}" class="lj-ekle-form">
                        @csrf
                        <div class="form-field">
                            <label>Lojman *</label>
                            <select name="lojman_id" class="form-select" required>
                                @if ($tahsisLojmanlari->count() > 1)<option value="">— Boş lojman seçin —</option>@endif
                                @foreach ($tahsisLojmanlari as $l)
                                    <option value="{{ $l->id }}" @selected(old('lojman_id') == $l->id)>{{ $l->ad }}{{ $l->tip ? ' · '.$l->tip : '' }}</option>
                                @endforeach
                            </select>
                            <span class="hint">Yalnız boş lojmanlar listelenir.</span>
                        </div>
                        <div class="lj-form-grid">
                            <div class="form-field"><label>Başlangıç *</label><input type="date" name="baslangic_tarihi" class="form-input" required value="{{ old('baslangic_tarihi', now()->toDateString()) }}"></div>
                            <div class="form-field"><label>Bitiş</label><input type="date" name="bitis_tarihi" class="form-input" value="{{ old('bitis_tarihi') }}"></div>
                            <div class="form-field"><label>Karar No</label><input type="text" name="karar_no" class="form-input" maxlength="100" value="{{ old('karar_no') }}"></div>
                            <div class="form-field"><label>Karar Tarihi</label><input type="date" name="karar_tarihi" class="form-input" max="{{ now()->toDateString() }}" value="{{ old('karar_tarihi') }}"></div>
                        </div>
                        <div class="lj-ekle-alt"><button type="submit" class="btn-submit">Tahsis Et</button></div>
                    </form>
                @endif
            @else
                <div class="lj-bos-kucuk">Henüz tahsis yapılmadı.</div>
            @endif
        </section>

        <section class="lj-kart" id="evraklar">
            <h3 class="lj-kart-baslik">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                Evraklar
                <span class="lj-kart-sayi">{{ $basvuru->evraklar->count() }}</span>
            </h3>
            @include('panel.lojman._evraklar', ['evraklar' => $basvuru->evraklar, 'basvuruId' => $basvuru->id, 'lojmanId' => $basvuru->lojman_id])
        </section>
    </div>
</div>

<div class="hs-bildirim" id="hs-bildirim" role="status" aria-live="polite"></div>
@include('panel.lojman._ortak-js')
@endsection
