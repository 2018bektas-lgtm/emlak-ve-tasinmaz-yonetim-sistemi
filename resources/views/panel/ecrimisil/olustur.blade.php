@extends('layouts.panel')

@section('title', 'Yeni Ecrimisil Kaydı')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/ecrimisil.css') }}?v=1">
@endpush

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Ecrimisil · Yeni Kayıt</p>
        <h2>İşgal Tespiti</h2>
        <small>İşgal alanını, işgalciyi ve sorumluyu girin. Tutanak, rapor ve resimler kayıttan sonra eklenir.</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.ecrimisil.index') }}" class="btn-cancel">← Listeye Dön</a>
    </div>
</section>

@if ($errors->any())
    <div class="flash-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@include('panel.ecrimisil._form')
@endsection
