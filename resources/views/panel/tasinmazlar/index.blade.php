@extends('layouts.panel')

@section('title', 'Taşınmaz Listesi')

@section('content')
@if (session('basari'))
    <div class="flash-success" role="status">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12 l5 5 L20 7"/></svg>
        {{ session('basari') }}
    </div>
@endif

<section class="page-hero">
    <div class="page-hero-text">
        <h2>Taşınmaz Listesi</h2>
        <small>Toplam {{ $toplamKayit }} kayıt · Bu sayfada {{ $tasinmazlar->count() }} · Filtre sonucu {{ $tasinmazlar->total() }}</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.tasinmazlar.olustur') }}" class="btn-submit">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
            Taşınmaz Ekle
        </a>
    </div>
</section>

@php
    $aktifFiltreler = collect($filtre)->filter(fn ($v) => filled($v))->keys();
    $gelismisAcik = $aktifFiltreler->intersect([
        'ilce_id', 'mahalle_id', 'nitelik', 'satis_durumu', 'mulkiyet', 'bina',
        'kira_var', 'tahsis_var', 'ust_hakki_var', 'meclis_satis_karari_var',
    ])->isNotEmpty();

    $satisSecenekleri = [
        'envanterde' => 'Envanterde',
        'hazirlik' => 'Hazırlık',
        'satista' => 'Satışta',
        'satildi' => 'Satıldı',
        'iptal' => 'İptal',
    ];
    $bayrakSecenekleri = [
        'kira_var' => 'Kira',
        'tahsis_var' => 'Tahsis',
        'ust_hakki_var' => 'Üst Hakkı',
        'meclis_satis_karari_var' => 'Meclis Kararı',
    ];
    $ilAdi = $filtre['il_id'] ? optional($iller->firstWhere('id', (int) $filtre['il_id']))->ad : null;
    $ilceAdi = $filtre['ilce_id'] ? optional($ilceler->firstWhere('id', (int) $filtre['ilce_id']))->ad : null;
    $mahalleAdi = $filtre['mahalle_id'] ? optional($mahalleler->firstWhere('id', (int) $filtre['mahalle_id']))->ad : null;
@endphp

