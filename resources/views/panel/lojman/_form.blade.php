{{--
    Lojman formu — olustur ve duzenle ortak.
    Lojman belediyenin kayıtlı taşınmazından çekilir: konum girilir, o parseldeki
    birimler (bağımsız bölümler) listelenir, biri seçilir.
    Değişkenler: $lojman (?Lojman), $iller, $ilceler, $mahalleler
--}}
@php
    use App\Models\Lojman;

    $duzenleme = $lojman !== null;
    $aktifTahsis = $lojman?->aktifTahsis();
    $seciliDurum = old('durum', $lojman && $lojman->durum !== 'dolu' ? $lojman->durum : 'bos');
    $alan = old('alan_m2', $lojman?->alan_m2 !== null ? number_format((float) $lojman->alan_m2, 2, ',', '') : null);
@endphp

<form method="POST" action="{{ $duzenleme ? route('panel.lojman.guncelle', $lojman->id) : route('panel.lojman.kaydet') }}" class="form-shell lj-form" id="lj-form">
    @csrf
    @if ($duzenleme) @method('PUT') @endif
    <input type="hidden" name="tasinmaz_id" id="lj-tasinmaz" value="{{ old('tasinmaz_id', $lojman?->tasinmaz_id) }}">

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">1</span>
            <div class="form-section-head-text">
                <h3>Taşınmaz</h3>
                <small>Lojman, belediyenin taşınmaz envanterindeki bir binadan seçilir. Konumu girin, parseldeki birimlerden birini seçin.</small>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="lj-il">İl</label>
                <select id="lj-il" name="il_id" class="form-select">
                    <option value="">— İl seçin —</option>
                    @foreach ($iller as $il)<option value="{{ $il->id }}" @selected(old('il_id', $lojman?->il_id) == $il->id)>{{ $il->ad }}</option>@endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="lj-ilce">İlçe</label>
                <select id="lj-ilce" name="ilce_id" class="form-select" @disabled($ilceler->isEmpty())>
                    <option value="">{{ $ilceler->isEmpty() ? '— Önce il seçin —' : '— İlçe seçin —' }}</option>
                    @foreach ($ilceler as $x)<option value="{{ $x->id }}" @selected(old('ilce_id', $lojman?->ilce_id) == $x->id)>{{ $x->ad }}</option>@endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="lj-mahalle">Mahalle</label>
                <select id="lj-mahalle" name="mahalle_id" class="form-select" @disabled($mahalleler->isEmpty())>
                    <option value="">{{ $mahalleler->isEmpty() ? '— Önce ilçe seçin —' : '— Mahalle seçin —' }}</option>
                    @foreach ($mahalleler as $x)<option value="{{ $x->id }}" @selected(old('mahalle_id', $lojman?->mahalle_id) == $x->id)>{{ $x->ad }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field"><label for="lj-ada">Ada</label><input type="text" id="lj-ada" name="ada" class="form-input td-mono" value="{{ old('ada', $lojman?->ada) }}" maxlength="20" inputmode="numeric"></div>
            <div class="form-field"><label for="lj-parsel">Parsel</label><input type="text" id="lj-parsel" name="parsel" class="form-input td-mono" value="{{ old('parsel', $lojman?->parsel) }}" maxlength="20" inputmode="numeric"></div>
        </div>

        <div class="lj-birimler" id="lj-birimler" aria-live="polite">
            <div class="lj-esle-kutu is-bos">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 8l10 6 10-6z"/><path d="M2 12l10 6 10-6"/></svg>
                <div>Mahalle, ada ve parsel girilince bu parseldeki binalar ve bağımsız bölümler burada listelenir.</div>
            </div>
        </div>
        @error('tasinmaz_id')<span class="error">{{ $message }}</span>@enderror

        <div class="form-row form-row-2 lj-serbest" id="lj-serbest" hidden>
            <div class="form-field"><label for="lj-blok">Blok</label><input type="text" id="lj-blok" name="blok" class="form-input" value="{{ old('blok', $lojman?->blok) }}" maxlength="50" placeholder="A, B, 1…"></div>
            <div class="form-field">
                <label for="lj-daire">Daire No</label>
                <input type="text" id="lj-daire" name="daire_no" class="form-input td-mono" value="{{ old('daire_no', $lojman?->daire_no) }}" maxlength="50">
                <span class="hint">Binanın bağımsız bölüm listesi girilmemiş; daireyi elle yazın.</span>
            </div>
        </div>
        @error('daire_no')<span class="error">{{ $message }}</span>@enderror
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">2</span>
            <div class="form-section-head-text">
                <h3>Lojman Bilgileri</h3>
                <small>Kat, oda ve alan seçilen bağımsız bölümden gelir; gerekirse düzeltin.</small>
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field">
                <label for="lj-ad">Lojman Adı <span class="required">*</span></label>
                <input type="text" id="lj-ad" name="ad" class="form-input" value="{{ old('ad', $lojman?->ad) }}" maxlength="200" placeholder="Örn. Merkez Lojmanları A Blok Daire 3">
                <span class="hint">Boş bırakırsanız mahalle, blok ve daireden önerilir.</span>
            </div>
            <div class="form-field">
                <label for="lj-tip">Tahsis Türü</label>
                <input type="text" id="lj-tip" name="tip" class="form-input" value="{{ old('tip', $lojman?->tip) }}" maxlength="40" list="lj-tipler" placeholder="Seçin ya da yazın">
                <datalist id="lj-tipler">@foreach (Lojman::TIPLER as $t)<option value="{{ $t }}">@endforeach</datalist>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="lj-kat">Kat</label>
                <input type="number" id="lj-kat" name="kat" class="form-input" value="{{ old('kat', $lojman?->kat) }}" min="-5" max="99" placeholder="0 = zemin, -1 = bodrum">
                @error('kat')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="lj-oda">Oda Sayısı</label>
                <input type="number" id="lj-oda" name="oda_sayisi" class="form-input" value="{{ old('oda_sayisi', $lojman?->oda_sayisi) }}" min="0" max="20" placeholder="3 (3+1 için)">
            </div>
            <div class="form-field">
                <label for="lj-alan">Alan (m²)</label>
                <input type="text" id="lj-alan" name="alan_m2" class="form-input td-mono" value="{{ $alan }}" inputmode="decimal" placeholder="95,50">
                @error('alan_m2')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">3</span>
            <div class="form-section-head-text">
                <h3>Durum</h3>
                <small>“Dolu” seçilmez; tahsis girildiğinde kendiliğinden dolu olur, tahsis bitince boşa düşer. İşgalde, bakımda ve kullanım dışı lojmana tahsis yapılmaz.</small>
            </div>
        </div>
        @if ($aktifTahsis)
            <div class="lj-bilgi-kutu">
                <strong>Lojman şu an dolu</strong> — {{ $aktifTahsis->ad_soyad }} adına {{ $aktifTahsis->baslangic_tarihi->format('d.m.Y') }}’den beri tahsisli.
                Durumunu değiştirmek için önce lojman ekranından tahsisi sonlandırın.
            </div>
            <input type="hidden" name="durum" value="bos">
        @else
            <div class="lj-secim" role="radiogroup" aria-label="Durum">
                @foreach (Lojman::ELLE_DURUMLAR as $d)
                    <label class="lj-secim-oge is-{{ $d }}">
                        <input type="radio" name="durum" value="{{ $d }}" @checked($seciliDurum === $d)>
                        <span>{{ Lojman::DURUMLAR[$d] }}</span>
                    </label>
                @endforeach
            </div>
            @error('durum')<span class="error">{{ $message }}</span>@enderror
        @endif
        <div class="form-row">
            <div class="form-field">
                <label for="lj-aciklama">Açıklama</label>
                <textarea id="lj-aciklama" name="aciklama" class="form-textarea" rows="3" maxlength="5000">{{ old('aciklama', $lojman?->aciklama) }}</textarea>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ $duzenleme ? route('panel.lojman.detay', $lojman->id) : route('panel.lojman.index') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit" id="lj-kaydet">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            {{ $duzenleme ? 'Değişiklikleri Kaydet' : 'Lojmanı Kaydet' }}
        </button>
    </div>
</form>

<script>
(function () {
    'use strict';
    const URL_ILCE = @json(url('/panel/ajax/ilceler'));
    const URL_MAH = @json(url('/panel/ajax/mahalleler'));
    const URL_BIRIM = @json(route('panel.lojman.tasinmaz-onizle'));
    const URL_LOJMAN = @json(url('/panel/lojman'));
    const CSRF = @json(csrf_token());
    const HARIC = @json($lojman?->id);
    const $ = id => document.getElementById(id);
    const il = $('lj-il'), ilce = $('lj-ilce'), mah = $('lj-mahalle'), ada = $('lj-ada'), parsel = $('lj-parsel');
    const tsn = $('lj-tasinmaz'), blok = $('lj-blok'), daire = $('lj-daire'), serbestKutu = $('lj-serbest');
    const adAlan = $('lj-ad'), kat = $('lj-kat'), oda = $('lj-oda'), alan = $('lj-alan');
    const liste = $('lj-birimler');
    const esc = s => (s == null ? '' : String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'));
    const norm = s => String(s ?? '').toLocaleUpperCase('tr-TR').replace(/[\s\-_.]+/g, '').replace(/^(BLOK|BLOĞU)|(BLOK|BLOĞU)$/g, '').replace(/^0+(?=\d)/, '');
    let birimler = [];

    async function doldur(sel, url, bos) {
        sel.innerHTML = `<option value="">${bos}</option>`;
        sel.disabled = !url;
        if (!url) return;
        try { (await (await fetch(url, { headers: { Accept: 'application/json' } })).json() || []).forEach(x => sel.add(new Option(x.ad, x.id))); }
        catch (e) { sel.innerHTML = '<option value="">— Yüklenemedi —</option>'; }
    }
    il.addEventListener('change', () => { doldur(ilce, il.value ? URL_ILCE + '/' + il.value : null, il.value ? '— İlçe seçin —' : '— Önce il seçin —'); doldur(mah, null, '— Önce ilçe seçin —'); temizle(); });
    ilce.addEventListener('change', () => { doldur(mah, ilce.value ? URL_MAH + '/' + ilce.value : null, ilce.value ? '— Mahalle seçin —' : '— Önce ilçe seçin —'); temizle(); });
    mah.addEventListener('change', gecikmeli);
    [ada, parsel].forEach(el => el.addEventListener('input', gecikmeli));

    function temizle() {
        tsn.value = '';
        serbestKutu.hidden = true;
        liste.innerHTML = '<div class="lj-esle-kutu is-bos"><div>Mahalle, ada ve parsel girilince birimler burada listelenir.</div></div>';
    }

    let zaman = null, sira = 0;
    function gecikmeli() { clearTimeout(zaman); zaman = setTimeout(getir, 400); }

    async function getir(ilkYukleme = false) {
        if (!mah.value || !ada.value.trim() || !parsel.value.trim()) { if (!ilkYukleme) temizle(); return; }
        const s = ++sira;
        liste.innerHTML = '<div class="lj-esle-kutu is-arama"><span class="lj-donen"></span><div>Taşınmaz envanterinde aranıyor…</div></div>';
        try {
            const r = await fetch(URL_BIRIM, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
                body: JSON.stringify({ mahalle_id: mah.value, ada: ada.value.trim(), parsel: parsel.value.trim(), haric: HARIC }),
            });
            const v = await r.json();
            if (s !== sira) return;
            birimler = v.birimler || [];
            ciz(v.mesaj, ilkYukleme);
        } catch (e) {
            if (s === sira) liste.innerHTML = '<div class="lj-esle-kutu is-uyari"><div>Taşınmaz listesi alınamadı.</div></div>';
        }
    }

    function ciz(mesaj, ilkYukleme) {
        let html = '';
        if (mesaj) html += `<div class="lj-esle-kutu ${birimler.length ? 'is-uyari' : 'is-bilgi'}"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg><div>${esc(mesaj)}</div></div>`;
        if (birimler.length) {
            html += '<div class="lj-birim-liste" role="radiogroup" aria-label="Birim seçimi">';
            birimler.forEach((b, i) => {
                const durum = b.lojman
                    ? `<a href="${URL_LOJMAN}/${b.lojman.id}" target="_blank" class="lj-birim-not is-var">Zaten lojman: ${esc(b.lojman.ad)}</a>`
                    : (b.sebep ? `<span class="lj-birim-not is-hayir">${esc(b.sebep)}</span>` : '<span class="lj-birim-not is-uygun">Uygun</span>');
                const ozellik = [b.oda ? b.oda + '+1' : null, b.alan ? Number(b.alan).toLocaleString('tr-TR') + ' m²' : null].filter(Boolean).join(' · ');
                html += `<label class="lj-birim ${b.uygun ? '' : 'is-kapali'}">
                    <input type="radio" name="_birim" value="${i}" ${b.uygun ? '' : 'disabled'}>
                    <span class="lj-birim-govde">
                        <strong>${esc(b.etiket)}</strong>
                        <small>${esc(b.alt)}${ozellik ? ' · ' + esc(ozellik) : ''}</small>
                        ${durum}
                    </span>
                </label>`;
            });
            html += '</div>';
        }
        liste.innerHTML = html;

        // Düzenlemede / form hatasından dönüşte mevcut seçimi işaretle
        const secili = birimler.findIndex(b => String(b.tasinmaz_id) === String(tsn.value)
            && (b.serbest || (norm(b.blok) === norm(blok.value) && norm(b.daire) === norm(daire.value))));
        if (secili >= 0) {
            const r = liste.querySelector(`input[value="${secili}"]`);
            if (r) { r.disabled = false; r.checked = true; r.closest('.lj-birim').classList.remove('is-kapali'); }
            sec(secili, true);
        } else if (!ilkYukleme) {
            tsn.value = '';
            serbestKutu.hidden = true;
            const uygunlar = birimler.map((b, i) => b.uygun ? i : -1).filter(i => i >= 0);
            if (uygunlar.length === 1) { liste.querySelector(`input[value="${uygunlar[0]}"]`).checked = true; sec(uygunlar[0]); }
        }
    }

    function sec(i, korunsun = false) {
        const b = birimler[i];
        if (!b) return;
        tsn.value = b.tasinmaz_id;
        serbestKutu.hidden = !b.serbest;
        if (!b.serbest) { blok.value = b.blok ?? ''; daire.value = b.daire ?? ''; }
        if (korunsun) return;
        if (kat.value === '' && b.kat !== null) kat.value = b.kat;
        if (!oda.value && b.oda) oda.value = b.oda;
        if (!alan.value && b.alan) alan.value = Number(b.alan).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace(/\./g, '');
    }
    liste.addEventListener('change', e => { if (e.target.name === '_birim') sec(+e.target.value); });

    $('lj-form').addEventListener('submit', e => {
        if (!tsn.value) {
            e.preventDefault();
            liste.scrollIntoView({ behavior: 'smooth', block: 'center' });
            liste.insertAdjacentHTML('afterbegin', '<div class="lj-esle-kutu is-hata"><div><strong>Bir birim seçin.</strong> Lojman taşınmaz envanterindeki bir binadan seçilmelidir.</div></div>');
            return;
        }
        if (!adAlan.value.trim()) {
            const parca = [mah.selectedOptions[0]?.value ? mah.selectedOptions[0].text : '', blok.value.trim() ? blok.value.trim() + ' Blok' : '', daire.value.trim() ? 'Daire ' + daire.value.trim() : ''];
            adAlan.value = parca.filter(Boolean).join(' ') || ('Ada ' + ada.value + ' Parsel ' + parsel.value);
        }
    });

    if (mah.value && ada.value && parsel.value) getir(true);
})();
</script>
