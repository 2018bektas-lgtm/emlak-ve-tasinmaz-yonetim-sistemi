{{-- Hisse Satışı — Düzenle modalları
     Her tabloya eklenen "Düzenle" butonu bu modalların ilgili olanını açar.
     JS `hsDuzenleAc(tur, data)` fonksiyonu formun action'ını ayarlayıp alanları doldurur.
--}}

@php
    $base = url('/panel/hisse-satisi');
@endphp

{{-- Başvuru düzenle --}}
<div class="hs-modal-backdrop" id="hs-modal-basvuru" hidden>
    <div class="hs-modal">
        <div class="hs-modal-baslik">
            <h3>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                Başvuru Düzenle
            </h3>
            <button type="button" class="hs-modal-kapat" onclick="hsDuzenleKapat('basvuru')">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" action="">
            @csrf
            @method('PUT')
            <div class="hs-modal-govde">
                <div class="hs-form-grid">
                    <div class="hs-secim-alan"><label>Ad Soyad *</label><input type="text" name="ad_soyad" class="form-input" required maxlength="150"></div>
                    <div class="hs-secim-alan"><label>TC Kimlik *</label><input type="text" name="tc_kimlik" class="form-input" required minlength="11" maxlength="11"></div>
                    <div class="hs-secim-alan"><label>GSM</label><input type="text" name="gsm_no" class="form-input" maxlength="20"></div>
                    <div class="hs-secim-alan"><label>Başvuru Tarihi *</label><input type="date" name="basvuru_tarihi" class="form-input" required></div>
                    <div class="hs-secim-alan"><label>Tapu Hisse (m²)</label><input type="text" name="tapu_hisse" class="form-input" inputmode="decimal"></div>
                    <div class="hs-secim-alan"><label>Talep Edilen (m²)</label><input type="text" name="talep_edilen_hisse" class="form-input" inputmode="decimal"></div>
                    <div class="hs-secim-alan" style="grid-column:1/-1"><label>Yeni Evrak (PDF, opsiyonel)</label><input type="file" name="basvuru_evrak" accept="application/pdf" class="form-input"></div>
                    <div class="hs-secim-alan" style="grid-column:1/-1"><label>Açıklama</label><textarea name="aciklama" class="form-input" rows="3" maxlength="5000"></textarea></div>
                </div>
            </div>
            <div class="hs-modal-alt">
                <button type="button" class="btn-cancel" onclick="hsDuzenleKapat('basvuru')">İptal</button>
                <button type="submit" class="btn-submit">Kaydet</button>
            </div>
        </form>
    </div>
</div>

{{-- Malik düzenle --}}
<div class="hs-modal-backdrop" id="hs-modal-malik" hidden>
    <div class="hs-modal">
        <div class="hs-modal-baslik">
            <h3>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                Malik Düzenle
            </h3>
            <button type="button" class="hs-modal-kapat" onclick="hsDuzenleKapat('malik')">✕</button>
        </div>
        <form method="POST" action="">
            @csrf
            @method('PUT')
            <div class="hs-modal-govde">
                <div class="hs-form-grid">
                    <div class="hs-secim-alan"><label>Ad Soyad *</label><input type="text" name="ad_soyad" class="form-input" required maxlength="150"></div>
                    <div class="hs-secim-alan"><label>TC Kimlik</label><input type="text" name="tc_kimlik" class="form-input" minlength="11" maxlength="11"></div>
                    <div class="hs-secim-alan"><label>Tapu Hisse (m²)</label><input type="text" name="tapu_hisse" class="form-input" inputmode="decimal"></div>
                    <div class="hs-secim-alan"><label>GSM</label><input type="text" name="gsm_no" class="form-input" maxlength="20"></div>
                    <div class="hs-secim-alan" style="grid-column:1/-1"><label>Adres</label><input type="text" name="adres" class="form-input" maxlength="500"></div>
                </div>
            </div>
            <div class="hs-modal-alt">
                <button type="button" class="btn-cancel" onclick="hsDuzenleKapat('malik')">İptal</button>
                <button type="submit" class="btn-submit">Kaydet</button>
            </div>
        </form>
    </div>
</div>

