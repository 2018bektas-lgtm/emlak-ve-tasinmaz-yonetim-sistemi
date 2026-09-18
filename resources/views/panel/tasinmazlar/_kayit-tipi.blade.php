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
@endphp

<section class="form-section kt-section" data-kt-section>
    <div class="form-section-head kt-head">
        <span class="form-section-num kt-num">00</span>
        <div class="form-section-head-text">
            <h3>Kayıt Tipi</h3>
            <small>Bu taşınmazın <strong>fiili durumu</strong> nedir? Kayıt tipi Web Tapu niteliğinden bağımsızdır — tapuda "arsa" görünen bir parselin üzerinde bina bulunabilir.</small>
        </div>
    </div>

    <details class="kt-yardim">
        <summary>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9 a2.5 2.5 0 0 1 5 0 c0 2-2.5 2-2.5 4"/><path d="M12 17.5 v.5"/></svg>
            <span>Hangi tipi seçmeliyim?</span>
        </summary>
        <div class="kt-yardim-govde">
            <p><strong>Kat mülkiyetli BBN'de</strong> her daire tapuda kendi TAKBİS zemin numarasını taşır — o daireye ait ayrı bir tapu kaydı vardır.</p>
            <p><strong>Kat mülkiyetsiz binada</strong> tapuda tek "arsa" kaydı görünür ama üzerinde bir bina olabilir. Tüm daireler bu tek taşınmaz altında listelenir; her BBN'nin ayrı tapusu yoktur.</p>
            <p><strong>Boş parselde</strong> yapı hiç yoktur (arsa/tarla/bağ/mera).</p>
        </div>
    </details>

    <div class="kt-secim kt-secim-3" role="radiogroup" aria-label="Kayıt tipi">
        {{-- 1) Boş Parsel --}}
        <label class="kt-kart kt-kart-v {{ $secili === 'bos_parsel' ? 'is-secili' : '' }}" data-kt-value="bos_parsel" data-kt-tema="arsa">
            <input type="radio" name="kayit_tipi" value="bos_parsel" @checked($secili === 'bos_parsel') data-kt-input>
            <div class="kt-sahne kt-sahne-arsa" aria-hidden="true">
                <svg viewBox="0 0 300 64" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="gk-gok-arsa" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#c7ebd1"/>
                            <stop offset="100%" stop-color="#e9f7ec"/>
                        </linearGradient>
                    </defs>
                    <rect width="300" height="64" fill="url(#gk-gok-arsa)"/>
                    <path d="M0 44 Q60 32 120 40 T240 40 T300 42 L300 64 L0 64 Z" fill="#8ac89a" opacity=".55"/>
                    <path d="M0 52 Q80 46 160 50 T300 50 L300 64 L0 64 Z" fill="#4e9c65"/>
                    <g fill="none" stroke="#2f6b41" stroke-width="1.2" stroke-linecap="round">
                        <path d="M40 60 v-4"/><path d="M80 60 v-3"/><path d="M130 58 v-5"/>
                        <path d="M180 60 v-4"/><path d="M220 58 v-3"/><path d="M260 60 v-4"/>
                    </g>
                    <circle cx="248" cy="18" r="9" fill="#ffd76b"/>
                    <circle cx="248" cy="18" r="9" fill="none" stroke="#ffb545" stroke-width="1" opacity=".7"/>
                    <g transform="translate(60 42)">
                        <rect x="-1" y="0" width="2" height="10" fill="#8a5a2b"/>
                        <circle cx="0" cy="-3" r="6" fill="#3f8e57"/>
                    </g>
                </svg>
            </div>
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
            <div class="kt-sahne kt-sahne-tek" aria-hidden="true">
                <svg viewBox="0 0 300 64" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="gk-gok-tek" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#d9e6ff"/>
                            <stop offset="100%" stop-color="#eef4ff"/>
                        </linearGradient>
                    </defs>
                    <rect width="300" height="64" fill="url(#gk-gok-tek)"/>
                    <rect x="0" y="56" width="300" height="8" fill="#c9d3e4"/>
                    <rect x="30" y="34" width="24" height="22" fill="#8b98b0" opacity=".5"/>
                    <rect x="240" y="38" width="26" height="18" fill="#8b98b0" opacity=".5"/>
                    {{-- Ön bina --}}
                    <rect x="110" y="18" width="80" height="38" fill="#f6f2e6" stroke="#c1b48f" stroke-width="1.2"/>
                    <path d="M106 18 L150 6 L194 18 Z" fill="#a67c3b"/>
                    <g fill="#0e1726">
                        <rect x="118" y="26" width="8" height="8"/><rect x="132" y="26" width="8" height="8"/>
                        <rect x="160" y="26" width="8" height="8"/><rect x="174" y="26" width="8" height="8"/>
                        <rect x="118" y="42" width="8" height="8"/><rect x="174" y="42" width="8" height="8"/>
                    </g>
                    {{-- Kat mülkiyetli tek daire (altın çerçeve) --}}
                    <rect x="142" y="38" width="16" height="18" fill="#d8bc8c" opacity=".9"/>
                    <rect x="142" y="38" width="16" height="18" fill="none" stroke="#0e1726" stroke-width="1.6"/>
                    <rect x="146" y="44" width="4" height="10" fill="#0e1726"/>
                    {{-- Tapu rozeti --}}
                    <g transform="translate(214 8)">
                        <rect x="0" y="0" width="34" height="24" rx="3" fill="#fff" stroke="#0e1726" stroke-width="1.2"/>
                        <path d="M5 7 h18 M5 12 h18 M5 17 h12" stroke="#0e1726" stroke-width="1" stroke-linecap="round"/>
                        <circle cx="28" cy="20" r="5" fill="#d8bc8c" stroke="#0e1726" stroke-width="1"/>
                        <path d="M26 20 l1.5 1.5 l3-3" fill="none" stroke="#0e1726" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                    </g>
                </svg>
            </div>
            <div class="kt-kart-icerik">
                <div class="kt-kart-basluk">
                    <strong>Kat Mülkiyetli BBN</strong>
                    <span class="kt-kart-check" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12 l5 5 L20 7"/></svg>
                    </span>
                </div>
                <p class="kt-kart-aciklama">Kendi TAKBİS numarası olan <strong>tek</strong> bağımsız bölüm.</p>
                <div class="kt-kart-ornek"><span>Örnek</span> Ayrı tapulu daire · dükkan · ofis</div>
            </div>
        </label>

        {{-- 3) Kat Mülkiyetsiz Bina --}}
        <label class="kt-kart kt-kart-v {{ $secili === 'kat_mulkiyetsiz_bina' ? 'is-secili' : '' }}" data-kt-value="kat_mulkiyetsiz_bina" data-kt-tema="coklu">
            <input type="radio" name="kayit_tipi" value="kat_mulkiyetsiz_bina" @checked($secili === 'kat_mulkiyetsiz_bina') data-kt-input>
            <div class="kt-sahne kt-sahne-coklu" aria-hidden="true">
                <svg viewBox="0 0 300 64" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="gk-gok-coklu" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#e0d8ff"/>
                            <stop offset="100%" stop-color="#f0eaff"/>
                        </linearGradient>
                    </defs>
                    <rect width="300" height="64" fill="url(#gk-gok-coklu)"/>
                    <rect x="0" y="58" width="300" height="6" fill="#c9c2e0"/>
                    {{-- Sol silüet --}}
                    <rect x="20" y="30" width="30" height="28" fill="#8677b8" opacity=".5"/>
                    {{-- Ana bina --}}
                    <rect x="90" y="10" width="120" height="48" fill="#3f356b"/>
                    <rect x="90" y="10" width="120" height="4" fill="#0e1726"/>
                    <g fill="#f5d47a">
                        @for ($sat = 0; $sat < 3; $sat++)
                            @for ($kol = 0; $kol < 6; $kol++)
                                <rect x="{{ 98 + $kol * 18 }}" y="{{ 20 + $sat * 12 }}" width="8" height="6"/>
                            @endfor
                        @endfor
                    </g>
                    {{-- Kapı --}}
                    <rect x="146" y="46" width="8" height="12" fill="#0e1726"/>
                    {{-- Sağ silüet --}}
                    <rect x="240" y="38" width="30" height="20" fill="#8677b8" opacity=".5"/>
                    {{-- Uyarı: "TAPU: ARSA" rozeti --}}
                    <g transform="translate(6 8)">
                        <rect x="0" y="0" width="70" height="18" rx="3" fill="#fff8e6" stroke="#d97706" stroke-width="1.2"/>
                        <text x="35" y="12" text-anchor="middle" font-family="Manrope, sans-serif" font-size="8" font-weight="800" fill="#7a5b0a" letter-spacing=".08em">TAPU: ARSA</text>
                    </g>
                </svg>
            </div>
            <div class="kt-kart-icerik">
                <div class="kt-kart-basluk">
                    <strong>Kat Mülkiyetsiz Bina</strong>
                    <span class="kt-kart-check" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12 l5 5 L20 7"/></svg>
                    </span>
                </div>
                <p class="kt-kart-aciklama">Tapuda "arsa" görünür ama üzerinde bina var — BBN'lerin ayrı tapusu yok.</p>
                <div class="kt-kart-ornek"><span>Örnek</span> Kat mülkiyeti tesis edilmemiş apartman</div>
            </div>
        </label>
    </div>

    {{-- uzeri_bina_var_mi kayit_tipi'nden türetilir --}}
    <input type="hidden" name="uzeri_bina_var_mi" value="{{ $secili && $secili !== 'bos_parsel' ? 1 : 0 }}" data-kt-bina-hidden>

    <p class="kt-uyari" data-kt-uyari {{ $secili !== null ? 'hidden' : '' }}>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8 v4 M12 16 v0.01"/></svg>
        Devam etmek için kayıt tipini seçin.
    </p>
</section>

<script>
(function () {
    const bolum07 = document.querySelector('[data-bolum="bbn"]');
    const tekPanel = document.querySelector('[data-bbn-mode="tek"]');
    const cokluPanel = document.querySelector('[data-bbn-mode="coklu"]');
    const kartlar = document.querySelectorAll('.kt-kart');
    const inputlar = document.querySelectorAll('[data-kt-input]');
    const uyari = document.querySelector('[data-kt-uyari]');
    const binaHidden = document.querySelector('[data-kt-bina-hidden]');
    const form = document.querySelector('form.form-shell');

    function bbnAlanTemizle(panel) {
        if (! panel) return;
        panel.querySelectorAll('input[name^="yapi["], input[name^="yapilar["], select[name^="yapi["], select[name^="yapilar["], textarea[name^="yapi["], textarea[name^="yapilar["]').forEach(el => {
            if (el.type === 'checkbox' || el.type === 'radio') el.checked = false;
            else if (el.type !== 'hidden') el.value = '';
        });
    }

    function uygula(deger) {
        kartlar.forEach(k => k.classList.toggle('is-secili', k.dataset.ktValue === deger));
        if (uyari) uyari.hidden = true;
        if (binaHidden) binaHidden.value = (deger && deger !== 'bos_parsel') ? '1' : '0';

        if (! bolum07) return;
        const gorunur = deger !== 'bos_parsel';
        bolum07.hidden = ! gorunur;
        if (! gorunur) {
            bbnAlanTemizle(tekPanel);
            bbnAlanTemizle(cokluPanel);
            return;
        }
        if (tekPanel) tekPanel.hidden = (deger !== 'kat_mulkiyetli');
        if (cokluPanel) cokluPanel.hidden = (deger !== 'kat_mulkiyetsiz_bina');
    }

    inputlar.forEach(inp => inp.addEventListener('change', () => uygula(inp.value)));
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
})();
</script>
