@extends('layouts.panel')

@section('title', 'Hisse Satışı Arşivi')

@push('head')
<style>
    .arsv-shell { display: grid; grid-template-columns: 260px 1fr; gap: 16px; align-items: start; }
    @media (max-width: 900px) { .arsv-shell { grid-template-columns: 1fr; } }

    /* ---- İstatistik şeridi ---- */
    .arsv-stats { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-bottom: 16px; }
    @media (max-width: 1100px) { .arsv-stats { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 600px)  { .arsv-stats { grid-template-columns: repeat(2, 1fr); } }
    .arsv-stat {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 14px;
        display: flex; gap: 10px; align-items: center;
    }
    .arsv-stat-ikon {
        width: 34px; height: 34px; border-radius: 8px;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; flex: 0 0 34px;
    }
    .arsv-stat-ikon svg { width: 16px; height: 16px; }
    .arsv-stat-meta { min-width: 0; }
    .arsv-stat-deger { font-size: 1.05rem; font-weight: 700; color: #0e1726; line-height: 1.1; }
    .arsv-stat-etiket { font-size: .72rem; color: #6b7280; margin-top: 2px; }

    /* ---- Sol sidebar (klasör ağacı) ---- */
    .arsv-sidebar { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; position: sticky; top: 16px; }
    .arsv-sidebar-baslik { padding: 12px 14px; font-size: .74rem; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; font-weight: 700; border-bottom: 1px solid #e5e7eb; }
    .arsv-kategori {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 14px; cursor: pointer; border-left: 3px solid transparent;
        transition: background .12s ease, border-color .12s ease;
    }
    .arsv-kategori:hover { background: #f3f4f6; }
    .arsv-kategori.is-aktif { background: #eff6ff; border-left-color: #2563eb; font-weight: 600; color: #1e40af; }
    .arsv-kategori-ikon { width: 26px; height: 26px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; color: #fff; flex: 0 0 26px; }
    .arsv-kategori-ikon svg { width: 14px; height: 14px; }
    .arsv-kategori-ad { font-size: .84rem; flex: 1; min-width: 0; }
    .arsv-kategori-sayac { font-size: .72rem; padding: 2px 8px; border-radius: 999px; background: #e5e7eb; color: #374151; font-weight: 600; }
    .arsv-kategori.is-aktif .arsv-kategori-sayac { background: #dbeafe; color: #1e40af; }

    /* ---- Sağ (ana) alan ---- */
    .arsv-main { min-width: 0; }

    /* Arama & filtre şerit */
    .arsv-toolbar {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
        padding: 12px; margin-bottom: 12px;
        display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
    }
    .arsv-ara { flex: 1; min-width: 240px; position: relative; }
    .arsv-ara input {
        width: 100%; padding: 8px 12px 8px 34px;
        border: 1px solid #d1d5db; border-radius: 8px; font-size: .86rem;
    }
    .arsv-ara input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
    .arsv-ara-ikon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9ca3af; }
    .arsv-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 8px 14px; font-size: .8rem; font-weight: 600;
        background: #2563eb; color: #fff; border: none; border-radius: 7px; cursor: pointer;
        text-decoration: none; transition: background .12s ease;
    }
    .arsv-btn svg { width: 14px; height: 14px; }
    .arsv-btn:hover { background: #1d4ed8; }
    .arsv-btn.is-ghost { background: #fff; color: #374151; border: 1px solid #d1d5db; }
    .arsv-btn.is-ghost:hover { background: #f3f4f6; }
    .arsv-btn.is-yesil { background: #059669; } .arsv-btn.is-yesil:hover { background: #047857; }
    .arsv-btn:disabled { opacity: .5; cursor: not-allowed; }

    /* Görünüm modu segmenti */
    .arsv-gorunum {
        display: inline-flex; background: #f3f4f6; border-radius: 7px; padding: 2px; gap: 2px;
    }
    .arsv-gorunum button {
        background: transparent; border: none; padding: 6px 12px; font-size: .78rem;
        color: #6b7280; cursor: pointer; border-radius: 5px; font-weight: 600;
        display: inline-flex; align-items: center; gap: 5px;
    }
    .arsv-gorunum button svg { width: 13px; height: 13px; }
    .arsv-gorunum button.is-aktif { background: #fff; color: #111827; box-shadow: 0 1px 2px rgba(0,0,0,.06); }

    /* Gelişmiş filtre paneli */
    .arsv-filtre {
        background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px;
        padding: 14px; margin-bottom: 12px;
    }
    .arsv-filtre[hidden] { display: none; }
    .arsv-filtre-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
    @media (max-width: 900px) { .arsv-filtre-grid { grid-template-columns: repeat(2, 1fr); } }
    .arsv-alan label { display: block; font-size: .7rem; text-transform: uppercase; color: #6b7280; letter-spacing: .04em; font-weight: 700; margin-bottom: 4px; }
    .arsv-alan input, .arsv-alan select {
        width: 100%; padding: 7px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: .82rem; background: #fff;
    }
    .arsv-alan input:focus, .arsv-alan select:focus { outline: none; border-color: #2563eb; }

    /* Aktif filtre chipleri */
    .arsv-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .arsv-chip {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 8px; font-size: .74rem; background: #eff6ff; color: #1e40af;
        border-radius: 12px; border: 1px solid #bfdbfe;
    }
    .arsv-chip button { background: transparent; border: none; padding: 0; cursor: pointer; color: #1e40af; display: inline-flex; }
    .arsv-chip button:hover { color: #1e3a8a; }

    /* Sonuç sayı & toplu işlem şeridi */
    .arsv-sonuc-bar {
        display: flex; justify-content: space-between; align-items: center;
        padding: 10px 4px; font-size: .82rem; color: #4b5563;
    }
    .arsv-toplu {
        display: none; align-items: center; gap: 10px;
        padding: 10px 12px; background: #1e40af; color: #fff; border-radius: 8px;
        font-size: .82rem;
    }
    .arsv-toplu.is-aktif { display: inline-flex; }
    .arsv-toplu-btn {
        background: rgba(255,255,255,.2); color: #fff; border: none;
        padding: 6px 10px; border-radius: 6px; font-size: .74rem; cursor: pointer;
        display: inline-flex; align-items: center; gap: 5px; font-weight: 600;
    }
    .arsv-toplu-btn:hover { background: rgba(255,255,255,.3); }

    /* ---- Kart görünümü ---- */
    .arsv-kartlar { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
    .arsv-kart {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden;
        display: flex; flex-direction: column; transition: box-shadow .15s ease, transform .15s ease;
        position: relative;
    }
    .arsv-kart:hover { box-shadow: 0 6px 20px rgba(0,0,0,.08); transform: translateY(-2px); }
    .arsv-kart.is-secili { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,.15); }
    .arsv-kart-secim {
        position: absolute; top: 8px; left: 8px; z-index: 2;
        background: rgba(255,255,255,.95); border-radius: 5px; padding: 3px;
    }
    .arsv-kart-secim input { margin: 0; cursor: pointer; width: 16px; height: 16px; accent-color: #2563eb; }
    .arsv-kart-thumb {
        height: 130px; background: #f3f4f6; display: flex; align-items: center; justify-content: center;
        color: #9ca3af; position: relative; overflow: hidden;
    }
    .arsv-kart-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .arsv-kart-thumb svg { width: 42px; height: 42px; }
    .arsv-kart-rozet {
        position: absolute; top: 8px; right: 8px;
        padding: 3px 8px; border-radius: 4px; font-size: .68rem; font-weight: 700; color: #fff;
    }
    .arsv-kart-govde { padding: 12px; flex: 1; display: flex; flex-direction: column; gap: 6px; }
    .arsv-kart-baslik { font-size: .84rem; font-weight: 700; color: #111827; line-height: 1.3; }
    .arsv-kart-alt { font-size: .74rem; color: #6b7280; display: flex; flex-wrap: wrap; gap: 8px; }
    .arsv-kart-alt span { display: inline-flex; align-items: center; gap: 3px; }
    .arsv-kart-aksiyonlar { padding: 10px 12px; border-top: 1px solid #f3f4f6; display: flex; gap: 6px; }
    .arsv-kart-aksiyonlar .arsv-btn { flex: 1; justify-content: center; padding: 6px 10px; font-size: .74rem; }

    /* ---- Liste görünümü ---- */
    .arsv-tablo { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
    .arsv-tablo table { width: 100%; border-collapse: collapse; }
    .arsv-tablo th {
        text-align: left; padding: 10px 12px; font-size: .72rem; text-transform: uppercase;
        color: #6b7280; font-weight: 700; background: #f9fafb; border-bottom: 1px solid #e5e7eb;
    }
    .arsv-tablo td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; font-size: .82rem; color: #374151; }
    .arsv-tablo tr:hover { background: #f9fafb; }
    .arsv-tablo tr.is-secili { background: #eff6ff; }
    .arsv-tablo input[type=checkbox] { cursor: pointer; accent-color: #2563eb; }
    .arsv-tablo .arsv-btn { padding: 4px 8px; font-size: .72rem; }

    /* Kategori rozetleri (renkler) */
    .arsv-rozet {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 2px 7px; border-radius: 4px; font-size: .68rem; font-weight: 700;
    }
    .arsv-r-basvuru  { background: #dbeafe; color: #1e40af; }
    .arsv-r-encumen  { background: #fef3c7; color: #92400e; }
    .arsv-r-gorus    { background: #ede9fe; color: #5b21b6; }
    .arsv-r-imar     { background: #fce7f3; color: #9d174d; }
    .arsv-r-tapu     { background: #d1fae5; color: #065f46; }

    /* ---- Zaman çizelgesi ---- */
    .arsv-timeline { position: relative; padding-left: 32px; }
    .arsv-timeline::before {
        content: ''; position: absolute; left: 12px; top: 8px; bottom: 8px;
        width: 2px; background: #e5e7eb;
    }
    .arsv-tl-grup { margin-bottom: 20px; position: relative; }
    .arsv-tl-grup-head {
        display: flex; align-items: center; gap: 10px; padding: 8px 12px;
        background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 8px;
        cursor: pointer;
    }
    .arsv-tl-grup-head:hover { background: #eff6ff; }
    .arsv-tl-grup-head::before {
        content: ''; position: absolute; left: -26px; top: 14px;
        width: 12px; height: 12px; border-radius: 50%; background: #2563eb;
        border: 2px solid #fff; box-shadow: 0 0 0 2px #2563eb;
    }
    .arsv-tl-grup-baslik { flex: 1; font-weight: 600; color: #111827; font-size: .88rem; }
    .arsv-tl-grup-sayac { font-size: .74rem; color: #6b7280; background: #e5e7eb; padding: 2px 8px; border-radius: 999px; }
    .arsv-tl-belge {
        display: flex; align-items: center; gap: 10px; padding: 8px 12px;
        border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 4px; background: #fff;
    }
    .arsv-tl-belge:hover { background: #f9fafb; }
    .arsv-tl-belge input { accent-color: #2563eb; cursor: pointer; }

    /* ---- Önizleme modal ---- */
    .arsv-onizleme {
        position: fixed; inset: 0; background: rgba(0,0,0,.75); z-index: 1000;
        display: none; align-items: center; justify-content: center; padding: 20px;
    }
    .arsv-onizleme.is-acik { display: flex; }
    .arsv-onizleme-icerik {
        background: #fff; border-radius: 12px; max-width: 90vw; max-height: 90vh;
        display: flex; flex-direction: column; overflow: hidden; width: 900px;
    }
    .arsv-onizleme-head {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 16px; border-bottom: 1px solid #e5e7eb;
    }
    .arsv-onizleme-baslik { flex: 1; font-weight: 700; color: #111827; }
    .arsv-onizleme-kapat {
        background: transparent; border: none; cursor: pointer; padding: 6px; border-radius: 6px;
    }
    .arsv-onizleme-kapat:hover { background: #f3f4f6; }
    .arsv-onizleme-govde { flex: 1; overflow: auto; background: #f3f4f6; min-height: 500px; display: flex; align-items: center; justify-content: center; }
    .arsv-onizleme-govde iframe { width: 100%; height: 70vh; border: none; background: #fff; }
    .arsv-onizleme-govde img { max-width: 100%; max-height: 70vh; }

    .arsv-bos {
        padding: 60px 20px; text-align: center; color: #6b7280;
        background: #fff; border: 2px dashed #e5e7eb; border-radius: 10px;
    }
    .arsv-bos svg { width: 48px; height: 48px; color: #d1d5db; margin-bottom: 12px; }
    .arsv-yukleniyor { padding: 40px; text-align: center; color: #6b7280; }
</style>
@endpush

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <h2>Belge Arşivi</h2>
        <small>Hisse satışı süreçlerinin tüm evraklarını arayın, filtreleyin, toplu indirin.</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.hisse-satisi.liste') }}" class="btn-cancel">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13"/></svg>
            Başvuru Listesi
        </a>
    </div>
</section>

<div class="arsv-stats">
    <div class="arsv-stat"><span class="arsv-stat-ikon" style="background:#2563eb;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h6l2 2h8a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg></span><div class="arsv-stat-meta"><div class="arsv-stat-deger">{{ $istatistik['toplam_belge'] }}</div><div class="arsv-stat-etiket">Toplam belge</div></div></div>
    <div class="arsv-stat"><span class="arsv-stat-ikon" style="background:#0ea5e9;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h16M4 8V6a2 2 0 012-2h12a2 2 0 012 2v2M4 8v10a2 2 0 002 2h12a2 2 0 002-2V8"/></svg></span><div class="arsv-stat-meta"><div class="arsv-stat-deger">{{ $istatistik['grup_sayisi'] }}</div><div class="arsv-stat-etiket">Başvuru grubu</div></div></div>
    <div class="arsv-stat"><span class="arsv-stat-ikon" style="background:#3b82f6;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg></span><div class="arsv-stat-meta"><div class="arsv-stat-deger">{{ $istatistik['basvuru'] }}</div><div class="arsv-stat-etiket">Başvuru evrağı</div></div></div>
    <div class="arsv-stat"><span class="arsv-stat-ikon" style="background:#f59e0b;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg></span><div class="arsv-stat-meta"><div class="arsv-stat-deger">{{ $istatistik['encumen'] }}</div><div class="arsv-stat-etiket">Encümen evrağı</div></div></div>
    <div class="arsv-stat"><span class="arsv-stat-ikon" style="background:#8b5cf6;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M4 4h16M4 12h16"/></svg></span><div class="arsv-stat-meta"><div class="arsv-stat-deger">{{ $istatistik['gorus'] + $istatistik['imar'] }}</div><div class="arsv-stat-etiket">Görüş + İmar</div></div></div>
    <div class="arsv-stat"><span class="arsv-stat-ikon" style="background:#10b981;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-5 7 5v14M9 9h1M14 9h1M9 13h1M14 13h1M10 21v-4h4v4"/></svg></span><div class="arsv-stat-meta"><div class="arsv-stat-deger">{{ $istatistik['tapu'] }}</div><div class="arsv-stat-etiket">Tapu tescil</div></div></div>
</div>

<div class="arsv-shell">
    {{-- ==== Sol sidebar (kategori ağacı) ==== --}}
    <aside class="arsv-sidebar">
        <div class="arsv-sidebar-baslik">Kategoriler</div>
        <div class="arsv-kategori is-aktif" data-kategori="">
            <span class="arsv-kategori-ikon" style="background:#6b7280;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h6l2 2h8a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg></span>
            <span class="arsv-kategori-ad">Tüm Belgeler</span>
            <span class="arsv-kategori-sayac" data-sayac="tumu">—</span>
        </div>
        <div class="arsv-kategori" data-kategori="basvuru">
            <span class="arsv-kategori-ikon" style="background:#2563eb;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg></span>
            <span class="arsv-kategori-ad">Başvuru Evrakları</span>
            <span class="arsv-kategori-sayac" data-sayac="basvuru">—</span>
        </div>
        <div class="arsv-kategori" data-kategori="encumen">
            <span class="arsv-kategori-ikon" style="background:#f59e0b;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg></span>
            <span class="arsv-kategori-ad">Encümen Kararları</span>
            <span class="arsv-kategori-sayac" data-sayac="encumen">—</span>
        </div>
        <div class="arsv-kategori" data-kategori="gorus">
            <span class="arsv-kategori-ikon" style="background:#8b5cf6;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></span>
            <span class="arsv-kategori-ad">Görüş Yazıları</span>
            <span class="arsv-kategori-sayac" data-sayac="gorus">—</span>
        </div>
        <div class="arsv-kategori" data-kategori="imar">
            <span class="arsv-kategori-ikon" style="background:#ec4899;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4v-4h4v4h4v-4h4v4h2M3 12v6h18v-6"/></svg></span>
            <span class="arsv-kategori-ad">İmar Evrakları</span>
            <span class="arsv-kategori-sayac" data-sayac="imar">—</span>
        </div>
        <div class="arsv-kategori" data-kategori="tapu">
            <span class="arsv-kategori-ikon" style="background:#10b981;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-5 7 5v14"/></svg></span>
            <span class="arsv-kategori-ad">Tapu Tescilleri</span>
            <span class="arsv-kategori-sayac" data-sayac="tapu">—</span>
        </div>
    </aside>

    {{-- ==== Ana içerik ==== --}}
    <main class="arsv-main">
        <div class="arsv-toolbar">
            <div class="arsv-ara">
                <svg class="arsv-ara-ikon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="search" id="arsv-q" placeholder="Dosya adı, ada/parsel, malik, açıklama ara..." autocomplete="off">
            </div>
            <button type="button" class="arsv-btn is-ghost" id="arsv-filtre-toggle" aria-expanded="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54z"/></svg>
                Gelişmiş Filtre
            </button>
            <div class="arsv-gorunum" role="tablist">
                <button type="button" data-gorunum="kart" class="is-aktif" title="Kart görünümü"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Kart</button>
                <button type="button" data-gorunum="liste" title="Liste görünümü"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>Liste</button>
                <button type="button" data-gorunum="timeline" title="Zaman çizelgesi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v8M8 12h8"/><circle cx="12" cy="12" r="9"/></svg>Timeline</button>
            </div>
        </div>

        {{-- Gelişmiş filtre --}}
        <div class="arsv-filtre" id="arsv-filtre" hidden>
            <form id="arsv-filtre-form" onsubmit="return arsvFiltreUygula(event)">
                <div class="arsv-filtre-grid">
                    <div class="arsv-alan"><label>İl</label>
                        <select name="il_id" id="arsv-f-il">
                            <option value="">— Tümü —</option>
                            @foreach ($iller as $il)<option value="{{ $il->id }}">{{ $il->ad }}</option>@endforeach
                        </select>
                    </div>
                    <div class="arsv-alan"><label>İlçe</label>
                        <select name="ilce_id" id="arsv-f-ilce" disabled><option>— Önce il —</option></select>
                    </div>
                    <div class="arsv-alan"><label>Mahalle</label>
                        <select name="mahalle_id" id="arsv-f-mahalle" disabled><option>— Önce ilçe —</option></select>
                    </div>
                    <div class="arsv-alan"><label>Dosya Türü</label>
                        <select name="dosya_turu">
                            <option value="">— Tümü —</option>
                            <option value="pdf">Sadece PDF</option>
                            <option value="image">Sadece Görsel</option>
                        </select>
                    </div>
                    <div class="arsv-alan"><label>Ada</label><input type="text" name="ada" autocomplete="off"></div>
                    <div class="arsv-alan"><label>Parsel</label><input type="text" name="parsel" autocomplete="off"></div>
                    <div class="arsv-alan"><label>Başlangıç Tarihi</label><input type="date" name="baslangic"></div>
                    <div class="arsv-alan"><label>Bitiş Tarihi</label><input type="date" name="bitis"></div>
                </div>
                <div style="display:flex;gap:8px;margin-top:12px;">
                    <button type="submit" class="arsv-btn">Uygula</button>
                    <button type="button" class="arsv-btn is-ghost" onclick="arsvFiltreTemizle()">Temizle</button>
                </div>
            </form>
        </div>

        {{-- Sonuç sayısı & toplu işlem --}}
        <div class="arsv-sonuc-bar">
            <div>
                <strong id="arsv-sayi">0</strong> belge · <span id="arsv-boyut">0 B</span>
            </div>
            <div class="arsv-toplu" id="arsv-toplu">
                <span><strong id="arsv-secili-sayi">0</strong> seçili</span>
                <button type="button" class="arsv-toplu-btn" onclick="arsvIndir('zip')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18M15 3v18"/></svg> ZIP indir
                </button>
                <button type="button" class="arsv-toplu-btn" onclick="arsvIndir('excel')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg> Excel
                </button>
                <button type="button" class="arsv-toplu-btn" onclick="arsvSecimTemizle()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg> Temizle
                </button>
            </div>
        </div>

        {{-- Belge listesi (dinamik render) --}}
        <div id="arsv-liste">
            <div class="arsv-yukleniyor">Belgeler yükleniyor...</div>
        </div>
    </main>
</div>

{{-- Önizleme modal --}}
<div class="arsv-onizleme" id="arsv-onizleme" onclick="if(event.target===this)arsvOnizlemeKapat()">
    <div class="arsv-onizleme-icerik">
        <div class="arsv-onizleme-head">
            <div class="arsv-onizleme-baslik" id="arsv-onizleme-baslik">Önizleme</div>
            <a class="arsv-btn is-ghost" id="arsv-onizleme-indir" href="#" download><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0 0l-4-4m4 4l4-4"/></svg>İndir</a>
            <button type="button" class="arsv-onizleme-kapat" onclick="arsvOnizlemeKapat()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
        </div>
        <div class="arsv-onizleme-govde" id="arsv-onizleme-govde"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';
    const API = {
        dosyalar: @json(route('panel.hisse-satisi.arsiv.dosyalar')),
        zip: @json(route('panel.hisse-satisi.arsiv.zip')),
        excel: @json(route('panel.hisse-satisi.arsiv.excel')),
        ilceler: @json(url('/panel/ajax/ilceler')),
        mahalleler: @json(url('/panel/ajax/mahalleler')),
    };

    let mevcut = { belgeler: [], toplam: 0, toplam_boyut_fmt: '0 B', kategori_sayilari: {} };
    let aktifKategori = '';
    let aktifGorunum = 'kart';
    let filtreEklenmis = new URLSearchParams();
    const secili = new Set();
    let debounce = null;

    // === Yükleme ===
    function yukle() {
        const params = new URLSearchParams(filtreEklenmis);
        const q = document.getElementById('arsv-q').value.trim();
        if (q) params.set('q', q);
        if (aktifKategori) params.set('kategoriler', aktifKategori);

        document.getElementById('arsv-liste').innerHTML = '<div class="arsv-yukleniyor">Yükleniyor...</div>';

        fetch(API.dosyalar + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(v => { mevcut = v; render(); })
            .catch(e => {
                document.getElementById('arsv-liste').innerHTML =
                    '<div class="arsv-bos">Yükleme hatası: ' + (e.message || 'bilinmiyor') + '</div>';
            });
    }

    function render() {
        document.getElementById('arsv-sayi').textContent = mevcut.toplam;
        document.getElementById('arsv-boyut').textContent = mevcut.toplam_boyut_fmt || '—';

        // Sidebar sayaç güncelle
        document.querySelector('[data-sayac="tumu"]').textContent = mevcut.toplam;
        ['basvuru', 'encumen', 'gorus', 'imar', 'tapu'].forEach(k => {
            document.querySelector(`[data-sayac="${k}"]`).textContent = (mevcut.kategori_sayilari?.[k] ?? 0);
        });

        if (mevcut.belgeler.length === 0) {
            document.getElementById('arsv-liste').innerHTML = `
                <div class="arsv-bos">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h6l2 2h8a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg>
                    <div style="font-weight:700;color:#374151;margin-bottom:4px;">Belge bulunamadı</div>
                    <div>Filtreyi genişletin veya farklı bir arama deneyin.</div>
                </div>`;
            return;
        }

        if (aktifGorunum === 'kart') renderKart();
        else if (aktifGorunum === 'liste') renderListe();
        else renderTimeline();

        seciliUiGuncelle();
    }

    // === Kart görünümü ===
    function renderKart() {
        const el = document.getElementById('arsv-liste');
        const kategoriRozet = { basvuru: 'Başvuru', encumen: 'Encümen', gorus: 'Görüş', imar: 'İmar', tapu: 'Tapu' };
        const kategoriRenk = { basvuru: '#2563eb', encumen: '#f59e0b', gorus: '#8b5cf6', imar: '#ec4899', tapu: '#10b981' };

        el.innerHTML = '<div class="arsv-kartlar">' + mevcut.belgeler.map(b => {
            const isImg = b.tur === 'image';
            const thumb = isImg
                ? `<img src="${escapeAttr(b.url)}" alt="" loading="lazy">`
                : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h4"/></svg>`;
            return `
            <div class="arsv-kart ${secili.has(b.id) ? 'is-secili' : ''}" data-id="${escapeAttr(b.id)}">
                <label class="arsv-kart-secim"><input type="checkbox" data-secim="${escapeAttr(b.id)}" ${secili.has(b.id) ? 'checked' : ''}></label>
                <div class="arsv-kart-rozet" style="background:${kategoriRenk[b.kategori]};">${kategoriRozet[b.kategori]}</div>
                <div class="arsv-kart-thumb" onclick="arsvOnizle('${escapeAttr(b.id)}')">${thumb}</div>
                <div class="arsv-kart-govde">
                    <div class="arsv-kart-baslik" title="${escapeAttr(b.baslik ?? '')}">${escapeHtml(b.baslik ?? b.dosya_adi)}</div>
                    <div class="arsv-kart-alt">
                        <span>📍 ${escapeHtml(b.ada ?? '—')}/${escapeHtml(b.parsel ?? '—')}</span>
                        <span>📅 ${b.tarih ? tr(b.tarih) : '—'}</span>
                    </div>
                    <div class="arsv-kart-alt">
                        <span title="Başvuru sahibi">👤 ${escapeHtml((b.basvuru_sahibi ?? '').substring(0, 30) || '—')}</span>
                        <span>💾 ${escapeHtml(b.boyut_fmt ?? '—')}</span>
                    </div>
                </div>
                <div class="arsv-kart-aksiyonlar">
                    <button type="button" class="arsv-btn is-ghost" onclick="arsvOnizle('${escapeAttr(b.id)}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>Önizle</button>
                    <a class="arsv-btn" href="${escapeAttr(b.url)}" download><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0 0l-4-4m4 4l4-4"/></svg>İndir</a>
                </div>
            </div>`;
        }).join('') + '</div>';

        bindSecim();
    }

    // === Liste görünümü ===
    function renderListe() {
        const el = document.getElementById('arsv-liste');
        const kategoriRozet = { basvuru: 'Başvuru', encumen: 'Encümen', gorus: 'Görüş', imar: 'İmar', tapu: 'Tapu' };

        el.innerHTML = `
            <div class="arsv-tablo"><table>
                <thead><tr>
                    <th style="width:32px;"><input type="checkbox" id="arsv-hepsi"></th>
                    <th>Kategori</th><th>Başlık / Dosya</th><th>Ada/Parsel</th>
                    <th>Başvuru Sahibi</th><th>Tarih</th><th>Boyut</th><th style="width:110px;">İşlem</th>
                </tr></thead>
                <tbody>
                ${mevcut.belgeler.map(b => `
                    <tr class="${secili.has(b.id) ? 'is-secili' : ''}" data-id="${escapeAttr(b.id)}">
                        <td><input type="checkbox" data-secim="${escapeAttr(b.id)}" ${secili.has(b.id) ? 'checked' : ''}></td>
                        <td><span class="arsv-rozet arsv-r-${b.kategori}">${kategoriRozet[b.kategori]}</span></td>
                        <td><div style="font-weight:600;color:#111827;">${escapeHtml(b.baslik ?? b.dosya_adi)}</div><div style="font-size:.72rem;color:#6b7280;">${escapeHtml(b.dosya_adi)}</div></td>
                        <td>${escapeHtml(b.ada ?? '—')}/${escapeHtml(b.parsel ?? '—')}<div style="font-size:.72rem;color:#6b7280;">${escapeHtml(b.ilce ?? '')} · ${escapeHtml(b.mahalle ?? '')}</div></td>
                        <td>${escapeHtml((b.basvuru_sahibi ?? '—').substring(0, 40))}</td>
                        <td>${b.tarih ? tr(b.tarih) : '—'}</td>
                        <td>${escapeHtml(b.boyut_fmt ?? '—')}</td>
                        <td>
                            <button type="button" class="arsv-btn is-ghost" onclick="arsvOnizle('${escapeAttr(b.id)}')">Önizle</button>
                            <a class="arsv-btn" href="${escapeAttr(b.url)}" download>↓</a>
                        </td>
                    </tr>`).join('')}
                </tbody>
            </table></div>`;

        document.getElementById('arsv-hepsi').addEventListener('change', e => {
            document.querySelectorAll('[data-secim]').forEach(cb => {
                cb.checked = e.target.checked;
                const id = cb.dataset.secim;
                if (e.target.checked) secili.add(id); else secili.delete(id);
                cb.closest('tr').classList.toggle('is-secili', e.target.checked);
            });
            seciliUiGuncelle();
        });
        bindSecim();
    }

    // === Timeline görünümü ===
    function renderTimeline() {
        const el = document.getElementById('arsv-liste');
        // Grup no'ya göre grupla
        const gruplar = {};
        mevcut.belgeler.forEach(b => {
            const k = b.grup_no || '_';
            (gruplar[k] ||= { ada: b.ada, parsel: b.parsel, belgeler: [] }).belgeler.push(b);
        });

        const kategoriRozet = { basvuru: 'Başvuru', encumen: 'Encümen', gorus: 'Görüş', imar: 'İmar', tapu: 'Tapu' };
        el.innerHTML = '<div class="arsv-timeline">' + Object.entries(gruplar).map(([grup, g]) => `
            <div class="arsv-tl-grup">
                <div class="arsv-tl-grup-head">
                    <div class="arsv-tl-grup-baslik">Grup #${grup} · Ada ${escapeHtml(g.ada ?? '—')} / Parsel ${escapeHtml(g.parsel ?? '—')}</div>
                    <div class="arsv-tl-grup-sayac">${g.belgeler.length} belge</div>
                </div>
                ${g.belgeler.map(b => `
                    <div class="arsv-tl-belge">
                        <input type="checkbox" data-secim="${escapeAttr(b.id)}" ${secili.has(b.id) ? 'checked' : ''}>
                        <span class="arsv-rozet arsv-r-${b.kategori}">${kategoriRozet[b.kategori]}</span>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;color:#111827;font-size:.84rem;">${escapeHtml(b.baslik ?? b.dosya_adi)}</div>
                            <div style="font-size:.72rem;color:#6b7280;">${b.tarih ? tr(b.tarih) : '—'} · ${escapeHtml(b.boyut_fmt ?? '')}</div>
                        </div>
                        <button type="button" class="arsv-btn is-ghost" onclick="arsvOnizle('${escapeAttr(b.id)}')">Önizle</button>
                        <a class="arsv-btn" href="${escapeAttr(b.url)}" download>İndir</a>
                    </div>`).join('')}
            </div>`).join('') + '</div>';

        bindSecim();
    }

    function bindSecim() {
        document.querySelectorAll('[data-secim]').forEach(cb => {
            cb.addEventListener('change', e => {
                const id = e.target.dataset.secim;
                if (e.target.checked) secili.add(id); else secili.delete(id);
                const kart = e.target.closest('.arsv-kart, tr');
                if (kart) kart.classList.toggle('is-secili', e.target.checked);
                seciliUiGuncelle();
            });
        });
    }

    function seciliUiGuncelle() {
        const bar = document.getElementById('arsv-toplu');
        bar.classList.toggle('is-aktif', secili.size > 0);
        document.getElementById('arsv-secili-sayi').textContent = secili.size;
    }

    window.arsvSecimTemizle = function () {
        secili.clear();
        document.querySelectorAll('[data-secim]').forEach(cb => cb.checked = false);
        document.querySelectorAll('.is-secili').forEach(el => el.classList.remove('is-secili'));
        seciliUiGuncelle();
    };

    window.arsvIndir = function (tip) {
        const url = tip === 'zip' ? API.zip : API.excel;
        const params = new URLSearchParams();
        if (secili.size > 0) params.set('ids', Array.from(secili).join(','));
        else {
            // Seçim yoksa mevcut filtreyi kullan
            for (const [k, v] of filtreEklenmis) params.set(k, v);
            const q = document.getElementById('arsv-q').value.trim();
            if (q) params.set('q', q);
            if (aktifKategori) params.set('kategoriler', aktifKategori);
        }
        window.location.href = url + (params.toString() ? '?' + params.toString() : '');
    };

    window.arsvOnizle = function (id) {
        const b = mevcut.belgeler.find(x => x.id === id);
        if (!b) return;
        document.getElementById('arsv-onizleme-baslik').textContent = b.baslik ?? b.dosya_adi;
        document.getElementById('arsv-onizleme-indir').href = b.url;
        const govde = document.getElementById('arsv-onizleme-govde');
        if (b.tur === 'pdf') govde.innerHTML = `<iframe src="${escapeAttr(b.url)}"></iframe>`;
        else if (b.tur === 'image') govde.innerHTML = `<img src="${escapeAttr(b.url)}" alt="">`;
        else govde.innerHTML = `<div style="padding:40px;text-align:center;color:#6b7280;">Bu dosya türü tarayıcıda önizlenemiyor. İndirin.</div>`;
        document.getElementById('arsv-onizleme').classList.add('is-acik');
    };
    window.arsvOnizlemeKapat = function () {
        document.getElementById('arsv-onizleme').classList.remove('is-acik');
        document.getElementById('arsv-onizleme-govde').innerHTML = '';
    };
    document.addEventListener('keydown', e => { if (e.key === 'Escape') arsvOnizlemeKapat(); });

    // === Kategori tıklama ===
    document.querySelectorAll('.arsv-kategori').forEach(el => {
        el.addEventListener('click', () => {
            document.querySelectorAll('.arsv-kategori').forEach(x => x.classList.remove('is-aktif'));
            el.classList.add('is-aktif');
            aktifKategori = el.dataset.kategori;
            yukle();
        });
    });

    // === Görünüm modu ===
    document.querySelectorAll('.arsv-gorunum button').forEach(b => {
        b.addEventListener('click', () => {
            document.querySelectorAll('.arsv-gorunum button').forEach(x => x.classList.remove('is-aktif'));
            b.classList.add('is-aktif');
            aktifGorunum = b.dataset.gorunum;
            render();
        });
    });

    // === Arama debounce ===
    document.getElementById('arsv-q').addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(yukle, 350);
    });

    // === Filtre toggle ===
    document.getElementById('arsv-filtre-toggle').addEventListener('click', () => {
        const f = document.getElementById('arsv-filtre');
        f.hidden = !f.hidden;
        document.getElementById('arsv-filtre-toggle').setAttribute('aria-expanded', f.hidden ? 'false' : 'true');
    });

    // İlçe/Mahalle chain
    document.getElementById('arsv-f-il').addEventListener('change', async e => {
        const ilce = document.getElementById('arsv-f-ilce');
        const mah = document.getElementById('arsv-f-mahalle');
        ilce.innerHTML = '<option value="">— Tümü —</option>';
        mah.innerHTML = '<option value="">— Önce ilçe —</option>';
        mah.disabled = true;
        if (!e.target.value) { ilce.disabled = true; return; }
        ilce.disabled = false;
        const r = await fetch(API.ilceler + '/' + e.target.value);
        const v = await r.json();
        (v || []).forEach(x => ilce.appendChild(new Option(x.ad, x.id)));
    });
    document.getElementById('arsv-f-ilce').addEventListener('change', async e => {
        const mah = document.getElementById('arsv-f-mahalle');
        mah.innerHTML = '<option value="">— Tümü —</option>';
        if (!e.target.value) { mah.disabled = true; return; }
        mah.disabled = false;
        const r = await fetch(API.mahalleler + '/' + e.target.value);
        const v = await r.json();
        (v || []).forEach(x => mah.appendChild(new Option(x.ad, x.id)));
    });

    window.arsvFiltreUygula = function (e) {
        e.preventDefault();
        filtreEklenmis = new URLSearchParams();
        for (const [k, v] of new FormData(e.target)) if (v) filtreEklenmis.set(k, v);
        yukle();
        return false;
    };
    window.arsvFiltreTemizle = function () {
        document.getElementById('arsv-filtre-form').reset();
        document.getElementById('arsv-f-ilce').innerHTML = '<option>— Önce il —</option>';
        document.getElementById('arsv-f-ilce').disabled = true;
        document.getElementById('arsv-f-mahalle').innerHTML = '<option>— Önce ilçe —</option>';
        document.getElementById('arsv-f-mahalle').disabled = true;
        filtreEklenmis = new URLSearchParams();
        yukle();
    };

    function tr(iso) { try { return new Date(iso).toLocaleDateString('tr-TR'); } catch(e) { return iso; } }
    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }
    function escapeAttr(s) { return escapeHtml(s).replace(/`/g,'&#96;'); }

    // İlk yükleme
    yukle();
})();
</script>
@endpush
