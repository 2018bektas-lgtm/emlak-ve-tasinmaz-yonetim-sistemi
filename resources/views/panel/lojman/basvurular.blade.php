@extends('layouts.panel')

@section('title', 'Lojman Başvuruları')

@section('content')
@if (session('basari'))<div class="flash-success">{{ session('basari') }}</div>@endif

<section class="page-hero">
    <div class="page-hero-text">
        <h2>Lojman Başvuruları</h2>
        <small>Toplam {{ $basvurular->total() }} başvuru</small>
    </div>
    <div class="page-hero-actions" style="display:flex;gap:8px;">
        <a href="{{ route('panel.lojman.basvuru.excel') }}" class="btn-cancel">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
            Excel İndir
        </a>
        @if (auth()->user()->izinVarMi('lojman.olustur'))
            <a href="{{ route('panel.lojman.basvuru.olustur') }}" class="btn-submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Yeni Başvuru
            </a>
        @endif
    </div>
</section>

@php
    $basvuruEt = ['basvuruldu'=>'Başvuruldu','degerlendirmede'=>'Değerlendirmede','onaylandi'=>'Onaylandı','reddedildi'=>'Reddedildi','tahsisedildi'=>'Tahsis Edildi'];
    $pillRenk = ['basvuruldu'=>'#dbeafe;color:#1e40af','degerlendirmede'=>'#fef3c7;color:#92400e','onaylandi'=>'#d1fae5;color:#065f46','reddedildi'=>'#fee2e2;color:#991b1b','tahsisedildi'=>'#ede9fe;color:#5b21b6'];
@endphp

<form method="GET" action="{{ route('panel.lojman.basvurular') }}" style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px;margin-bottom:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <input type="search" name="q" value="{{ $filtre['q'] ?? '' }}" placeholder="Ad soyad, TC, sicil no ile ara..." style="flex:1;min-width:240px;padding:9px 12px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.86rem;">
    <select name="durum" style="padding:9px 12px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.86rem;">
        <option value="">— Tüm Durumlar —</option>
        @foreach ($basvuruEt as $k => $v)<option value="{{ $k }}" @selected(($filtre['durum'] ?? '') === $k)>{{ $v }}</option>@endforeach
    </select>
    <button type="submit" class="btn-submit" style="padding:9px 16px;">Filtrele</button>
    @if (($filtre['q'] ?? '') || ($filtre['durum'] ?? ''))
        <a href="{{ route('panel.lojman.basvurular') }}" style="color:#dc2626;font-size:.82rem;font-weight:600;">× Temizle</a>
    @endif
</form>

@if ($basvurular->isEmpty())
    <div class="td-bos td-bos-block">Başvuru bulunamadı.</div>
@else
    <div class="tl-tablo-wrap">
        <table class="tl-tablo">
            <thead>
                <tr><th>#</th><th>Ad Soyad</th><th>TC / Sicil</th><th>Ünvan / Birim</th><th>Tahsis Türü</th><th>Lojman</th><th>Başvuru Tarihi</th><th>Durum</th><th>İşlem</th></tr>
            </thead>
            <tbody>
                @foreach ($basvurular as $b)
                    <tr>
                        <td class="td-mono">#{{ $b->id }}</td>
                        <td><strong>{{ $b->ad_soyad }}</strong></td>
                        <td class="td-mono">{{ $b->tc_kimlik ?? '—' }}<div style="font-size:.7rem;color:#6b7280;">{{ $b->sicil_no ?? '' }}</div></td>
                        <td>{{ $b->unvan ?? '—' }}<div style="font-size:.7rem;color:#6b7280;">{{ $b->birim ?? '' }}</div></td>
                        <td>{{ $b->tahsis_turu ?? '—' }}</td>
                        <td>{{ $b->lojman?->ad ?? '— (herhangi)' }}</td>
                        <td>{{ $b->basvuru_tarihi?->format('d.m.Y') }}</td>
                        <td><span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:.74rem;font-weight:600;background:{{ $pillRenk[$b->durum] ?? '#e5e7eb' }}">{{ $basvuruEt[$b->durum] ?? $b->durum }}</span></td>
                        <td><a href="{{ route('panel.lojman.basvuru-detay', $b->id) }}" class="btn-cancel" style="padding:5px 12px;font-size:.78rem;">Detay</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="tl-pagination">{{ $basvurular->links() }}</div>
@endif
@endsection
