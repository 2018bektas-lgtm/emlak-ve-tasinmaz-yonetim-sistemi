@extends('layouts.panel')

@section('title', $dosya ? 'Kira Dosyası Düzenle' : 'Yeni Kira Dosyası')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=5">
    <link rel="stylesheet" href="{{ asset('css/kira.css') }}?v=3">
@endpush

@php
    // Bedel satırları: doğrulama hatasında kullanıcının girdiği satırlar korunur
    $bedeller = old('bedeller', $v['bedeller'] ?? [['yillik_bedel' => '', 'donem_baslangic' => '', 'donem_bitis' => '', 'tufe_orani' => '']]);
@endphp

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Kira İşlemleri</p>
        <h2>{{ $dosya ? 'Kira Dosyası Düzenle' : 'Yeni Kira Dosyası' }}@if ($dosya) <span class="kr-hero-alt">{{ $dosya->dosya_no }}</span>@endif</h2>
        <small>{{ $dosya ? 'Dosya, kiracı, taşınmaz ve yürürlükteki sözleşme birlikte güncellenir.' : 'Dosya, kiracı, taşınmaz ve sözleşme tek adımda açılır; bedeller yıl yıl kaydedilir.' }}</small>
    </div>
    <div class="page-hero-actions">
        @if ($dosya)<a href="{{ route('panel.kira.detay', $dosya->id) }}" class="btn-cancel">Detaya dön</a>@endif
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

