{{-- Sözleşme bölümü — dosya formu ve kira alanı formu ortak kullanır.
     Parametreler: $no (bölüm numarası), $v (varsayılan değerler) --}}
<section class="form-section">
    <div class="form-section-head">
        <span class="form-section-num">{{ $no }}</span>
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
            <input id="artirma_yontemi" name="artirma_yontemi" class="form-input" value="{{ old('artirma_yontemi', $v['artirma_yontemi'] ?? 'TÜFE') }}" maxlength="100">
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
