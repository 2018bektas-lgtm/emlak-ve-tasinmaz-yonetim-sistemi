@extends('layouts.panel')

@section('title', 'Yeni Kiracı · '.$dosya->dosya_no)

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=5">
    <link rel="stylesheet" href="{{ asset('css/kira.css') }}?v=12">
    <link rel="stylesheet" href="{{ asset('css/kira-cakisma.css') }}?v=1">
@endpush

@php
    $gun = fn ($t) => $t?->format('d.m.Y') ?: '—';
    // Yeni dönem varsayılan olarak öncekinin bitişinin ertesi günü başlar
    $varsayilanBaslangic = old('baslangic_tarihi',
        $oncekiDonem?->bitis_tarihi?->copy()->addDay()->format('Y-m-d') ?? now()->format('Y-m-d'));
    $bedeller = old('bedeller', [['yillik_bedel' => '', 'donem_baslangic' => '', 'donem_bitis' => '', 'tufe_orani' => '']]);
@endphp

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Kira İşlemleri · Kiracı Değişikliği</p>
        <h2>
            <a href="{{ route('panel.kira.detay', $dosya->id) }}" class="kr-ust-dosya">{{ $dosya->dosya_no }}</a>
            <span class="kr-ust-ayrac">›</span>
            Yeni Kiracı
        </h2>
        <small>Dosya numarası aynı kalır; önceki dönem kapanır ve geçmişte görünmeye devam eder.</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.kira.detay', $dosya->id) }}" class="btn-cancel">Vazgeç</a>
    </div>
</section>

@if ($errors->any())
    <div class="flash-error" role="alert">
        <strong>Kayıt tamamlanmadı.</strong>
        <ul>@foreach ($errors->all() as $hata)<li>{{ $hata }}</li>@endforeach</ul>
    </div>
@endif

@if ($devamEden)
    {{-- Süresi dolmamış kira: erken kapatılacağı için açık onay istenir --}}
    <div class="kr-cakisma" role="alert">
        <div class="kr-cakisma-ust">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            <strong>Bu dosyada devam eden bir kira var</strong>
        </div>
        <dl class="kr-cakisma-liste">
            <div><dt>Kiracı</dt><dd>{{ $devamEden->anaKiraci?->ad_unvan ?: 'kiracı girilmemiş' }}</dd></div>
            <div><dt>Sözleşme</dt><dd class="td-mono">{{ $gun($devamEden->baslangic_tarihi) }} – {{ $gun($devamEden->bitis_tarihi) }}</dd></div>
            <div>
                <dt>Kalan süre</dt>
                <dd class="td-mono">{{ $devamEden->kalanGun() !== null ? $devamEden->kalanGun().' gün' : 'belirsiz' }}</dd>
            </div>
        </dl>
        <p>
            Yeni kiracıyı kaydederseniz bu sözleşme <strong>süresinden önce kapatılacak</strong>;
            bitiş tarihi yeni kiranın başlangıcından bir gün öncesine çekilecek.
            Kayıt geçmişte görünmeye devam eder, silinmez.
        </p>
        <label class="kr-cakisma-onay">
            <input type="checkbox" name="devam_onayi" value="1" form="donemForm" @checked(old('devam_onayi'))>
            <span>Devam eden kirayı sonlandırarak yeni kiracıyı kaydetmek istiyorum.</span>
        </label>
        @error('devam_onayi')<span class="error">{{ $message }}</span>@enderror
    </div>
@elseif ($oncekiDonem)
    <div class="kr-kunye">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/></svg>
        <div>
            <strong>Önceki kiracı:</strong>
            {{ $oncekiDonem->anaKiraci?->ad_unvan ?: 'kiracı girilmemiş' }}
            · {{ $gun($oncekiDonem->baslangic_tarihi) }} – {{ $gun($oncekiDonem->bitis_tarihi) }}
            <br>
            Süresi dolmuş; yeni kiracıyı kaydettiğinizde geçmiş dönemler arasında kalacak.
        </div>
    </div>
@endif