<form method="GET" action="{{ route('panel.tasinmazlar.index') }}" class="tl-filtre" id="tl-filtre-form">
    <div class="tl-filtre-satir tl-filtre-ust">
        <div class="tl-filtre-alan">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" name="q" value="{{ $filtre['q'] }}" placeholder="Ada / parsel / nitelik / kullanım / açıklama / TAKBİS…" autocomplete="off">
        </div>
        <button type="submit" class="btn-submit tl-filtre-btn">Filtrele</button>
        <button type="button" class="btn-cancel tl-filtre-toggle" id="tl-filtre-toggle" aria-expanded="{{ $gelismisAcik ? 'true' : 'false' }}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h18M6 12h12M10 20h4"/></svg>
            Gelişmiş
        </button>
        @if ($aktifFiltreler->isNotEmpty())
            <a href="{{ route('panel.tasinmazlar.index') }}" class="btn-cancel tl-filtre-clear">Tümünü Temizle</a>
        @endif
    </div>

    <div class="tl-filtre-gelismis" id="tl-filtre-gelismis" {{ $gelismisAcik ? '' : 'hidden' }}>
        <div class="tl-filtre-satir">
            <div class="tl-filtre-grup">
                <label>İl</label>
                <select name="il_id" class="form-select" id="tl-fltr-il">
                    <option value="">Tümü</option>
                    @foreach ($iller as $il)
                        <option value="{{ $il->id }}" @selected($filtre['il_id'] == $il->id)>{{ $il->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="tl-filtre-grup">
                <label>İlçe</label>
                <select name="ilce_id" class="form-select" id="tl-fltr-ilce" data-secili="{{ $filtre['ilce_id'] }}">
                    <option value="">Tümü</option>
                    @foreach ($ilceler as $ilce)
                        <option value="{{ $ilce->id }}" @selected($filtre['ilce_id'] == $ilce->id)>{{ $ilce->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="tl-filtre-grup">
                <label>Mahalle</label>
                <select name="mahalle_id" class="form-select" id="tl-fltr-mahalle" data-secili="{{ $filtre['mahalle_id'] }}">
                    <option value="">Tümü</option>
                    @foreach ($mahalleler as $mah)
                        <option value="{{ $mah->id }}" @selected($filtre['mahalle_id'] == $mah->id)>{{ $mah->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="tl-filtre-grup">
                <label>Nitelik</label>
                <input type="text" name="nitelik" value="{{ $filtre['nitelik'] }}" class="form-input" placeholder="Arsa, Tarla…">
            </div>
        </div>

        <div class="tl-filtre-satir">
            <div class="tl-filtre-grup">
                <label>Satış Durumu</label>
                <select name="satis_durumu" class="form-select">
                    <option value="">Tümü</option>
                    @foreach ($satisSecenekleri as $val => $lbl)
                        <option value="{{ $val }}" @selected($filtre['satis_durumu'] === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="tl-filtre-grup">
                <label>Mülkiyet</label>
                <select name="mulkiyet" class="form-select">
                    <option value="">Tümü</option>
                    <option value="TAM" @selected($filtre['mulkiyet'] === 'TAM')>TAM</option>
                    <option value="HISSELI" @selected($filtre['mulkiyet'] === 'HISSELI')>HİSSELİ</option>
                </select>
            </div>
            <div class="tl-filtre-grup">
                <label>Bina Durumu</label>
                <select name="bina" class="form-select">
                    <option value="">Tümü</option>
                    <option value="1" @selected($filtre['bina'] === '1')>Var</option>
                    <option value="0" @selected($filtre['bina'] === '0')>Yok</option>
                </select>
            </div>
            <div class="tl-filtre-grup tl-filtre-bayraklar">
                <label>Durum Bayrakları</label>
                <div class="tl-filtre-chipler">
                    @foreach ($bayrakSecenekleri as $alan => $lbl)
                        @php $val = $filtre[$alan] ?? ''; @endphp
                        <div class="tl-tri-chip" data-alan="{{ $alan }}">
                            <span class="tl-tri-lbl">{{ $lbl }}</span>
                            <div class="tl-tri-btnler">
                                <label class="tl-tri-btn" title="Var olanlar">
                                    <input type="radio" name="{{ $alan }}" value="1" @checked($val === '1')>
                                    <span>Var</span>
                                </label>
                                <label class="tl-tri-btn" title="Olmayanlar">
                                    <input type="radio" name="{{ $alan }}" value="0" @checked($val === '0')>
                                    <span>Yok</span>
                                </label>
                                <label class="tl-tri-btn" title="Filtre yok">
                                    <input type="radio" name="{{ $alan }}" value="" @checked($val === '')>
                                    <span>—</span>
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if ($aktifFiltreler->isNotEmpty())
        <div class="tl-filtre-aktif" role="status" aria-label="Aktif filtreler">
            <span class="tl-filtre-aktif-etkt">Aktif:</span>
            @if ($filtre['q'])
                <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except(['q','page']))) }}" class="tl-fchip">
                    <span>Ara: <strong>{{ $filtre['q'] }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
            @endif
            @if ($ilAdi)
                <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except(['il_id','ilce_id','mahalle_id','page']))) }}" class="tl-fchip">
                    <span>İl: <strong>{{ $ilAdi }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
            @endif
            @if ($ilceAdi)
                <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except(['ilce_id','mahalle_id','page']))) }}" class="tl-fchip">
                    <span>İlçe: <strong>{{ $ilceAdi }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
            @endif
            @if ($mahalleAdi)
                <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except(['mahalle_id','page']))) }}" class="tl-fchip">
                    <span>Mahalle: <strong>{{ $mahalleAdi }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
            @endif
            @if ($filtre['nitelik'])
                <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except(['nitelik','page']))) }}" class="tl-fchip">
                    <span>Nitelik: <strong>{{ $filtre['nitelik'] }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
            @endif
            @if ($filtre['satis_durumu'])
                <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except(['satis_durumu','page']))) }}" class="tl-fchip">
                    <span>Satış: <strong>{{ $satisSecenekleri[$filtre['satis_durumu']] ?? $filtre['satis_durumu'] }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
            @endif
            @if ($filtre['mulkiyet'])
                <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except(['mulkiyet','page']))) }}" class="tl-fchip">
                    <span>Mülkiyet: <strong>{{ $filtre['mulkiyet'] === 'TAM' ? 'TAM' : 'HİSSELİ' }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
            @endif
            @if ($filtre['bina'] === '1' || $filtre['bina'] === '0')
                <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except(['bina','page']))) }}" class="tl-fchip">
                    <span>Bina: <strong>{{ $filtre['bina'] === '1' ? 'Var' : 'Yok' }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
            @endif
            @foreach ($bayrakSecenekleri as $alan => $lbl)
                @php $val = $filtre[$alan] ?? ''; @endphp
                @if ($val === '1' || $val === '0')
                    <a href="{{ route('panel.tasinmazlar.index', array_merge(request()->except([$alan,'page']))) }}" class="tl-fchip">
                        <span>{{ $lbl }}: <strong>{{ $val === '1' ? 'Var' : 'Yok' }}</strong></span> <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </a>
                @endif
            @endforeach
        </div>
    @endif
</form>

<script>
(function () {
    // Gelişmiş filtre toggle
    const toggleBtn = document.getElementById('tl-filtre-toggle');
    const panel = document.getElementById('tl-filtre-gelismis');
    if (toggleBtn && panel) {
        toggleBtn.addEventListener('click', function () {
            const acilacak = panel.hidden;
            panel.hidden = !acilacak;
            toggleBtn.setAttribute('aria-expanded', acilacak ? 'true' : 'false');
        });
    }

    // Cascade: il → ilçe → mahalle (AJAX)
    const ilSel = document.getElementById('tl-fltr-il');
    const ilceSel = document.getElementById('tl-fltr-ilce');
    const mahalleSel = document.getElementById('tl-fltr-mahalle');
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
})();
</script>

{{-- Toplu işlem barı (checkbox seçili olduğunda görünür — işlemler sonra eklenecek) --}}
<div class="tl-toplu-bar" id="tl-toplu-bar" hidden>
    <span><strong id="tl-secili-sayi">0</strong> kayıt seçildi</span>
    <span class="tl-toplu-hint">Toplu işlemler ileride eklenecek.</span>
    <button type="button" class="btn-cancel" id="tl-secim-temizle">Seçimi Temizle</button>
</div>

@if ($tasinmazlar->isEmpty())
    <div class="tl-bos">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 13h6M9 17h4"/></svg>
        <h3>Kayıt bulunamadı</h3>
        <p>{{ $filtre['q'] || $filtre['il_id'] ? 'Filtreye uygun taşınmaz yok. Filtreyi temizleyip tekrar deneyin.' : 'Henüz taşınmaz eklenmemiş. Sağ üstteki "Taşınmaz Ekle" butonu ile başlayabilirsiniz.' }}</p>
    </div>
@else
<div class="tl-tablo-wrap tl-genis">
    <table class="tl-tablo" id="tl-tablo">
        <thead>
            <tr>
                <th class="tl-sticky-sol tl-th-cb">
                    <label class="tl-cb-wrap">
                        <input type="checkbox" id="tl-tumu-cb" title="Hepsini seç">
                    </label>
                </th>
                <th class="tl-sticky-sol tl-sticky-2 tl-th-id">#</th>
                <th class="tl-sticky-sol tl-sticky-3 tl-th-islem">İşlem</th>

                <th>TKGM Parsel</th>
                <th>TAKBİS Zemin No</th>
                <th>İlçe</th>
                <th>Mahalle</th>
                <th>Ada No</th>
                <th>Parsel No</th>
                <th>Tapu Yüzölçüm (m²)</th>
                <th>Taşınmaz Niteliği</th>
                <th>Hisse Durumu</th>
                <th>Hisseye Düşen (m²)</th>
                <th>B.Bölüm No</th>
                <th>Blok No</th>
                <th>Kat No</th>
                <th>İmar Durumu</th>
                <th>Mülkiyet Durumu</th>
                <th>Muhasebe Niteliği</th>
                <th>Dosya/Resim</th>
                <th>Satış Durumu</th>
                <th>Bina Durum</th>
                <th>Tüm Açıklamalar</th>
                <th>Tahsis</th>
                <th>Üsthakkı</th>
                <th>Kira</th>
                <th>Meclis Satış Kararı</th>
                <th>Geometri</th>
                <th>Ek Rapor</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tasinmazlar as $t)
                @php
                    // Kavram-bazlı ayrım: kategori (hasOne) + ekbilgi (hasOne) + yapılar (hasMany)
                    $kategori = $t->kategori;
                    $ekbilgi = $t->ekbilgi;
                    $yapilar = $t->yapilar;
                    $bbn = $yapilar->first(); // ilk BBN — kolonlarda blok/kat/no gösterimi

                    $aktifHisseler = $t->hisseler->where('hisse_durum', 'aktif');
                    $hisseeToplam = $aktifHisseler->sum('hisse_yuzolcum');
                    $satisOzet = $t->satisOzeti();
                    $mahalleTkgm = $t->mahalle->tkgm_id ?? null;
                    $tkgmParsel = ($mahalleTkgm && $t->ada && $t->parsel) ? "{$mahalleTkgm}/{$t->ada}/{$t->parsel}" : '—';
                    $tkgmUrl = $t->tkgmSorguUrl();
                    $mulkiyet = $t->mulkiyet_durumu;
                    $geometriVar = ($t->koordinat && ! empty($t->koordinat->koordinat));
                    $binaVar = (bool) $t->uzeri_bina_var_mi || $yapilar->isNotEmpty();

                    // Bayraklar: arsa (ekbilgi) VEYA herhangi bir yapıda varsa true
                    $bayrak = function (string $alan) use ($ekbilgi, $yapilar) {
                        if ($ekbilgi && (bool) $ekbilgi->{$alan}) return true;
                        return $yapilar->contains(fn ($y) => (bool) $y->{$alan});
                    };
                    $meclisVar = $bayrak('meclis_satis_karari_var');
                    $tahsisVar = $bayrak('tahsis_var');
                    $ustHakkiVar = $bayrak('ust_hakki_var');
                    $kiraVar = $bayrak('kira_var');

                    // Muhasebe: arsa kategori, yoksa ilk yapı
                    $muhasebeKayit = $kategori?->muhasebeKayit ?? optional($yapilar->first())->muhasebeKayit;
                    $muhasebeFarkli = $yapilar->pluck('muhasebe_kayit_id')->filter()
                        ->push($kategori?->muhasebe_kayit_id)->filter()->unique()->count() > 1;

                    // Açıklama: ekbilgi + yapı açıklamaları + imar notu
                    $aciklamalar = collect([$ekbilgi?->aciklama])
                        ->merge($yapilar->pluck('aciklama'))
                        ->filter()->implode(' · ');
                    $aciklamaBirlesik = trim($aciklamalar.' '.($t->imar->imar_notu ?? ''));
                @endphp
                <tr data-tasinmaz-id="{{ $t->id }}">
                    <td class="tl-sticky-sol tl-td-cb">
                        <label class="tl-cb-wrap">
                            <input type="checkbox" class="tl-satir-cb" value="{{ $t->id }}">
                        </label>
                    </td>
                    <td class="tl-sticky-sol tl-sticky-2 tl-td-id">
                        <span class="tl-td-num">#{{ $t->id }}</span>
                    </td>
                    <td class="tl-sticky-sol tl-sticky-3 tl-td-islem">
                        <div class="tl-dd">
                            <button type="button" class="tl-dd-btn" data-dd-toggle>
                                İşlem
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <div class="tl-dd-menu">
                                <a href="{{ $t->rota('duzenle') }}" class="tl-dd-item">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5 a2.121 2.121 0 0 1 3 3 L7 19 l-4 1 1-4z"/></svg>
                                    Düzenle
                                </a>
                                <form method="POST" action="{{ $t->rota('sil') }}"
                                      onsubmit="return confirm('#{{ $t->id }} numaralı taşınmazı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="tl-dd-item tl-dd-item-danger">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4 a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14 a2 2 0 0 1-2 2H8 a2 2 0 0 1-2-2L5 6"/></svg>
                                        Sil
                                    </button>
                                </form>
                            </div>
                        </div>
                    </td>

                    <td class="tl-td-mono">
                        @if ($tkgmUrl)
                            <a href="{{ $tkgmUrl }}" target="_blank" rel="noopener noreferrer"
                               class="tl-tkgm-link" title="TKGM Parsel Sorgu — yeni sekmede aç">
                                {{ $tkgmParsel }}
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>
                            </a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="tl-td-mono">{{ $t->tapu->takbis_zemin_no ?? '—' }}</td>
                    <td>{{ $t->ilce->ad ?? '—' }}</td>
                    <td>{{ $t->mahalle->ad ?? '—' }}</td>
                    <td class="tl-td-mono"><span class="tl-badge">{{ $t->ada ?? '—' }}</span></td>
                    <td class="tl-td-mono"><span class="tl-badge">{{ $t->parsel ?? '—' }}</span></td>
                    <td class="tl-td-mono">{{ $t->alan !== null ? number_format((float) $t->alan, 2, ',', '.') : '—' }}</td>
                    <td>{{ $t->nitelik ?? '—' }}</td>
                    <td>
                        @if ($mulkiyet === 'TAM')
                            <span class="tl-pill tl-pill-ok" title="Tek aktif hisse ve pay = payda">TAM</span>
                        @elseif ($mulkiyet === 'HİSSELİ')
                            <span class="tl-pill tl-pill-danger" title="{{ $aktifHisseler->count() }} aktif hisse">HİSSELİ</span>
                        @else
                            <span class="tl-pill tl-pill-mute">—</span>
                        @endif
                    </td>
                    <td class="tl-td-mono">{{ $hisseeToplam > 0 ? number_format($hisseeToplam, 2, ',', '.') : '—' }}</td>
                    <td class="tl-td-mono">
                        @if ($yapilar->count() > 1)
                            {{ $bbn->bagimsiz_bolum_no }} <span class="tl-pill tl-pill-info">+{{ $yapilar->count() - 1 }}</span>
                        @else
                            {{ $bbn->bagimsiz_bolum_no ?? '—' }}
                        @endif
                    </td>
                    <td class="tl-td-mono">{{ $bbn->blok_no ?? '—' }}</td>
                    <td class="tl-td-mono">{{ $bbn->kat_no ?? '—' }}</td>
                    <td>{{ $t->imar->imarDurumu->ad ?? '—' }}</td>
                    <td>
                        <span class="tl-pill tl-pill-ok">Kayıtlı</span>
                    </td>
                    <td>
                        @if ($muhasebeKayit)
                            <span title="{{ $muhasebeKayit->ad }}">{{ Str::limit($muhasebeKayit->ad, 40) }}</span>
                            @if ($muhasebeFarkli)
                                <span class="tl-pill tl-pill-mute" title="Arsa ve BBN'lerde farklı muhasebe kayıtları">karışık</span>
                            @endif
                        @else — @endif
                    </td>
                    <td class="tl-td-center">
                        @if ($t->resimler_count > 0)
                            <span class="tl-pill tl-pill-info">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                                {{ $t->resimler_count }}
                            </span>
                        @else
                            <span class="tl-pill tl-pill-mute">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="tl-pill {{ $satisOzet->pill() }}">{{ $satisOzet->etiket() }}</span>
                        @if ($yapilar->count() > 0)
                            @php $satistaSay = $yapilar->filter(fn ($y) => ($y->satis_durumu instanceof \App\Enums\SatisDurumu ? $y->satis_durumu->value : $y->satis_durumu) === 'satista')->count(); @endphp
                            <span class="tl-pill tl-pill-mute" title="Satıştaki BBN / toplam BBN">{{ $satistaSay }}/{{ $yapilar->count() }}</span>
                        @endif
                    </td>
                    <td>{!! $binaVar ? '<span class="tl-pill tl-pill-ok">Var</span>' : '<span class="tl-pill tl-pill-mute">Yok</span>' !!}</td>
                    <td class="tl-td-metin" title="{{ $aciklamaBirlesik }}">{{ Str::limit($aciklamaBirlesik, 60) ?: '—' }}</td>
                    <td>{!! $tahsisVar ? '<span class="tl-pill tl-pill-info">Var</span>' : '<span class="tl-pill tl-pill-mute">Yok</span>' !!}</td>
                    <td>{!! $ustHakkiVar ? '<span class="tl-pill tl-pill-info">Var</span>' : '<span class="tl-pill tl-pill-mute">Yok</span>' !!}</td>
                    <td>{!! $kiraVar ? '<span class="tl-pill tl-pill-info">Var</span>' : '<span class="tl-pill tl-pill-mute">Yok</span>' !!}</td>
                    <td>{!! $meclisVar ? '<span class="tl-pill tl-pill-warn">Var</span>' : '<span class="tl-pill tl-pill-mute">Yok</span>' !!}</td>
                    <td>{!! $geometriVar ? '<span class="tl-pill tl-pill-ok">Var</span>' : '<span class="tl-pill tl-pill-mute">Yok</span>' !!}</td>
                    <td class="tl-td-metin"><span class="tl-pill tl-pill-mute">—</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="tl-pagination">
    {{ $tasinmazlar->links() }}
