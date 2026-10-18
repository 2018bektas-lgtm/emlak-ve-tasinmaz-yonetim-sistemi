@extends('layouts.panel')

@section('title', $kayit->exists ? 'Değerleme Düzenle' : 'Yeni Değerleme')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=3">
@endpush

@php
    $tl = fn ($n) => number_format((float) $n, 2, ',', '.');
    $tip = old('tip', $kayit->tip);
    $imar = $tasinmaz->imar;
    $yapiVerisi = $tasinmaz->yapilar->map(fn ($y) => [
        'id' => $y->id,
        'etiket' => collect([$y->blok_no ? $y->blok_no.' Blok' : null, $y->bagimsiz_bolum_no ? 'BB '.$y->bagimsiz_bolum_no : null, $y->kat_no !== null ? 'Kat '.$y->kat_no : null, $y->nitelik])->filter()->implode(' · ') ?: 'Yapı #'.$y->id,
        'bb_no' => $y->bagimsiz_bolum_no, 'kat' => $y->kat_no, 'bb_nitelik' => $y->nitelik,
        'oda_sayisi' => $y->oda_sayisi, 'brut_alan' => $y->brut_alan,
    ])->values();
    $tekBolum = $tasinmaz->kayit_tipi === \App\Enums\KayitTipi::KatMulkiyetli;
    $parselAlani = $tasinmaz->alan !== null ? (float) $tasinmaz->alan : null;
@endphp

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Değerleme · {{ $kayit->exists ? 'Düzenle' : 'Yeni' }}</p>
        <h2>{{ $tasinmaz->mahalle?->ad }} {{ $tasinmaz->ada }}/{{ $tasinmaz->parsel }}</h2>
        <small>{{ $tasinmaz->il?->ad }} / {{ $tasinmaz->ilce?->ad }} · Taşınmaz #{{ $tasinmaz->id }} · {{ $tasinmaz->kayit_tipi?->etiket() }}</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ $kayit->exists ? route('panel.degerleme.detay', $kayit->id) : route('panel.degerleme.yeni') }}" class="btn-cancel">← Geri</a>
    </div>
</section>

