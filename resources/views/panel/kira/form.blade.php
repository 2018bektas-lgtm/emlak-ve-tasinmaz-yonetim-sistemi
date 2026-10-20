@extends('layouts.panel')

@section('title', 'Yeni Kira Dosyası')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=5">
    <link rel="stylesheet" href="{{ asset('css/kira.css') }}?v=2">
@endpush

@php
    // Bedel satırları: doğrulama hatasında kullanıcının girdiği satırlar korunur
    $bedeller = old('bedeller', [['yillik_bedel' => '', 'donem_baslangic' => '', 'donem_bitis' => '', 'tufe_orani' => '']]);
@endphp

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Kira İşlemleri</p>
        <h2>Yeni Kira Dosyası</h2>
        <small>Dosya, kiracı, taşınmaz ve sözleşme tek adımda açılır; bedeller yıl yıl kaydedilir.</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.kira.index') }}" class="btn-cancel">Listeye dön</a>
    </div>
</section>

@if ($errors->any())
    <div class="flash-error" role="alert">
        <strong>Kayıt tamamlanmadı.</strong>
        <ul>@foreach ($errors->all() as $hata)<li>{{ $hata }}</li>@endforeach</ul>
    </div>
@endif

@if (($onDolgu['koordinat'] ?? null) || ($onDolgu['ada'] ?? null))
    <div class="kr-harita-serit" role="status">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4L3 6v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14M15 6v14"/></svg>
        <span>
            Parsel haritadan alındı:
            <strong>{{ $onDolgu['ada'] ?? '—' }}/{{ $onDolgu['parsel'] ?? '—' }}</strong>
            @if (($onDolgu['ilce_ad'] ?? null)) · {{ $onDolgu['ilce_ad'] }}@if (($onDolgu['mahalle_ad'] ?? null)) · {{ $onDolgu['mahalle_ad'] }}@endif @endif
            @if (($onDolgu['koordinat'] ?? null)) · <em>geometri dosyaya kaydedilecek</em>@endif
        </span>
    </div>
@endif

