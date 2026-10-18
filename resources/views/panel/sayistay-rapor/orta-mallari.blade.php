@extends('layouts.panel')

@section('title', $baslik)

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/araclar.css') }}?v=1">
@endpush

@section('content')
    @include('panel.sayistay-rapor._govde')
@endsection
