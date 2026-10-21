{{-- Kiracı bölümü — dosya formu ve kira alanı formu ortak kullanır.
     Parametreler: $no (bölüm numarası), $v (varsayılan değerler), $baslik, $aciklama --}}
<section class="form-section" id="kiraciBolum">
    <div class="form-section-head">
        <span class="form-section-num">{{ $no }}</span>
        <div class="form-section-head-text">
            <h3>{{ $baslik ?? 'Ana Kiracı' }}</h3>
            <small>{{ $aciklama ?? 'Kiracı bu sözleşme dönemine bağlanır; kiracı değişince yeni dönem açılır.' }}</small>
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