<form method="POST" action="{{ route('panel.kira.donem.kaydet', $dosya->id) }}" class="form-shell" id="donemForm">
    @csrf

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">1</span>
            <div class="form-section-head-text">
                <h3>Yeni Kiracı</h3>
                <small>Bu dönemin kiracısı</small>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="kiraci_ad_unvan">Ad / Unvan <span class="required">*</span></label>
                <input id="kiraci_ad_unvan" name="kiraci_ad_unvan" class="form-input" value="{{ old('kiraci_ad_unvan') }}" maxlength="250" required autofocus>
                @error('kiraci_ad_unvan')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="kiraci_tc_vergi_no">TC / Vergi No</label>
                <input id="kiraci_tc_vergi_no" name="kiraci_tc_vergi_no" class="form-input td-mono" value="{{ old('kiraci_tc_vergi_no') }}" maxlength="20">
            </div>
            <div class="form-field">
                <label for="kiraci_telefon">Telefon</label>
                <input id="kiraci_telefon" name="kiraci_telefon" class="form-input td-mono" value="{{ old('kiraci_telefon') }}" maxlength="30">
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="kiraci_eposta">E-posta</label>
                <input id="kiraci_eposta" name="kiraci_eposta" type="email" class="form-input" value="{{ old('kiraci_eposta') }}" maxlength="150">
            </div>
            <div class="form-field">
                <label for="kiraci_iban">IBAN</label>
                <input id="kiraci_iban" name="kiraci_iban" class="form-input td-mono" value="{{ old('kiraci_iban') }}" maxlength="34">
            </div>
            <div class="form-field">
                <label for="kiraci_avukat">Avukat</label>
                <input id="kiraci_avukat" name="kiraci_avukat" class="form-input" value="{{ old('kiraci_avukat') }}" maxlength="200">
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label for="kiraci_adres">Adres</label>
                <textarea id="kiraci_adres" name="kiraci_adres" class="form-textarea" rows="2" maxlength="1000">{{ old('kiraci_adres') }}</textarea>
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">2</span>
            <div class="form-section-head-text"><h3>Sözleşme</h3></div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="sozlesme_no">Sözleşme No</label>
                <input id="sozlesme_no" name="sozlesme_no" class="form-input td-mono" value="{{ old('sozlesme_no') }}" maxlength="50">
            </div>
            <div class="form-field">
                <label for="baslangic_tarihi">Kira Başlangıç <span class="required">*</span></label>
                <input id="baslangic_tarihi" name="baslangic_tarihi" type="date" class="form-input" value="{{ $varsayilanBaslangic }}" required>
                @error('baslangic_tarihi')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="bitis_tarihi">Kira Bitiş <span class="required">*</span></label>
                <input id="bitis_tarihi" name="bitis_tarihi" type="date" class="form-input" value="{{ old('bitis_tarihi') }}" required>
                @error('bitis_tarihi')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="sure_ay">Süre (ay)</label>
                <input id="sure_ay" name="sure_ay" type="number" class="form-input td-mono" value="{{ old('sure_ay', 12) }}" min="1" max="1200">
                <span class="hint">Bir yıllık dönemler için 12.</span>
            </div>
            <div class="form-field">
                <label for="taksit_sayisi">Taksit Sayısı</label>
                <input id="taksit_sayisi" name="taksit_sayisi" type="number" class="form-input td-mono" value="{{ old('taksit_sayisi') }}" min="1" max="255">
            </div>
            <div class="form-field">
                <label for="odeme_sekli">Ödeme Şekli</label>
                <input id="odeme_sekli" name="odeme_sekli" class="form-input" value="{{ old('odeme_sekli') }}" maxlength="100">
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="sozlesme_tarihi">Sözleşme Tarihi</label>
                <input id="sozlesme_tarihi" name="sozlesme_tarihi" type="date" class="form-input" value="{{ old('sozlesme_tarihi') }}">
            </div>
            <div class="form-field">
                <label for="noter_tarihi">Noter Tarihi</label>
                <input id="noter_tarihi" name="noter_tarihi" type="date" class="form-input" value="{{ old('noter_tarihi') }}">
            </div>
            <div class="form-field">
                <label for="yevmiye_no">Yevmiye No</label>
                <input id="yevmiye_no" name="yevmiye_no" class="form-input td-mono" value="{{ old('yevmiye_no') }}" maxlength="50">
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="kdv_orani">KDV Oranı (%)</label>
                <input id="kdv_orani" name="kdv_orani" type="number" class="form-input td-mono" value="{{ old('kdv_orani') }}" step="0.01" min="0" max="100">
            </div>
            <div class="form-field">
                <label for="artirma_yontemi">Artırma Yöntemi</label>
                <input id="artirma_yontemi" name="artirma_yontemi" class="form-input" value="{{ old('artirma_yontemi') }}" maxlength="100" placeholder="TÜFE, sabit oran…">
            </div>
            <div class="form-field kr-onaylar">
                <label class="kr-satir-onay"><input type="checkbox" name="kdv_dahil_mi" value="1" @checked(old('kdv_dahil_mi'))> KDV bedele dahil</label>
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">3</span>
            <div class="form-section-head-text">
                <h3>Kira Bedelleri</h3>
                <small>Yıl yıl; tarih boş bırakılırsa başlangıçtan itibaren otomatik türetilir</small>
            </div>
            <button type="button" class="btn-cancel hs-mini-btn dg-sag" data-bedel-ekle>+ Yıl Ekle</button>
        </div>
        <div id="bedelSatirlari">
            @foreach ($bedeller as $i => $b)
                <div class="form-row form-row-4 kr-bedel-satir">
                    <div class="form-field">
                        <label>Yıllık Bedel (₺)</label>
                        <input name="bedeller[{{ $i }}][yillik_bedel]" type="number" class="form-input td-mono" value="{{ $b['yillik_bedel'] ?? '' }}" step="0.01" min="0">
                    </div>
                    <div class="form-field">
                        <label>Dönem Başlangıç</label>
                        <input name="bedeller[{{ $i }}][donem_baslangic]" type="date" class="form-input" value="{{ $b['donem_baslangic'] ?? '' }}">
                    </div>
                    <div class="form-field">
                        <label>Dönem Bitiş</label>
                        <input name="bedeller[{{ $i }}][donem_bitis]" type="date" class="form-input" value="{{ $b['donem_bitis'] ?? '' }}">
                    </div>
                    <div class="form-field">
                        <label>Artış (%)</label>
                        <input name="bedeller[{{ $i }}][tufe_orani]" type="number" class="form-input td-mono" value="{{ $b['tufe_orani'] ?? '' }}" step="0.01">
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">4</span>
            <div class="form-section-head-text"><h3>Açıklama</h3></div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <textarea name="aciklama" class="form-textarea" rows="3" maxlength="5000" placeholder="Devir, tahliye, ihale kararı…">{{ old('aciklama') }}</textarea>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ route('panel.kira.detay', $dosya->id) }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">Dönemi Kaydet</button>
    </div>
