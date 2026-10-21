{{-- Kira bedelleri bölümü — dosya formu ve kira alanı formu ortak kullanır.
     Parametreler: $no (bölüm numarası), $bedeller (satırlar). Davranış: _form-betik --}}
<section class="form-section">
    <div class="form-section-head">
        <span class="form-section-num">{{ $no }}</span>
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