</div>
@endif

<script>
/* Toplu seçim + satır işlem dropdown */
(function () {
    const tumu = document.getElementById('tl-tumu-cb');
    const satirlar = document.querySelectorAll('.tl-satir-cb');
    const bar = document.getElementById('tl-toplu-bar');
    const sayi = document.getElementById('tl-secili-sayi');
    const temizle = document.getElementById('tl-secim-temizle');

    function guncelle() {
        const seciliSayi = Array.from(satirlar).filter(c => c.checked).length;
        if (sayi) sayi.textContent = seciliSayi;
        if (bar) bar.hidden = seciliSayi === 0;
        if (tumu) {
            tumu.checked = seciliSayi > 0 && seciliSayi === satirlar.length;
            tumu.indeterminate = seciliSayi > 0 && seciliSayi < satirlar.length;
        }
    }

    if (tumu) {
        tumu.addEventListener('change', () => {
            satirlar.forEach(c => c.checked = tumu.checked);
            guncelle();
        });
    }
    satirlar.forEach(c => c.addEventListener('change', guncelle));

    if (temizle) {
        temizle.addEventListener('click', () => {
            satirlar.forEach(c => c.checked = false);
            if (tumu) { tumu.checked = false; tumu.indeterminate = false; }
            guncelle();
        });
    }

    // Satır işlem dropdown toggle
    document.querySelectorAll('[data-dd-toggle]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const dd = btn.closest('.tl-dd');
            document.querySelectorAll('.tl-dd.is-acik').forEach(d => { if (d !== dd) d.classList.remove('is-acik'); });
            dd.classList.toggle('is-acik');
        });
    });
    document.addEventListener('click', () => {
        document.querySelectorAll('.tl-dd.is-acik').forEach(d => d.classList.remove('is-acik'));
    });
})();
</script>
@endsection
