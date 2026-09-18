@extends('layouts.panel')

@section('title', 'Yeni Hisse Satış Başvurusu')

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <h2>Yeni Başvuru</h2>
        <small>Başvuru açılacak taşınmazı arayın, sonuçtan "Yeni Başvuru" butonuna basın.</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.hisse-satisi.liste') }}" class="btn-cancel">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
            Başvuru Listesi
        </a>
    </div>
</section>

<form method="GET" action="{{ route('panel.hisse-satisi.index') }}" class="form-section hs-ara-form">
    <div class="form-section-head">
        <span class="form-section-num">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
        </span>
        <div class="form-section-head-text">
            <h3>Taşınmaz Ara</h3>
            <small>İl / ilçe / mahalle / ada / parsel — istediğiniz alanları doldurup arayın.</small>
        </div>
    </div>

    <div class="form-row form-row-3">
        <div class="form-field">
            <label for="hs-ara-il">İl</label>
            <select name="il_id" class="form-select" id="hs-ara-il">
                <option value="">Tümü</option>
                @foreach ($iller as $il)
                    <option value="{{ $il->id }}" @selected($filtre['il_id'] == $il->id)>{{ $il->ad }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-field">
            <label for="hs-ara-ilce">İlçe</label>
            <select name="ilce_id" class="form-select" id="hs-ara-ilce" data-secili="{{ $filtre['ilce_id'] }}">
                <option value="">Tümü</option>
                @foreach ($ilceler as $ilce)
                    <option value="{{ $ilce->id }}" @selected($filtre['ilce_id'] == $ilce->id)>{{ $ilce->ad }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-field">
            <label for="hs-ara-mahalle">Mahalle</label>
            <select name="mahalle_id" class="form-select" id="hs-ara-mahalle" data-secili="{{ $filtre['mahalle_id'] }}">
                <option value="">Tümü</option>
                @foreach ($mahalleler as $mah)
                    <option value="{{ $mah->id }}" @selected($filtre['mahalle_id'] == $mah->id)>{{ $mah->ad }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-row form-row-3">
        <div class="form-field">
            <label for="hs-ara-ada">Ada No</label>
            <input id="hs-ara-ada" type="text" name="ada" value="{{ $filtre['ada'] }}" class="form-input" placeholder="Örn. 1234">
        </div>
        <div class="form-field">
            <label for="hs-ara-parsel">Parsel No</label>
            <input id="hs-ara-parsel" type="text" name="parsel" value="{{ $filtre['parsel'] }}" class="form-input" placeholder="Örn. 45">
        </div>
        <div class="form-field hs-ara-aksiyon">
            @if ($aradiMi)
                <a href="{{ route('panel.hisse-satisi.index') }}" class="btn-cancel hs-ara-temizle">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    Temizle
                </a>
            @endif
            <button type="submit" class="btn-submit hs-ara-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
                Ara
            </button>
        </div>
    </div>
</form>

@if ($sonuclar !== null)
    <div class="td-section">
        <div class="td-section-head">
            <div>
                <h3>Arama Sonucu</h3>
                <small>Toplam {{ $sonuclar->total() }} taşınmaz</small>
            </div>
        </div>
        @if ($sonuclar->isEmpty())
            <p class="td-bos">Sonuç yok. (Sadece kurumun aktif hissesi olan ve <strong>tam mülkiyet olmayan</strong> — yani başka hissedarı bulunan — taşınmazlar listelenir.)</p>
        @else
            <div class="tl-tablo-wrap">
                <table class="tl-tablo td-tablo">
                    <thead>
                        <tr>
                            <th>İlçe</th>
                            <th>Mahalle</th>
                            <th>Ada / Parsel</th>
                            <th>Alan (m²)</th>
                            <th>Kurum Hissesi (m²)</th>
                            <th>Diğer Hisse (m²)</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sonuclar as $t)
                            @php
                                $alan = (float) ($t->alan ?? 0);
                                $bizim = (float) ($t->aktif_hisse_toplami ?? 0);
                                $diger = max(0, $alan - $bizim);
                            @endphp
                            <tr>
                                <td>{{ optional($t->ilce)->ad ?? '—' }}</td>
                                <td>{{ optional($t->mahalle)->ad ?? '—' }}</td>
                                <td class="td-mono"><span class="tl-badge">{{ $t->ada }}</span> / <span class="tl-badge">{{ $t->parsel }}</span></td>
                                <td class="td-mono">{{ number_format($alan, 2, ',', '.') }}</td>
                                <td class="td-mono"><strong>{{ number_format($bizim, 2, ',', '.') }}</strong></td>
                                <td class="td-mono" style="color:#0e7c39;font-weight:700;">{{ number_format($diger, 2, ',', '.') }}</td>
                                <td>
                                    <a href="{{ route('panel.hisse-satisi.olustur', $t->id) }}" class="btn-submit" style="padding:5px 12px;font-size:0.78rem;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                                        Yeni Başvuru
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="tl-pagination">{{ $sonuclar->links() }}</div>
        @endif
    </div>
@else
    <div class="hs-arama-bos">
        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
        <p>Bir arama yapın — sonuçlar burada listelenecek.</p>
    </div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Cascade: il → ilçe → mahalle (AJAX) — ozel-select bunları otomatik zaten
    // özel dropdown'a çevirmiş olur (.hs-ara-form kapsamında).
    const ilSel = document.getElementById('hs-ara-il');
    const ilceSel = document.getElementById('hs-ara-ilce');
    const mahalleSel = document.getElementById('hs-ara-mahalle');
    if (!ilSel || !ilceSel || !mahalleSel) return;

    const ilceEndpoint = @json(url('/panel/ajax/ilceler'));
    const mahalleEndpoint = @json(url('/panel/ajax/mahalleler'));

    function optionsSet(sel, kayitlar, secili) {
        while (sel.options.length > 1) sel.remove(1);
        (kayitlar || []).forEach(function (k) {
            const o = document.createElement('option');
            o.value = k.id;
            o.textContent = k.ad;
            if (String(secili) === String(k.id)) o.selected = true;
            sel.appendChild(o);
        });
    }

    async function fetchJSON(url) {
        try {
            const r = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!r.ok) return [];
            return await r.json();
        } catch (e) { return []; }
    }

    ilSel.addEventListener('change', async function () {
        optionsSet(ilceSel, [], '');
        optionsSet(mahalleSel, [], '');
        if (!ilSel.value) return;
        const data = await fetchJSON(ilceEndpoint + '/' + ilSel.value);
        optionsSet(ilceSel, data, '');
    });

    ilceSel.addEventListener('change', async function () {
        optionsSet(mahalleSel, [], '');
        if (!ilceSel.value) return;
        const data = await fetchJSON(mahalleEndpoint + '/' + ilceSel.value);
        optionsSet(mahalleSel, data, '');
    });
});
</script>
@endsection
