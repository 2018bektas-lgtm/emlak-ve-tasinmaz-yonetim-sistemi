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

<form method="GET" action="{{ route('panel.tasinmazlar.index') }}" class="tl-filtre">
    <div class="tl-filtre-alan">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" name="q" value="{{ $filtre['q'] }}" placeholder="Ada / parsel / nitelik / kullanım ara..." autocomplete="off">
    </div>
    <select name="il_id" class="form-select tl-filtre-select">
        <option value="">Tüm İller</option>
        @foreach ($iller as $il)
            <option value="{{ $il->id }}" @selected($filtre['il_id'] == $il->id)>{{ $il->ad }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn-submit tl-filtre-btn">Filtrele</button>
    @if ($filtre['q'] || $filtre['il_id'])
        <a href="{{ route('panel.tasinmazlar.index') }}" class="btn-cancel tl-filtre-clear">Temizle</a>
    @endif
</form>

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
                    $bbn = $t->bagimsizBolumler->first();
                    $aktifHisseler = $t->hisseler->where('hisse_durum', 'aktif');
                    $hisseeToplam = $aktifHisseler->sum('hisse_yuzolcum');
                    $satisVar = $t->hisseler->contains(fn ($h) => $h->islem_tipi === 'satis');
                    $mahalleTkgm = $t->mahalle->tkgm_id ?? null;
                    $tkgmParsel = ($mahalleTkgm && $t->ada && $t->parsel) ? "{$mahalleTkgm}/{$t->ada}/{$t->parsel}" : '—';
                    $geometriVar = ($t->koordinat && ! empty($t->koordinat->koordinat));
                    $binaVar = (bool) $t->uzeri_bina_var_mi;
                    $meclisVar = (bool) $t->meclis_satis_karari_var;
                    $aciklamaBirlesik = trim(($t->aciklama ?: '').' '.($t->imar->imar_notu ?? ''));
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
                                <a href="{{ route('panel.tasinmazlar.duzenle', $t->id) }}" class="tl-dd-item">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5 a2.121 2.121 0 0 1 3 3 L7 19 l-4 1 1-4z"/></svg>
                                    Düzenle
                                </a>
                                <form method="POST" action="{{ route('panel.tasinmazlar.sil', $t->id) }}"
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

                    <td class="tl-td-mono">{{ $tkgmParsel }}</td>
                    <td class="tl-td-mono">{{ $t->tapu->takbis_zemin_no ?? '—' }}</td>
                    <td>{{ $t->ilce->ad ?? '—' }}</td>
                    <td>{{ $t->mahalle->ad ?? '—' }}</td>
                    <td class="tl-td-mono"><span class="tl-badge">{{ $t->ada ?? '—' }}</span></td>
                    <td class="tl-td-mono"><span class="tl-badge">{{ $t->parsel ?? '—' }}</span></td>
                    <td class="tl-td-mono">{{ $t->alan !== null ? number_format((float) $t->alan, 2, ',', '.') : '—' }}</td>
                    <td>{{ $t->nitelik ?? '—' }}</td>
                    <td>
                        @if ($aktifHisseler->count())
                            <span class="tl-pill tl-pill-ok">{{ $aktifHisseler->count() }} Aktif</span>
                        @elseif ($t->hisseler->count())
                            <span class="tl-pill">{{ $t->hisseler->count() }} Pasif</span>
                        @else
                            <span class="tl-pill tl-pill-mute">Yok</span>
                        @endif
                    </td>
                    <td class="tl-td-mono">{{ $hisseeToplam > 0 ? number_format($hisseeToplam, 2, ',', '.') : '—' }}</td>
                    <td class="tl-td-mono">{{ $bbn->bagimsiz_bolum_no ?? '—' }}</td>
                    <td class="tl-td-mono">{{ $bbn->blok_no ?? '—' }}</td>
                    <td class="tl-td-mono">{{ $bbn->kat_no ?? '—' }}</td>
                    <td>{{ $t->imar->imarDurumu->ad ?? '—' }}</td>
                    <td>
                        <span class="tl-pill tl-pill-ok">Kayıtlı</span>
                    </td>
                    <td>
                        @if ($t->muhasebeKayit)
                            <span title="{{ $t->muhasebeKayit->ad }}">{{ Str::limit($t->muhasebeKayit->ad, 40) }}</span>
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
                        @if ($satisVar)
                            <span class="tl-pill tl-pill-danger">Satışta</span>
                        @else
                            <span class="tl-pill tl-pill-mute">—</span>
                        @endif
                    </td>
                    <td>{!! $binaVar ? '<span class="tl-pill tl-pill-ok">Var</span>' : '<span class="tl-pill tl-pill-mute">Yok</span>' !!}</td>
                    <td class="tl-td-metin" title="{{ $aciklamaBirlesik }}">{{ Str::limit($aciklamaBirlesik, 60) ?: '—' }}</td>
                    <td><span class="tl-pill tl-pill-mute">Yok</span></td>
                    <td><span class="tl-pill tl-pill-mute">Yok</span></td>
                    <td><span class="tl-pill tl-pill-mute">Yok</span></td>
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
