@extends('layouts.panel')

@section('title', 'Ecrimisil — İşgal Kayıtları')

@push('head')
<style>
    .ec-toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; margin-bottom: 12px; }
    .ec-ara { flex: 1; min-width: 260px; position: relative; }
    .ec-ara input { width: 100%; padding: 9px 12px 9px 36px; border: 1px solid #d1d5db; border-radius: 8px; font-size: .88rem; }
    .ec-ara input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
    .ec-ara-ikon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; }
    .ec-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 14px; font-size: .82rem; font-weight: 600; background: #2563eb; color: #fff; border: none; border-radius: 7px; cursor: pointer; text-decoration: none; }
    .ec-btn svg { width: 14px; height: 14px; }
    .ec-btn:hover { background: #1d4ed8; }
    .ec-btn.is-ghost { background: #fff; color: #374151; border: 1px solid #d1d5db; }
    .ec-btn.is-ghost:hover { background: #f3f4f6; }
    .ec-btn.is-tehlike { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    .ec-filtre { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; margin-bottom: 12px; }
    .ec-filtre[hidden] { display: none; }
    .ec-filtre-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
    @media (max-width: 1100px) { .ec-filtre-grid { grid-template-columns: repeat(2, 1fr); } }
    .ec-alan label { display: block; font-size: .7rem; text-transform: uppercase; color: #6b7280; letter-spacing: .04em; font-weight: 700; margin-bottom: 4px; }
    .ec-alan input, .ec-alan select { width: 100%; padding: 7px 10px; font-size: .84rem; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; }
</style>
@endpush

@section('content')
@if (session('basari'))
    <div class="flash-success">{{ session('basari') }}</div>
@endif

<section class="page-hero">
    <div class="page-hero-text">
        <h2>Ecrimisil — İşgal Kayıtları</h2>
        <small>Toplam {{ $kayitlar->total() }} kayıt</small>
    </div>
    <div class="page-hero-actions">
        @if (auth()->user()->izinVarMi('ecrimisil.olustur'))
            <a href="{{ route('panel.ecrimisil.olustur') }}" class="btn-submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Yeni Kayıt
            </a>
        @endif
    </div>
</section>

<form method="GET" action="{{ route('panel.ecrimisil.index') }}" id="ec-form">
    <div class="ec-toolbar">
        <div class="ec-ara">
            <svg class="ec-ara-ikon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="search" name="q" value="{{ $filtre['q'] ?? '' }}" placeholder="Ad soyad/ünvan, TC/vergi no, ada veya parsel ile ara...">
        </div>
        <button type="submit" class="ec-btn">Ara</button>
        <button type="button" class="ec-btn is-ghost" id="ec-filtre-toggle">Detaylı Filtre</button>
        @if (collect($filtre)->filter(fn ($v) => filled($v))->isNotEmpty())
            <a href="{{ route('panel.ecrimisil.index') }}" class="ec-btn is-tehlike">Temizle</a>
        @endif
    </div>

    <div class="ec-filtre" id="ec-filtre" {{ collect($filtre)->except('q')->filter(fn ($v) => filled($v))->isNotEmpty() ? '' : 'hidden' }}>
        <div class="ec-filtre-grid">
            <div class="ec-alan"><label>İl</label>
                <select name="il_id" id="ec-f-il">
                    <option value="">— Tümü —</option>
                    @foreach ($iller as $il)<option value="{{ $il->id }}" @selected(($filtre['ilId'] ?? '') == $il->id)>{{ $il->ad }}</option>@endforeach
                </select>
            </div>
            <div class="ec-alan"><label>İlçe</label>
                <select name="ilce_id" id="ec-f-ilce" @disabled(! ($filtre['ilId'] ?? null))>
                    <option value="">— Tümü —</option>
                    @foreach ($ilceler as $x)<option value="{{ $x->id }}" @selected(($filtre['ilceId'] ?? '') == $x->id)>{{ $x->ad }}</option>@endforeach
                </select>
            </div>
            <div class="ec-alan"><label>Mahalle</label>
                <select name="mahalle_id" id="ec-f-mahalle" @disabled(! ($filtre['ilceId'] ?? null))>
                    <option value="">— Tümü —</option>
                    @foreach ($mahalleler as $x)<option value="{{ $x->id }}" @selected(($filtre['mahalleId'] ?? '') == $x->id)>{{ $x->ad }}</option>@endforeach
                </select>
            </div>
            <div class="ec-alan"><label>Sorumlu Kullanıcı</label>
                <select name="kullanici_id">
                    <option value="">— Tümü —</option>
                    @foreach ($kullanicilar as $k)<option value="{{ $k->id }}" @selected(($filtre['kullaniciId'] ?? '') == $k->id)>{{ $k->ad }} {{ $k->soyad }}</option>@endforeach
                </select>
            </div>
            <div class="ec-alan"><label>Ada</label><input type="text" name="ada" value="{{ $filtre['ada'] ?? '' }}"></div>
            <div class="ec-alan"><label>Parsel</label><input type="text" name="parsel" value="{{ $filtre['parsel'] ?? '' }}"></div>
        </div>
        <div style="display:flex;gap:8px;margin-top:14px;">
            <button type="submit" class="ec-btn">Uygula</button>
            <a href="{{ route('panel.ecrimisil.index') }}" class="ec-btn is-ghost">Temizle</a>
        </div>
    </div>
</form>

@if ($kayitlar->isEmpty())
    <div class="td-bos td-bos-block">Kayıt bulunamadı.</div>
@else
    <div class="tl-tablo-wrap">
        <table class="tl-tablo">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Ad Soyad / Ünvan</th>
                    <th>TC / Vergi No</th>
                    <th>Taşınmaz</th>
                    <th>İlçe / Mahalle</th>
                    <th>Sorumlu Kullanıcı</th>
                    <th>Tutanak</th>
                    <th>Kayıt Tarihi</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kayitlar as $k)
                    <tr>
                        <td class="td-mono">#{{ $k->id }}</td>
                        <td><strong>{{ $k->ad_soyad_unvan }}</strong></td>
                        <td class="td-mono">{{ $k->tc_vergi_no ?? '—' }}</td>
                        <td>
                            @if ($k->tasinmaz)
                                <span class="tl-badge">{{ $k->tasinmaz->ada }}</span> / <span class="tl-badge">{{ $k->tasinmaz->parsel }}</span>
                            @else — @endif
                        </td>
                        <td>{{ optional(optional($k->tasinmaz)->ilce)->ad ?? '—' }}<div style="font-size:.72rem;color:#6b7280;">{{ optional(optional($k->tasinmaz)->mahalle)->ad ?? '' }}</div></td>
                        <td>{{ $k->kullanici ? $k->kullanici->ad.' '.$k->kullanici->soyad : '—' }}</td>
                        <td class="td-mono">{{ $k->tutanaklar_count }}</td>
                        <td>{{ $k->created_at?->format('d.m.Y') }}</td>
                        <td>
                            <a href="{{ route('panel.ecrimisil.detay', $k->id) }}" class="btn-cancel" style="padding:5px 12px;font-size:.78rem;">Detay</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="tl-pagination">{{ $kayitlar->links() }}</div>
@endif

<script>
(function () {
    document.getElementById('ec-filtre-toggle')?.addEventListener('click', () => {
        const p = document.getElementById('ec-filtre');
        p.hidden = !p.hidden;
    });

    const ilcelerUrl = @json(url('/panel/ajax/ilceler'));
    const mahallelerUrl = @json(url('/panel/ajax/mahalleler'));
    const il = document.getElementById('ec-f-il');
    const ilce = document.getElementById('ec-f-ilce');
    const mah = document.getElementById('ec-f-mahalle');

    il?.addEventListener('change', async () => {
        ilce.innerHTML = '<option value="">— Tümü —</option>';
        mah.innerHTML = '<option value="">— Önce ilçe —</option>';
        mah.disabled = true;
        if (!il.value) { ilce.disabled = true; return; }
        ilce.disabled = false;
        const r = await fetch(ilcelerUrl + '/' + il.value);
        (await r.json() || []).forEach(x => ilce.appendChild(new Option(x.ad, x.id)));
    });
    ilce?.addEventListener('change', async () => {
        mah.innerHTML = '<option value="">— Tümü —</option>';
        if (!ilce.value) { mah.disabled = true; return; }
        mah.disabled = false;
        const r = await fetch(mahallelerUrl + '/' + ilce.value);
        (await r.json() || []).forEach(x => mah.appendChild(new Option(x.ad, x.id)));
    });
})();
</script>
@endsection