<form method="POST" action="{{ route('panel.kira.kaydet') }}" class="form-shell" id="kiraForm">
    @csrf
    {{-- Haritadan gelen parsel geometrisi; kaydederken kira_koordinatlari'na yazılır --}}
    <input type="hidden" name="koordinat" value="{{ old('koordinat', $onDolgu['koordinat'] ?? '') }}">

    {{-- 1 · Dosya --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">1</span>
            <div class="form-section-head-text">
                <h3>Dosya Bilgileri</h3>
                <small>Dosya numarası kurum içinde benzersizdir; alt dosya ise ana dosyasını seçin.</small>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="dosya_no">Dosya No <span class="required">*</span></label>
                <input id="dosya_no" name="dosya_no" class="form-input td-mono" value="{{ old('dosya_no') }}" maxlength="50" required>
                @error('dosya_no')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="eski_dosya_no">Eski Dosya No</label>
                <input id="eski_dosya_no" name="eski_dosya_no" class="form-input td-mono" value="{{ old('eski_dosya_no') }}" maxlength="50">
            </div>
            <div class="form-field">
                <label for="ana_dosya_id">Ana Dosya</label>
                <select id="ana_dosya_id" name="ana_dosya_id" class="form-select">
                    <option value="">Yok (bağımsız dosya)</option>
                    @foreach ($anaDosyalar as $a)
                        <option value="{{ $a->id }}" @selected((string) old('ana_dosya_id') === (string) $a->id)>{{ $a->dosya_no }}@if ($a->isyeri_adi) — {{ $a->isyeri_adi }}@endif</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="isyeri_adi">İşyeri Adı</label>
                <input id="isyeri_adi" name="isyeri_adi" class="form-input" value="{{ old('isyeri_adi') }}" maxlength="200">
            </div>
            <div class="form-field">
                <label for="is_kolu_id">İş Kolu</label>
                <select id="is_kolu_id" name="is_kolu_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($isKollari as $i)
                        <option value="{{ $i->id }}" @selected((string) old('is_kolu_id') === (string) $i->id)>{{ $i->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="arac_kapasitesi">Araç Kapasitesi</label>
                <input id="arac_kapasitesi" name="arac_kapasitesi" type="number" class="form-input td-mono" value="{{ old('arac_kapasitesi') }}" min="0">
                <span class="hint">Otopark ve durak dosyalarında.</span>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label>Doluluk <span class="required">*</span></label>
                <div class="kr-secim">
                    <label class="kr-secim-kart" style="--kr-renk:#dc2626">
                        <input type="radio" name="dolu_bos" value="dolu" @checked(old('dolu_bos', 'dolu') === 'dolu')>
                        <span>Dolu</span>
                    </label>
                    <label class="kr-secim-kart" style="--kr-renk:#16a34a">
                        <input type="radio" name="dolu_bos" value="bos" @checked(old('dolu_bos') === 'bos')>
                        <span>Boş</span>
                    </label>
                </div>
            </div>
            <div class="form-field">
                <label>İhale Durumu <span class="required">*</span></label>
                <div class="kr-secim">
                    <label class="kr-secim-kart" style="--kr-renk:#6366f1">
                        <input type="radio" name="ihale_turu" value="ihaleli" @checked(old('ihale_turu') === 'ihaleli')>
                        <span>İhaleli</span>
                    </label>
                    <label class="kr-secim-kart" style="--kr-renk:#64748b">
                        <input type="radio" name="ihale_turu" value="ihalesiz" @checked(old('ihale_turu', 'ihalesiz') === 'ihalesiz')>
                        <span>İhalesiz</span>
                    </label>
                </div>
            </div>
            <div class="form-field">
                <label for="muhasebe_niteligi_id">Muhasebe Niteliği</label>
                <select id="muhasebe_niteligi_id" name="muhasebe_niteligi_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($muhasebeNitelikleri as $m)
                        <option value="{{ $m->id }}" @selected((string) old('muhasebe_niteligi_id') === (string) $m->id)>{{ $m->ad }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </section>

    {{-- 2 · Kiracı --}}
    <section class="form-section" id="kiraciBolum">
        <div class="form-section-head">
            <span class="form-section-num">2</span>
            <div class="form-section-head-text">
                <h3>Ana Kiracı</h3>
                <small>Alt kiracılar dosya açıldıktan sonra eklenir. Boş yerlerde bu bölüm doldurulmaz.</small>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="kiraci_ad_unvan">Ad / Unvan</label>
                <input id="kiraci_ad_unvan" name="kiraci_ad_unvan" class="form-input" value="{{ old('kiraci_ad_unvan') }}" maxlength="250">
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
                @error('kiraci_eposta')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="kiraci_iban">IBAN</label>
                <input id="kiraci_iban" name="kiraci_iban" class="form-input td-mono" value="{{ old('kiraci_iban') }}" maxlength="34">
            </div>
            <div class="form-field">
                <label for="kiraci_avukat">Kiracının Avukatı</label>
                <input id="kiraci_avukat" name="kiraci_avukat" class="form-input" value="{{ old('kiraci_avukat') }}" maxlength="200">
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label for="kiraci_adres">Kiracı Adresi</label>
                <textarea id="kiraci_adres" name="kiraci_adres" class="form-textarea" rows="2" maxlength="1000">{{ old('kiraci_adres') }}</textarea>
            </div>
        </div>
    </section>

    {{-- 3 · Taşınmaz --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">3</span>
            <div class="form-section-head-text">
                <h3>Kiralanan Taşınmaz</h3>
                <small>Kiralanan yer envanterde kayıtlı olmak zorunda değil; konum bilgisi yeterli.</small>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="il_id">İl</label>
                <select id="il_id" name="il_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($iller as $i)
                        <option value="{{ $i->id }}" @selected((string) old('il_id', $onDolgu['il_id'] ?? null) === (string) $i->id)>{{ $i->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="ilce_id">İlçe</label>
                <select id="ilce_id" name="ilce_id" class="form-select">
                    <option value="">Önce il seçin</option>
                    @foreach ($ilceler as $i)
                        <option value="{{ $i->id }}" @selected((string) old('ilce_id', $onDolgu['ilce_id'] ?? null) === (string) $i->id)>{{ $i->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="mahalle_id">Mahalle</label>
                <select id="mahalle_id" name="mahalle_id" class="form-select">
                    <option value="">Önce ilçe seçin</option>
                    @foreach ($mahalleler as $m)
                        <option value="{{ $m->id }}" @selected((string) old('mahalle_id', $onDolgu['mahalle_id'] ?? null) === (string) $m->id)>{{ $m->ad }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="ada">Ada</label>
                <input id="ada" name="ada" class="form-input td-mono" value="{{ old('ada', $onDolgu['ada'] ?? null) }}" maxlength="20" inputmode="numeric">
            </div>
            <div class="form-field">
                <label for="parsel">Parsel</label>
                <input id="parsel" name="parsel" class="form-input td-mono" value="{{ old('parsel', $onDolgu['parsel'] ?? null) }}" maxlength="20" inputmode="numeric">
            </div>
            <div class="form-field">
                <label for="yuzolcum">Yüzölçüm (m²)</label>
                <input id="yuzolcum" name="yuzolcum" type="number" class="form-input td-mono" value="{{ old('yuzolcum', $onDolgu['yuzolcum'] ?? null) }}" step="0.01" min="0">
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field">
                <label for="imar_durumu_id">İmar Durumu</label>
                <select id="imar_durumu_id" name="imar_durumu_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($imarDurumlari as $id)
                        <option value="{{ $id->id }}" @selected((string) old('imar_durumu_id') === (string) $id->id)>{{ $id->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="tasinmaz_id">Envanter Kaydı</label>
                <input id="tasinmaz_id" name="tasinmaz_id" type="number" class="form-input td-mono" value="{{ old('tasinmaz_id') }}" min="1" placeholder="Taşınmaz kayıt no (varsa)">
                <span class="hint">Envanterdeki taşınmaza bağlamak için.</span>
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label for="adres">Kiralanan Yerin Adresi</label>
                <textarea id="adres" name="adres" class="form-textarea" rows="2" maxlength="1000">{{ old('adres') }}</textarea>
            </div>
        </div>
    </section>

    {{-- 4 · Sözleşme --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">4</span>
            <div class="form-section-head-text">
                <h3>Sözleşme</h3>
                <small>Süre tarihlerden hesaplanır; sözleşmede yazan ay sayısı farklıysa ayrıca girin.</small>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="baslangic_tarihi">Kira Başlangıç <span class="required">*</span></label>
                <input id="baslangic_tarihi" name="baslangic_tarihi" type="date" class="form-input" value="{{ old('baslangic_tarihi') }}" required>
                @error('baslangic_tarihi')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="bitis_tarihi">Kira Bitiş <span class="required">*</span></label>
                <input id="bitis_tarihi" name="bitis_tarihi" type="date" class="form-input" value="{{ old('bitis_tarihi') }}" required>
                @error('bitis_tarihi')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="sure_ay">Süre (ay)</label>
                <input id="sure_ay" name="sure_ay" type="number" class="form-input td-mono" value="{{ old('sure_ay') }}" min="1" max="1200">
                <span class="hint" id="sureIpucu"></span>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="sozlesme_no">Sözleşme No</label>
                <input id="sozlesme_no" name="sozlesme_no" class="form-input td-mono" value="{{ old('sozlesme_no') }}" maxlength="60">
            </div>
            <div class="form-field">
                <label for="sozlesme_tarihi">Sözleşme Tarihi</label>
                <input id="sozlesme_tarihi" name="sozlesme_tarihi" type="date" class="form-input" value="{{ old('sozlesme_tarihi') }}">
            </div>
            <div class="form-field">
                <label for="taksit_sayisi">Taksit Sayısı</label>
                <input id="taksit_sayisi" name="taksit_sayisi" type="number" class="form-input td-mono" value="{{ old('taksit_sayisi', 12) }}" min="1" max="255">
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="odeme_sekli">Ödeme Şekli</label>
                <input id="odeme_sekli" name="odeme_sekli" class="form-input" value="{{ old('odeme_sekli') }}" maxlength="80" placeholder="Örn. Aylık peşin">
            </div>
            <div class="form-field">
                <label for="artirma_yontemi">Artırma Yöntemi</label>
                <input id="artirma_yontemi" name="artirma_yontemi" class="form-input" value="{{ old('artirma_yontemi', 'TÜFE') }}" maxlength="100">
            </div>
            <div class="form-field">
                <label for="kdv_orani">KDV Oranı (%)</label>
                <input id="kdv_orani" name="kdv_orani" type="number" class="form-input td-mono" value="{{ old('kdv_orani') }}" step="0.01" min="0" max="100">
                <label class="kr-satir-onay"><input type="checkbox" name="kdv_dahil_mi" value="1" @checked(old('kdv_dahil_mi'))> KDV bedele dahil</label>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="noter_tarihi">Noter Tarihi</label>
                <input id="noter_tarihi" name="noter_tarihi" type="date" class="form-input" value="{{ old('noter_tarihi') }}">
            </div>
            <div class="form-field">
                <label for="yevmiye_no">Yevmiye No</label>
                <input id="yevmiye_no" name="yevmiye_no" class="form-input td-mono" value="{{ old('yevmiye_no') }}" maxlength="60">
            </div>
        </div>
    </section>

    {{-- 5 · Bedeller --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">5</span>
            <div class="form-section-head-text">
                <h3>Kira Bedelleri</h3>
                <small>Her sözleşme yılı bir satır. İlk yılı girip artış oranıyla kalan yılları üretebilirsiniz.</small>
            </div>
        </div>

        <div class="kr-uretec">
            <label class="kr-alan"><span>İlk yıl bedeli (₺)</span><input type="number" id="uretIlkBedel" step="0.01" min="0"></label>
            <label class="kr-alan"><span>Yıl sayısı</span><input type="number" id="uretYilSayisi" min="1" max="30" value="3"></label>
            <label class="kr-alan"><span>Yıllık artış (%)</span><input type="number" id="uretArtis" step="0.01" value="25"></label>
            <button type="button" class="btn-cancel hs-mini-btn" id="uretBtn">Satırları Üret</button>
        </div>

        <div class="tl-tablo-wrap">
            <table class="tl-tablo kr-bedel-tablo" id="bedelTablo">
                <thead>
                    <tr>
                        <th style="width:70px">Yıl</th>
                        <th>Dönem Başlangıç</th>
                        <th>Dönem Bitiş</th>
                        <th class="is-sag">Yıllık Bedel (₺)</th>
                        <th class="is-sag">Artış / TÜFE (%)</th>
                        <th style="width:60px"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bedeller as $i => $b)
                        <tr>
                            <td class="kr-yil-no">{{ $i + 1 }}</td>
                            <td><input type="date" name="bedeller[{{ $i }}][donem_baslangic]" class="form-input" value="{{ $b['donem_baslangic'] ?? '' }}"></td>
                            <td><input type="date" name="bedeller[{{ $i }}][donem_bitis]" class="form-input" value="{{ $b['donem_bitis'] ?? '' }}"></td>
                            <td><input type="number" name="bedeller[{{ $i }}][yillik_bedel]" class="form-input td-mono" value="{{ $b['yillik_bedel'] ?? '' }}" step="0.01" min="0"></td>
                            <td><input type="number" name="bedeller[{{ $i }}][tufe_orani]" class="form-input td-mono" value="{{ $b['tufe_orani'] ?? '' }}" step="0.01"></td>
                            <td><button type="button" class="dg-kaldir" data-bedel-sil>Sil</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <button type="button" class="btn-cancel hs-mini-btn" id="bedelEkle">+ Yıl ekle</button>
        @error('bedeller')<span class="error">{{ $message }}</span>@enderror
        @error('bedeller.*.yillik_bedel')<span class="error">{{ $message }}</span>@enderror
    </section>

    {{-- 6 · Durum --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">6</span>
            <div class="form-section-head-text">
                <h3>Durum ve Notlar</h3>
                <small>Hukuki süreç ve uyarılar; listede bu bilgilerle süzülür.</small>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="hukuki_durum_id">Hukuki Durum</label>
                <select id="hukuki_durum_id" name="hukuki_durum_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($hukukiDurumlar as $h)
                        <option value="{{ $h->id }}" @selected((string) old('hukuki_durum_id') === (string) $h->id)>{{ $h->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="kiracilik_durum_id">Kiracılık Durumu</label>
                <select id="kiracilik_durum_id" name="kiracilik_durum_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($kiracilikDurumlar as $k)
                        <option value="{{ $k->id }}" @selected((string) old('kiracilik_durum_id') === (string) $k->id)>{{ $k->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="uyari">Uyarı</label>
                <input id="uyari" name="uyari" class="form-input" value="{{ old('uyari') }}" maxlength="300">
                <span class="hint">Listede kırmızı etiket olarak görünür.</span>
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field kr-onaylar">
                <label class="kr-satir-onay"><input type="checkbox" name="sozlesme_aktif_mi" value="1" @checked(old('sozlesme_aktif_mi', true))> Sözleşme yürürlükte</label>
                <label class="kr-satir-onay"><input type="checkbox" name="tahliye_ihbarnamesi_mi" value="1" @checked(old('tahliye_ihbarnamesi_mi'))> Tahliye ihbarnamesi çekildi</label>
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label for="aciklama">Açıklama / Not</label>
                <textarea id="aciklama" name="aciklama" class="form-textarea" rows="3" maxlength="5000">{{ old('aciklama') }}</textarea>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ route('panel.kira.index') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">Dosyayı Oluştur</button>
    </div>
</form>

<script>
(function () {
    const form = document.getElementById('kiraForm');
    const govde = document.querySelector('#bedelTablo tbody');
    const bas = document.getElementById('baslangic_tarihi');
    const bit = document.getElementById('bitis_tarihi');

    // Tarihlerden süreyi tahmin edip ipucu olarak göster
    function sureyiGoster() {
        const ipucu = document.getElementById('sureIpucu');
        if (!bas.value || !bit.value) { ipucu.textContent = ''; return; }
        const gun = (new Date(bit.value) - new Date(bas.value)) / 86400000;
        if (gun <= 0) { ipucu.textContent = ''; return; }
        ipucu.textContent = 'Tarihlere göre ' + (gun / 365).toFixed(1) + ' yıl (' + Math.round(gun / 30.44) + ' ay)';
    }
    [bas, bit].forEach(function (a) { a.addEventListener('change', sureyiGoster); });
    sureyiGoster();

    function yenidenNumarala() {
        Array.from(govde.rows).forEach(function (tr, i) {
            tr.querySelector('.kr-yil-no').textContent = i + 1;
            tr.querySelectorAll('input').forEach(function (g) {
                g.name = g.name.replace(/bedeller\[\d+\]/, 'bedeller[' + i + ']');
            });
        });
    }

    function satirKur(deger) {
        deger = deger || {};
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="kr-yil-no"></td>' +
            '<td><input type="date" name="x[donem_baslangic]" class="form-input" value="' + (deger.bas || '') + '"></td>' +
            '<td><input type="date" name="x[donem_bitis]" class="form-input" value="' + (deger.bit || '') + '"></td>' +
            '<td><input type="number" name="x[yillik_bedel]" class="form-input td-mono" step="0.01" min="0" value="' + (deger.bedel || '') + '"></td>' +
            '<td><input type="number" name="x[tufe_orani]" class="form-input td-mono" step="0.01" value="' + (deger.oran || '') + '"></td>' +
            '<td><button type="button" class="dg-kaldir" data-bedel-sil>Sil</button></td>';
        // Şablondaki 'x[alan]' adından 'x' atılır: 'bedeller[0][alan]'
        tr.querySelectorAll('input').forEach(function (g) {
            g.name = 'bedeller[0]' + g.name.slice(1);
        });
        govde.appendChild(tr);
        yenidenNumarala();
    }

    document.getElementById('bedelEkle').addEventListener('click', function () { satirKur(); });

    govde.addEventListener('click', function (e) {
        if (e.target.matches('[data-bedel-sil]')) {
            e.target.closest('tr').remove();
            yenidenNumarala();
        }
    });

    // İlk yıl + artış oranından tüm yılları üret; dönemler kira başlangıcından yürür
    document.getElementById('uretBtn').addEventListener('click', function () {
        const ilk = parseFloat(document.getElementById('uretIlkBedel').value);
        const yil = parseInt(document.getElementById('uretYilSayisi').value, 10);
        const artis = parseFloat(document.getElementById('uretArtis').value) || 0;
        if (!ilk || !yil) { alert('İlk yıl bedeli ve yıl sayısı girin.'); return; }

        govde.innerHTML = '';
        let bedel = ilk;
        for (let y = 0; y < yil; y++) {
            let donemBas = '', donemBit = '';
            if (bas.value) {
                const d = new Date(bas.value);
                d.setFullYear(d.getFullYear() + y);
                donemBas = d.toISOString().slice(0, 10);
                const s = new Date(d);
                s.setFullYear(s.getFullYear() + 1);
                s.setDate(s.getDate() - 1);
                donemBit = s.toISOString().slice(0, 10);
            }
            satirKur({ bas: donemBas, bit: donemBit, bedel: bedel.toFixed(2), oran: y > 0 ? artis : '' });
            bedel = bedel * (1 + artis / 100);
        }
    });

    // İl → ilçe → mahalle
    const ilSecim = document.getElementById('il_id');
    const ilceSecim = document.getElementById('ilce_id');
    const mahalleSecim = document.getElementById('mahalle_id');

    async function doldur(secim, url, bosMetin) {
        secim.innerHTML = '<option value="">Yükleniyor…</option>';
        secim.disabled = true;
        try {
            const yanit = await fetch(url, { headers: { Accept: 'application/json' } });
            const veri = await yanit.json();
            secim.innerHTML = '<option value="">' + bosMetin + '</option>';
            veri.forEach(function (s) {
                const o = document.createElement('option');
                o.value = s.id;
                o.textContent = s.ad;
                secim.appendChild(o);
            });
        } catch (e) {
            secim.innerHTML = '<option value="">Liste alınamadı</option>';
        }
        secim.disabled = false;
    }

    ilSecim.addEventListener('change', function () {
        mahalleSecim.innerHTML = '<option value="">Önce ilçe seçin</option>';
        if (!this.value) { ilceSecim.innerHTML = '<option value="">Önce il seçin</option>'; return; }
        doldur(ilceSecim, '{{ route('panel.ajax.ilceler', ['il' => '__ID__']) }}'.replace('__ID__', this.value), 'Seçin');
    });

    ilceSecim.addEventListener('change', function () {
        if (!this.value) { mahalleSecim.innerHTML = '<option value="">Önce ilçe seçin</option>'; return; }
        doldur(mahalleSecim, '{{ route('panel.ajax.mahalleler', ['ilce' => '__ID__']) }}'.replace('__ID__', this.value), 'Seçin');
    });

    // Boş yerde kiracı istenmez; bölüm soluklaşır
    function kiraciBolumuTazele() {
        const bos = form.querySelector('[name="dolu_bos"]:checked').value === 'bos';
        document.getElementById('kiraciBolum').classList.toggle('is-pasif', bos);
    }
    form.querySelectorAll('[name="dolu_bos"]').forEach(function (r) { r.addEventListener('change', kiraciBolumuTazele); });
    kiraciBolumuTazele();
})();
</script>
@endsection
