@php
    use App\Enums\KayitTipi;

    /**
     * "Kayıt Tipi" ilk adımı — 3 senaryo. Bkz. App\Enums\KayitTipi.
     */
    $tasinmazMevcut = $tasinmazMevcut ?? null;
    $mevcut = old('kayit_tipi', optional($tasinmazMevcut?->kayit_tipi)->value ?? null);
    if (! $mevcut && $tasinmazMevcut) {
        if ($tasinmazMevcut->uzeri_bina_var_mi) {
            $mevcut = $tasinmazMevcut->yapilar->count() > 1
                ? KayitTipi::KatMulkiyetsizBina->value
                : KayitTipi::KatMulkiyetli->value;
        } else {
            $mevcut = KayitTipi::BosParsel->value;
        }
    }
    $secili = $mevcut ?: null;

    // Düzenlemede: kaydın veritabanındaki tipi ve bağımsız bölüm sayısı — tip
    // değiştirilirse veri kaybı uyarısı için.
    $orijinalTip = $tasinmazMevcut
        ? (optional($tasinmazMevcut->kayit_tipi)->value ?? ($tasinmazMevcut->uzeri_bina_var_mi
            ? ($tasinmazMevcut->yapilar->count() > 1 ? 'kat_mulkiyetsiz_bina' : 'kat_mulkiyetli')
            : 'bos_parsel'))
        : null;
    $yapiSayisi = $tasinmazMevcut ? $tasinmazMevcut->yapilar->count() : 0;
@endphp

