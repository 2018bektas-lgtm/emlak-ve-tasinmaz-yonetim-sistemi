@extends('layouts.panel')

@section('title', 'Başvuru — '.$basvuru->ad_soyad)

@push('head')
<style>
    .lb-grid { display: grid; grid-template-columns: 340px 1fr; gap: 16px; align-items: start; }
    @media (max-width: 900px) { .lb-grid { grid-template-columns: 1fr; } }
    .lb-kart { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; margin-bottom: 12px; }
    .lb-kart h3 { margin: 0 0 12px; font-size: .96rem; font-weight: 700; color: #111827; padding-bottom: 10px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; gap: 8px; }
    .lb-form-alan label { display: block; font-size: .7rem; text-transform: uppercase; color: #6b7280; letter-spacing: .04em; font-weight: 700; margin-bottom: 4px; }
    .lb-form-alan input, .lb-form-alan select, .lb-form-alan textarea { width: 100%; padding: 8px 12px; font-size: .84rem; border: 1.5px solid #d1d5db; border-radius: 8px; background: #fff; margin-bottom: 10px; }
    .lb-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; font-size: .82rem; font-weight: 600; background: #2563eb; color: #fff; border: none; border-radius: 7px; cursor: pointer; text-decoration: none; }
    .lb-btn:hover { background: #1d4ed8; }
    .lb-btn.is-ghost { background: #fff; color: #374151; border: 1px solid #d1d5db; }
    .lb-btn.is-kirmizi { background: #dc2626; }
    .lb-btn.is-yesil { background: #059669; }
    .lb-evrak { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; margin-bottom: 8px; background: #f9fafb; display: flex; justify-content: space-between; align-items: center; }
    .lb-kat-rozet { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: .68rem; font-weight: 700; }
    .lb-kat-basvuru { background: #dbeafe; color: #1e40af; }
    .lb-kat-gorus-gelen { background: #ede9fe; color: #5b21b6; }
    .lb-kat-gorus-giden { background: #fce7f3; color: #9d174d; }
    .lb-kat-encumen { background: #fef3c7; color: #92400e; }
    .lb-kat-tahsis { background: #d1fae5; color: #065f46; }
    .lb-kat-diger { background: #e5e7eb; color: #4b5563; }
</style>
@endpush

@section('content')
@if (session('basari'))<div class="flash-success">{{ session('basari') }}</div>@endif
@if ($errors->any())<div class="flash-error"><ul style="margin:0;padding-left:20px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

@php
    $basvuruEt = ['basvuruldu'=>'Başvuruldu','degerlendirmede'=>'Değerlendirmede','onaylandi'=>'Onaylandı','reddedildi'=>'Reddedildi','tahsisedildi'=>'Tahsis Edildi'];
    $katEt = ['basvuru'=>'Başvuru','gorus-gelen'=>'Görüş (Gelen)','gorus-giden'=>'Görüş (Giden)','encumen'=>'Encümen','tahsis'=>'Tahsis','diger'=>'Diğer'];
@endphp

<section class="page-hero">
    <div class="page-hero-text">
        <h2>{{ $basvuru->ad_soyad }}</h2>
        <small>Başvuru #{{ $basvuru->id }} · {{ $basvuruEt[$basvuru->durum] ?? $basvuru->durum }} · {{ $basvuru->basvuru_tarihi?->format('d.m.Y') }}</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.lojman.basvurular') }}" class="btn-cancel">Listeye Dön</a>
        @if (auth()->user()->izinVarMi('lojman.sil'))
            <button type="button" class="lb-btn is-kirmizi" onclick="lbBasvuruSil({{ $basvuru->id }})">Sil</button>
        @endif
    </div>
</section>

<div class="lb-grid">
    <div>
        {{-- Başvuru düzenle formu --}}
        <div class="lb-kart">
            <h3>Başvuru Bilgileri</h3>
            <form method="POST" action="{{ route('panel.lojman.basvuru.guncelle', $basvuru->id) }}">
                @csrf @method('PUT')
                <div class="lb-form-alan"><label>Ad Soyad *</label><input type="text" name="ad_soyad" value="{{ old('ad_soyad', $basvuru->ad_soyad) }}" required></div>
                <div class="lb-form-alan"><label>TC Kimlik</label><input type="text" name="tc_kimlik" value="{{ old('tc_kimlik', $basvuru->tc_kimlik) }}" maxlength="11"></div>
                <div class="lb-form-alan"><label>Sicil No</label><input type="text" name="sicil_no" value="{{ old('sicil_no', $basvuru->sicil_no) }}" maxlength="50"></div>
                <div class="lb-form-alan"><label>Ünvan</label><input type="text" name="unvan" value="{{ old('unvan', $basvuru->unvan) }}" maxlength="150"></div>
                <div class="lb-form-alan"><label>Birim</label><input type="text" name="birim" value="{{ old('birim', $basvuru->birim) }}" maxlength="200"></div>
                <div class="lb-form-alan">
                    <label>Tahsis Türü</label>
                    <select name="tahsis_turu">
                        <option value="">—</option>
                        @foreach (['Sıra','Görev','Hizmet','Temsil'] as $t)
                            <option value="{{ $t }}" @selected(old('tahsis_turu', $basvuru->tahsis_turu) === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lb-form-alan">
                    <label>Lojman</label>
                    <select name="lojman_id">
                        <option value="">— Herhangi —</option>
                        @foreach ($lojmanlar as $l)<option value="{{ $l->id }}" @selected(old('lojman_id', $basvuru->lojman_id) == $l->id)>{{ $l->ad }}</option>@endforeach
                    </select>
                </div>
                <div class="lb-form-alan"><label>Başvuru Tarihi *</label><input type="date" name="basvuru_tarihi" value="{{ old('basvuru_tarihi', optional($basvuru->basvuru_tarihi)->toDateString()) }}" required></div>
                <div class="lb-form-alan">
                    <label>Durum</label>
                    <select name="durum">
                        @foreach ($basvuruEt as $k => $v)<option value="{{ $k }}" @selected(old('durum', $basvuru->durum) === $k)>{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="lb-form-alan"><label>Açıklama</label><textarea name="aciklama" rows="3">{{ old('aciklama', $basvuru->aciklama) }}</textarea></div>
                <button type="submit" class="lb-btn">Güncelle</button>
            </form>
        </div>
    </div>

    <div>
        {{-- EVRAKLAR --}}
        <div class="lb-kart">
            <h3><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg> Evraklar <span style="margin-left:auto;background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:999px;font-size:.72rem;">{{ $basvuru->evraklar->count() }}</span></h3>
            @forelse ($basvuru->evraklar as $e)
                <div class="lb-evrak">
                    <div style="min-width:0;">
                        <span class="lb-kat-rozet lb-kat-{{ $e->kategori }}">{{ $katEt[$e->kategori] ?? $e->kategori }}</span>
                        <div style="font-weight:600;color:#111827;margin-top:4px;">{{ $e->evrak_no ?? 'Evrak #'.$e->id }} · {{ optional($e->evrak_tarihi)->format('d.m.Y') ?? '—' }}</div>
                        @if ($e->aciklama)<div style="font-size:.76rem;color:#4b5563;">{{ $e->aciklama }}</div>@endif
                    </div>
                    <div style="display:flex;gap:6px;">
                        @if ($e->dosya_yolu)<a class="lb-btn is-ghost" style="padding:5px 10px;font-size:.74rem;" href="{{ asset('storage/'.$e->dosya_yolu) }}" target="_blank">Aç</a>@endif
                        @if (auth()->user()->izinVarMi('lojman.sil'))<button type="button" class="lb-btn is-kirmizi" style="padding:5px 10px;font-size:.74rem;" onclick="lbEvrakSil({{ $e->id }})">Sil</button>@endif
                    </div>
                </div>
            @empty
                <div style="padding:20px;text-align:center;color:#6b7280;background:#f9fafb;border:1px dashed #e5e7eb;border-radius:8px;">Henüz evrak yok.</div>
            @endforelse

            @if (auth()->user()->izinVarMi('lojman.duzenle'))
                <details style="margin-top:12px;">
                    <summary style="cursor:pointer;font-weight:600;color:#2563eb;font-size:.86rem;">➕ Yeni Evrak Ekle</summary>
                    <form method="POST" action="{{ route('panel.lojman.evrak.yukle') }}" enctype="multipart/form-data" style="margin-top:12px;padding:14px;background:#f9fafb;border-radius:8px;">
                        @csrf
                        <input type="hidden" name="basvuru_id" value="{{ $basvuru->id }}">
                        @if ($basvuru->lojman_id)<input type="hidden" name="lojman_id" value="{{ $basvuru->lojman_id }}">@endif
                        <div class="lb-form-alan">
                            <label>Kategori *</label>
                            <select name="kategori" required>@foreach ($katEt as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <div class="lb-form-alan"><label>Evrak No</label><input type="text" name="evrak_no" maxlength="100"></div>
                            <div class="lb-form-alan"><label>Evrak Tarihi</label><input type="date" name="evrak_tarihi"></div>
                        </div>
                        <div class="lb-form-alan"><label>Dosya (PDF/Word/Görsel, max 20 MB)</label><input type="file" name="dosya" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"></div>
                        <div class="lb-form-alan"><label>Açıklama</label><textarea name="aciklama" rows="2"></textarea></div>
                        <button type="submit" class="lb-btn is-yesil">Evrakı Kaydet</button>
                    </form>
                </details>
            @endif
        </div>

        {{-- MEVCUT TAHSIS --}}
        @php $t = $basvuru->tahsis(); @endphp
        <div class="lb-kart">
            <h3><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Tahsis</h3>
            @if ($t)
                <div style="padding:12px;background:#ecfdf5;border-radius:8px;border:1px solid #a7f3d0;">
                    <div style="color:#065f46;font-weight:700;">{{ $t->ad_soyad }} → {{ $t->lojman?->ad ?? '—' }}</div>
                    <div style="color:#059669;font-size:.78rem;margin-top:4px;">
                        {{ optional($t->baslangic_tarihi)->format('d.m.Y') }} — {{ $t->bitis_tarihi ? $t->bitis_tarihi->format('d.m.Y') : 'Devam ediyor' }}
                    </div>
                    @if ($t->karar_no)<div style="color:#065f46;font-size:.74rem;margin-top:2px;">Karar: {{ $t->karar_no }} · {{ optional($t->karar_tarihi)->format('d.m.Y') }}</div>@endif
                    <a class="lb-btn is-ghost" style="margin-top:8px;padding:4px 10px;font-size:.72rem;" href="{{ route('panel.lojman.detay', $t->lojman_id) }}">Lojmanı Aç</a>
                </div>
            @else
                <div style="padding:16px;text-align:center;color:#6b7280;background:#f9fafb;border:1px dashed #e5e7eb;border-radius:8px;">
                    Bu başvurudan henüz tahsis yapılmamış.
                    @if ($basvuru->lojman_id)
                        <br><a href="{{ route('panel.lojman.detay', $basvuru->lojman_id) }}" class="lb-btn" style="margin-top:8px;">Lojman detayına git → Tahsis ekle</a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

<script>
async function lbEvrakSil(id) {
    if (!confirm('Evrakı silmek istediğinize emin misiniz?')) return;
    const r = await fetch('{{ url('/panel/lojman/evrak') }}/' + id, {
        method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    });
    if (r.ok) location.reload(); else alert('Silinemedi.');
}
async function lbBasvuruSil(id) {
    if (!confirm('Başvuruyu silmek istediğinize emin misiniz?')) return;
    const r = await fetch('{{ url('/panel/lojman/basvuru') }}/' + id, {
        method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    });
    if (r.ok) location.href = '{{ route('panel.lojman.basvurular') }}'; else alert('Silinemedi.');
}
</script>
@endsection
