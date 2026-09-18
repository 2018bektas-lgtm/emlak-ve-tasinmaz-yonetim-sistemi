/* ozel-select.js — Native <select> öğelerini şık custom dropdown ile değiştirir.
 * - Form submit: native select değeri gönderilir (submit uyumu korunur)
 * - Cascade uyumu: value değiştiğinde native select 'change' event tetiklenir
 * - Klavye: ↑ ↓ Enter Esc, ilk harfe atlama, arama (yazınca filtre)
 * - Opt-out: <select data-native> elemanlar dokunulmaz
 * - Multiple / size>1 desteklenmez (native kalır)
 */
(function () {
    'use strict';

    const OZEL_SINIFI = 'oz-select';
    const KURULDU = 'ozSelectKuruldu';

    function normalize(s) {
        return (s || '').toLocaleLowerCase('tr').replace(/\s+/g, ' ').trim();
    }

    function optionsOku(select) {
        return Array.from(select.options).map((o, i) => ({
            index: i,
            deger: o.value,
            etiket: o.textContent.trim(),
            disabled: o.disabled,
            secili: o.selected,
        }));
    }

    function isCustomable(select) {
        if (! (select instanceof HTMLSelectElement)) return false;
        if (select.dataset.native !== undefined) return false;
        if (select.multiple) return false;
        if (select.size && select.size > 1) return false;
        if (select.dataset[KURULDU]) return false;
        // Görünmeyen / display: none select'lere de dokunmalıyız — hidden değil test etme
        return true;
    }

    function kurCustomSelect(select) {
        if (!isCustomable(select)) return;
        select.dataset[KURULDU] = '1';

        const sarici = document.createElement('div');
        sarici.className = OZEL_SINIFI;
        // Etiket için data attributeleri
        sarici.tabIndex = -1;

        // Native select'i gizle ama DOM'da tut (submit için)
        select.style.position = 'absolute';
        select.style.opacity = '0';
        select.style.pointerEvents = 'none';
        select.style.width = '1px';
        select.style.height = '1px';
        select.style.top = '0';
        select.style.left = '0';

        select.parentNode.insertBefore(sarici, select);
        sarici.appendChild(select);

        // Trigger button
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'oz-btn';
        btn.setAttribute('aria-haspopup', 'listbox');
        btn.setAttribute('aria-expanded', 'false');
        btn.innerHTML =
            '<span class="oz-btn-metin"></span>' +
            '<svg class="oz-btn-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>';
        sarici.appendChild(btn);

        // Panel
        const panel = document.createElement('div');
        panel.className = 'oz-panel';
        panel.setAttribute('role', 'listbox');
        panel.hidden = true;
        panel.innerHTML =
            '<div class="oz-ara-wrap">' +
                '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>' +
                '<input type="text" class="oz-ara" placeholder="Ara..." autocomplete="off">' +
            '</div>' +
            '<ul class="oz-liste"></ul>' +
            '<div class="oz-bos" hidden>Sonuç yok</div>';
        document.body.appendChild(panel); // body'ye taşı — z-index / overflow sorunu olmaz
        panel._sahip = select;

        const araInput = panel.querySelector('.oz-ara');
        const liste = panel.querySelector('.oz-liste');
        const bosEl = panel.querySelector('.oz-bos');

        // Listeyi doldur
        function doldur(filtre) {
            const q = normalize(filtre || '');
            liste.innerHTML = '';
            const items = optionsOku(select);
            let gorunen = 0;
            let secilenIdx = -1;
            items.forEach(o => {
                const eslesti = q === '' || normalize(o.etiket).includes(q);
                if (! eslesti) return;
                gorunen++;
                const li = document.createElement('li');
                li.className = 'oz-option';
                if (o.secili) { li.classList.add('is-secili'); secilenIdx = liste.children.length; }
                if (o.disabled) li.classList.add('is-disabled');
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', o.secili ? 'true' : 'false');
                li.dataset.deger = o.deger;
                li.innerHTML =
                    '<span class="oz-option-metin">' + (o.etiket.replace(/[<>]/g, '') || '&nbsp;') + '</span>' +
                    (o.secili ? '<svg class="oz-option-tik" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>' : '');
                liste.appendChild(li);
            });
            bosEl.hidden = gorunen > 0;
            return secilenIdx;
        }

        // Trigger metnini güncelle
        function btnMetnini_guncelle() {
            const secili = select.selectedOptions[0];
            const metniEl = btn.querySelector('.oz-btn-metin');
            if (! secili || secili.value === '') {
                metniEl.textContent = secili ? secili.textContent.trim() : (select.options[0]?.textContent || 'Seçiniz');
                sarici.classList.add('is-bos');
            } else {
                metniEl.textContent = secili.textContent.trim();
                sarici.classList.remove('is-bos');
            }
        }

        // Panel konumla
        function konumla() {
            const r = btn.getBoundingClientRect();
            panel.style.width = r.width + 'px';
            const top = window.scrollY + r.bottom + 4;
            const left = window.scrollX + r.left;
            panel.style.top = top + 'px';
            panel.style.left = left + 'px';
            // Aşağıda yer yoksa yukarı aç
            const kalanAlt = window.innerHeight - r.bottom;
            if (kalanAlt < 240 && r.top > 240) {
                panel.style.top = (window.scrollY + r.top - panel.offsetHeight - 4) + 'px';
                panel.classList.add('is-ust');
            } else {
                panel.classList.remove('is-ust');
            }
        }

        // Odaklama
        let odakIdx = -1;
        function odaklaIdx(idx) {
            const ogeler = liste.querySelectorAll('.oz-option');
            ogeler.forEach(o => o.classList.remove('is-odak'));
            if (idx < 0 || idx >= ogeler.length) { odakIdx = -1; return; }
            odakIdx = idx;
            const el = ogeler[idx];
            el.classList.add('is-odak');
            el.scrollIntoView({ block: 'nearest' });
        }

        function ac() {
            if (select.disabled) return;
            const secilenIdx = doldur('');
            araInput.value = '';
            panel.hidden = false;
            konumla();
            btn.setAttribute('aria-expanded', 'true');
            sarici.classList.add('is-acik');
            odaklaIdx(secilenIdx >= 0 ? secilenIdx : 0);
            setTimeout(() => araInput.focus(), 30);
        }
        function kapat() {
            panel.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
            sarici.classList.remove('is-acik');
            odakIdx = -1;
        }
        function sec(deger) {
            if (select.value === deger) { kapat(); btn.focus(); return; }
            select.value = deger;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            btnMetnini_guncelle();
            kapat();
            btn.focus();
        }

        // Buton events
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (panel.hidden) ac(); else kapat();
        });
        btn.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                ac();
            }
        });

        // Panel events
        araInput.addEventListener('input', () => {
            doldur(araInput.value);
            odaklaIdx(0);
        });
        araInput.addEventListener('keydown', (e) => {
            const ogeler = liste.querySelectorAll('.oz-option:not(.is-disabled)');
            if (e.key === 'ArrowDown') { e.preventDefault(); odaklaIdx(Math.min((odakIdx < 0 ? -1 : odakIdx) + 1, ogeler.length - 1)); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); odaklaIdx(Math.max(odakIdx - 1, 0)); }
            else if (e.key === 'Enter') {
                e.preventDefault();
                const el = liste.querySelector('.oz-option.is-odak') || ogeler[0];
                if (el) sec(el.dataset.deger);
            }
            else if (e.key === 'Escape') { e.preventDefault(); kapat(); btn.focus(); }
        });

        liste.addEventListener('click', (e) => {
            const opt = e.target.closest('.oz-option:not(.is-disabled)');
            if (! opt) return;
            sec(opt.dataset.deger);
        });
        liste.addEventListener('mousemove', (e) => {
            const opt = e.target.closest('.oz-option');
            if (! opt) return;
            const ogeler = Array.from(liste.querySelectorAll('.oz-option'));
            odaklaIdx(ogeler.indexOf(opt));
        });

        // Native select programatik değişince (cascade AJAX) trigger metnini yenile
        select.addEventListener('change', () => {
            btnMetnini_guncelle();
        });
        // Options replace olduğunda (cascade il→ilçe→mahalle) — MutationObserver
        const mo = new MutationObserver(() => {
            btnMetnini_guncelle();
            if (! panel.hidden) doldur(araInput.value);
        });
        mo.observe(select, { childList: true, subtree: false });

        btnMetnini_guncelle();
    }

    // Dışa tıklama panel kapatır — global
    document.addEventListener('click', (e) => {
        document.querySelectorAll('.oz-panel:not([hidden])').forEach(p => {
            if (p.contains(e.target)) return;
            const sahip = p._sahip;
            const wrapper = sahip?.parentElement;
            const btn = wrapper?.querySelector('.oz-btn');
            if (btn && btn.contains(e.target)) return;
            p.hidden = true;
            btn?.setAttribute('aria-expanded', 'false');
            wrapper?.classList.remove('is-acik');
        });
    });

    // Scroll ve resize'de açık paneli kapat
    window.addEventListener('scroll', () => {
        document.querySelectorAll('.oz-panel:not([hidden])').forEach(p => {
            const sahip = p._sahip;
            const btn = sahip?.parentElement?.querySelector('.oz-btn');
            if (btn) {
                const r = btn.getBoundingClientRect();
                p.style.top = (window.scrollY + r.bottom + 4) + 'px';
                p.style.left = (window.scrollX + r.left) + 'px';
                p.style.width = r.width + 'px';
            }
        });
    }, { passive: true });

    // Sayfa yüklenince otomatik kur — filtre + hisse satışı arama formu select'leri
    function tarama(scope) {
        const secici = '.tl-filtre-gelismis select.form-select, .hs-ara-form select.form-select';
        (scope || document).querySelectorAll(secici).forEach(kurCustomSelect);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => tarama(document));
    } else {
        tarama(document);
    }

    // Dinamik eklenen select'ler için global expose
    window.ozelSelectKur = kurCustomSelect;
    window.ozelSelectTara = tarama;
})();
