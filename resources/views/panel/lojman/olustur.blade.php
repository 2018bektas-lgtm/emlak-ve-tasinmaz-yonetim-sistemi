@extends('layouts.panel')

@section('title', 'Yeni Lojman')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/lojman.css') }}?v=1">
@endpush

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Lojman · Yeni Kayıt</p>
        <h2>Yeni Lojman</h2>
        <small>Konut biriminin konumu, özellikleri ve durumu. Tahsis ve başvurular kayıttan sonra eklenir.</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.lojman.index') }}" class="btn-cancel">← Listeye Dön</a>
    </div>
</section>

@if ($errors->any())
    <div class="flash-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@include('panel.lojman._form')
@endsection