{{-- Görüş düzenle --}}
<div class="hs-modal-backdrop" id="hs-modal-gorus" hidden>
    <div class="hs-modal">
        <div class="hs-modal-baslik">
            <h3>Görüş Düzenle</h3>
            <button type="button" class="hs-modal-kapat" onclick="hsDuzenleKapat('gorus')">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" action="">
            @csrf
            <div class="hs-modal-govde">
                <div class="hs-form-grid">
                    <div class="hs-secim-alan"><label>Görüş İstenen Şube *</label><input type="text" name="gorus_sube" class="form-input" required maxlength="200"></div>
                    <div class="hs-secim-alan"><label>Giden Evrak Tarihi</label><input type="date" name="giden_tarih" class="form-input"></div>
                    <div class="hs-secim-alan"><label>Giden Evrak Sayısı</label><input type="text" name="giden_yazi" class="form-input" maxlength="100"></div>
                    <div class="hs-secim-alan"><label>Giden Evrak (PDF, yeni)</label><input type="file" name="giden_evrak" accept="application/pdf" class="form-input"></div>
                    <div class="hs-secim-alan"><label>Gelen Evrak Tarihi</label><input type="date" name="gelen_tarih" class="form-input"></div>
                    <div class="hs-secim-alan"><label>Gelen Evrak Sayısı</label><input type="text" name="gelen_yazi" class="form-input" maxlength="100"></div>
                    <div class="hs-secim-alan"><label>Gelen Evrak (PDF, yeni)</label><input type="file" name="gelen_evrak" accept="application/pdf" class="form-input"></div>
                    <div class="hs-secim-alan"><label>Engel Durumu</label>
                        <select name="engel_var_yok" class="form-select">
                            <option value="">— Seçilmedi —</option>
                            <option value="Yok">Engel Yok</option>
                            <option value="Var">Engel Var</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="hs-modal-alt">
                <button type="button" class="btn-cancel" onclick="hsDuzenleKapat('gorus')">İptal</button>
                <button type="submit" class="btn-submit">Kaydet</button>
            </div>
        </form>
    </div>
</div>

{{-- İmar düzenle --}}
<div class="hs-modal-backdrop" id="hs-modal-imar" hidden>
    <div class="hs-modal">
        <div class="hs-modal-baslik">
            <h3>İmar Bilgisi Düzenle</h3>
            <button type="button" class="hs-modal-kapat" onclick="hsDuzenleKapat('imar')">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" action="">
            @csrf
            <div class="hs-modal-govde">
                <div class="hs-form-grid">
                    <div class="hs-secim-alan"><label>Giden Evrak Sayısı</label><input type="text" name="imar_giden_yazi" class="form-input" maxlength="100"></div>
                    <div class="hs-secim-alan"><label>Giden Evrak Tarihi</label><input type="date" name="imar_giden_tarih" class="form-input"></div>
                    <div class="hs-secim-alan"><label>Giden Evrak (PDF, yeni)</label><input type="file" name="imar_giden_evrak" accept="application/pdf" class="form-input"></div>
                    <div class="hs-secim-alan"><label>Gelen Evrak Sayısı</label><input type="text" name="imar_gelen_yazi" class="form-input" maxlength="100"></div>
                    <div class="hs-secim-alan"><label>Gelen Evrak Tarihi</label><input type="date" name="imar_gelen_tarih" class="form-input"></div>
                    <div class="hs-secim-alan"><label>Gelen Evrak (PDF, yeni)</label><input type="file" name="imar_gelen_evrak" accept="application/pdf" class="form-input"></div>
                    <div class="hs-secim-alan"><label>İmar Durumu</label><input type="text" name="imar_durum" class="form-input" maxlength="150"></div>
                    <div class="hs-secim-alan"><label>Emsal</label><input type="text" name="emsal" class="form-input" maxlength="30"></div>
                    <div class="hs-secim-alan"><label>Yençok</label><input type="text" name="yencok" class="form-input" maxlength="30"></div>
                    <div class="hs-secim-alan" style="grid-column:1/-1"><label>Plan Notları</label><textarea name="plan_notlari" class="form-input" rows="3" maxlength="5000"></textarea></div>
                </div>
            </div>
            <div class="hs-modal-alt">
                <button type="button" class="btn-cancel" onclick="hsDuzenleKapat('imar')">İptal</button>
                <button type="submit" class="btn-submit">Kaydet</button>
            </div>
        </form>
    </div>
</div>

