@extends('layouts.panel')

@section('title', 'İşgal Kaydı — '.$kayit->ad_soyad_unvan)

@push('head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/themes/airbnb.css">
<style>
    .ec-detay-grid { display: grid; grid-template-columns: 320px 1fr; gap: 16px; align-items: start; }
    @media (max-width: 900px) { .ec-detay-grid { grid-template-columns: 1fr; } }

    .ec-kart { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; margin-bottom: 12px; }
    .ec-kart h3 { margin: 0 0 12px 0; font-size: .96rem; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 8px; padding-bottom: 10px; border-bottom: 1px solid #f3f4f6; }
    .ec-kart h3 svg { color: #2563eb; width: 18px; height: 18px; }
    .ec-alan-lbl { font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; font-weight: 700; margin-bottom: 3px; }
    .ec-alan-val { font-size: .88rem; color: #111827; margin-bottom: 12px; word-break: break-word; }
    .ec-alan-val:last-child { margin-bottom: 0; }

    .ec-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; font-size: .78rem; font-weight: 600; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; }
    .ec-btn svg { width: 13px; height: 13px; }
    .ec-btn:hover { background: #1d4ed8; }
    .ec-btn.is-ghost { background: #fff; color: #374151; border: 1px solid #d1d5db; }
    .ec-btn.is-yesil { background: #059669; }
    .ec-btn.is-kirmizi { background: #dc2626; }
    .ec-btn.is-mini { padding: 4px 8px; font-size: .72rem; }

    /* Tutanaklar */
    .ec-tut-item { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-bottom: 10px; background: #f9fafb; }
    .ec-tut-ust { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 10px; }
    .ec-tut-seri { font-weight: 700; color: #111827; }
    .ec-tut-tarih { font-size: .74rem; color: #6b7280; }
    .ec-tut-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    @media (max-width: 700px) { .ec-tut-grid { grid-template-columns: 1fr; } }
    .ec-tut-alan { font-size: .78rem; }
    .ec-tut-alan .lbl { color: #6b7280; font-size: .68rem; text-transform: uppercase; font-weight: 700; }
    .ec-tut-alan .val { color: #111827; margin-top: 2px; }
    .ec-gun-rozet { background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 999px; font-size: .7rem; font-weight: 700; }
    .ec-gun-rozet.is-devam { background: #dcfce7; color: #166534; }

    .ec-form-satir { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
    @media (max-width: 900px) { .ec-form-satir { grid-template-columns: repeat(2, 1fr); } }
    .ec-form-alan label { display: block; font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; font-weight: 700; margin-bottom: 4px; }
    .ec-form-alan input, .ec-form-alan textarea { width: 100%; padding: 7px 10px; font-size: .84rem; border: 1px solid #d1d5db; border-radius: 6px; }
    .ec-form-alan textarea { min-height: 60px; resize: vertical; }

    /* Resimler galeri */
    .ec-galeri { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; }
    .ec-resim { position: relative; border-radius: 8px; overflow: hidden; background: #f3f4f6; aspect-ratio: 4/3; }
    .ec-resim img { width: 100%; height: 100%; object-fit: cover; cursor: pointer; }
    .ec-resim-sil { position: absolute; top: 6px; right: 6px; background: rgba(220,38,38,.9); color: #fff; border: none; width: 26px; height: 26px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .ec-resim-sil:hover { background: #b91c1c; }
    .ec-resim-alt { position: absolute; bottom: 0; left: 0; right: 0; padding: 4px 8px; font-size: .68rem; color: #fff; background: rgba(0,0,0,.7); }

    .ec-lightbox { position: fixed; inset: 0; background: rgba(0,0,0,.85); z-index: 1000; display: none; align-items: center; justify-content: center; }
    .ec-lightbox.is-acik { display: flex; }
    .ec-lightbox img { max-width: 92vw; max-height: 92vh; }
    .ec-lightbox-kapat { position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,.15); border: none; color: #fff; padding: 8px 14px; border-radius: 6px; cursor: pointer; }

    /* ---------- ELEGANT DATE INPUT ---------- */
    .ec-form-alan input[type="date"] {
        appearance: none; -webkit-appearance: none;
        padding: 10px 14px; font-size: .86rem; font-weight: 500;
        color: #111827; background: #fff;
        border: 1.5px solid #d1d5db; border-radius: 8px;
        transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
        cursor: pointer; width: 100%;
        font-family: inherit;
    }
    .ec-form-alan input[type="date"]:hover { border-color: #93c5fd; background: #f8fafc; }
    .ec-form-alan input[type="date"]:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); background: #fff; }
    .ec-form-alan input[type="date"]::-webkit-calendar-picker-indicator {
        cursor: pointer; opacity: .5; transition: opacity .15s ease;
        filter: invert(30%) sepia(90%) saturate(1500%) hue-rotate(210deg);
    }
    .ec-form-alan input[type="date"]:hover::-webkit-calendar-picker-indicator { opacity: 1; }

    /* Number input estetik */
    .ec-form-alan input[type="number"] {
        padding: 10px 14px; font-size: .88rem;
        border: 1.5px solid #d1d5db; border-radius: 8px;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .ec-form-alan input[type="number"]:hover { border-color: #93c5fd; }
    .ec-form-alan input[type="number"]:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }

    /* Text input & textarea estetik */
    .ec-form-alan input[type="text"], .ec-form-alan textarea {
        padding: 10px 14px; font-size: .88rem;
        border: 1.5px solid #d1d5db; border-radius: 8px;
        transition: border-color .15s ease, box-shadow .15s ease;
        font-family: inherit;
    }
    .ec-form-alan input[type="text"]:hover, .ec-form-alan textarea:hover { border-color: #93c5fd; }
    .ec-form-alan input[type="text"]:focus, .ec-form-alan textarea:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }

    /* ---------- ELEGANT FILE UPLOAD (drag & drop görünümü) ---------- */
    .ec-file-drop {
        position: relative;
        display: flex; align-items: center; gap: 14px;
        padding: 14px 16px;
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        border: 2px dashed #d1d5db;
        border-radius: 10px;
        cursor: pointer;
        transition: all .18s ease;
    }
    .ec-file-drop:hover {
        border-color: #2563eb; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37,99,235,.1);
    }
    .ec-file-drop.is-dragover {
        border-color: #059669; background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        border-style: solid;
    }
    .ec-file-drop.has-file {
        border-style: solid; border-color: #10b981;
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    }
    .ec-file-drop input[type="file"] {
        position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
    }
    .ec-file-ikon {
        width: 42px; height: 42px; flex: 0 0 42px;
        display: flex; align-items: center; justify-content: center;
        background: #fff; border-radius: 10px;
        color: #6b7280; box-shadow: 0 2px 4px rgba(0,0,0,.06);
        transition: all .18s ease;
    }
    .ec-file-ikon svg { width: 22px; height: 22px; }
    .ec-file-drop:hover .ec-file-ikon { color: #2563eb; transform: scale(1.05); }
    .ec-file-drop.has-file .ec-file-ikon { color: #10b981; }
    .ec-file-meta { flex: 1; min-width: 0; }
    .ec-file-baslik { font-size: .86rem; font-weight: 700; color: #111827; margin-bottom: 2px; }
    .ec-file-alt { font-size: .74rem; color: #6b7280; }
    .ec-file-drop.has-file .ec-file-baslik { color: #065f46; }
    .ec-file-drop.has-file .ec-file-alt { color: #059669; word-break: break-all; }
    .ec-file-sil {
        background: rgba(220,38,38,.1); color: #dc2626; border: none;
        padding: 4px 10px; border-radius: 6px; font-size: .72rem; font-weight: 600;
        cursor: pointer; opacity: 0; transition: opacity .15s ease;
    }
    .ec-file-drop.has-file .ec-file-sil { opacity: 1; }
    .ec-file-sil:hover { background: rgba(220,38,38,.2); }

    /* ---------- Flatpickr özel stil ---------- */
    .flatpickr-calendar {
        box-shadow: 0 20px 50px rgba(0,0,0,.18) !important;
        border-radius: 12px !important;
        border: 1px solid #e5e7eb !important;
        font-family: inherit !important;
    }
    .flatpickr-calendar.arrowTop::before,
    .flatpickr-calendar.arrowTop::after { border-bottom-color: #2563eb !important; }
    .flatpickr-months {
        background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%) !important;
        border-radius: 12px 12px 0 0 !important;
        padding: 8px 0 !important;
    }
    .flatpickr-months .flatpickr-month,
    .flatpickr-current-month,
    .flatpickr-current-month input.cur-year,
    .flatpickr-monthDropdown-months { color: #fff !important; }
    .flatpickr-monthDropdown-months option { color: #111827 !important; }
    .flatpickr-prev-month, .flatpickr-next-month { fill: #fff !important; color: #fff !important; }
    .flatpickr-prev-month:hover svg, .flatpickr-next-month:hover svg { fill: #fbbf24 !important; }
    span.flatpickr-weekday {
        color: #6b7280 !important; font-weight: 700 !important; text-transform: uppercase; font-size: .7rem !important;
    }
    .flatpickr-day {
        border-radius: 8px !important; font-weight: 500;
        transition: background .12s ease, color .12s ease;
    }
    .flatpickr-day:hover { background: #dbeafe !important; color: #1e40af !important; border-color: transparent !important; }
    .flatpickr-day.today { border-color: #2563eb !important; font-weight: 700; }
    .flatpickr-day.selected,
    .flatpickr-day.selected:hover {
        background: #2563eb !important; color: #fff !important; border-color: #2563eb !important;
        box-shadow: 0 3px 8px rgba(37,99,235,.35);
    }
    .flatpickr-day.prevMonthDay, .flatpickr-day.nextMonthDay { color: #d1d5db !important; }
    .flatpickr-time { border-top: 1px solid #e5e7eb !important; }
    .flatpickr-input.flatpickr-input { cursor: pointer; }
</style>
@endpush

@section('content')
@if (session('basari'))
    <div class="flash-success">{{ session('basari') }}</div>
@endif
@if ($errors->any())
    <div class="flash-error"><ul style="margin:0;padding-left:20px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<section class="page-hero">
    <div class="page-hero-text">
        <h2>{{ $kayit->ad_soyad_unvan }}</h2>
        <small>Ecrimisil işgal kaydı #{{ $kayit->id }}</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.ecrimisil.index') }}" class="btn-cancel">Listeye Dön</a>
        @if (auth()->user()->izinVarMi('ecrimisil.duzenle'))
            <a href="{{ route('panel.ecrimisil.duzenle', $kayit->id) }}" class="btn-cancel">Düzenle</a>
        @endif
        @if (auth()->user()->izinVarMi('ecrimisil.sil'))
            <form method="POST" action="{{ route('panel.ecrimisil.sil', $kayit->id) }}" style="display:inline;" onsubmit="return confirm('İşgal kaydını, tutanakları ve resimleri silmek istediğinize emin misiniz?');">
                @csrf @method('DELETE')
                <button type="submit" class="ec-btn is-kirmizi">Sil</button>
            </form>
        @endif
    </div>
</section>

<div class="ec-detay-grid">
    {{-- SOL: Genel bilgi --}}
    <div>
        <div class="ec-kart">
            <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Şirket / Kişi</h3>
            <div class="ec-alan-lbl">Ad Soyad / Ünvan</div>
            <div class="ec-alan-val"><strong>{{ $kayit->ad_soyad_unvan }}</strong></div>
            <div class="ec-alan-lbl">TC / Vergi No</div>
            <div class="ec-alan-val td-mono">{{ $kayit->tc_vergi_no ?? '—' }}</div>
            @if ($kayit->nitelik)
                <div class="ec-alan-lbl">İşgal Niteliği</div>
                <div class="ec-alan-val"><span style="display:inline-block;background:#fef3c7;color:#92400e;padding:2px 10px;border-radius:999px;font-size:.78rem;font-weight:600;">{{ $kayit->nitelik }}</span></div>
            @endif
            @if ($kayit->cadde_sokak)
                <div class="ec-alan-lbl">Cadde / Sokak</div>
                <div class="ec-alan-val">{{ $kayit->cadde_sokak }}</div>
            @endif
            <div class="ec-alan-lbl">Adres</div>
            <div class="ec-alan-val">{{ $kayit->adres ?? '—' }}</div>
        </div>

        <div class="ec-kart">
            <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-5 7 5v14"/></svg> Taşınmaz</h3>
            @if ($kayit->tasinmaz)
                <div class="ec-alan-lbl">Ada / Parsel</div>
                <div class="ec-alan-val td-mono"><span class="tl-badge">{{ $kayit->tasinmaz->ada }}</span> / <span class="tl-badge">{{ $kayit->tasinmaz->parsel }}</span></div>
                <div class="ec-alan-lbl">Konum</div>
                <div class="ec-alan-val">{{ $kayit->tasinmaz->il?->ad }} / {{ $kayit->tasinmaz->ilce?->ad }}<br><small style="color:#6b7280;">{{ $kayit->tasinmaz->mahalle?->ad }}</small></div>
            @else
                <div class="ec-alan-val" style="color:#6b7280;">Taşınmaz bağlanmamış</div>
            @endif
        </div>

        <div class="ec-kart">
            <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"/><path d="M4 21c0-4 4-7 8-7s8 3 8 7"/></svg> Sorumlu</h3>
            @if ($kayit->kullanici)
                <div class="ec-alan-val"><strong>{{ $kayit->kullanici->ad }} {{ $kayit->kullanici->soyad }}</strong><br><small style="color:#6b7280;">{{ $kayit->kullanici->mail }}</small></div>
            @else
                <div class="ec-alan-val" style="color:#6b7280;">Kullanıcı atanmamış</div>
            @endif
            @if ($kayit->aciklama)
                <div class="ec-alan-lbl">Açıklama</div>
                <div class="ec-alan-val">{{ $kayit->aciklama }}</div>
            @endif
        </div>
    </div>

    {{-- SAĞ: Tutanaklar + Resimler --}}
    <div>
        <div class="ec-kart">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="12" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M9 11h6M9 15h4"/></svg>
                Tutanaklar
                <span style="margin-left:auto;font-size:.72rem;background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:999px;">{{ $kayit->tutanaklar->count() }}</span>
            </h3>

            @forelse ($kayit->tutanaklar as $t)
                <div class="ec-tut-item">
                    <div class="ec-tut-ust">
                        <div>
                            <span class="ec-tut-seri">Seri No: {{ $t->seri_no ?? '—' }}</span>
                            <span class="ec-tut-tarih">· Tutanak: {{ optional($t->tutanak_tarihi)->format('d.m.Y') ?? '—' }}</span>
                        </div>
                        <div style="display:flex;gap:6px;align-items:center;">
                            @php $gun = $t->isgalGunSayisi(); @endphp
                            @if ($gun !== null)
                                <span class="ec-gun-rozet {{ ! $t->isgal_bitis_tarihi ? 'is-devam' : '' }}">{{ $gun }} gün{{ ! $t->isgal_bitis_tarihi ? ' (devam)' : '' }}</span>
                            @endif
                            @if (auth()->user()->izinVarMi('ecrimisil.sil'))
                                <button type="button" class="ec-btn is-kirmizi is-mini" onclick="ecTutanakSil({{ $t->id }})">Sil</button>
                            @endif
                        </div>
                    </div>
                    <div class="ec-tut-grid">
                        <div class="ec-tut-alan"><div class="lbl">İşgal Başlangıç</div><div class="val">{{ optional($t->isgal_baslangic_tarihi)->format('d.m.Y') ?? '—' }}</div></div>
                        <div class="ec-tut-alan"><div class="lbl">İşgal Bitiş</div><div class="val">{{ optional($t->isgal_bitis_tarihi)->format('d.m.Y') ?? 'Devam ediyor' }}</div></div>
                        <div class="ec-tut-alan"><div class="lbl">Tutanak Tarihi</div><div class="val">{{ optional($t->tutanak_tarihi)->format('d.m.Y') ?? '—' }}</div></div>
                    </div>
                    @if ($t->aciklama)
                        <div class="ec-tut-alan" style="margin-top:8px;"><div class="lbl">Açıklama</div><div class="val">{{ $t->aciklama }}</div></div>
                    @endif
                </div>
            @empty
                <div style="padding:20px;text-align:center;color:#6b7280;background:#f9fafb;border:1px dashed #e5e7eb;border-radius:8px;margin-bottom:12px;">Henüz tutanak eklenmemiş.</div>
            @endforelse

            @if (auth()->user()->izinVarMi('ecrimisil.duzenle'))
                <details style="margin-top:12px;">
                    <summary style="cursor:pointer;font-weight:600;color:#2563eb;font-size:.86rem;">➕ Yeni Tutanak Ekle</summary>
                    <form method="POST" action="{{ route('panel.ecrimisil.tutanak.kaydet', $kayit->id) }}" style="margin-top:12px;padding:14px;background:#f9fafb;border-radius:8px;">
                        @csrf
                        <div class="ec-form-satir">
                            <div class="ec-form-alan"><label>Seri No</label><input type="text" name="seri_no" maxlength="100"></div>
                            <div class="ec-form-alan"><label>Tutanak Tarihi</label><input type="date" name="tutanak_tarihi"></div>
                            <div class="ec-form-alan"><label>İşgal Başlangıç</label><input type="date" name="isgal_baslangic_tarihi"></div>
                            <div class="ec-form-alan"><label>İşgal Bitiş</label><input type="date" name="isgal_bitis_tarihi"></div>
                        </div>
                        <div class="ec-form-alan" style="margin-top:10px;"><label>Açıklama</label><textarea name="aciklama" maxlength="2000"></textarea></div>
                        <button type="submit" class="ec-btn is-yesil" style="margin-top:10px;">Tutanağı Kaydet</button>
                    </form>
                </details>
            @endif
        </div>

        <div class="ec-kart">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h5"/></svg>
                Raporlar
                <span style="margin-left:auto;font-size:.72rem;background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:999px;">{{ $kayit->raporlar->count() }}</span>
            </h3>

            @forelse ($kayit->raporlar as $r)
                <div class="ec-tut-item">
                    <div class="ec-tut-ust">
                        <div>
                            <span class="ec-tut-seri">{{ $r->rapor_no ? 'Rapor No: '.$r->rapor_no : 'Rapor #'.$r->id }}</span>
                            <span class="ec-tut-tarih">· {{ optional($r->rapor_tarihi)->format('d.m.Y') ?? '—' }}</span>
                        </div>
                        <div style="display:flex;gap:6px;">
                            @if ($r->dosya_yolu)
                                <a class="ec-btn" href="{{ asset('storage/'.$r->dosya_yolu) }}" target="_blank" style="padding:4px 10px;font-size:.72rem;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
                                    Aç
                                </a>
                            @endif
                            @if (auth()->user()->izinVarMi('ecrimisil.sil'))
                                <button type="button" class="ec-btn is-kirmizi is-mini" onclick="ecRaporSil({{ $r->id }})">Sil</button>
                            @endif
                        </div>
                    </div>
                    @if ($r->fiyat !== null)
                        <div style="display:inline-block;background:#ecfdf5;color:#065f46;padding:3px 10px;border-radius:6px;font-weight:700;font-size:.82rem;margin-bottom:6px;">
                            {{ number_format((float) $r->fiyat, 2, ',', '.') }} ₺
                        </div>
                    @endif
                    @if ($r->aciklama)
                        <div style="color:#4b5563;font-size:.82rem;line-height:1.5;">{{ $r->aciklama }}</div>
                    @endif
                </div>
            @empty
                <div style="padding:20px;text-align:center;color:#6b7280;background:#f9fafb;border:1px dashed #e5e7eb;border-radius:8px;margin-bottom:12px;">Henüz rapor eklenmemiş.</div>
            @endforelse

            @if (auth()->user()->izinVarMi('ecrimisil.duzenle'))
                <details style="margin-top:12px;">
                    <summary style="cursor:pointer;font-weight:600;color:#2563eb;font-size:.86rem;">➕ Yeni Rapor Ekle</summary>
                    <form method="POST" action="{{ route('panel.ecrimisil.rapor.kaydet', $kayit->id) }}" enctype="multipart/form-data" style="margin-top:12px;padding:14px;background:#f9fafb;border-radius:8px;">
                        @csrf
                        <div class="ec-form-satir">
                            <div class="ec-form-alan"><label>Rapor No</label><input type="text" name="rapor_no" maxlength="100"></div>
                            <div class="ec-form-alan"><label>Rapor Tarihi</label><input type="date" name="rapor_tarihi"></div>
                            <div class="ec-form-alan" style="grid-column:span 2;"><label>Fiyat (₺)</label><input type="number" name="fiyat" step="0.01" min="0" placeholder="0,00"></div>
                        </div>
                        <div class="ec-form-alan" style="margin-top:12px;">
                            <label>Dosya Eki</label>
                            <label class="ec-file-drop" data-file-drop>
                                <input type="file" name="dosya" accept=".pdf,.doc,.docx">
                                <span class="ec-file-ikon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l3-3 3 3M12 12v6"/></svg>
                                </span>
                                <span class="ec-file-meta">
                                    <span class="ec-file-baslik" data-file-baslik>Dosyayı buraya sürükleyin veya tıklayın</span>
                                    <span class="ec-file-alt" data-file-alt>PDF veya Word (.doc, .docx) · max 20 MB</span>
                                </span>
                                <button type="button" class="ec-file-sil" data-file-sil>Kaldır</button>
                            </label>
                        </div>
                        <div class="ec-form-alan" style="margin-top:12px;"><label>Açıklama / İçerik</label><textarea name="aciklama" maxlength="5000"></textarea></div>
                        <button type="submit" class="ec-btn is-yesil" style="margin-top:10px;">Raporu Kaydet</button>
                    </form>
                </details>
            @endif
        </div>

        <div class="ec-kart">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                Resimler
                <span style="margin-left:auto;font-size:.72rem;background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:999px;">{{ $kayit->resimler->count() }}</span>
            </h3>

            @if ($kayit->resimler->count() > 0)
                <div class="ec-galeri">
                    @foreach ($kayit->resimler as $r)
                        <div class="ec-resim">
                            <img src="{{ asset('storage/'.$r->dosya_yolu) }}" alt="{{ $r->aciklama ?? '' }}" onclick="ecLightbox('{{ asset('storage/'.$r->dosya_yolu) }}')">
                            @if (auth()->user()->izinVarMi('ecrimisil.sil'))
                                <button type="button" class="ec-resim-sil" onclick="ecResimSil({{ $r->id }})" title="Sil">×</button>
                            @endif
                            @if ($r->aciklama)
                                <div class="ec-resim-alt">{{ $r->aciklama }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div style="padding:20px;text-align:center;color:#6b7280;background:#f9fafb;border:1px dashed #e5e7eb;border-radius:8px;">Henüz resim yüklenmemiş.</div>
            @endif

            @if (auth()->user()->izinVarMi('ecrimisil.duzenle'))
                <details style="margin-top:12px;">
                    <summary style="cursor:pointer;font-weight:600;color:#2563eb;font-size:.86rem;">➕ Resim Yükle</summary>
                    <form method="POST" action="{{ route('panel.ecrimisil.resim.yukle', $kayit->id) }}" enctype="multipart/form-data" style="margin-top:12px;padding:14px;background:#f9fafb;border-radius:8px;">
                        @csrf
                        <div class="ec-form-alan">
                            <label>Resim(ler)</label>
                            <label class="ec-file-drop" data-file-drop>
                                <input type="file" name="resimler[]" accept="image/*" multiple required>
                                <span class="ec-file-ikon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                                </span>
                                <span class="ec-file-meta">
                                    <span class="ec-file-baslik" data-file-baslik>Resimleri buraya sürükleyin veya tıklayın</span>
                                    <span class="ec-file-alt" data-file-alt>JPG, PNG, WEBP · her biri max 8 MB · çoklu seçim</span>
                                </span>
                                <button type="button" class="ec-file-sil" data-file-sil>Kaldır</button>
                            </label>
                        </div>
                        <div class="ec-form-satir" style="margin-top:12px;">
                            <div class="ec-form-alan">
                                <label>Tutanağa Bağla (opsiyonel)</label>
                                <select name="tutanak_id" style="width:100%;padding:10px 14px;font-size:.86rem;border:1.5px solid #d1d5db;border-radius:8px;background:#fff;">
                                    <option value="">— Bağlama —</option>
                                    @foreach ($kayit->tutanaklar as $t)
                                        <option value="{{ $t->id }}">Seri {{ $t->seri_no ?? $t->id }} · {{ optional($t->tutanak_tarihi)->format('d.m.Y') }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ec-form-alan" style="grid-column:span 3;"><label>Açıklama</label><input type="text" name="aciklama" maxlength="300"></div>
                        </div>
                        <button type="submit" class="ec-btn is-yesil" style="margin-top:12px;">Yükle</button>
                    </form>
                </details>
            @endif
        </div>
    </div>
</div>

<div class="ec-lightbox" id="ec-lightbox" onclick="if(event.target===this)ecLightboxKapat()">
    <button type="button" class="ec-lightbox-kapat" onclick="ecLightboxKapat()">Kapat (Esc)</button>
    <img id="ec-lightbox-img" src="" alt="">
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/tr.js"></script>
<script>
function ecLightbox(url) {
    document.getElementById('ec-lightbox-img').src = url;
    document.getElementById('ec-lightbox').classList.add('is-acik');
}
function ecLightboxKapat() {
    document.getElementById('ec-lightbox').classList.remove('is-acik');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') ecLightboxKapat(); });

async function ecTutanakSil(id) {
    if (!confirm('Tutanağı silmek istediğinize emin misiniz?')) return;
    const r = await fetch('{{ url('/panel/ecrimisil/tutanak') }}/' + id, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    });
    if (r.ok) location.reload(); else alert('Silinemedi.');
}
async function ecResimSil(id) {
    if (!confirm('Resmi silmek istediğinize emin misiniz?')) return;
    const r = await fetch('{{ url('/panel/ecrimisil/resim') }}/' + id, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    });
    if (r.ok) location.reload(); else alert('Silinemedi.');
}
async function ecRaporSil(id) {
    if (!confirm('Raporu silmek istediğinize emin misiniz?')) return;
    const r = await fetch('{{ url('/panel/ecrimisil/rapor') }}/' + id, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    });
    if (r.ok) location.reload(); else alert('Silinemedi.');
}

// Flatpickr — şık takvim popup
(function () {
    function fpBaslat() {
        if (typeof flatpickr === 'undefined') return;
        document.querySelectorAll('input[type="date"]:not(.fp-inited)').forEach(el => {
            el.classList.add('fp-inited');
            flatpickr(el, {
                locale: (typeof flatpickr.l10ns !== 'undefined' && flatpickr.l10ns.tr) ? flatpickr.l10ns.tr : 'default',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd F Y, l',
                allowInput: true,
                monthSelectorType: 'static',
            });
        });
    }
    fpBaslat();
    // Dinamik eklenen date input'lar için MutationObserver
    new MutationObserver(fpBaslat).observe(document.body, { childList: true, subtree: true });
})();

// Şık dosya yükleme davranışı — drag & drop + seçilen dosya adı gösterimi
(function () {
    document.addEventListener('change', function (e) {
        if (e.target.matches('.ec-file-drop input[type="file"]')) {
            const wrap = e.target.closest('.ec-file-drop');
            const baslik = wrap.querySelector('[data-file-baslik]');
            const alt = wrap.querySelector('[data-file-alt]');
            const files = e.target.files;
            if (files && files.length > 0) {
                wrap.classList.add('has-file');
                if (files.length === 1) {
                    baslik.textContent = files[0].name;
                    alt.textContent = (files[0].size / 1024 / 1024).toFixed(2) + ' MB · seçildi';
                } else {
                    baslik.textContent = files.length + ' dosya seçildi';
                    alt.textContent = Array.from(files).map(f => f.name).slice(0, 3).join(', ') + (files.length > 3 ? '...' : '');
                }
            } else {
                wrap.classList.remove('has-file');
            }
        }
    });
    document.addEventListener('click', function (e) {
        if (e.target.matches('[data-file-sil]')) {
            e.preventDefault();
            const wrap = e.target.closest('.ec-file-drop');
            const input = wrap.querySelector('input[type="file"]');
            const baslik = wrap.querySelector('[data-file-baslik]');
            const alt = wrap.querySelector('[data-file-alt]');
            input.value = '';
            wrap.classList.remove('has-file');
            baslik.textContent = baslik.dataset.eski || baslik.textContent;
            // Reset to defaults — recompute from initial HTML (kept via mutation)
            // Kolay yol: sayfayı yenilemeden reset için baslik/alt'ın initial'ını attribute olarak sakla
        }
    });
    // Drag & drop görsel state
    document.addEventListener('dragover', function (e) {
        const wrap = e.target.closest?.('.ec-file-drop');
        if (wrap) { e.preventDefault(); wrap.classList.add('is-dragover'); }
    });
    document.addEventListener('dragleave', function (e) {
        const wrap = e.target.closest?.('.ec-file-drop');
        if (wrap) wrap.classList.remove('is-dragover');
    });
    document.addEventListener('drop', function (e) {
        const wrap = e.target.closest?.('.ec-file-drop');
        if (wrap) wrap.classList.remove('is-dragover');
    });
})();
</script>
@endsection
