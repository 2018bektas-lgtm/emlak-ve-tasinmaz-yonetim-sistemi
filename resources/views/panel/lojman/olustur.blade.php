@extends('layouts.panel')

@section('title', 'Yeni Lojman')

@push('head')
<style>
    .loj-tsn-bilgi { margin-top: 4px; }
    .loj-tsn-durum { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; font-size: .82rem; }
    .loj-tsn-durum svg { flex: 0 0 14px; }
    .loj-tsn-durum strong { display: block; font-weight: 700; }
    .loj-tsn-durum small { display: block; color: #6b7280; font-size: .74rem; margin-top: 2px; }
    .loj-tsn-durum.is-bos { background: #f9fafb; border: 1px dashed #d1d5db; color: #6b7280; }
    .loj-tsn-durum.is-arama { background: #fef3c7; border: 1px solid #fde68a; color: #92400e; }
    .loj-tsn-durum.is-bulundu { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
    .loj-tsn-durum.is-bulunamadi { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
</style>
@endpush

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <h2>Yeni Lojman</h2>
        <small>Lojmana ait fiziksel bilgiler.</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.lojman.index') }}" class="btn-cancel">Vazgeç</a>
    </div>
</section>

@if ($errors->any())<div class="flash-error"><ul style="margin:0;padding-left:20px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ route('panel.lojman.kaydet') }}" id="loj-form">
    @csrf
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">1</span>
            <div class="form-section-head-text"><h3>Konum</h3><small>İl / ilçe / mahalle / ada / parsel — taşınmaz otomatik eşlenir.</small></div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="loj-il">İl <span class="required">*</span></label>
                <select id="loj-il" name="il_id" class="form-select" required>
                    <option value="">— İl seçin —</option>
                    @foreach ($iller as $il)<option value="{{ $il->id }}" @selected(old('il_id') == $il->id)>{{ $il->ad }}</option>@endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="loj-ilce">İlçe <span class="required">*</span></label>
                <select id="loj-ilce" name="ilce_id" class="form-select" disabled required>
                    <option value="">— Önce il seçin —</option>
                </select>
            </div>
            <div class="form-field">
                <label for="loj-mahalle">Mahalle <span class="required">*</span></label>
                <select id="loj-mahalle" name="mahalle_id" class="form-select" disabled required>
                    <option value="">— Önce ilçe seçin —</option>
                </select>
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field"><label>Ada *</label><input type="text" id="loj-ada" name="ada" class="form-input" value="{{ old('ada') }}" required maxlength="20" placeholder="Örn. 123"></div>
            <div class="form-field"><label>Parsel *</label><input type="text" id="loj-parsel" name="parsel" class="form-input" value="{{ old('parsel') }}" required maxlength="20" placeholder="Örn. 45"></div>
        </div>
        <div class="loj-tsn-bilgi" id="loj-tsn-bilgi">
            <div class="loj-tsn-durum is-bos">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 8l10 6 10-6z"/><path d="M2 12l10 6 10-6"/></svg>
                Ada / parsel girildikten sonra otomatik eşleme yapılacak.
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">2</span>
            <div class="form-section-head-text"><h3>Lojman / BBN Bilgileri</h3><small>Bağımsız bölüm ise blok ve daire numarasını girin.</small></div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field"><label>Lojman Adı *</label><input type="text" name="ad" class="form-input" value="{{ old('ad') }}" required maxlength="200" placeholder="Örn. Merkez Lojman B - Daire 3"></div>
            <div class="form-field"><label>Tip</label><input type="text" name="tip" class="form-input" value="{{ old('tip') }}" maxlength="40" placeholder="Memur / İşçi / Görev / Sıra"></div>
        </div>
        <div class="form-row form-row-4">
            <div class="form-field"><label>Blok</label><input type="text" id="loj-blok" name="blok" class="form-input" value="{{ old('blok') }}" maxlength="50" placeholder="A / B / 1"></div>
            <div class="form-field"><label>Daire No (BBN)</label><input type="text" id="loj-daire" name="daire_no" class="form-input" value="{{ old('daire_no') }}" maxlength="50"></div>
            <div class="form-field"><label>Kat</label><input type="number" name="kat" class="form-input" value="{{ old('kat') }}" min="0" max="50"></div>
            <div class="form-field"><label>Oda Sayısı</label><input type="number" name="oda_sayisi" class="form-input" value="{{ old('oda_sayisi') }}" min="0" max="20" placeholder="3 (3+1 için)"></div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field"><label>Alan (m²)</label><input type="number" name="alan_m2" class="form-input" value="{{ old('alan_m2') }}" step="0.01" min="0"></div>
            <div class="form-field">
                <label>Durum *</label>
                <select name="durum" class="form-select" required>
                    <option value="bos" @selected(old('durum', 'bos') === 'bos')>Boş</option>
                    <option value="dolu" @selected(old('durum') === 'dolu')>Dolu</option>
                    <option value="bakimda" @selected(old('durum') === 'bakimda')>Bakımda</option>
                    <option value="kullanim-disi" @selected(old('durum') === 'kullanim-disi')>Kullanım Dışı</option>
                </select>
            </div>
        </div>
        <div class="form-field"><label>Açıklama</label><textarea name="aciklama" class="form-input" rows="3" maxlength="5000">{{ old('aciklama') }}</textarea></div>
    </section>

    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
        <a href="{{ route('panel.lojman.index') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">Kaydet</button>
    </div>
</form>

<script>
(function () {
    const ilcelerUrlPattern = @json(route('panel.ajax.ilceler', ['il' => 0]));
    const mahallelerUrlPattern = @json(route('panel.ajax.mahalleler', ['ilce' => 0]));
    const onizleUrl = @json(route('panel.lojman.tasinmaz-onizle'));
    const CSRF = @json(csrf_token());
    const eskiIlceId = @json(old('ilce_id'));
    const eskiMahalleId = @json(old('mahalle_id'));

    const il = document.getElementById('loj-il');
    const ilce = document.getElementById('loj-ilce');
    const mah = document.getElementById('loj-mahalle');
    const ada = document.getElementById('loj-ada');
    const parsel = document.getElementById('loj-parsel');
    const daire = document.getElementById('loj-daire');
    const blok = document.getElementById('loj-blok');
    const bilgi = document.getElementById('loj-tsn-bilgi');

    function urlYap(pattern, id) { return pattern.replace(/\/0(?=[/?]|$)/, '/' + id); }
    function secBosalt(select, placeholder, enable = false) {
        select.innerHTML = '<option value="">' + placeholder + '</option>';
        select.disabled = !enable;
    }

    async function ilceleriYukle(ilId, secilecekIlceId = null) {
        secBosalt(ilce, '— Yükleniyor... —');
        secBosalt(mah, '— Önce ilçe seçin —');
        try {
            const yanit = await fetch(urlYap(ilcelerUrlPattern, ilId), { headers: { 'Accept': 'application/json' } });
            const ilceler = await yanit.json();
            secBosalt(ilce, '— İlçe seçin —', true);
            ilceler.forEach(i => {
                const opt = new Option(i.ad, i.id);
                if (secilecekIlceId && String(i.id) === String(secilecekIlceId)) opt.selected = true;
                ilce.add(opt);
            });
            if (secilecekIlceId) ilce.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (e) { secBosalt(ilce, '— Yükleme hatası —'); }
    }

    async function mahalleleriYukle(ilceId, secilecekMahalleId = null) {
        secBosalt(mah, '— Yükleniyor... —');
        try {
            const yanit = await fetch(urlYap(mahallelerUrlPattern, ilceId), { headers: { 'Accept': 'application/json' } });
            const mahalleler = await yanit.json();
            secBosalt(mah, '— Mahalle seçin —', true);
            mahalleler.forEach(m => {
                const opt = new Option(m.ad, m.id);
                if (secilecekMahalleId && String(m.id) === String(secilecekMahalleId)) opt.selected = true;
                mah.add(opt);
            });
            if (secilecekMahalleId) mah.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (e) { secBosalt(mah, '— Yükleme hatası —'); }
    }

    il.addEventListener('change', function () {
        if (this.value) ilceleriYukle(this.value);
        else { secBosalt(ilce, '— Önce il seçin —'); secBosalt(mah, '— Önce ilçe seçin —'); onizleTetikle(); }
    });
    ilce.addEventListener('change', function () {
        if (this.value) mahalleleriYukle(this.value);
        else { secBosalt(mah, '— Önce ilçe seçin —'); onizleTetikle(); }
    });

    // Eski input restore (validation hatası sonrası)
    if (il.value) {
        ilceleriYukle(il.value, eskiIlceId).then(() => {
            if (eskiMahalleId) mahalleleriYukle(eskiIlceId, eskiMahalleId);
        });
    }

    let dbTimer = null;
    function onizleTetikle() { clearTimeout(dbTimer); dbTimer = setTimeout(onizle, 400); }
    [ada, parsel, daire, blok].forEach(el => el.addEventListener('input', onizleTetikle));
    mah.addEventListener('change', onizleTetikle);

    async function onizle() {
        if (!mah.value || !ada.value.trim() || !parsel.value.trim()) return;
        bilgi.innerHTML = `<div class="loj-tsn-durum is-arama"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg><div>Taşınmaz aranıyor...</div></div>`;
        try {
            const r = await fetch(onizleUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ mahalle_id: mah.value, ada: ada.value.trim(), parsel: parsel.value.trim(), daire_no: daire.value.trim(), blok: blok.value.trim() }),
            });
            const v = await r.json();
            renderSonuc(v);
        } catch (e) { bilgi.innerHTML = `<div class="loj-tsn-durum is-bulunamadi">Sorgu başarısız.</div>`; }
    }

    function renderSonuc(v) {
        const t = v.tasinmaz, y = v.yapi;
        // Başarı: BBN tam eşleşti veya tek mesken
        if (v.bulundu) {
            const bbn = y ? `<div style="margin-top:4px;font-size:.72rem;background:rgba(52,211,153,.2);padding:2px 8px;border-radius:4px;display:inline-block;">Blok: <strong>${e(y.blok_no ?? '—')}</strong> · Kat: <strong>${e(y.kat_no ?? '—')}</strong> · BBN: <strong>${e(y.bagimsiz_bolum_no ?? '—')}</strong>${y.nitelik ? ' · '+e(y.nitelik) : ''}</div>` : '';
            bilgi.innerHTML = `
                <div class="loj-tsn-durum is-bulundu">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12l5 5L20 7"/></svg>
                    <div>
                        <strong>${v.sebep === 'bbn-esti' ? '✓ BBN Tam Eşleşti' : '✓ Mesken Taşınmaz Eşleşti'}</strong>
                        <small>Ada ${e(t.ada)} / Parsel ${e(t.parsel)} · ${e(t.il ?? '')} · ${e(t.ilce ?? '')} · ${e(t.mahalle ?? '')} · <em>${e(t.kayit_tipi ?? '')}</em></small>
                        ${bbn}
                    </div>
                </div>`;
            return;
        }
        // Uyarı durumları — sebebe göre farklı görsel
        const stil = v.sebep === 'sadece-arsa' ? 'is-bulunamadi' : 'is-arama';
        const icon = v.sebep === 'sadece-arsa' ? '⚠' : 'ⓘ';
        const tsnMeta = t ? `<small>Ada ${e(t.ada)} / Parsel ${e(t.parsel)} · ${e(t.kayit_tipi ?? 'Kayıt tipi belirsiz')}</small>` : '';
        bilgi.innerHTML = `
            <div class="loj-tsn-durum ${stil}">
                <span style="font-size:16px;">${icon}</span>
                <div>
                    <strong>${e(v.mesaj)}</strong>
                    ${tsnMeta}
                </div>
            </div>`;
    }

    function e(s) { if (s == null) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
})();
</script>
@endsection