<form method="POST" action="{{ $dosya ? route('panel.kira.guncelle', $dosya->id) : route('panel.kira.kaydet') }}" class="form-shell" id="kiraForm">
    @csrf
    @if ($dosya)@method('PUT')@endif
    {{-- Haritadan gelen parsel geometrisi; kaydederken kira_koordinatlari'na yazılır --}}
    <input type="hidden" name="koordinat" value="{{ old('koordinat', $v['koordinat'] ?? '') }}">

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
                <input id="dosya_no" name="dosya_no" class="form-input td-mono" value="{{ old('dosya_no', $v['dosya_no'] ?? null) }}" maxlength="50" required>
                @error('dosya_no')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="eski_dosya_no">Eski Dosya No</label>
                <input id="eski_dosya_no" name="eski_dosya_no" class="form-input td-mono" value="{{ old('eski_dosya_no', $v['eski_dosya_no'] ?? null) }}" maxlength="50">
            </div>
            <div class="form-field">
                <label for="ana_dosya_id">Ana Dosya</label>
                <select id="ana_dosya_id" name="ana_dosya_id" class="form-select">
                    <option value="">Yok (bağımsız dosya)</option>
                    @foreach ($anaDosyalar as $a)
                        <option value="{{ $a->id }}" @selected((string) old('ana_dosya_id', $v['ana_dosya_id'] ?? null) === (string) $a->id)>{{ $a->dosya_no }}@if ($a->isyeri_adi) — {{ $a->isyeri_adi }}@endif</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="isyeri_adi">İşyeri Adı</label>
                <input id="isyeri_adi" name="isyeri_adi" class="form-input" value="{{ old('isyeri_adi', $v['isyeri_adi'] ?? null) }}" maxlength="200">
            </div>
            <div class="form-field">
                <label for="is_kolu_id">İş Kolu</label>
                <select id="is_kolu_id" name="is_kolu_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($isKollari as $i)
                        <option value="{{ $i->id }}" @selected((string) old('is_kolu_id', $v['is_kolu_id'] ?? null) === (string) $i->id)>{{ $i->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="arac_kapasitesi">Araç Kapasitesi</label>
                <input id="arac_kapasitesi" name="arac_kapasitesi" type="number" class="form-input td-mono" value="{{ old('arac_kapasitesi', $v['arac_kapasitesi'] ?? null) }}" min="0">
                <span class="hint">Otopark ve durak dosyalarında.</span>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label>Doluluk <span class="required">*</span></label>
                <div class="kr-secim">
                    <label class="kr-secim-kart" style="--kr-renk:#dc2626">
                        <input type="radio" name="dolu_bos" value="dolu" @checked(old('dolu_bos', $v['dolu_bos'] ?? 'dolu') === 'dolu')>
                        <span>Dolu</span>
                    </label>
                    <label class="kr-secim-kart" style="--kr-renk:#16a34a">
                        <input type="radio" name="dolu_bos" value="bos" @checked(old('dolu_bos', $v['dolu_bos'] ?? null) === 'bos')>
                        <span>Boş</span>
                    </label>
                </div>
            </div>
            <div class="form-field">
                <label>İhale Durumu <span class="required">*</span></label>
                <div class="kr-secim">
                    <label class="kr-secim-kart" style="--kr-renk:#6366f1">
                        <input type="radio" name="ihale_turu" value="ihaleli" @checked(old('ihale_turu', $v['ihale_turu'] ?? null) === 'ihaleli')>
                        <span>İhaleli</span>
                    </label>
                    <label class="kr-secim-kart" style="--kr-renk:#64748b">
                        <input type="radio" name="ihale_turu" value="ihalesiz" @checked(old('ihale_turu', $v['ihale_turu'] ?? 'ihalesiz') === 'ihalesiz')>
                        <span>İhalesiz</span>
                    </label>
                </div>
            </div>
            <div class="form-field">
                <label for="muhasebe_niteligi_id">Muhasebe Niteliği</label>
                <select id="muhasebe_niteligi_id" name="muhasebe_niteligi_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($muhasebeNitelikleri as $m)
                        <option value="{{ $m->id }}" @selected((string) old('muhasebe_niteligi_id', $v['muhasebe_niteligi_id'] ?? null) === (string) $m->id)>{{ $m->ad }}</option>
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
                <input id="kiraci_ad_unvan" name="kiraci_ad_unvan" class="form-input" value="{{ old('kiraci_ad_unvan', $v['kiraci_ad_unvan'] ?? null) }}" maxlength="250">
                @error('kiraci_ad_unvan')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="kiraci_tc_vergi_no">TC / Vergi No</label>
                <input id="kiraci_tc_vergi_no" name="kiraci_tc_vergi_no" class="form-input td-mono" value="{{ old('kiraci_tc_vergi_no', $v['kiraci_tc_vergi_no'] ?? null) }}" maxlength="20">
            </div>
            <div class="form-field">
                <label for="kiraci_telefon">Telefon</label>
                <input id="kiraci_telefon" name="kiraci_telefon" class="form-input td-mono" value="{{ old('kiraci_telefon', $v['kiraci_telefon'] ?? null) }}" maxlength="30">
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="kiraci_eposta">E-posta</label>
                <input id="kiraci_eposta" name="kiraci_eposta" type="email" class="form-input" value="{{ old('kiraci_eposta', $v['kiraci_eposta'] ?? null) }}" maxlength="150">
                @error('kiraci_eposta')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="kiraci_iban">IBAN</label>
                <input id="kiraci_iban" name="kiraci_iban" class="form-input td-mono" value="{{ old('kiraci_iban', $v['kiraci_iban'] ?? null) }}" maxlength="34">
            </div>
            <div class="form-field">
                <label for="kiraci_avukat">Kiracının Avukatı</label>
                <input id="kiraci_avukat" name="kiraci_avukat" class="form-input" value="{{ old('kiraci_avukat', $v['kiraci_avukat'] ?? null) }}" maxlength="200">
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label for="kiraci_adres">Kiracı Adresi</label>
                <textarea id="kiraci_adres" name="kiraci_adres" class="form-textarea" rows="2" maxlength="1000">{{ old('kiraci_adres', $v['kiraci_adres'] ?? null) }}</textarea>
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
                        <option value="{{ $i->id }}" @selected((string) old('il_id', $v['il_id'] ?? null) === (string) $i->id)>{{ $i->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="ilce_id">İlçe</label>
                <select id="ilce_id" name="ilce_id" class="form-select">
                    <option value="">Önce il seçin</option>
                    @foreach ($ilceler as $i)
                        <option value="{{ $i->id }}" @selected((string) old('ilce_id', $v['ilce_id'] ?? null) === (string) $i->id)>{{ $i->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="mahalle_id">Mahalle</label>
                <select id="mahalle_id" name="mahalle_id" class="form-select">
                    <option value="">Önce ilçe seçin</option>
                    @foreach ($mahalleler as $m)
                        <option value="{{ $m->id }}" @selected((string) old('mahalle_id', $v['mahalle_id'] ?? null) === (string) $m->id)>{{ $m->ad }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="ada">Ada</label>
                <input id="ada" name="ada" class="form-input td-mono" value="{{ old('ada', $v['ada'] ?? null) }}" maxlength="20" inputmode="numeric">
            </div>
            <div class="form-field">
                <label for="parsel">Parsel</label>
                <input id="parsel" name="parsel" class="form-input td-mono" value="{{ old('parsel', $v['parsel'] ?? null) }}" maxlength="20" inputmode="numeric">
            </div>
            <div class="form-field">
                <label for="yuzolcum">Yüzölçüm (m²)</label>
                <input id="yuzolcum" name="yuzolcum" type="number" class="form-input td-mono" value="{{ old('yuzolcum', $v['yuzolcum'] ?? null) }}" step="0.01" min="0">
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field">
                <label for="imar_durumu_id">İmar Durumu</label>
                <select id="imar_durumu_id" name="imar_durumu_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($imarDurumlari as $id)
                        <option value="{{ $id->id }}" @selected((string) old('imar_durumu_id', $v['imar_durumu_id'] ?? null) === (string) $id->id)>{{ $id->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="tasinmaz_id">Envanter Kaydı</label>
                <input id="tasinmaz_id" name="tasinmaz_id" type="number" class="form-input td-mono" value="{{ old('tasinmaz_id', $v['tasinmaz_id'] ?? null) }}" min="1" placeholder="Taşınmaz kayıt no (varsa)">
                <span class="hint">Envanterdeki taşınmaza bağlamak için.</span>
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label for="adres">Kiralanan Yerin Adresi</label>
                <textarea id="adres" name="adres" class="form-textarea" rows="2" maxlength="1000">{{ old('adres', $v['adres'] ?? null) }}</textarea>
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
                <input id="baslangic_tarihi" name="baslangic_tarihi" type="date" class="form-input" value="{{ old('baslangic_tarihi', $v['baslangic_tarihi'] ?? null) }}" required>
                @error('baslangic_tarihi')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="bitis_tarihi">Kira Bitiş <span class="required">*</span></label>
                <input id="bitis_tarihi" name="bitis_tarihi" type="date" class="form-input" value="{{ old('bitis_tarihi', $v['bitis_tarihi'] ?? null) }}" required>
                @error('bitis_tarihi')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="sure_ay">Süre (ay)</label>
                <input id="sure_ay" name="sure_ay" type="number" class="form-input td-mono" value="{{ old('sure_ay', $v['sure_ay'] ?? null) }}" min="1" max="1200">
                <span class="hint" id="sureIpucu"></span>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="sozlesme_no">Sözleşme No</label>
                <input id="sozlesme_no" name="sozlesme_no" class="form-input td-mono" value="{{ old('sozlesme_no', $v['sozlesme_no'] ?? null) }}" maxlength="60">
            </div>
            <div class="form-field">
                <label for="sozlesme_tarihi">Sözleşme Tarihi</label>
                <input id="sozlesme_tarihi" name="sozlesme_tarihi" type="date" class="form-input" value="{{ old('sozlesme_tarihi', $v['sozlesme_tarihi'] ?? null) }}">
            </div>
            <div class="form-field">
                <label for="taksit_sayisi">Taksit Sayısı</label>
                <input id="taksit_sayisi" name="taksit_sayisi" type="number" class="form-input td-mono" value="{{ old('taksit_sayisi', $v['taksit_sayisi'] ?? 12) }}" min="1" max="255">
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="odeme_sekli">Ödeme Şekli</label>
                <input id="odeme_sekli" name="odeme_sekli" class="form-input" value="{{ old('odeme_sekli', $v['odeme_sekli'] ?? null) }}" maxlength="80" placeholder="Örn. Aylık peşin">
            </div>
            <div class="form-field">
                <label for="artirma_yontemi">Artırma Yöntemi</label>
                <input id="artirma_yontemi" name="artirma_yontemi" class="form-input" value="{{ old('artirma_yontemi', 'TÜFE') }}" maxlength="100">
            </div>
            <div class="form-field">
                <label for="kdv_orani">KDV Oranı (%)</label>
                <input id="kdv_orani" name="kdv_orani" type="number" class="form-input td-mono" value="{{ old('kdv_orani', $v['kdv_orani'] ?? null) }}" step="0.01" min="0" max="100">
                <label class="kr-satir-onay"><input type="checkbox" name="kdv_dahil_mi" value="1" @checked(old('kdv_dahil_mi', $v['kdv_dahil_mi'] ?? null))> KDV bedele dahil</label>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="noter_tarihi">Noter Tarihi</label>
                <input id="noter_tarihi" name="noter_tarihi" type="date" class="form-input" value="{{ old('noter_tarihi', $v['noter_tarihi'] ?? null) }}">
            </div>
            <div class="form-field">
                <label for="yevmiye_no">Yevmiye No</label>
                <input id="yevmiye_no" name="yevmiye_no" class="form-input td-mono" value="{{ old('yevmiye_no', $v['yevmiye_no'] ?? null) }}" maxlength="60">
            </div>
        </div>
    </section>

    {{-- 5 · Bedeller --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">5</span>
            <div class="form-section-head-text">
                <h3>Kira Bedelleri</h3>
                <small>Her sözleşme yılı bir satır. İlk yılı girip artış oranıyla kalan yılları üretebilirsiniz. Dönem tarihleri boş bırakılırsa sözleşme başlangıcından yıl yıl hesaplanır.</small>
            </div>
        </div>

        {{-- Hızlı doldurma: ilk yıl + artış oranından bütün yıllar --}}
        <div class="kr-uretec">
            <div class="kr-uretec-baslik">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                <span>Hızlı doldur</span>
            </div>
            <div class="form-field">
                <label for="uretIlkBedel">İlk yıl bedeli</label>
                <div class="kr-ek"><input type="number" id="uretIlkBedel" class="form-input td-mono" step="0.01" min="0" placeholder="0,00"><span>₺</span></div>
            </div>
            <div class="form-field">
                <label for="uretYilSayisi">Yıl sayısı</label>
                <div class="kr-ek"><input type="number" id="uretYilSayisi" class="form-input td-mono" min="1" max="30" value="3"><span>yıl</span></div>
            </div>
            <div class="form-field">
                <label for="uretArtis">Yıllık artış</label>
                <div class="kr-ek"><input type="number" id="uretArtis" class="form-input td-mono" step="0.01" value="25"><span>%</span></div>
            </div>
            <button type="button" class="btn-cancel kr-uretec-btn" id="uretBtn">Yılları oluştur</button>
            <p class="kr-uretec-mesaj" id="uretMesaj" hidden></p>
        </div>

        <div class="kr-bedeller" id="bedelListe">
            <div class="kr-bedel-baslik" aria-hidden="true">
                <span>Yıl</span>
                <span>Dönem</span>
                <span class="is-sag">Yıllık bedel</span>
                <span class="is-sag">Artış</span>
                <span class="is-sag">Aylık</span>
                <span></span>
            </div>
            @foreach ($bedeller as $i => $b)
                <div class="kr-bedel-satir">
                    <span class="kr-yil-no">{{ $i + 1 }}. yıl</span>
                    <div class="kr-donem">
                        <input type="date" name="bedeller[{{ $i }}][donem_baslangic]" class="form-input" value="{{ $b['donem_baslangic'] ?? '' }}" aria-label="Dönem başlangıcı">
                        <span class="kr-donem-ayrac">–</span>
                        <input type="date" name="bedeller[{{ $i }}][donem_bitis]" class="form-input" value="{{ $b['donem_bitis'] ?? '' }}" aria-label="Dönem bitişi">
                    </div>
                    <div class="kr-ek"><input type="number" name="bedeller[{{ $i }}][yillik_bedel]" class="form-input td-mono" value="{{ $b['yillik_bedel'] ?? '' }}" step="0.01" min="0" placeholder="0,00" aria-label="Yıllık bedel" data-bedel><span>₺</span></div>
                    <div class="kr-ek"><input type="number" name="bedeller[{{ $i }}][tufe_orani]" class="form-input td-mono" value="{{ $b['tufe_orani'] ?? '' }}" step="0.01" placeholder="—" aria-label="Artış oranı" data-oran><span>%</span></div>
                    <span class="kr-aylik td-mono" data-aylik>—</span>
                    <button type="button" class="kr-satir-sil" data-bedel-sil aria-label="Bu yılı kaldır" title="Bu yılı kaldır">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/></svg>
                    </button>
                </div>
            @endforeach
        </div>

        <div class="kr-bedel-alt">
            <button type="button" class="kr-yil-ekle" id="bedelEkle">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Yıl ekle
            </button>
            <dl class="kr-bedel-toplam">
                <div><dt>Yıllık bedeller toplamı</dt><dd class="td-mono" id="bedelToplam">—</dd></div>
                <div><dt>Ortalama aylık</dt><dd class="td-mono" id="bedelOrtalama">—</dd></div>
            </dl>
        </div>
        @error('bedeller')<span class="error">{{ $message }}</span>@enderror
        @error('bedeller.*.yillik_bedel')<span class="error">{{ $message }}</span>@enderror
        @error('bedeller.*.donem_baslangic')<span class="error">{{ $message }}</span>@enderror
        @error('bedeller.*.donem_bitis')<span class="error">{{ $message }}</span>@enderror
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
                        <option value="{{ $h->id }}" @selected((string) old('hukuki_durum_id', $v['hukuki_durum_id'] ?? null) === (string) $h->id)>{{ $h->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="kiracilik_durum_id">Kiracılık Durumu</label>
                <select id="kiracilik_durum_id" name="kiracilik_durum_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($kiracilikDurumlar as $k)
                        <option value="{{ $k->id }}" @selected((string) old('kiracilik_durum_id', $v['kiracilik_durum_id'] ?? null) === (string) $k->id)>{{ $k->ad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="uyari">Uyarı</label>
                <input id="uyari" name="uyari" class="form-input" value="{{ old('uyari', $v['uyari'] ?? null) }}" maxlength="300">
                <span class="hint">Listede kırmızı etiket olarak görünür.</span>
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field kr-onaylar">
                <label class="kr-satir-onay"><input type="checkbox" name="sozlesme_aktif_mi" value="1" @checked(old('sozlesme_aktif_mi', $v['sozlesme_aktif_mi'] ?? true))> Sözleşme yürürlükte</label>
                <label class="kr-satir-onay"><input type="checkbox" name="tahliye_ihbarnamesi_mi" value="1" @checked(old('tahliye_ihbarnamesi_mi', $v['tahliye_ihbarnamesi_mi'] ?? null))> Tahliye ihbarnamesi çekildi</label>
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label for="aciklama">Açıklama / Not</label>
                <textarea id="aciklama" name="aciklama" class="form-textarea" rows="3" maxlength="5000">{{ old('aciklama', $v['aciklama'] ?? null) }}</textarea>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ route('panel.kira.index') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">{{ $dosya ? 'Değişiklikleri Kaydet' : 'Dosyayı Oluştur' }}</button>
    </div>
</form>

<script>
(function () {
    const form = document.getElementById('kiraForm');
    const liste = document.getElementById('bedelListe');
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

    // ---- Kira bedelleri ----
    const TL = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' });
    const SIL_IKON = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/></svg>';
    const satirlar = function () { return Array.from(liste.querySelectorAll('.kr-bedel-satir')); };
    const sayi = function (girdi) { const n = parseFloat(girdi.value); return Number.isFinite(n) ? n : null; };
    const yuvarla = function (n) { return (Math.round(n * 100) / 100).toFixed(2); };

    // Yerel gün; toISOString saat dilimi yüzünden bir gün kaydırabiliyor
    function isoGun(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function gunOku(s) {
        const p = (s || '').split('-').map(Number);
        return p.length === 3 && p[0] ? new Date(p[0], p[1] - 1, p[2]) : null;
    }

    function yenidenNumarala() {
        satirlar().forEach(function (satir, i) {
            satir.querySelector('.kr-yil-no').textContent = (i + 1) + '. yıl';
            satir.querySelectorAll('input[name]').forEach(function (g) {
                g.name = g.name.replace(/bedeller\[\d+\]/, 'bedeller[' + i + ']');
            });
            // İlk yılın artışı olmaz
            const oran = satir.querySelector('[data-oran]');
            oran.placeholder = i === 0 ? 'İlk yıl' : '—';
        });
        hesapla();
    }

    /** Satırların aylık tutarı ve alttaki toplamlar. */
    function hesapla() {
        let toplam = 0;
        let adet = 0;
        satirlar().forEach(function (satir) {
            const b = sayi(satir.querySelector('[data-bedel]'));
            satir.querySelector('[data-aylik]').textContent = b !== null ? TL.format(b / 12) : '—';
            if (b !== null) { toplam += b; adet++; }
        });
        document.getElementById('bedelToplam').textContent = adet ? TL.format(toplam) : '—';
        document.getElementById('bedelOrtalama').textContent = adet ? TL.format(toplam / (adet * 12)) : '—';
    }

    /** Oranı girilmiş yılların bedelini önceki yıldan yeniden hesaplar (sıradan sonrası). */
    function zincir(baslangic) {
        const s = satirlar();
        for (let i = Math.max(1, baslangic); i < s.length; i++) {
            const onceki = sayi(s[i - 1].querySelector('[data-bedel]'));
            const oran = sayi(s[i].querySelector('[data-oran]'));
            if (onceki === null || oran === null) continue;
            s[i].querySelector('[data-bedel]').value = yuvarla(onceki * (1 + oran / 100));
        }
    }

    function satirKur(deger) {
        deger = deger || {};
        const satir = document.createElement('div');
        satir.className = 'kr-bedel-satir';
        satir.innerHTML =
            '<span class="kr-yil-no"></span>' +
            '<div class="kr-donem">' +
                '<input type="date" name="bedeller[0][donem_baslangic]" class="form-input" aria-label="Dönem başlangıcı">' +
                '<span class="kr-donem-ayrac">–</span>' +
                '<input type="date" name="bedeller[0][donem_bitis]" class="form-input" aria-label="Dönem bitişi">' +
            '</div>' +
            '<div class="kr-ek"><input type="number" name="bedeller[0][yillik_bedel]" class="form-input td-mono" step="0.01" min="0" placeholder="0,00" aria-label="Yıllık bedel" data-bedel><span>₺</span></div>' +
            '<div class="kr-ek"><input type="number" name="bedeller[0][tufe_orani]" class="form-input td-mono" step="0.01" placeholder="—" aria-label="Artış oranı" data-oran><span>%</span></div>' +
            '<span class="kr-aylik td-mono" data-aylik>—</span>' +
            '<button type="button" class="kr-satir-sil" data-bedel-sil aria-label="Bu yılı kaldır" title="Bu yılı kaldır">' + SIL_IKON + '</button>';
        // Değerler özellik olarak atanır; HTML'e gömülmez
        satir.querySelector('[name$="[donem_baslangic]"]').value = deger.bas || '';
        satir.querySelector('[name$="[donem_bitis]"]').value = deger.bit || '';
        satir.querySelector('[data-bedel]').value = deger.bedel || '';
        satir.querySelector('[data-oran]').value = deger.oran !== undefined ? deger.oran : '';
        liste.appendChild(satir);
        return satir;
    }

    document.getElementById('bedelEkle').addEventListener('click', function () {
        // Yeni yıl, öncekinin dönemini bir yıl ileri taşır
        const s = satirlar();
        const son = s[s.length - 1];
        const deger = {};
        const sonBit = son && gunOku(son.querySelector('[name$="[donem_bitis]"]').value);
        if (sonBit) {
            const yeniBas = new Date(sonBit.getFullYear(), sonBit.getMonth(), sonBit.getDate() + 1);
            const yeniBit = new Date(yeniBas.getFullYear() + 1, yeniBas.getMonth(), yeniBas.getDate() - 1);
            const sozBit = gunOku(bit.value);
            deger.bas = isoGun(yeniBas);
            // Sözleşme içinde kalan son dönem sözleşme bitişinde kesilir; sözleşme
            // dışına taşan yıl (uzatma) tam yıl olarak açılır, ters dönem oluşmaz
            deger.bit = isoGun(sozBit && yeniBas <= sozBit && yeniBit > sozBit ? sozBit : yeniBit);
        }
        const yeni = satirKur(deger);
        yenidenNumarala();
        yeni.querySelector('[data-bedel]').focus();
    });

    liste.addEventListener('click', function (e) {
        const sil = e.target.closest('[data-bedel-sil]');
        if (!sil) return;
        const satir = sil.closest('.kr-bedel-satir');
        if (satirlar().length === 1) {
            // En az bir yıl kalmalı; son satır silinmez, temizlenir
            satir.querySelectorAll('input').forEach(function (g) { g.value = ''; });
        } else {
            satir.remove();
        }
        yenidenNumarala();
    });

    liste.addEventListener('input', function (e) {
        const satir = e.target.closest('.kr-bedel-satir');
        if (!satir) return;
        const s = satirlar();
        const i = s.indexOf(satir);
        if (e.target.matches('[data-oran]')) {
            zincir(i);
        } else if (e.target.matches('[data-bedel]')) {
            // Bedel elle değişti: bu yılın oranı güncellenir, sonraki yıllar kendi oranıyla kayar
            const onceki = i > 0 ? sayi(s[i - 1].querySelector('[data-bedel]')) : null;
            const b = sayi(e.target);
            if (onceki && b !== null) s[i].querySelector('[data-oran]').value = yuvarla((b / onceki - 1) * 100);
            zincir(i + 1);
        }
        hesapla();
    });

    // Yıl sayısı sözleşme süresinden önerilir (kullanıcı elle değiştirmediyse)
    const uretYil = document.getElementById('uretYilSayisi');
    let uretYilElle = false;
    uretYil.addEventListener('input', function () { uretYilElle = true; });
    function yilSayisiOner() {
        const b1 = gunOku(bas.value), b2 = gunOku(bit.value);
        if (uretYilElle || !b1 || !b2 || b2 <= b1) return;
        uretYil.value = Math.min(30, Math.max(1, Math.ceil(((b2 - b1) / 86400000 + 1) / 365.25 - 0.01)));
    }
    [bas, bit].forEach(function (a) { a.addEventListener('change', yilSayisiOner); });
    yilSayisiOner();

    function uretMesaj(metin) {
        const m = document.getElementById('uretMesaj');
        m.textContent = metin || '';
        m.hidden = !metin;
    }

    // İlk yıl + artış oranından tüm yılları üret; dönemler kira başlangıcından yürür
    document.getElementById('uretBtn').addEventListener('click', function () {
        const ilk = parseFloat(document.getElementById('uretIlkBedel').value);
        const yil = parseInt(uretYil.value, 10);
        const artis = parseFloat(document.getElementById('uretArtis').value) || 0;
        if (!(ilk > 0) || !(yil > 0)) { uretMesaj('İlk yıl bedelini ve yıl sayısını girin.'); return; }
        uretMesaj(bas.value ? '' : 'Kira başlangıcı girilmediği için dönem tarihleri boş bırakıldı; kayıtta sözleşmeden hesaplanır.');

        const b1 = gunOku(bas.value), b2 = gunOku(bit.value);
        satirlar().forEach(function (s) { s.remove(); });
        let bedel = ilk;
        for (let y = 0; y < yil; y++) {
            const deger = { bedel: yuvarla(bedel), oran: y > 0 ? String(artis) : '' };
            if (b1) {
                const d = new Date(b1.getFullYear() + y, b1.getMonth(), b1.getDate());
                const s = new Date(d.getFullYear() + 1, d.getMonth(), d.getDate() - 1);
                deger.bas = isoGun(d);
                deger.bit = isoGun(b2 && s > b2 ? b2 : s);
            }
            satirKur(deger);
            bedel = bedel * (1 + artis / 100);
        }
        yenidenNumarala();
    });

    yenidenNumarala();

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
