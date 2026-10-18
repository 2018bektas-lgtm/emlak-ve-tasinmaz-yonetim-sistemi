@extends('layouts.panel')

@section('title', 'Yeni Lojman Başvurusu')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/lojman.css') }}?v=1">
@endpush

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Lojman · Yeni Başvuru</p>
        <h2>{{ $lojman ? $lojman->ad : 'Lojman Başvurusu' }}</h2>
        <small>Personel bilgileri ve lojman tercihi. Lojman seçilmezse başvuru genel sıraya girer ve herhangi bir boş lojmana tahsis edilebilir.</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ $lojman ? route('panel.lojman.detay', $lojman->id) : route('panel.lojman.basvurular') }}" class="btn-cancel">← Vazgeç</a>
    </div>
</section>

@if ($errors->any())<div class="flash-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ route('panel.lojman.basvuru.kaydet') }}" class="form-shell lj-form">
    @csrf
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">1</span>
            <div class="form-section-head-text">
                <h3>Başvuru</h3>
                <small>T.C. Kimlik No kontrol haneleriyle doğrulanır; aynı kişinin sonuçlanmamış ikinci başvurusu açılamaz.</small>
            </div>
        </div>
        @include('panel.lojman._basvuru-form', ['basvuru' => null, 'kompakt' => false])
    </section>

    <div class="form-actions">
        <a href="{{ route('panel.lojman.basvurular') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            Başvuruyu Kaydet
        </button>
    </div>
</form>
@endsection