</form>

<script>
(function () {
    const kap = document.getElementById('bedelSatirlari');
    const ekleBtn = document.querySelector('[data-bedel-ekle]');
    if (!kap || !ekleBtn) return;

    ekleBtn.addEventListener('click', function () {
        const ilk = kap.querySelector('.kr-bedel-satir');
        const yeni = ilk.cloneNode(true);
        const sira = kap.querySelectorAll('.kr-bedel-satir').length;
        yeni.querySelectorAll('input').forEach(function (g) {
            // bedeller[0][yillik_bedel] → bedeller[3][yillik_bedel]
            g.name = g.name.replace(/\[\d+\]/, '[' + sira + ']');
            g.value = '';
        });
        kap.appendChild(yeni);
    });

    // Süre (ay) girilince bitiş tarihi kendiliğinden hesaplanır
    const bas = document.getElementById('baslangic_tarihi');
    const bit = document.getElementById('bitis_tarihi');
    const sure = document.getElementById('sure_ay');
    function bitisiHesapla() {
        if (!bas.value || !sure.value || bit.dataset.elle === '1') return;
        const t = new Date(bas.value);
        t.setMonth(t.getMonth() + parseInt(sure.value, 10));
        t.setDate(t.getDate() - 1);
        bit.value = t.toISOString().slice(0, 10);
    }
    bit.addEventListener('input', function () { bit.dataset.elle = '1'; });
    bas.addEventListener('change', bitisiHesapla);
    sure.addEventListener('change', bitisiHesapla);
    bitisiHesapla();
})();

/* Devam eden kira varsa onay kutusu işaretlenmeden gönderilmesin.
   Sunucu tarafında da ayrıca kontrol ediliyor; bu yalnızca erken uyarı. */
(function () {
    const form = document.getElementById('donemForm');
    const onay = document.querySelector('[name="devam_onayi"]');
    if (!form || !onay) return;

    form.addEventListener('submit', function (e) {
        if (onay.checked) return;
        e.preventDefault();
        const kutu = onay.closest('.kr-cakisma');
        kutu.classList.add('is-vurgu');
        kutu.scrollIntoView({ behavior: 'smooth', block: 'center' });
        onay.focus();
        setTimeout(function () { kutu.classList.remove('is-vurgu'); }, 1200);
    });
    onay.addEventListener('change', function () {
        onay.closest('.kr-cakisma').classList.toggle('is-onayli', onay.checked);
    });
})();
</script>
@endsection