@if ($errors->any())
    <div class="flash-error" role="alert"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" enctype="multipart/form-data" class="form-shell" id="dg-form"
      action="{{ $kayit->exists ? route('panel.degerleme.guncelle', $kayit->id) : route('panel.degerleme.kaydet', $tasinmaz->id) }}"
      data-yapilar="{{ json_encode($yapiVerisi) }}" data-parsel-alani="{{ $parselAlani }}">
    @csrf
    @if ($kayit->exists) @method('PUT') @endif

    {{-- Taşınmaz özeti (salt okunur) --}}
    <section class="form-section dg-tasinmaz-ozet">
        <div class="form-section-head">
            <span class="form-section-num">1</span>
            <div class="form-section-head-text">
                <h3>Taşınmaz</h3>
                <small>Bilgiler envanterden gelir; değiştirmek için taşınmaz kaydını düzenleyin.</small>
            </div>
            @can('tasinmaz.goruntule')
                <a href="{{ $tasinmaz->rota('detay') }}" target="_blank" class="btn-cancel hs-mini-btn dg-sag">Taşınmaz kaydı ↗</a>
            @endcan
        </div>
        <dl class="dg-bilgi">
            <div><dt>Parsel Alanı</dt><dd class="td-mono">{{ $parselAlani !== null ? $tl($parselAlani).' m²' : '—' }}</dd></div>
            <div><dt>Kurum Hissesi</dt><dd class="td-mono">{{ $kurumHisse !== null ? $tl($kurumHisse).' m²' : '—' }}</dd></div>
            <div><dt>Nitelik</dt><dd>{{ $tasinmaz->nitelik ?: '—' }}</dd></div>
            <div><dt>İmar Durumu</dt><dd>{{ $imar?->imarDurumu?->ad ?: '—' }}</dd></div>
            <div><dt>Emsal</dt><dd class="td-mono">{{ $imar?->emsal !== null ? $tl($imar->emsal) : '—' }}</dd></div>
            <div><dt>Yençok / Yenaz</dt><dd class="td-mono">{{ $imar?->yenaz_yencok ?: '—' }}</dd></div>
        </dl>
    </section>

    {{-- Tür --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">2</span>
            <div class="form-section-head-text">
                <h3>Değerleme Türü</h3>
                <small>
                    @if (count($tipler) === 1)
                        Bu taşınmaz {{ $tasinmaz->kayit_tipi?->etiket() }} olduğu için yalnız {{ mb_strtolower(\App\Models\TasinmazDegerleme::TIPLER[$tipler[0]]) }} açılabilir.
                    @else
                        Parselin (kurum hissesinin) tamamı ya da binadaki tek bir bağımsız bölüm değerlenebilir.
                    @endif
                </small>
            </div>
        </div>
        <div class="dg-tur-secim" role="radiogroup" aria-label="Değerleme türü">
            @foreach ($tipler as $t)
                <label class="dg-tur-kart is-{{ $t }}">
                    <input type="radio" name="tip" value="{{ $t }}" @checked($tip === $t)>
                    <span>
                        <strong>{{ \App\Models\TasinmazDegerleme::TIPLER[$t] }}</strong>
                        <small>{{ $t === 'arsa' ? 'Parsel / kurum hissesi m² × birim fiyat' : 'Bağımsız bölümün toplam değeri' }}</small>
                    </span>
                </label>
            @endforeach
        </div>
    </section>

    {{-- Bağımsız bölüm --}}
    <section class="form-section" data-tip-alan="bb">
        <div class="form-section-head">
            <span class="form-section-num">3</span>
            <div class="form-section-head-text">
                <h3>Bağımsız Bölüm</h3>
                <small>{{ $yapiVerisi->isNotEmpty() ? 'Yapı kaydından seçin; bilgiler rapora kopyalanır ve gerekirse düzeltilebilir.' : 'Bu taşınmazda yapı kaydı yok; bölüm bilgilerini elle girin.' }}</small>
            </div>
        </div>
        @if ($yapiVerisi->isNotEmpty() && ! $tekBolum)
            <div class="form-row">
                <div class="form-field">
                    <label for="yapi_id">Bölüm</label>
                    <select id="yapi_id" name="yapi_id" class="form-select">
                        <option value="">— Elle gir —</option>
                        @foreach ($yapiVerisi as $y)
                            <option value="{{ $y['id'] }}" @selected((string) old('yapi_id', $kayit->yapi_id) === (string) $y['id'])>{{ $y['etiket'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @elseif ($tekBolum && $yapiVerisi->isNotEmpty())
            <input type="hidden" name="yapi_id" value="{{ old('yapi_id', $kayit->yapi_id ?? $yapiVerisi->first()['id']) }}">
        @endif
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="bb_no">BB No <span class="required">*</span></label>
                <input id="bb_no" name="bb_no" class="form-input td-mono" value="{{ old('bb_no', $kayit->bb_no) }}" maxlength="30">
                @error('bb_no')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="kat">Kat</label>
                <input id="kat" name="kat" class="form-input" value="{{ old('kat', $kayit->kat) }}" maxlength="30" placeholder="Örn. 3, Zemin, Bodrum">
            </div>
            <div class="form-field">
                <label for="oda_sayisi">Oda Sayısı</label>
                <input id="oda_sayisi" name="oda_sayisi" class="form-input" value="{{ old('oda_sayisi', $kayit->oda_sayisi) }}" maxlength="20" placeholder="Örn. 3+1">
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field">
                <label for="bb_nitelik">Nitelik</label>
                <input id="bb_nitelik" name="bb_nitelik" class="form-input" value="{{ old('bb_nitelik', $kayit->bb_nitelik) }}" maxlength="100" placeholder="Örn. Mesken, Dükkan">
            </div>
            <div class="form-field">
                <label for="brut_alan">Brüt Alan (m²)</label>
                <input id="brut_alan" name="brut_alan" type="number" step="0.01" min="0" class="form-input td-mono" value="{{ old('brut_alan', $kayit->brut_alan) }}" data-hesap>
                @error('brut_alan')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
    </section>

    {{-- Değer --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num" data-bolum-no>4</span>
            <div class="form-section-head-text">
                <h3>Değer</h3>
                <small>Toplam boş bırakılırsa birim fiyat × alan ile hesaplanır.</small>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field" data-tip-alan="arsa">
                <label for="hisse_alan">Değerlenen Alan (m²)</label>
                <input id="hisse_alan" name="hisse_alan" type="number" step="0.01" min="0" class="form-input td-mono" value="{{ old('hisse_alan', $kayit->hisse_alan) }}" data-hesap>
                <span class="hint">
                    Kurum hissesi: {{ $kurumHisse !== null ? $tl($kurumHisse) : '—' }} m²
                    @if ($kurumHisse !== null)<button type="button" class="dg-mini-link" data-alan-doldur="{{ $kurumHisse }}">kullan</button>@endif
                    @if ($parselAlani !== null && $parselAlani !== $kurumHisse) · parsel: {{ $tl($parselAlani) }} m² <button type="button" class="dg-mini-link" data-alan-doldur="{{ $parselAlani }}">kullan</button>@endif
                </span>
                @error('hisse_alan')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="birim_fiyat">Birim Fiyat (TL/m²)</label>
                <input id="birim_fiyat" name="birim_fiyat" type="number" step="0.01" min="0" class="form-input td-mono" value="{{ old('birim_fiyat', $kayit->birim_fiyat) }}" data-hesap>
                @error('birim_fiyat')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="toplam_bedel">Toplam Değer (TL) <span class="required">*</span></label>
                <input id="toplam_bedel" name="toplam_bedel" type="number" step="0.01" min="0" class="form-input td-mono dg-toplam" value="{{ old('toplam_bedel', $kayit->toplam_bedel) }}">
                <span class="hint" id="dg-hesap-not"></span>
                @error('toplam_bedel')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
    </section>

    {{-- Rapor --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num" data-bolum-no>5</span>
            <div class="form-section-head-text">
                <h3>Rapor</h3>
                <small>Rapor numarası, toplu PDF eşleştirmesinde dosya adıyla eşlenir (2026/15 → 2026-15.pdf).</small>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="rapor_no">Rapor No</label>
                <input id="rapor_no" name="rapor_no" class="form-input td-mono" value="{{ old('rapor_no', $kayit->rapor_no) }}" maxlength="60" placeholder="Örn. 2026/15">
            </div>
            <div class="form-field">
                <label for="rapor_tarihi">Rapor Tarihi</label>
                <input id="rapor_tarihi" name="rapor_tarihi" type="date" class="form-input" value="{{ old('rapor_tarihi', $kayit->rapor_tarihi?->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}">
                @error('rapor_tarihi')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="hazirlayan_id">Hazırlayan</label>
                <select id="hazirlayan_id" name="hazirlayan_id" class="form-select">
                    <option value="">—</option>
                    @foreach ($kullanicilar as $u)
                        <option value="{{ $u->id }}" @selected((string) old('hazirlayan_id', $kayit->hazirlayan_id) === (string) $u->id)>{{ $u->ad }} {{ $u->soyad }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field">
                <label for="evrak">Rapor PDF</label>
                @if ($kayit->evrak)
                    <div class="dg-evrak-mevcut">
                        <a href="{{ asset('storage/'.$kayit->evrak) }}" target="_blank" rel="noopener" class="td-link">Mevcut raporu aç</a>
                        <label class="dg-kaldir"><input type="checkbox" name="evrak_kaldir" value="1"> Kaldır</label>
                    </div>
                @endif
                <input id="evrak" name="evrak" type="file" accept="application/pdf" class="form-input">
                <span class="hint">PDF, en fazla 20 MB{{ $kayit->evrak ? ' · yeni dosya seçilirse mevcutun yerine geçer' : '' }}.</span>
                @error('evrak')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="aciklama">Açıklama</label>
                <textarea id="aciklama" name="aciklama" class="form-textarea" rows="3" maxlength="5000" placeholder="Emsal, yöntem, varsayımlar…">{{ old('aciklama', $kayit->aciklama) }}</textarea>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ $kayit->exists ? route('panel.degerleme.detay', $kayit->id) : route('panel.degerleme.yeni') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            {{ $kayit->exists ? 'Değişiklikleri Kaydet' : 'Değerlemeyi Kaydet' }}
        </button>
    </div>
</form>

<script>
(function () {
    const form = document.getElementById('dg-form');
    const yapilar = JSON.parse(form.dataset.yapilar || '[]');
    const parselAlani = parseFloat(form.dataset.parselAlani) || null;
    const $ = id => document.getElementById(id);
    const tip = () => (form.querySelector('input[name="tip"]:checked') || form.querySelector('input[name="tip"]')).value;
    const tl = n => n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let toplamElle = $('toplam_bedel').value !== '';

    function turGoster() {
        const t = tip();
        form.querySelectorAll('[data-tip-alan]').forEach(el => {
            const goster = el.dataset.tipAlan === t;
            el.hidden = !goster;
            el.querySelectorAll('input, select').forEach(i => { i.disabled = !goster; });
        });
        // Bölüm numaraları: BB bölümü gizliyse 3'ten devam
        let n = t === 'bb' ? 4 : 3;
        form.querySelectorAll('[data-bolum-no]').forEach(el => { el.textContent = n++; });
        hesapla();
    }

    function hesapla() {
        const birim = parseFloat($('birim_fiyat').value);
        const alan = tip() === 'bb' ? parseFloat($('brut_alan').value) : (parseFloat($('hisse_alan').value) || parselAlani);
        const not = $('dg-hesap-not');
        if (!(birim >= 0) || !(alan > 0)) { not.textContent = ''; return; }
        const sonuc = Math.round(birim * alan * 100) / 100;
        if (!toplamElle) $('toplam_bedel').value = sonuc.toFixed(2);
        not.textContent = tl(alan) + ' m² × ' + tl(birim) + ' = ' + tl(sonuc) + ' TL' + (toplamElle && Math.abs(sonuc - parseFloat($('toplam_bedel').value)) > 0.01 ? ' (toplam elle değiştirildi)' : '');
    }

    form.addEventListener('change', e => { if (e.target.name === 'tip') turGoster(); });
    form.querySelectorAll('[data-hesap]').forEach(i => i.addEventListener('input', hesapla));
    $('toplam_bedel').addEventListener('input', () => { toplamElle = $('toplam_bedel').value !== ''; hesapla(); });
    form.querySelectorAll('[data-alan-doldur]').forEach(b => b.addEventListener('click', () => { $('hisse_alan').value = b.dataset.alanDoldur; hesapla(); }));

    const yapiSec = $('yapi_id');
    if (yapiSec && yapiSec.tagName === 'SELECT') {
        yapiSec.addEventListener('change', () => {
            const y = yapilar.find(x => String(x.id) === yapiSec.value);
            if (!y) return;
            ['bb_no', 'kat', 'bb_nitelik', 'oda_sayisi', 'brut_alan'].forEach(a => { $(a).value = y[a] ?? ''; });
            hesapla();
        });
    }
    turGoster();
})();
</script>
@endsection