{{-- Tebligat düzenle --}}
<div class="hs-modal-backdrop" id="hs-modal-tebligat" hidden>
    <div class="hs-modal">
        <div class="hs-modal-baslik">
            <h3>Ön Tebligat Düzenle</h3>
            <button type="button" class="hs-modal-kapat" onclick="hsDuzenleKapat('tebligat')">✕</button>
        </div>
        <form method="POST" action="">
            @csrf
            @method('PUT')
            <div class="hs-modal-govde">
                <div class="hs-form-grid">
                    <div class="hs-secim-alan"><label>Ad Soyad</label><input type="text" name="ad_soyad" class="form-input" maxlength="150"></div>
                    <div class="hs-secim-alan"><label>TC Kimlik</label><input type="text" name="tc_kimlik" class="form-input" minlength="11" maxlength="11"></div>
                    <div class="hs-secim-alan"><label>Tapu Hisse (m²)</label><input type="text" name="tapu_hisse" class="form-input" inputmode="decimal"></div>
                    <div class="hs-secim-alan"><label>Tebligat Tarihi</label><input type="date" name="tebligat_tarihi" class="form-input"></div>
                    <div class="hs-secim-alan"><label>Ulaştığı Tarih</label><input type="date" name="ulastigi_tarihi" class="form-input"></div>
                </div>
                <p class="hs-info" style="margin-top:12px;font-size:0.78rem;">
                    <strong>Not:</strong> "Başvurdu" ve "Tebligat Ulaşmadı" checkbox'ları listede satır üzerinde doğrudan değiştirilir.
                </p>
            </div>
            <div class="hs-modal-alt">
                <button type="button" class="btn-cancel" onclick="hsDuzenleKapat('tebligat')">İptal</button>
                <button type="submit" class="btn-submit">Kaydet</button>
            </div>
        </form>
    </div>
</div>

{{-- Encümen düzenle --}}
<div class="hs-modal-backdrop" id="hs-modal-encumen" hidden>
    <div class="hs-modal">
        <div class="hs-modal-baslik">
            <h3>Encümen Kararı Düzenle</h3>
            <button type="button" class="hs-modal-kapat" onclick="hsDuzenleKapat('encumen')">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" action="">
            @csrf
            <div class="hs-modal-govde">
                <div class="hs-form-grid">
                    <div class="hs-secim-alan"><label>Karar No</label><input type="text" name="karar_no" class="form-input" maxlength="100"></div>
                    <div class="hs-secim-alan"><label>Kayıt No</label><input type="text" name="kayit_no" class="form-input" maxlength="100"></div>
                    <div class="hs-secim-alan"><label>Birim Fiyat (₺/m²)</label><input type="text" name="birim_fiyat" class="form-input" inputmode="decimal"></div>
                    <div class="hs-secim-alan"><label>Gelen Tarih</label><input type="date" name="gelen_tarih" class="form-input"></div>
                    <div class="hs-secim-alan"><label>Giden Tarih</label><input type="date" name="giden_tarih" class="form-input"></div>
                    <div class="hs-secim-alan"><label>Gelen Evrak (PDF, yeni)</label><input type="file" name="gelen_evrak" accept="application/pdf" class="form-input"></div>
                    <div class="hs-secim-alan"><label>Giden Evrak (PDF, yeni)</label><input type="file" name="giden_evrak" accept="application/pdf" class="form-input"></div>
                    <div class="hs-secim-alan" style="grid-column:1/-1"><label>Açıklama</label><textarea name="aciklama" class="form-input" rows="3" maxlength="2000"></textarea></div>
                </div>
            </div>
            <div class="hs-modal-alt">
                <button type="button" class="btn-cancel" onclick="hsDuzenleKapat('encumen')">İptal</button>
                <button type="submit" class="btn-submit">Kaydet</button>
            </div>
        </form>
    </div>
</div>

