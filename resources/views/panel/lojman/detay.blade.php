@extends('layouts.panel')

@section('title', 'Lojman — '.$lojman->ad)

@push('head')
<style>
    .loj-detay-grid { display: grid; grid-template-columns: 320px 1fr; gap: 16px; align-items: start; }
    @media (max-width: 900px) { .loj-detay-grid { grid-template-columns: 1fr; } }
    .loj-kart { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; margin-bottom: 12px; }
    .loj-kart h3 { margin: 0 0 12px; font-size: .96rem; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 8px; padding-bottom: 10px; border-bottom: 1px solid #f3f4f6; }
    .loj-kart h3 svg { width: 18px; height: 18px; color: #2563eb; }
    .loj-lbl { font-size: .7rem; text-transform: uppercase; color: #6b7280; font-weight: 700; margin-bottom: 3px; }
    .loj-val { font-size: .88rem; color: #111827; margin-bottom: 12px; }
    .loj-val:last-child { margin-bottom: 0; }

    .loj-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; font-size: .78rem; font-weight: 600; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; }
    .loj-btn svg { width: 13px; height: 13px; }
    .loj-btn:hover { background: #1d4ed8; }
    .loj-btn.is-ghost { background: #fff; color: #374151; border: 1px solid #d1d5db; }
    .loj-btn.is-kirmizi { background: #dc2626; } .loj-btn.is-kirmizi:hover { background: #b91c1c; }
    .loj-btn.is-yesil { background: #059669; } .loj-btn.is-yesil:hover { background: #047857; }
    .loj-btn.is-mini { padding: 4px 8px; font-size: .72rem; }

    .loj-durum { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: .74rem; font-weight: 700; }
    .loj-durum-bos { background: #d1fae5; color: #065f46; }
    .loj-durum-dolu { background: #fee2e2; color: #991b1b; }
    .loj-durum-bakimda { background: #fef3c7; color: #92400e; }
    .loj-durum-kullanim-disi { background: #e5e7eb; color: #4b5563; }

    .loj-item { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-bottom: 10px; background: #f9fafb; }
    .loj-item-ust { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 8px; }
    .loj-item-baslik { font-weight: 700; color: #111827; font-size: .86rem; }
    .loj-item-alt { color: #6b7280; font-size: .74rem; }
    .loj-item-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; font-size: .78rem; }
    .loj-item-grid .k { color: #6b7280; font-size: .68rem; text-transform: uppercase; }
    .loj-item-grid .v { color: #111827; margin-top: 2px; }

    .loj-form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .loj-form-alan label { display: block; font-size: .7rem; text-transform: uppercase; color: #6b7280; letter-spacing: .04em; font-weight: 700; margin-bottom: 4px; }
    .loj-form-alan input, .loj-form-alan textarea, .loj-form-alan select { width: 100%; padding: 8px 12px; font-size: .84rem; border: 1.5px solid #d1d5db; border-radius: 8px; background: #fff; }

    .loj-basvuru-durum { display: inline-flex; padding: 2px 8px; border-radius: 4px; font-size: .7rem; font-weight: 600; }
    .loj-basvuru-durum.is-basvuruldu { background: #dbeafe; color: #1e40af; }
    .loj-basvuru-durum.is-degerlendirmede { background: #fef3c7; color: #92400e; }
    .loj-basvuru-durum.is-onaylandi { background: #d1fae5; color: #065f46; }
    .loj-basvuru-durum.is-reddedildi { background: #fee2e2; color: #991b1b; }
    .loj-basvuru-durum.is-tahsisedildi { background: #ede9fe; color: #5b21b6; }
</style>
@endpush

@section('content')
@if (session('basari'))<div class="flash-success">{{ session('basari') }}</div>@endif
@if ($errors->any())<div class="flash-error"><ul style="margin:0;padding-left:20px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

@php
    $durumEt = ['bos'=>'Boş','dolu'=>'Dolu','bakimda'=>'Bakımda','kullanim-disi'=>'Kullanım Dışı'];
    $basvuruEt = ['basvuruldu'=>'Başvuruldu','degerlendirmede'=>'Değerlendirmede','onaylandi'=>'Onaylandı','reddedildi'=>'Reddedildi','tahsisedildi'=>'Tahsis Edildi'];
@endphp

<section class="page-hero">
    <div class="page-hero-text">
        <h2>{{ $lojman->ad }} <span class="loj-durum loj-durum-{{ $lojman->durum }}" style="margin-left:8px;font-size:.72rem;">{{ $durumEt[$lojman->durum] }}</span></h2>
        <small>Lojman #{{ $lojman->id }} · {{ $lojman->tip ?? '—' }}</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.lojman.index') }}" class="btn-cancel">Listeye Dön</a>
        @if (auth()->user()->izinVarMi('lojman.duzenle'))
            <a href="{{ route('panel.lojman.duzenle', $lojman->id) }}" class="btn-cancel">Düzenle</a>
        @endif
        @if (auth()->user()->izinVarMi('lojman.sil'))
            <form method="POST" action="{{ route('panel.lojman.sil', $lojman->id) }}" style="display:inline;" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                @csrf @method('DELETE')
                <button type="submit" class="loj-btn is-kirmizi">Sil</button>
            </form>
        @endif
    </div>
</section>

<div class="loj-detay-grid">
    <div>
        <div class="loj-kart">
            <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V6a2 2 0 012-2h10a2 2 0 012 2v15"/></svg> Lojman</h3>
            <div class="loj-lbl">Ad</div><div class="loj-val"><strong>{{ $lojman->ad }}</strong></div>
            <div class="loj-lbl">Blok / Daire / Kat</div><div class="loj-val td-mono">{{ $lojman->blok ?? '—' }} / {{ $lojman->daire_no ?? '—' }} / Kat {{ $lojman->kat ?? '—' }}</div>
            <div class="loj-lbl">Oda / Alan</div><div class="loj-val">{{ $lojman->oda_sayisi ? $lojman->oda_sayisi.'+1' : '—' }} · {{ $lojman->alan_m2 ? number_format((float) $lojman->alan_m2, 2, ',', '.').' m²' : '—' }}</div>
            <div class="loj-lbl">Tip</div><div class="loj-val">{{ $lojman->tip ?? '—' }}</div>
            @if ($lojman->aciklama)<div class="loj-lbl">Açıklama</div><div class="loj-val">{{ $lojman->aciklama }}</div>@endif
        </div>
        <div class="loj-kart">
            <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-7-7-7-13a7 7 0 0114 0c0 6-7 13-7 13z"/><circle cx="12" cy="9" r="2.6"/></svg> Taşınmaz</h3>
            @if ($lojman->tasinmaz)
                <div class="loj-val td-mono"><span class="tl-badge">{{ $lojman->tasinmaz->ada }}</span> / <span class="tl-badge">{{ $lojman->tasinmaz->parsel }}</span></div>
                <div class="loj-lbl">Konum</div>
                <div class="loj-val">{{ $lojman->tasinmaz->il?->ad }} / {{ $lojman->tasinmaz->ilce?->ad }}<br><small style="color:#6b7280;">{{ $lojman->tasinmaz->mahalle?->ad }}</small></div>
            @else
                <div class="loj-val" style="color:#6b7280;">Taşınmaz bağlanmamış</div>
            @endif
        </div>
    </div>

    <div>
        {{-- TAHSISLER --}}
        <div class="loj-kart">
            <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Tahsisler <span style="margin-left:auto;background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:999px;font-size:.72rem;">{{ $lojman->tahsisler->count() }}</span></h3>
            @forelse ($lojman->tahsisler as $t)
                @php $aktif = ! $t->bitis_tarihi || $t->bitis_tarihi >= now(); @endphp
                <div class="loj-item">
                    <div class="loj-item-ust">
                        <div>
                            <div class="loj-item-baslik">{{ $t->ad_soyad }} {{ $t->sicil_no ? '('.$t->sicil_no.')' : '' }}</div>
                            <div class="loj-item-alt">{{ optional($t->baslangic_tarihi)->format('d.m.Y') }} — {{ $t->bitis_tarihi ? $t->bitis_tarihi->format('d.m.Y') : 'Devam ediyor' }}</div>
                        </div>
                        <div style="display:flex;gap:6px;align-items:center;">
                            @if ($aktif)
                                <span class="loj-durum loj-durum-dolu">Aktif · {{ $t->gunSayisi() }} gün</span>
                                @if (auth()->user()->izinVarMi('lojman.duzenle'))
                                    <form method="POST" action="{{ route('panel.lojman.tahsis.sonlandir', $t->id) }}" style="display:inline;" onsubmit="return confirm('Tahsisi bugün itibariyle sonlandırılsın mı?');">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="loj-btn is-mini">Sonlandır</button>
                                    </form>
                                @endif
                            @else
                                <span class="loj-durum loj-durum-bos">Sonlandı · {{ $t->gunSayisi() }} gün</span>
                            @endif
                            @if (auth()->user()->izinVarMi('lojman.sil'))
                                <button type="button" class="loj-btn is-kirmizi is-mini" onclick="lojTahsisSil({{ $t->id }})">Sil</button>
                            @endif
                        </div>
                    </div>
                    @if ($t->karar_no || $t->karar_tarihi || $t->aciklama)
                        <div class="loj-item-grid">
                            <div><div class="k">Karar No</div><div class="v">{{ $t->karar_no ?? '—' }}</div></div>
                            <div><div class="k">Karar Tarihi</div><div class="v">{{ optional($t->karar_tarihi)->format('d.m.Y') ?? '—' }}</div></div>
                            <div><div class="k">Başvuru</div><div class="v">{{ $t->basvuru_id ? '#'.$t->basvuru_id : '—' }}</div></div>
                        </div>
                        @if ($t->aciklama)<div style="margin-top:6px;font-size:.78rem;color:#4b5563;">{{ $t->aciklama }}</div>@endif
                    @endif
                </div>
            @empty
                <div style="padding:20px;text-align:center;color:#6b7280;background:#f9fafb;border:1px dashed #e5e7eb;border-radius:8px;">Henüz tahsis yok.</div>
            @endforelse

            @if (auth()->user()->izinVarMi('lojman.duzenle'))
                <details style="margin-top:12px;">
                    <summary style="cursor:pointer;font-weight:600;color:#2563eb;font-size:.86rem;">➕ Yeni Tahsis Ekle</summary>
                    <form method="POST" action="{{ route('panel.lojman.tahsis.kaydet', $lojman->id) }}" style="margin-top:12px;padding:14px;background:#f9fafb;border-radius:8px;">
                        @csrf
                        <div class="loj-form-grid">
                            <div class="loj-form-alan"><label>Ad Soyad *</label><input type="text" name="ad_soyad" required maxlength="200"></div>
                            <div class="loj-form-alan"><label>Sicil No</label><input type="text" name="sicil_no" maxlength="50"></div>
                            <div class="loj-form-alan">
                                <label>Başvuru (opsiyonel)</label>
                                <select name="basvuru_id"><option value="">— Bağımsız —</option>
                                    @foreach ($lojman->basvurular as $b)
                                        <option value="{{ $b->id }}">{{ $b->ad_soyad }} · {{ optional($b->basvuru_tarihi)->format('d.m.Y') }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="loj-form-alan"><label>Başlangıç *</label><input type="date" name="baslangic_tarihi" required></div>
                            <div class="loj-form-alan"><label>Bitiş</label><input type="date" name="bitis_tarihi"></div>
                            <div class="loj-form-alan"><label>Karar No</label><input type="text" name="karar_no" maxlength="100"></div>
                            <div class="loj-form-alan"><label>Karar Tarihi</label><input type="date" name="karar_tarihi"></div>
                        </div>
                        <div class="loj-form-alan" style="margin-top:10px;"><label>Açıklama</label><textarea name="aciklama" maxlength="2000"></textarea></div>
                        <button type="submit" class="loj-btn is-yesil" style="margin-top:10px;">Tahsis Kaydet</button>
                    </form>
                </details>
            @endif
        </div>

        {{-- BAŞVURULAR --}}
        <div class="loj-kart">
            <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg> Başvurular <span style="margin-left:auto;background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:999px;font-size:.72rem;">{{ $lojman->basvurular->count() }}</span></h3>
            @forelse ($lojman->basvurular as $b)
                <div class="loj-item">
                    <div class="loj-item-ust">
                        <div>
                            <div class="loj-item-baslik">{{ $b->ad_soyad }} {{ $b->sicil_no ? '('.$b->sicil_no.')' : '' }}</div>
                            <div class="loj-item-alt">{{ optional($b->basvuru_tarihi)->format('d.m.Y') }} · {{ $b->unvan ?? '' }} · {{ $b->birim ?? '' }}</div>
                        </div>
                        <div style="display:flex;gap:6px;">
                            <span class="loj-basvuru-durum is-{{ $b->durum }}">{{ $basvuruEt[$b->durum] ?? $b->durum }}</span>
                            <a href="{{ route('panel.lojman.basvuru-detay', $b->id) }}" class="loj-btn is-ghost is-mini">Detay</a>
                        </div>
                    </div>
                </div>
            @empty
                <div style="padding:20px;text-align:center;color:#6b7280;background:#f9fafb;border:1px dashed #e5e7eb;border-radius:8px;">Bu lojmana başvuru yok.</div>
            @endforelse

            @if (auth()->user()->izinVarMi('lojman.olustur'))
                <div style="margin-top:12px;">
                    <a href="{{ route('panel.lojman.basvuru.olustur', ['lojman_id' => $lojman->id]) }}" class="loj-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                        Bu Lojmana Başvuru Ekle
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
async function lojTahsisSil(id) {
    if (!confirm('Tahsisi silmek istediğinize emin misiniz?')) return;
    const r = await fetch('{{ url('/panel/lojman/tahsis') }}/' + id, {
        method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    });
    if (r.ok) location.reload(); else alert('Silinemedi.');
}
</script>
@endsection
