@php
    /**
     * Kat mülkiyetsiz bina — çoklu BBN yönetimi (tablo + modal).
     * BBN'ler kendi TAKBİS nosuna sahip değil (tapu ana parselde "arsa" gösterir),
     * bu yüzden tümü aynı taşınmaz altında listelenir.
     */
    $yapilarEski = $yapilarEski ?? [];
    $nitelikler = $nitelikler ?? ['Mesken', 'Dükkan', 'Depo', 'İşyeri', 'Büro', 'Ortak Alan'];
@endphp

<div class="yapi-tekil-hint">
    <strong>Kat mülkiyetsiz bina.</strong> Bina üzerindeki tüm bağımsız bölümler bu taşınmaz altında listelenir. Her BBN için sadece fiziksel bilgiler (blok/kat/no/nitelik/m²) girilir; muhasebe, satış ve durum bilgileri Bölüm 05–06'da parselin tümü için tek girişle takip edilir.
</div>

<div class="hisse-shell" id="yapi-shell" data-eski='@json($yapilarEski)'>
    <div class="hisse-tablo-kabuk yapi-tablo-kabuk">
        <table class="hisse-tablo" id="yapi-tablo">
            <thead>
                <tr>
                    <th class="hisse-th-no">#</th>
                    <th>Blok / Kat / B.B. No</th>
                    <th>Nitelik</th>
                    <th>Net (m²)</th>
                    <th>Satış</th>
                    <th>Durum</th>
                    <th class="hisse-th-eylem" aria-label="Eylemler"></th>
                </tr>
            </thead>
            <tbody id="yapi-tablo-body"></tbody>
        </table>
        <div class="hisse-tablo-bos" id="yapi-tablo-bos">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 3v18"/></svg>
            <span>Henüz bağımsız bölüm eklenmedi. Aşağıdaki butonla eklemeye başlayın.</span>
        </div>
    </div>

    <button type="button" class="hisse-ekle-btn" id="yapi-ekle-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
        Bağımsız Bölüm Ekle
    </button>

    <div id="yapi-hidden-alan" hidden aria-hidden="true"></div>
</div>

<div class="modal-backdrop" id="yapi-modal" hidden>
    <div class="modal-shell modal-shell-lg" role="dialog" aria-labelledby="yapi-modal-baslik" aria-modal="true">
        <div class="modal-head">
            <h4 id="yapi-modal-baslik">Bağımsız Bölüm Ekle</h4>
            <button type="button" class="modal-close" data-yapi-modal-kapat aria-label="Kapat">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-govde">
            <div class="modal-mesaj" id="yapi-modal-mesaj" hidden></div>

            <div class="form-row form-row-4">
                <div class="form-field">
                    <label>Blok No</label>
                    <input type="text" data-fld="blok_no" class="form-input" maxlength="20" placeholder="A">
                </div>
                <div class="form-field">
                    <label>Kat No</label>
                    <input type="text" data-fld="kat_no" class="form-input" maxlength="10" placeholder="Zemin · 1">
                </div>
                <div class="form-field">
                    <label>Bölüm No</label>
                    <input type="text" data-fld="bagimsiz_bolum_no" class="form-input" maxlength="20" placeholder="12">
                </div>
                <div class="form-field">
                    <label>Nitelik</label>
                    <select data-fld="nitelik" class="form-select">
                        <option value="">— Seçiniz —</option>
                        @foreach ($nitelikler as $n)
                            <option value="{{ $n }}">{{ $n }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-row form-row-4">
                <div class="form-field">
                    <label>Brüt Alan (m²)</label>
                    <input type="text" data-fld="brut_alan" data-tr-numeric class="form-input" inputmode="decimal" placeholder="120,50">
                </div>
                <div class="form-field">
                    <label>Net Alan (m²)</label>
                    <input type="text" data-fld="net_alan" data-tr-numeric class="form-input" inputmode="decimal" placeholder="98,00">
                </div>
                <div class="form-field">
                    <label>Oda</label>
                    <input type="text" data-fld="oda_sayisi" class="form-input" maxlength="10" placeholder="3+1">
                </div>
                <div class="form-field">
                    <label>Cephe</label>
                    <input type="text" data-fld="cephe" class="form-input" maxlength="50" placeholder="Kuzey, Doğu">
                </div>
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label>Bölüm Açıklaması</label>
                    <input type="text" data-fld="aciklama" class="form-input" maxlength="500" placeholder="Birime özel not">
                </div>
            </div>
        </div>
        <div class="modal-alt">
            <button type="button" class="btn-cancel" data-yapi-modal-kapat>Vazgeç</button>
            <button type="button" class="btn-submit" id="yapi-modal-kaydet">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                <span data-yapi-modal-btn>Ekle</span>
            </button>
        </div>
    </div>
</div>
