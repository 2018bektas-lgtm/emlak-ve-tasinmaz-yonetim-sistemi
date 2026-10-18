@extends('layouts.panel')

@section('title', 'İşgal Kaydını Düzenle')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/ecrimisil.css') }}?v=1">
@endpush

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Ecrimisil · Kayıt #{{ $kayit->id }}</p>
        <h2>{{ $kayit->ad_soyad_unvan }}</h2>
        <small>İşgal alanı, işgalci ve sorumlu bilgilerini düzenleyin. Tutanak, rapor ve resimler kayıt ekranından yönetilir.</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.ecrimisil.detay', $kayit->id) }}" class="btn-cancel">← Kayda Dön</a>
    </div>
</section>

@if ($errors->any())
    <div class="flash-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@include('panel.ecrimisil._form')
@endsection
