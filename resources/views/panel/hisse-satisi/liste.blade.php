@extends('layouts.panel')

@section('title', 'Hisse Satış Başvuruları')

@push('head')
<style>
    /* Kısayol chip şeridi */
    .hsl-kisayol-bar {
        display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px;
        background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px;
    }
    .hsl-kisayol {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 7px 12px; font-size: .78rem; font-weight: 600;
        background: transparent; color: #4b5563; border: 1px solid transparent;
        border-radius: 6px; cursor: pointer; text-decoration: none;
        transition: all .12s ease; white-space: nowrap;
    }
    .hsl-kisayol:hover { background: #f3f4f6; color: #111827; }
    .hsl-kisayol.is-aktif { background: #2563eb; color: #fff; }
    .hsl-kisayol .say {
        font-size: .68rem; padding: 1px 6px; border-radius: 999px;
        background: #e5e7eb; color: #374151; font-weight: 700;
    }
    .hsl-kisayol.is-aktif .say { background: rgba(255,255,255,.28); color: #fff; }
    .hsl-kisayol.is-yesil.is-aktif { background: #059669; }
    .hsl-kisayol.is-turuncu.is-aktif { background: #d97706; }
    .hsl-kisayol.is-kirmizi.is-aktif { background: #dc2626; }
    .hsl-kisayol.is-mor.is-aktif { background: #7c3aed; }

    /* Arama & araç şeridi */
    .hsl-toolbar {
        display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
        background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px;
        margin-bottom: 12px;
    }
    .hsl-ara { flex: 1; min-width: 280px; position: relative; }
    .hsl-ara input {
        width: 100%; padding: 9px 12px 9px 36px;
        border: 1px solid #d1d5db; border-radius: 8px; font-size: .88rem;
    }
    .hsl-ara input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
    .hsl-ara-ikon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; }
    .hsl-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 9px 14px; font-size: .82rem; font-weight: 600;
        background: #2563eb; color: #fff; border: none; border-radius: 7px; cursor: pointer;
        text-decoration: none; transition: background .12s ease;
    }
    .hsl-btn svg { width: 14px; height: 14px; }
    .hsl-btn:hover { background: #1d4ed8; }
    .hsl-btn.is-ghost { background: #fff; color: #374151; border: 1px solid #d1d5db; }
    .hsl-btn.is-ghost:hover { background: #f3f4f6; }
    .hsl-btn.is-tehlike { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .hsl-btn.is-tehlike:hover { background: #fee2e2; }

    /* Detaylı filtre paneli */
    .hsl-filtre {
        background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px;
        padding: 14px; margin-bottom: 12px;
    }
    .hsl-filtre[hidden] { display: none; }
    .hsl-filtre-baslik {
        font-size: .72rem; text-transform: uppercase; letter-spacing: .06em;
        color: #6b7280; font-weight: 700; margin-bottom: 12px;
        display: flex; align-items: center; gap: 8px;
    }
    .hsl-filtre-baslik::after {
        content: ''; flex: 1; height: 1px; background: #e5e7eb;
    }
    .hsl-filtre-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
    @media (max-width: 1100px) { .hsl-filtre-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 600px)  { .hsl-filtre-grid { grid-template-columns: 1fr; } }
    .hsl-alan label {
        display: block; font-size: .7rem; text-transform: uppercase;
        color: #6b7280; letter-spacing: .04em; font-weight: 700; margin-bottom: 4px;
    }
    .hsl-alan input, .hsl-alan select {
        width: 100%; padding: 7px 10px; font-size: .84rem;
        border: 1px solid #d1d5db; border-radius: 6px; background: #fff;
    }
    .hsl-alan input:focus, .hsl-alan select:focus { outline: none; border-color: #2563eb; }
    .hsl-filtre-eylem { display: flex; gap: 8px; margin-top: 14px; }

    /* Aktif filtre chip'leri */
    .hsl-aktif-cizgi {
        display: flex; flex-wrap: wrap; gap: 6px; align-items: center;
        margin-bottom: 12px;
    }
    .hsl-aktif-cizgi .lbl { font-size: .74rem; color: #6b7280; font-weight: 600; }
    .hsl-aktif-chip {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 3px 4px 3px 10px; font-size: .74rem;
        background: #eff6ff; color: #1e40af; border-radius: 999px;
        border: 1px solid #bfdbfe; text-decoration: none;
    }
    .hsl-aktif-chip strong { font-weight: 700; }
    .hsl-aktif-chip .x {
        display: inline-flex; align-items: center; justify-content: center;
        width: 18px; height: 18px; border-radius: 50%; background: #dbeafe; color: #1e40af;
    }
    .hsl-aktif-chip .x:hover { background: #bfdbfe; }
    .hsl-aktif-tumu-temizle {
        color: #dc2626; font-size: .74rem; text-decoration: none; font-weight: 600;
        display: inline-flex; align-items: center; gap: 4px; margin-left: 6px;
    }
    .hsl-aktif-tumu-temizle:hover { text-decoration: underline; }
</style>
@endpush

@section('content')
@if (session('basari'))
    <div class="flash-success">{{ session('basari') }}</div>
@endif

<section class="page-hero">
    <div class="page-hero-text">
        <h2>Hisse Satış Başvuruları</h2>
        <small>Toplam {{ $gruplar->total() }} başvuru grubu</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.hisse-satisi.index') }}" class="btn-submit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
            Yeni Başvuru
        </a>
    </div>
</section>

@php
    $aktifKisayol = $filtre['kisayol'] ?? '';
    // Aktif filtre var mı — kısayol dışı bir şey
    $detayFiltreAktif = collect($filtre)->except('kisayol')->filter(fn ($v) => filled($v))->isNotEmpty() || filled($durum);
@endphp

{{-- ==== Kısayol chip şeridi ==== --}}
<div class="hsl-kisayol-bar" role="tablist" aria-label="Hızlı kısayollar">
    @php
        $kisayollar = [
            ['ad' => 'Tümü',        'k' => '',            'renk' => '',           'ikon' => '<path d="M4 6h16M4 12h16M4 18h16"/>',                                                                                                            'say' => $kisayolSayaci['hepsi']],
            ['ad' => 'İşlemde',     'k' => 'islemde',     'renk' => 'is-turuncu', 'ikon' => '<path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/>',                                                                                        'say' => $kisayolSayaci['islemde']],
            ['ad' => 'Beklemede',   'k' => 'beklemede',   'renk' => 'is-mor',     'ikon' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5M12 15h.01"/>',                                                                                'say' => $kisayolSayaci['beklemede']],
            ['ad' => 'Yeni',        'k' => 'yeni',        'renk' => '',           'ikon' => '<path d="M12 5v14M5 12h14"/>',                                                                                                                  'say' => $kisayolSayaci['yeni']],
            ['ad' => 'Tapu Aşaması','k' => 'tapu',        'renk' => '',           'ikon' => '<path d="M3 21h18M5 21V7l7-5 7 5v14"/>',                                                                                                        'say' => $kisayolSayaci['tapu']],
            ['ad' => 'Tamamlanan',  'k' => 'tamamlanan',  'renk' => 'is-yesil',   'ikon' => '<path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/>',                                                                                     'say' => $kisayolSayaci['tamamlanan']],
            ['ad' => 'Reddedilen',  'k' => 'reddedilen',  'renk' => 'is-kirmizi', 'ikon' => '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',                                                                                'say' => $kisayolSayaci['reddedilen']],
            ['ad' => 'Bu Ay',       'k' => 'bu-ay',       'renk' => '',           'ikon' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',                                                            'say' => $kisayolSayaci['bu-ay']],
        ];
    @endphp
    @foreach ($kisayollar as $ks)
        <a href="{{ route('panel.hisse-satisi.liste', $ks['k'] === '' ? [] : ['kisayol' => $ks['k']]) }}"
           class="hsl-kisayol {{ $ks['renk'] }} {{ $aktifKisayol === $ks['k'] ? 'is-aktif' : '' }}"
           role="tab" aria-selected="{{ $aktifKisayol === $ks['k'] ? 'true' : 'false' }}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $ks['ikon'] !!}</svg>
            {{ $ks['ad'] }}
            <span class="say">{{ $ks['say'] }}</span>
        </a>
    @endforeach
</div>

{{-- ==== Arama + toolbar ==== --}}
<form method="GET" action="{{ route('panel.hisse-satisi.liste') }}" id="hsl-form">
    {{-- Kısayol seçimini korumak için hidden --}}
    @if ($aktifKisayol)
        <input type="hidden" name="kisayol" value="{{ $aktifKisayol }}">
    @endif

    <div class="hsl-toolbar">
        <div class="hsl-ara">
            <svg class="hsl-ara-ikon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="search" name="q" value="{{ $filtre['q'] ?? '' }}" placeholder="Ad soyad, TC, ada veya parsel ile ara...">
        </div>
        <button type="submit" class="hsl-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>Ara</button>
        <button type="button" class="hsl-btn is-ghost" id="hsl-filtre-toggle" aria-expanded="{{ $detayFiltreAktif ? 'true' : 'false' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54z"/></svg>
            Detaylı Filtre
        </button>
        @if ($detayFiltreAktif || $filtre['q'] || $aktifKisayol)
            <a href="{{ route('panel.hisse-satisi.liste') }}" class="hsl-btn is-tehlike">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                Tümünü Temizle
            </a>
        @endif
    </div>

    {{-- ==== Detaylı filtre paneli ==== --}}
    <div class="hsl-filtre" id="hsl-filtre" {{ $detayFiltreAktif ? '' : 'hidden' }}>
        <div class="hsl-filtre-baslik">Konum</div>
        <div class="hsl-filtre-grid">
            <div class="hsl-alan"><label>İl</label>
                <select name="il_id" id="hsl-f-il" data-secili="{{ $filtre['ilId'] ?? '' }}">
                    <option value="">— Tümü —</option>
                    @foreach ($iller as $il)
                        <option value="{{ $il->id }}" @selected(($filtre['ilId'] ?? '') == $il->id)>{{ $il->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="hsl-alan"><label>İlçe</label>
                <select name="ilce_id" id="hsl-f-ilce" data-secili="{{ $filtre['ilceId'] ?? '' }}" @disabled(! ($filtre['ilId'] ?? null))>
                    <option value="">— Tümü —</option>
                    @foreach ($ilceler as $ilce)
                        <option value="{{ $ilce->id }}" @selected(($filtre['ilceId'] ?? '') == $ilce->id)>{{ $ilce->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="hsl-alan"><label>Mahalle</label>
                <select name="mahalle_id" id="hsl-f-mahalle" data-secili="{{ $filtre['mahalleId'] ?? '' }}" @disabled(! ($filtre['ilceId'] ?? null))>
                    <option value="">— Tümü —</option>
                    @foreach ($mahalleler as $m)
                        <option value="{{ $m->id }}" @selected(($filtre['mahalleId'] ?? '') == $m->id)>{{ $m->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="hsl-alan"><label>Aşama</label>
                <select name="durum">
                    <option value="">— Tümü —</option>
                    @foreach ($durumSecenekleri as $d)
                        <option value="{{ $d->value }}" @selected($durum === $d->value)>{{ $d->etiket() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="hsl-alan"><label>Ada</label>
                <input type="text" name="ada" value="{{ $filtre['ada'] ?? '' }}" autocomplete="off" placeholder="Örn. 50285">
            </div>
            <div class="hsl-alan"><label>Parsel</label>
                <input type="text" name="parsel" value="{{ $filtre['parsel'] ?? '' }}" autocomplete="off" placeholder="Örn. 5">
            </div>
            <div class="hsl-alan"><label>Başlangıç Tarihi</label>
                <input type="date" name="baslangic" value="{{ $filtre['baslangic'] ?? '' }}">
            </div>
            <div class="hsl-alan"><label>Bitiş Tarihi</label>
                <input type="date" name="bitis" value="{{ $filtre['bitis'] ?? '' }}">
            </div>
        </div>

        <div class="hsl-filtre-baslik" style="margin-top:14px;">Başvuran</div>
        <div class="hsl-filtre-grid">
            <div class="hsl-alan" style="grid-column:span 2;"><label>Ad Soyad</label>
                <input type="text" name="ad_soyad" value="{{ $filtre['adSoyad'] ?? '' }}" autocomplete="off" placeholder="Başvuru sahibinin adı">
            </div>
            <div class="hsl-alan" style="grid-column:span 2;"><label>TC Kimlik</label>
                <input type="text" name="tc_kimlik" value="{{ $filtre['tcKimlik'] ?? '' }}" autocomplete="off" placeholder="11 haneli TC">
            </div>
        </div>

        <div class="hsl-filtre-eylem">
            <button type="submit" class="hsl-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54z"/></svg>Uygula</button>
            <a href="{{ route('panel.hisse-satisi.liste') }}" class="hsl-btn is-ghost">Temizle</a>
        </div>
    </div>
</form>

{{-- Aktif filtre chip'leri --}}
@php
    $aktifChipler = [];
    $durumEtiket = null;
    foreach ($durumSecenekleri as $d) { if ($d->value === $durum) { $durumEtiket = $d->etiket(); break; } }
    $mapChip = [
        'q' => ['Arama', $filtre['q'] ?? ''],
        'adSoyad' => ['Ad Soyad', $filtre['adSoyad'] ?? ''],
        'tcKimlik' => ['TC', $filtre['tcKimlik'] ?? ''],
        'ada' => ['Ada', $filtre['ada'] ?? ''],
        'parsel' => ['Parsel', $filtre['parsel'] ?? ''],
        'baslangic' => ['≥ Tarih', $filtre['baslangic'] ?? ''],
        'bitis' => ['≤ Tarih', $filtre['bitis'] ?? ''],
    ];
    $ilAd = $iller->firstWhere('id', $filtre['ilId'])?->ad;
    $ilceAd = $ilceler->firstWhere('id', $filtre['ilceId'])?->ad;
    $mahalleAd = $mahalleler->firstWhere('id', $filtre['mahalleId'])?->ad;
    if ($ilAd) $aktifChipler[] = ['İl', $ilAd, 'il_id'];
    if ($ilceAd) $aktifChipler[] = ['İlçe', $ilceAd, 'ilce_id'];
    if ($mahalleAd) $aktifChipler[] = ['Mahalle', $mahalleAd, 'mahalle_id'];
    if ($durumEtiket) $aktifChipler[] = ['Aşama', $durumEtiket, 'durum'];
    foreach ($mapChip as $qKey => [$label, $val]) {
        $formKey = ['adSoyad' => 'ad_soyad', 'tcKimlik' => 'tc_kimlik'][$qKey] ?? $qKey;
        if (filled($val)) $aktifChipler[] = [$label, $val, $formKey];
    }
@endphp

@if (count($aktifChipler) > 0)
    <div class="hsl-aktif-cizgi">
        <span class="lbl">Aktif filtreler:</span>
        @foreach ($aktifChipler as [$lbl, $val, $key])
            @php
                $params = request()->except([$key, 'page']);
            @endphp
            <a href="{{ route('panel.hisse-satisi.liste', $params) }}" class="hsl-aktif-chip">
                <span>{{ $lbl }}: <strong>{{ $val }}</strong></span>
                <span class="x"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg></span>
            </a>
        @endforeach
        <a href="{{ route('panel.hisse-satisi.liste', $aktifKisayol ? ['kisayol' => $aktifKisayol] : []) }}" class="hsl-aktif-tumu-temizle">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            Detaylı filtreleri sıfırla
        </a>
    </div>
@endif

@if ($gruplar->isEmpty())
    <div class="td-bos td-bos-block">Filtreye uygun kayıt bulunamadı.</div>
@else
    <div class="tl-tablo-wrap">
        <table class="tl-tablo">
            <thead>
                <tr>
                    <th>Grup No</th>
                    <th>İlçe</th>
                    <th>Mahalle</th>
                    <th>Ada / Parsel</th>
                    <th>Başvuran</th>
                    <th>Başvuru Sayısı</th>
                    <th>İlk Tarih</th>
                    <th>Aşama</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($gruplar as $g)
                    @php $b = $basvurular->get($g->ilk_basvuru_id); if (! $b) continue; @endphp
                    <tr>
                        <td class="td-mono"><span class="tl-badge">#{{ $g->grup_no }}</span></td>
                        <td>{{ optional($b->tasinmaz->ilce)->ad ?? '—' }}</td>
                        <td>{{ optional($b->tasinmaz->mahalle)->ad ?? '—' }}</td>
                        <td class="td-mono"><span class="tl-badge">{{ $b->tasinmaz->ada }}</span> / <span class="tl-badge">{{ $b->tasinmaz->parsel }}</span></td>
                        <td>{{ $b->ad_soyad }}</td>
                        <td class="td-mono">{{ $g->basvuru_sayisi }}</td>
                        <td>{{ \Carbon\Carbon::parse($g->ilk_tarih)->format('d.m.Y') }}</td>
                        <td><span class="tl-pill {{ $b->durum->pill() }}">{{ $b->durum->etiket() }}</span></td>
                        <td>
                            <a href="{{ route('panel.hisse-satisi.detay', $g->grup_no) }}" class="btn-cancel" style="padding:5px 12px;font-size:0.78rem;">Detay</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="tl-pagination">{{ $gruplar->links() }}</div>
@endif

<script>
(function () {
    const filtreBtn = document.getElementById('hsl-filtre-toggle');
    const filtre = document.getElementById('hsl-filtre');
    filtreBtn?.addEventListener('click', () => {
        filtre.hidden = !filtre.hidden;
        filtreBtn.setAttribute('aria-expanded', filtre.hidden ? 'false' : 'true');
    });

    // İl/İlçe/Mahalle zincir — form submit ile geliyor değişince, ama AJAX daha akıcı
    const ilcelerUrl = @json(url('/panel/ajax/ilceler'));
    const mahallelerUrl = @json(url('/panel/ajax/mahalleler'));
    const il = document.getElementById('hsl-f-il');
    const ilce = document.getElementById('hsl-f-ilce');
    const mah = document.getElementById('hsl-f-mahalle');

    il?.addEventListener('change', async () => {
        ilce.innerHTML = '<option value="">— Tümü —</option>';
        mah.innerHTML = '<option value="">— Önce ilçe —</option>';
        mah.disabled = true;
        if (!il.value) { ilce.disabled = true; return; }
        ilce.disabled = false;
        try {
            const r = await fetch(ilcelerUrl + '/' + il.value);
            const v = await r.json();
            (v || []).forEach(x => ilce.appendChild(new Option(x.ad, x.id)));
        } catch(e) { console.error(e); }
    });
    ilce?.addEventListener('change', async () => {
        mah.innerHTML = '<option value="">— Tümü —</option>';
        if (!ilce.value) { mah.disabled = true; return; }
        mah.disabled = false;
        try {
            const r = await fetch(mahallelerUrl + '/' + ilce.value);
            const v = await r.json();
            (v || []).forEach(x => mah.appendChild(new Option(x.ad, x.id)));
        } catch(e) { console.error(e); }
    });
})();
</script>
@endsection
