@extends('layouts.panel')

@section('title', 'Lojmanı Düzenle')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/lojman.css') }}?v=1">
@endpush

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Lojman · #{{ $lojman->id }}</p>
        <h2>{{ $lojman->ad }}</h2>
        <small>Konum, özellik ve durum bilgilerini düzenleyin. Tahsisler lojman ekranından yönetilir.</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.lojman.detay', $lojman->id) }}" class="btn-cancel">← Lojmana Dön</a>
    </div>
</section>

@if ($errors->any())
    <div class="flash-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@include('panel.lojman._form')
@endsection