<section class="form-section kt-section" data-kt-section
         @if ($orijinalTip) data-kt-orijinal="{{ $orijinalTip }}" data-kt-yapi-sayisi="{{ $yapiSayisi }}" @endif>
    <div class="form-section-head kt-head">
        <span class="form-section-num kt-num">00</span>
        <div class="form-section-head-text">
            <h3>Kayıt Tipi</h3>
            @if ($tasinmazMevcut)
                <small>Kaydın tipi. Değiştirirseniz form ilgili alanları buna göre düzenler; veri kaybı olacaksa burada uyarılırsınız.</small>
            @else
                <small>Önce kaydın tipini seçin — form, seçtiğiniz tipe göre ilgili alanları açar.</small>
            @endif
        </div>
    </div>

    <details class="kt-yardim">
        <summary>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9 a2.5 2.5 0 0 1 5 0 c0 2-2.5 2-2.5 4"/><path d="M12 17.5 v.5"/></svg>
            <span>Hangi tipi seçmeliyim?</span>
        </summary>
        <div class="kt-yardim-govde">
            <p><strong>Kat mülkiyetli BBN:</strong> Tapu kaydı bir bağımsız bölüme (daire, dükkân, ofis) aittir. Her bölümün kendi tapu kaydı, arsa payı ve hissedarları olduğu için her bölüm <em>ayrı kayıt</em> olarak girilir.</p>
            <p><strong>Kat mülkiyetsiz bina:</strong> Tapuda yalnızca ana taşınmaz (parsel) kaydı vardır ama üzerinde bina bulunur. Bölümlerin ayrı tapusu yoktur; hepsi bu kaydın altında listelenir.</p>
            <p><strong>Boş parsel:</strong> Üzerinde yapı yoktur — arsa, tarla, bağ, mera vb.</p>
        </div>
    </details>

    <div class="kt-secim kt-secim-3" role="radiogroup" aria-label="Kayıt tipi">
        {{-- 1) Boş Parsel --}}
        <label class="kt-kart kt-kart-v {{ $secili === 'bos_parsel' ? 'is-secili' : '' }}" data-kt-value="bos_parsel" data-kt-tema="arsa">
            <input type="radio" name="kayit_tipi" value="bos_parsel" @checked($secili === 'bos_parsel') data-kt-input>
            <span class="kt-ikon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 19 9 10l4 6 3-4 5 7Z"/><circle cx="17" cy="6" r="2"/></svg></span>
            <div class="kt-kart-icerik">
                <div class="kt-kart-basluk">
                    <strong>Boş Parsel</strong>
                    <span class="kt-kart-check" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12 l5 5 L20 7"/></svg>
                    </span>
                </div>
                <p class="kt-kart-aciklama">Üzerinde yapı yok — arsa, tarla, bağ, mera vb.</p>
                <div class="kt-kart-ornek"><span>Örnek</span> Tarım arazisi · imar parseli</div>
            </div>
        </label>

        {{-- 2) Kat Mülkiyetli BBN --}}
        <label class="kt-kart kt-kart-v {{ $secili === 'kat_mulkiyetli' ? 'is-secili' : '' }}" data-kt-value="kat_mulkiyetli" data-kt-tema="tek">
            <input type="radio" name="kayit_tipi" value="kat_mulkiyetli" @checked($secili === 'kat_mulkiyetli') data-kt-input>
            <span class="kt-ikon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h1.5M13.5 7H15M9 11h1.5M13.5 11H15"/><rect x="10" y="15" width="4" height="6" rx="0.6" fill="currentColor" stroke="none"/></svg></span>
            <div class="kt-kart-icerik">
                <div class="kt-kart-basluk">
                    <strong>Kat Mülkiyetli BBN</strong>
                    <span class="kt-kart-check" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12 l5 5 L20 7"/></svg>
                    </span>
                </div>
                <p class="kt-kart-aciklama">Kendi tapusu olan <strong>tek</strong> bağımsız bölüm.</p>
                <div class="kt-kart-ornek"><span>Örnek</span> Ayrı tapulu daire · dükkan · ofis</div>
            </div>
        </label>

        {{-- 3) Kat Mülkiyetsiz Bina --}}
        <label class="kt-kart kt-kart-v {{ $secili === 'kat_mulkiyetsiz_bina' ? 'is-secili' : '' }}" data-kt-value="kat_mulkiyetsiz_bina" data-kt-tema="coklu">
            <input type="radio" name="kayit_tipi" value="kat_mulkiyetsiz_bina" @checked($secili === 'kat_mulkiyetsiz_bina') data-kt-input>
            <span class="kt-ikon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V10l5-3v14M8 21V4h13v17M2 21h20"/><path d="M12 8h1M16 8h1M12 12h1M16 12h1M12 16h1M16 16h1"/></svg></span>
            <div class="kt-kart-icerik">
                <div class="kt-kart-basluk">
                    <strong>Kat Mülkiyetsiz Bina</strong>
                    <span class="kt-kart-check" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12 l5 5 L20 7"/></svg>
                    </span>
                </div>
                <p class="kt-kart-aciklama">Tapuda tek parsel kaydı, üzerinde bina var — bölümlerin ayrı tapusu yok.</p>
                <div class="kt-kart-ornek"><span>Örnek</span> Kat mülkiyeti tesis edilmemiş apartman</div>
            </div>
        </label>
    </div>

    {{-- uzeri_bina_var_mi kayit_tipi'nden türetilir --}}
    <input type="hidden" name="uzeri_bina_var_mi" value="{{ $secili && $secili !== 'bos_parsel' ? 1 : 0 }}" data-kt-bina-hidden>

    <p class="kt-uyari" data-kt-uyari hidden>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8 v4 M12 16 v0.01"/></svg>
        Devam etmek için kayıt tipini seçin.
    </p>

    {{-- Düzenlemede tip değişikliği veri kaybına yol açacaksa gösterilir --}}
    <div class="kt-degisim" data-kt-degisim role="alert" hidden>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg>
        <span data-kt-degisim-metin></span>
    </div>
</section>

<script>
// Formun altındaki bölümler (BBN panelleri vb.) bu script çalıştığında henüz
// DOM'da olmadığı için başlatma DOMContentLoaded'a ertelenir.
document.addEventListener('DOMContentLoaded', function () {
    const bolum07 = document.querySelector('[data-bolum="bbn"]');
    const tekPanel = document.querySelector('[data-bbn-mode="tek"]');
    const cokluPanel = document.querySelector('[data-bbn-mode="coklu"]');
    const kartlar = document.querySelectorAll('.kt-kart');
    const inputlar = document.querySelectorAll('[data-kt-input]');
    const uyari = document.querySelector('[data-kt-uyari]');
    const binaHidden = document.querySelector('[data-kt-bina-hidden]');
    const form = document.querySelector('form.form-shell');

    // Düzenlemede tip değişikliği mevcut bağımsız bölümleri silecekse uyar
    const bolumKt = document.querySelector('[data-kt-section]');
    const orijinal = bolumKt?.dataset.ktOrijinal || null;
    const yapiSayisi = parseInt(bolumKt?.dataset.ktYapiSayisi || '0', 10);
    const degisimKutu = document.querySelector('[data-kt-degisim]');
    const degisimMetin = document.querySelector('[data-kt-degisim-metin]');

    function degisimUyarisi(deger) {
        if (! degisimKutu || ! orijinal) return;
        let mesaj = '';
        if (deger !== orijinal && yapiSayisi > 0) {
            if (deger === 'bos_parsel') {
                mesaj = 'Kayıt tipini Boş Parsel yaparsanız bu kayda bağlı ' + yapiSayisi
                    + ' bağımsız bölüm kaydı (tapu, karar ve resimleriyle birlikte) kaydettiğinizde silinir.';
            } else if (deger === 'kat_mulkiyetli' && yapiSayisi > 1) {
                mesaj = 'Kat mülkiyetli kayıt tek bir bağımsız bölüm taşır: binadaki ' + yapiSayisi
                    + ' bölümden yalnızca ilki korunur, diğer ' + (yapiSayisi - 1) + ' bölüm kaydettiğinizde silinir.';
            }
        }
        degisimMetin.textContent = mesaj;
        degisimKutu.hidden = mesaj === '';
    }

    const etiketler = {
        bos_parsel: 'Boş Parsel',
        kat_mulkiyetli: 'Kat Mülkiyetli BBN',
        kat_mulkiyetsiz_bina: 'Kat Mülkiyetsiz Bina',
    };

    // data-tip-metin / data-tip-placeholder: {"kat_mulkiyetli": "...", "varsayilan": "..."}
    function metinleriUygula(deger) {
        const sec = (el, attr) => {
            try {
                const harita = JSON.parse(el.getAttribute(attr));
                return harita[deger] ?? harita.varsayilan ?? null;
            } catch (e) { return null; }
        };
        document.querySelectorAll('[data-tip-metin]').forEach(el => {
            const m = sec(el, 'data-tip-metin');
            if (m !== null) el.innerHTML = m;
        });
        document.querySelectorAll('[data-tip-placeholder]').forEach(el => {
            const m = sec(el, 'data-tip-placeholder');
            if (m !== null) el.placeholder = m;
        });
    }

    // Görünen bölümleri 01, 02 … diye yeniden numaralandır (kayıt tipi bölümü 00 kalır)
    function numaralandir() {
        if (! form) return;
        let n = 0;
        form.querySelectorAll('section.form-section:not(.kt-section)').forEach(sec => {
            if (sec.hidden || sec.offsetParent === null) return;
            const num = sec.querySelector('.form-section-num');
            if (num) num.textContent = String(++n).padStart(2, '0');
        });
    }

    function uygula(deger) {
        kartlar.forEach(k => k.classList.toggle('is-secili', k.dataset.ktValue === deger));
        if (uyari) uyari.hidden = true;
        if (binaHidden) binaHidden.value = (deger && deger !== 'bos_parsel') ? '1' : '0';
        if (form) form.dataset.kayitTipi = deger;
        document.querySelectorAll('[data-tf-ozet]').forEach(el => { el.textContent = etiketler[deger] || ''; });
        metinleriUygula(deger);

        // Alanlar temizlenmez: sunucu seçili tipe ait olmayan BBN verisini zaten yok
        // sayar (prepareForValidation). Temizlemek, kullanıcı tipe geri döndüğünde
        // tabloda görünen daireleri formdan sessizce düşürüp silinmesine yol açıyordu.
        if (bolum07) {
            bolum07.hidden = deger === 'bos_parsel';
            if (tekPanel) tekPanel.hidden = (deger !== 'kat_mulkiyetli');
            if (cokluPanel) cokluPanel.hidden = (deger !== 'kat_mulkiyetsiz_bina');
        }

        degisimUyarisi(deger);
        numaralandir();
        // Gizliyken başlatılan harita boyutunu yeniden hesaplasın
        requestAnimationFrame(() => window.dispatchEvent(new Event('resize')));
    }

    inputlar.forEach(inp => inp.addEventListener('change', () => {
        const ilkSecim = form && ! form.dataset.kayitTipi;
        uygula(inp.value);
        if (ilkSecim) {
            const hedef = form.querySelector('[data-tip-adim]:not([hidden])');
            hedef?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }));
    const secili = document.querySelector('[data-kt-input]:checked');
    if (secili) uygula(secili.value);
    else if (bolum07) bolum07.hidden = true;

    if (form) {
        form.addEventListener('submit', (e) => {
            const cur = document.querySelector('[data-kt-input]:checked');
            if (! cur) {
                e.preventDefault();
                if (uyari) uyari.hidden = false;
                document.querySelector('[data-kt-section]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }
});
</script>
