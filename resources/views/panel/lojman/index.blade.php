@extends('layouts.panel')

@section('title', 'Lojmanlar')

@push('head')
<style>
    .loj-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 16px; }
    @media (max-width: 800px) { .loj-stats { grid-template-columns: repeat(2, 1fr); } }
    .loj-stat { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 14px; display: flex; gap: 10px; align-items: center; }
    .loj-stat-ikon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; flex: 0 0 34px; }
    .loj-stat-ikon svg { width: 16px; height: 16px; }
    .loj-stat-deger { font-size: 1.15rem; font-weight: 700; color: #0e1726; line-height: 1; }
    .loj-stat-etiket { font-size: .74rem; color: #6b7280; margin-top: 3px; }

    .loj-toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; margin-bottom: 12px; }
    .loj-ara { flex: 1; min-width: 240px; position: relative; }
    .loj-ara input { width: 100%; padding: 9px 12px 9px 34px; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: .86rem; }
    .loj-ara input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
    .loj-ara-ikon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; }
    .loj-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 14px; font-size: .82rem; font-weight: 600; background: #2563eb; color: #fff; border: none; border-radius: 7px; cursor: pointer; text-decoration: none; }
    .loj-btn svg { width: 14px; height: 14px; }
    .loj-btn:hover { background: #1d4ed8; }
    .loj-btn.is-ghost { background: #fff; color: #374151; border: 1px solid #d1d5db; }

    .loj-filtre { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; margin-bottom: 12px; }
    .loj-filtre[hidden] { display: none; }
    .loj-filtre-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
    @media (max-width: 900px) { .loj-filtre-grid { grid-template-columns: 1fr 1fr; } }
    .loj-alan label { display: block; font-size: .7rem; text-transform: uppercase; color: #6b7280; letter-spacing: .04em; font-weight: 700; margin-bottom: 4px; }
    .loj-alan input, .loj-alan select { width: 100%; padding: 8px 12px; font-size: .84rem; border: 1.5px solid #d1d5db; border-radius: 8px; background: #fff; }

    .loj-durum { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: .74rem; font-weight: 600; }
    .loj-durum-bos { background: #d1fae5; color: #065f46; }
    .loj-durum-dolu { background: #fee2e2; color: #991b1b; }
    .loj-durum-bakimda { background: #fef3c7; color: #92400e; }
    .loj-durum-kullanim-disi { background: #e5e7eb; color: #4b5563; }
</style>
@endpush

@section('content')
@if (session('basari'))<div class="flash-success">{{ session('basari') }}</div>@endif

<section class="page-hero">
    <div class="page-hero-text">
        <h2>Lojmanlar</h2>
        <small>Toplam {{ $lojmanlar->total() }} kayıt</small>
    </div>
    <div class="page-hero-actions" style="display:flex;gap:8px;">
        <a href="{{ route('panel.lojman.excel', request()->query()) }}" class="btn-cancel">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
            Excel İndir
        </a>
        @if (auth()->user()->izinVarMi('lojman.olustur'))
            <button type="button" class="btn-cancel" onclick="document.getElementById('loj-excel-yukle').hidden = !document.getElementById('loj-excel-yukle').hidden">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                Toplu Yükle
            </button>
            <a href="{{ route('panel.lojman.olustur') }}" class="btn-submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Yeni Lojman
            </a>
        @endif
    </div>
</section>

@if (auth()->user()->izinVarMi('lojman.olustur'))
<div id="loj-excel-yukle" hidden style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px;margin-bottom:12px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <strong style="color:#1e40af;">Excel ile Toplu Lojman Yükleme</strong>
        <a href="{{ route('panel.lojman.excel-sablon') }}" style="color:#1e40af;font-size:.82rem;font-weight:600;">📄 Örnek şablonu indir</a>
    </div>
    <form method="POST" action="{{ route('panel.lojman.excel-yukle') }}" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        @csrf
        <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required style="flex:1;min-width:250px;padding:8px;border:1.5px solid #93c5fd;border-radius:6px;background:#fff;">
        <button type="submit" class="btn-submit">Yükle</button>
    </form>
    <small style="color:#3b82f6;font-size:.74rem;display:block;margin-top:8px;">
        Sütunlar: <strong>Ad, İl, İlçe, Mahalle, Ada, Parsel, Blok, Daire No, Kat, Oda, Alan m², Tip, Durum, Açıklama</strong> — İl/İlçe/Mahalle isimlerinin veritabanındakiyle eşleşmesi gerekir. Taşınmaz otomatik bağlanır.
    </small>
</div>
@endif

<div class="loj-stats">
    <div class="loj-stat"><span class="loj-stat-ikon" style="background:#2563eb;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V6a2 2 0 012-2h10a2 2 0 012 2v15"/></svg></span><div><div class="loj-stat-deger">{{ $sayilar['toplam'] }}</div><div class="loj-stat-etiket">Toplam Lojman</div></div></div>
    <div class="loj-stat"><span class="loj-stat-ikon" style="background:#10b981;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg></span><div><div class="loj-stat-deger">{{ $sayilar['bos'] }}</div><div class="loj-stat-etiket">Boş</div></div></div>
    <div class="loj-stat"><span class="loj-stat-ikon" style="background:#dc2626;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span><div><div class="loj-stat-deger">{{ $sayilar['dolu'] }}</div><div class="loj-stat-etiket">Dolu</div></div></div>
    <div class="loj-stat"><span class="loj-stat-ikon" style="background:#f59e0b;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg></span><div><div class="loj-stat-deger">{{ $sayilar['bakim'] }}</div><div class="loj-stat-etiket">Bakımda</div></div></div>
</div>

<form method="GET" action="{{ route('panel.lojman.index') }}">
    <div class="loj-toolbar">
        <div class="loj-ara">
            <svg class="loj-ara-ikon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="search" name="q" value="{{ $filtre['q'] ?? '' }}" placeholder="Lojman adı, blok veya daire no ile ara...">
        </div>
        <button type="submit" class="loj-btn">Ara</button>
        <button type="button" class="loj-btn is-ghost" onclick="document.getElementById('loj-filtre').hidden=!document.getElementById('loj-filtre').hidden">Filtrele</button>
    </div>
    <div class="loj-filtre" id="loj-filtre" {{ ($filtre['ilId'] ?? '') || ($filtre['durum'] ?? '') || ($filtre['tip'] ?? '') ? '' : 'hidden' }}>
        <div class="loj-filtre-grid">
            <div class="loj-alan"><label>İl</label>
                <select name="il_id">
                    <option value="">— Tümü —</option>
                    @foreach ($iller as $il)<option value="{{ $il->id }}" @selected(($filtre['ilId'] ?? '') == $il->id)>{{ $il->ad }}</option>@endforeach
                </select>
            </div>
            <div class="loj-alan"><label>İlçe</label>
                <select name="ilce_id" @disabled(! ($filtre['ilId'] ?? null))>
                    <option value="">— Tümü —</option>
                    @foreach ($ilceler as $x)<option value="{{ $x->id }}" @selected(($filtre['ilceId'] ?? '') == $x->id)>{{ $x->ad }}</option>@endforeach
                </select>
            </div>
            <div class="loj-alan"><label>Durum</label>
                <select name="durum">
                    <option value="">— Tümü —</option>
                    <option value="bos" @selected(($filtre['durum'] ?? '') === 'bos')>Boş</option>
                    <option value="dolu" @selected(($filtre['durum'] ?? '') === 'dolu')>Dolu</option>
                    <option value="bakimda" @selected(($filtre['durum'] ?? '') === 'bakimda')>Bakımda</option>
                    <option value="kullanim-disi" @selected(($filtre['durum'] ?? '') === 'kullanim-disi')>Kullanım Dışı</option>
                </select>
            </div>
            <div class="loj-alan"><label>Tip</label><input type="text" name="tip" value="{{ $filtre['tip'] ?? '' }}" placeholder="Memur, İşçi..."></div>
        </div>
        <div style="display:flex;gap:8px;margin-top:12px;">
            <button type="submit" class="loj-btn">Uygula</button>
            <a href="{{ route('panel.lojman.index') }}" class="loj-btn is-ghost">Temizle</a>
        </div>
    </div>
</form>

@if ($lojmanlar->isEmpty())
    <div class="td-bos td-bos-block">Kayıt bulunamadı.</div>
@else
    <div class="tl-tablo-wrap">
        <table class="tl-tablo">
            <thead>
                <tr>
                    <th>#</th><th>Ad</th><th>Blok / Daire</th><th>Tip</th><th>Oda / Alan</th><th>Konum</th><th>Başvuru</th><th>Durum</th><th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                @php $durumEtiket = ['bos'=>'Boş','dolu'=>'Dolu','bakimda'=>'Bakımda','kullanim-disi'=>'Kullanım Dışı']; @endphp
                @foreach ($lojmanlar as $l)
                    <tr>
                        <td class="td-mono">#{{ $l->id }}</td>
                        <td><strong>{{ $l->ad }}</strong></td>
                        <td class="td-mono">{{ $l->blok ?? '—' }} / {{ $l->daire_no ?? '—' }}<div style="font-size:.7rem;color:#6b7280;">Kat: {{ $l->kat ?? '—' }}</div></td>
                        <td>{{ $l->tip ?? '—' }}</td>
                        <td class="td-mono">{{ $l->oda_sayisi ? $l->oda_sayisi.'+1' : '—' }}<div style="font-size:.7rem;color:#6b7280;">{{ $l->alan_m2 ? number_format((float) $l->alan_m2, 0, ',', '.').' m²' : '' }}</div></td>
                        <td>{{ optional(optional($l->tasinmaz)->ilce)->ad ?? '—' }}<div style="font-size:.7rem;color:#6b7280;">{{ optional(optional($l->tasinmaz)->mahalle)->ad ?? '' }}</div></td>
                        <td class="td-mono">{{ $l->basvurular_count }} / {{ $l->tahsisler_count }}</td>
                        <td><span class="loj-durum loj-durum-{{ $l->durum }}">{{ $durumEtiket[$l->durum] ?? $l->durum }}</span></td>
                        <td><a href="{{ route('panel.lojman.detay', $l->id) }}" class="btn-cancel" style="padding:5px 12px;font-size:.78rem;">Detay</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="tl-pagination">{{ $lojmanlar->links() }}</div>
@endif
@endsection