{{-- Satış Tebligatı düzenle --}}
<div class="hs-modal-backdrop" id="hs-modal-satis" hidden>
    <div class="hs-modal">
        <div class="hs-modal-baslik">
            <h3>Satış Tebligatı Düzenle</h3>
            <button type="button" class="hs-modal-kapat" onclick="hsDuzenleKapat('satis')">✕</button>
        </div>
        <form method="POST" action="">
            @csrf
            @method('PUT')
            <div class="hs-modal-govde">
                <div class="hs-form-grid">
                    <div class="hs-secim-alan"><label>Tebligat Tarihi</label><input type="date" name="tebligat_tarihi" class="form-input"></div>
                    <div class="hs-secim-alan"><label>Ulaştığı Tarih</label><input type="date" name="ulastigi_tarihi" class="form-input"></div>
                    <div class="hs-secim-alan"><label>Verilen Hisse (m²)</label><input type="text" name="hisseye_dusen_yuzolcum" class="form-input" inputmode="decimal"></div>
                    <div class="hs-secim-alan"><label>Birim Fiyat (₺)</label><input type="text" name="birim_fiyat" class="form-input" inputmode="decimal"></div>
                    <div class="hs-secim-alan" style="grid-column:1/-1"><label>Toplam Bedel (₺)</label><input type="text" name="toplam_bedel" class="form-input" inputmode="decimal"></div>
                    <div class="hs-secim-alan" style="grid-column:1/-1"><label>Açıklama</label><textarea name="aciklama" class="form-input" rows="3" maxlength="2000"></textarea></div>
                </div>
                <p class="hs-info" style="margin-top:12px;font-size:0.78rem;">
                    <strong>Not:</strong> "Ödedi" ve "Tebligat Ulaşmadı" checkbox'ları listede satır üzerinde doğrudan değiştirilir.
                </p>
            </div>
            <div class="hs-modal-alt">
                <button type="button" class="btn-cancel" onclick="hsDuzenleKapat('satis')">İptal</button>
                <button type="submit" class="btn-submit">Kaydet</button>
            </div>
        </form>
    </div>
</div>

{{-- Tapu Tescili düzenle --}}
<div class="hs-modal-backdrop" id="hs-modal-tapu" hidden>
    <div class="hs-modal">
        <div class="hs-modal-baslik">
            <h3>Tapu Tescili Düzenle</h3>
            <button type="button" class="hs-modal-kapat" onclick="hsDuzenleKapat('tapu')">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" action="">
            @csrf
            <div class="hs-modal-govde">
                <div class="hs-form-grid">
                    <div class="hs-secim-alan"><label>Tescil Tarihi *</label><input type="date" name="tescil_tarihi" class="form-input" required></div>
                    <div class="hs-secim-alan"><label>Yevmiye No</label><input type="text" name="yevmiye_no" class="form-input" maxlength="100"></div>
                    <div class="hs-secim-alan"><label>Tescil Edilen (m²)</label><input type="text" name="tescil_edilen_yuzolcum" class="form-input" inputmode="decimal"></div>
                    <div class="hs-secim-alan"><label>Yeni Tapu Senedi (PDF)</label><input type="file" name="tescil_evrak" accept="application/pdf" class="form-input"></div>
                    <div class="hs-secim-alan" style="grid-column:1/-1"><label>Açıklama</label><textarea name="aciklama" class="form-input" rows="3" maxlength="2000"></textarea></div>
                </div>
            </div>
            <div class="hs-modal-alt">
                <button type="button" class="btn-cancel" onclick="hsDuzenleKapat('tapu')">İptal</button>
                <button type="submit" class="btn-submit">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
// Her modal için tür → route eşleştirmesi
const HS_DUZENLE_ROTA = {
    basvuru: '{{ $base }}/basvuru',
    malik: '{{ $base }}/malik',
    gorus: '{{ $base }}/gorus',
    imar: '{{ $base }}/imar',
    tebligat: '{{ $base }}/tebligat',
    encumen: '{{ $base }}/encumen',
    satis: '{{ $base }}/satis-tebligat',
    tapu: '{{ $base }}/tapu',
};

function hsDuzenleAc(tur, veri) {
    const modal = document.getElementById(`hs-modal-${tur}`);
    if (! modal) return;
    const form = modal.querySelector('form');
    form.action = `${HS_DUZENLE_ROTA[tur]}/${veri.id}`;
    // Alan alan doldur
    Object.entries(veri).forEach(([key, val]) => {
        if (key === 'id') return;
        const alan = form.querySelector(`[name="${key}"]`);
        if (! alan) return;
        if (alan.type === 'file') return; // dosya alanı doldurulamaz
        alan.value = val ?? '';
    });
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
}

function hsDuzenleKapat(tur) {
    const modal = document.getElementById(`hs-modal-${tur}`);
    if (modal) modal.hidden = true;
    document.body.style.overflow = '';
}

// Backdrop tıklaması modalı kapatır
document.querySelectorAll('.hs-modal-backdrop').forEach(bd => {
    bd.addEventListener('click', e => {
        if (e.target === bd) {
            bd.hidden = true;
            document.body.style.overflow = '';
        }
    });
});

// Escape ile kapat
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.hs-modal-backdrop:not([hidden])').forEach(bd => {
            bd.hidden = true;
        });
        document.body.style.overflow = '';
    }
});
</script>
