@extends('layouts.panel')

@section('title', 'Özet')

@section('content')
<section class="page-hero">
    <p class="civic-kicker">Yönetim Özeti</p>
    <h2>Hoş geldiniz, {{ auth()->user()->getAdSoyad() }}.</h2>
    <p class="lead">Ada, parsel, mesken, tahsis ve tapu işlemleri bu panel üzerinden yürütülür.</p>
</section>

<div class="stat-grid">
    <article class="stat-card">
        <span class="stat-label">Kayıtlı parsel</span>
        <span class="stat-value">—</span>
        <span class="stat-hint">Toplam ada/parsel adedi</span>
    </article>
    <article class="stat-card">
        <span class="stat-label">Aktif tahsis</span>
        <span class="stat-value">—</span>
        <span class="stat-hint">Yürürlükte olan tahsisler</span>
    </article>
    <article class="stat-card">
        <span class="stat-label">İmar planı</span>
        <span class="stat-value">—</span>
        <span class="stat-hint">Onaylı plan sayısı</span>
    </article>
    <article class="stat-card">
        <span class="stat-label">Bekleyen işlem</span>
        <span class="stat-value">—</span>
        <span class="stat-hint">İnceleme aşamasında</span>
    </article>
</div>
@endsection
