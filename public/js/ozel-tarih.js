/* ozel-tarih.js — Native <input type="date"> öğelerini panelin takvimiyle değiştirir.
 * - Form submit: native input yerinde kalır, değeri yine yyyy-aa-gg gider
 * - Görünen alan gg.aa.yyyy; elle yazılabilir (31122025 → 31.12.2025)
 * - Kod native.value'yu değiştirirse (modal doldurma) görünen alan da güncellenir
 * - Seçimde native 'input' ve 'change' tetiklenir (onchange="..." çalışır)
 * - min / max / required / disabled / readonly native'den okunur
 * - Klavye: ↓ açar; açıkken ← → ↑ ↓ gün, PageUp/PageDown ay, Shift ile yıl, Enter seçer, Esc kapatır
 * - Opt-out: <input type="date" data-native> dokunulmaz
 * - Sonradan eklenen alanlar (modal, JS ile üretilen satır) otomatik kurulur
 */
(function () {
    'use strict';

    const KURULDU = 'ozTarihKuruldu';
    const AYLAR = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    const GUNLER = ['Pt', 'Sa', 'Ça', 'Pe', 'Cu', 'Ct', 'Pz'];
    const DEGER = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
    const IKON = 'url("data:image/svg+xml,' + encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6c7484" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
        '<rect x="3" y="4.5" width="18" height="16.5" rx="2.5"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/></svg>'
    ) + '")';

    // ---- Tarih yardımcıları (saat dilimi kayması olmasın diye hep yerel gün) ----
    const iki = (n) => String(n).padStart(2, '0');
    const isoYaz = (d) => d.getFullYear() + '-' + iki(d.getMonth() + 1) + '-' + iki(d.getDate());
    const goster = (d) => iki(d.getDate()) + '.' + iki(d.getMonth() + 1) + '.' + d.getFullYear();
    const bugun = () => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), d.getDate()); };
    const ayniGun = (a, b) => !!a && !!b && a.getTime() === b.getTime();
    const gunEkle = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);

    function tarihKur(y, a, g) {
        const d = new Date(y, a - 1, g);
        // 31.02 gibi taşan günleri reddet
        return d.getFullYear() === y && d.getMonth() === a - 1 && d.getDate() === g ? d : null;
    }

    function isoOku(s) {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || '');
        return m ? tarihKur(+m[1], +m[2], +m[3]) : null;
    }

    /** "5.3.2025", "05/03/2025", "05032025", "5.3.25" → Date */
    function metinOku(s) {
        s = (s || '').trim();
        if (s === '') return null;
        let m = /^(\d{1,2})[.\/\-\s](\d{1,2})[.\/\-\s](\d{2}|\d{4})$/.exec(s);
        if (!m && /^\d{8}$/.test(s)) m = [s, s.slice(0, 2), s.slice(2, 4), s.slice(4)];
        if (!m) return null;
        let y = +m[3];
        if (m[3].length === 2) {
            // İki haneli yıl: gelecek 10 yıla kadar 20xx, ötesi 19xx
            const sinir = (bugun().getFullYear() % 100) + 10;
            y += y <= sinir ? 2000 : 1900;
        }
        return tarihKur(y, +m[2], +m[1]);
    }

    /** Elle yazarken noktaları kendisi koyar: "3112" → "31.12" */
    function maskele(s) {
        if (/[.\/\-]/.test(s)) return s.replace(/[\/\-]/g, '.').replace(/[^\d.]/g, '').slice(0, 10);
        const r = s.replace(/\D/g, '').slice(0, 8);
        if (r.length <= 2) return r;
        if (r.length <= 4) return r.slice(0, 2) + '.' + r.slice(2);
        return r.slice(0, 2) + '.' + r.slice(2, 4) + '.' + r.slice(4);
    }

    // ---- Tek paylaşılan takvim paneli ----
    let panel = null;
    let aktif = null;          // açık takvimin kaydı
    let gorunum = 'gun';       // gun | ay | yil
    let sayfa = null;          // görünen ayın 1'i
    let odak = null;           // klavyeyle gezilen gün

    function panelKur() {
        if (panel) return panel;
        panel = document.createElement('div');
        panel.className = 'oz-takvim';
        panel.hidden = true;
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Tarih seç');
        panel.innerHTML =
            '<div class="oz-takvim-ust">' +
                '<button type="button" class="oz-takvim-ok" data-git="-1" aria-label="Önceki">' +
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></button>' +
                '<div class="oz-takvim-baslik">' +
                    '<button type="button" class="oz-takvim-baslik-btn" data-gorunum="ay"></button>' +
                    '<button type="button" class="oz-takvim-baslik-btn" data-gorunum="yil"></button>' +
                '</div>' +
                '<button type="button" class="oz-takvim-ok" data-git="1" aria-label="Sonraki">' +
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg></button>' +
            '</div>' +
            '<div class="oz-takvim-govde"></div>' +
            '<div class="oz-takvim-alt">' +
                '<button type="button" class="oz-takvim-alt-btn" data-eylem="temizle">Temizle</button>' +
                '<button type="button" class="oz-takvim-alt-btn is-ana" data-eylem="bugun">Bugün</button>' +
            '</div>';
        document.body.appendChild(panel);

        // Odak metin alanında kalsın: panele tıklamak alanı blur etmesin
        panel.addEventListener('mousedown', (e) => e.preventDefault());

        panel.addEventListener('click', (e) => {
            if (!aktif) return;
            const ok = e.target.closest('[data-git]');
            if (ok) { kaydir(+ok.dataset.git); return; }

            const baslik = e.target.closest('[data-gorunum]');
            if (baslik) { gorunum = gorunum === baslik.dataset.gorunum ? 'gun' : baslik.dataset.gorunum; ciz(); return; }

            const eylem = e.target.closest('[data-eylem]');
            if (eylem) {
                if (eylem.dataset.eylem === 'temizle') { degerYaz(aktif, null); kapat(); }
                else if (!eylem.disabled) { degerYaz(aktif, bugun()); kapat(); }
                return;
            }

            const hucre = e.target.closest('[data-tarih], [data-ay], [data-yil]');
            if (!hucre || hucre.disabled) return;
            if (hucre.dataset.tarih) {
                degerYaz(aktif, isoOku(hucre.dataset.tarih));
                kapat();
            } else if (hucre.dataset.ay) {
                sayfa = new Date(sayfa.getFullYear(), +hucre.dataset.ay, 1);
                gorunum = 'gun';
                ciz();
            } else {
                sayfa = new Date(+hucre.dataset.yil, sayfa.getMonth(), 1);
                gorunum = 'ay';
                ciz();
            }
        });
        return panel;
    }

    function sinirlar(k) {
        return { min: isoOku(k.native.min), max: isoOku(k.native.max) };
    }

    function aralikta(d, s) {
        return (!s.min || d >= s.min) && (!s.max || d <= s.max);
    }

    function kaydir(yon) {
        if (gorunum === 'gun') sayfa = new Date(sayfa.getFullYear(), sayfa.getMonth() + yon, 1);
        else if (gorunum === 'ay') sayfa = new Date(sayfa.getFullYear() + yon, sayfa.getMonth(), 1);
        else sayfa = new Date(sayfa.getFullYear() + yon * 12, sayfa.getMonth(), 1);
        ciz();
    }

    function ciz() {
        const k = aktif;
        const s = sinirlar(k);
        const secili = isoOku(k.native.value);
        const govde = panel.querySelector('.oz-takvim-govde');
        const [ayBtn, yilBtn] = panel.querySelectorAll('.oz-takvim-baslik-btn');
        ayBtn.textContent = AYLAR[sayfa.getMonth()];
        yilBtn.textContent = sayfa.getFullYear();
        ayBtn.classList.toggle('is-acik', gorunum === 'ay');
        yilBtn.classList.toggle('is-acik', gorunum === 'yil');
        ayBtn.hidden = gorunum === 'yil';
        panel.dataset.mod = gorunum; // data-gorunum olmasın: başlık düğmesi seçicisiyle çakışır

        let html = '';
        if (gorunum === 'gun') {
            html += '<div class="oz-takvim-hafta">' + GUNLER.map((g) => '<span>' + g + '</span>').join('') + '</div><div class="oz-takvim-gunler">';
            const ilk = new Date(sayfa.getFullYear(), sayfa.getMonth(), 1);
            const bas = gunEkle(ilk, -((ilk.getDay() + 6) % 7)); // haftayı pazartesiden başlat
            const bg = bugun();
            for (let i = 0; i < 42; i++) {
                const d = gunEkle(bas, i);
                const sinif = ['oz-takvim-gun'];
                if (d.getMonth() !== sayfa.getMonth()) sinif.push('is-diger');
                if (ayniGun(d, bg)) sinif.push('is-bugun');
                if (ayniGun(d, secili)) sinif.push('is-secili');
                if (ayniGun(d, odak)) sinif.push('is-odak');
                if (d.getDay() === 0 || d.getDay() === 6) sinif.push('is-haftasonu');
                const kapali = !aralikta(d, s);
                html += '<button type="button" tabindex="-1" class="' + sinif.join(' ') + '" data-tarih="' + isoYaz(d) + '"' +
                    (kapali ? ' disabled' : '') + ' aria-label="' + goster(d) + '">' + d.getDate() + '</button>';
            }
            html += '</div>';
        } else if (gorunum === 'ay') {
            html += '<div class="oz-takvim-izgara">';
            AYLAR.forEach((ad, i) => {
                const ayBasi = new Date(sayfa.getFullYear(), i, 1);
                const aySonu = new Date(sayfa.getFullYear(), i + 1, 0);
                const kapali = (s.max && ayBasi > s.max) || (s.min && aySonu < s.min);
                const sec = secili && secili.getFullYear() === sayfa.getFullYear() && secili.getMonth() === i;
                html += '<button type="button" tabindex="-1" class="oz-takvim-hucre' + (sec ? ' is-secili' : '') +
                    (i === sayfa.getMonth() ? ' is-odak' : '') + '" data-ay="' + i + '"' + (kapali ? ' disabled' : '') + '>' + ad.slice(0, 3) + '</button>';
            });
            html += '</div>';
        } else {
            const ilkYil = sayfa.getFullYear() - (sayfa.getFullYear() % 12);
            yilBtn.textContent = ilkYil + ' – ' + (ilkYil + 11);
            html += '<div class="oz-takvim-izgara">';
            for (let y = ilkYil; y < ilkYil + 12; y++) {
                const kapali = (s.max && y > s.max.getFullYear()) || (s.min && y < s.min.getFullYear());
                const sec = secili && secili.getFullYear() === y;
                html += '<button type="button" tabindex="-1" class="oz-takvim-hucre' + (sec ? ' is-secili' : '') +
                    (y === sayfa.getFullYear() ? ' is-odak' : '') + (y === bugun().getFullYear() ? ' is-bugun' : '') +
                    '" data-yil="' + y + '"' + (kapali ? ' disabled' : '') + '>' + y + '</button>';
            }
            html += '</div>';
        }
        govde.innerHTML = html;

        panel.querySelector('[data-eylem="bugun"]').disabled = !aralikta(bugun(), s);
        panel.querySelector('[data-eylem="temizle"]').hidden = k.native.required;
        konumla();
    }

    function konumla() {
        if (!aktif || panel.hidden) return;
        const r = aktif.metin.getBoundingClientRect();
        const yukseklik = panel.offsetHeight;
        const genislik = panel.offsetWidth;
        const altta = window.innerHeight - r.bottom;
        const ustte = altta < yukseklik + 8 && r.top > yukseklik + 8;
        let sol = r.left;
        if (sol + genislik > window.innerWidth - 8) sol = Math.max(8, window.innerWidth - genislik - 8);
        panel.style.left = (window.scrollX + sol) + 'px';
        panel.style.top = (window.scrollY + (ustte ? r.top - yukseklik - 6 : r.bottom + 6)) + 'px';
        panel.classList.toggle('is-ust', ustte);
    }

    function ac(k) {
        if (k.native.disabled || k.native.readOnly) return;
        panelKur();
        if (aktif && aktif !== k) kapat();
        aktif = k;
        k.yazildi = false;
        const secili = isoOku(k.native.value) || metinOku(k.metin.value);
        const s = sinirlar(k);
        let baslangic = secili || bugun();
        if (!secili && s.max && baslangic > s.max) baslangic = s.max;
        if (!secili && s.min && baslangic < s.min) baslangic = s.min;
        odak = baslangic;
        sayfa = new Date(baslangic.getFullYear(), baslangic.getMonth(), 1);
        gorunum = 'gun';
        panel.hidden = false;
        k.sarici.classList.add('is-acik');
        k.metin.setAttribute('aria-expanded', 'true');
        ciz();
    }

    function kapat() {
        if (!aktif) return;
        aktif.sarici.classList.remove('is-acik');
        aktif.metin.setAttribute('aria-expanded', 'false');
        panel.hidden = true;
        aktif = null;
    }

    // ---- Alan kaydı ----

    /** Native değeri görünen alana yansıtır. */
    function yansit(k) {
        const d = isoOku(DEGER.get.call(k.native));
        k.metin.value = d ? goster(d) : '';
        k.metin.setCustomValidity('');
        k.sarici.classList.toggle('is-bos', !d);
        k.sarici.classList.remove('is-hata');
        if (aktif === k && !panel.hidden) { if (d) { odak = d; sayfa = new Date(d.getFullYear(), d.getMonth(), 1); } ciz(); }
    }

    /** Kullanıcının seçtiği/yazdığı tarihi native'e yazar ve olayları tetikler. */
    function degerYaz(k, d) {
        const yeni = d ? isoYaz(d) : '';
        const eski = DEGER.get.call(k.native);
        DEGER.set.call(k.native, yeni);
        yansit(k);
        if (eski !== yeni) {
            k.native.dispatchEvent(new Event('input', { bubbles: true }));
            k.native.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    /** Elle yazılanı doğrular; geçerliyse yazar, değilse alanı hatalı işaretler. */
    function metniIsle(k, kesin) {
        const ham = k.metin.value.trim();
        if (ham === '') {
            if (DEGER.get.call(k.native) !== '') degerYaz(k, null);
            k.metin.setCustomValidity('');
            k.sarici.classList.remove('is-hata');
            return;
        }
        const d = metinOku(ham);
        const s = sinirlar(k);
        let hata = '';
        if (!d) hata = 'Geçerli bir tarih girin (gg.aa.yyyy).';
        else if (s.min && d < s.min) hata = 'Tarih en erken ' + goster(s.min) + ' olabilir.';
        else if (s.max && d > s.max) hata = 'Tarih en geç ' + goster(s.max) + ' olabilir.';

        if (hata) {
            // Yazma bitmeden hata gösterme; blur/Enter'da göster
            if (kesin) {
                k.metin.setCustomValidity(hata);
                k.sarici.classList.add('is-hata');
                k.metin.title = hata;
            }
            return;
        }
        k.metin.title = '';
        if (DEGER.get.call(k.native) !== isoYaz(d)) degerYaz(k, d);
        else if (kesin) yansit(k);
    }

    function nitelikleriAktar(k) {
        const n = k.native;
        k.metin.disabled = n.disabled;
        k.metin.readOnly = n.readOnly;
        k.metin.required = n.required;
        k.metin.hidden = n.hidden;
        k.sarici.classList.toggle('is-kilitli', n.disabled || n.readOnly);
        // Kod native'e sınıf eklerse (is-invalid vb.) görünen alana da geçsin
        k.metin.className = (n.className.replace(/\boz-tarih-gizli\b/g, '').trim() + ' oz-tarih-metin').trim();
    }

    function ikonuYerlestir(k) {
        // Arka plan, alanın kendi stilinin (gradyan) üstüne eklenir; satır içi stil
        // sayfaların farklı input kurallarını ezmeden ikonu her yerde gösterir.
        const m = k.metin;
        m.style.backgroundImage = '';
        const mevcut = getComputedStyle(m).backgroundImage;
        const katman = mevcut && mevcut !== 'none' ? mevcut : null;
        m.style.backgroundImage = IKON + (katman ? ', ' + katman : '');
        m.style.backgroundRepeat = 'no-repeat' + (katman ? ', repeat' : '');
        m.style.backgroundPosition = 'right 10px center' + (katman ? ', 0 0' : '');
        m.style.backgroundSize = '16px 16px' + (katman ? ', auto' : '');
        // Sayfa kuralları (.kr-alan input[type=text] gibi) padding'i ezmesin
        m.style.paddingRight = '34px';
    }

    function kur(native) {
        if (!(native instanceof HTMLInputElement) || native.type !== 'date') return;
        if (native.dataset.native !== undefined || native.dataset[KURULDU]) return;
        native.dataset[KURULDU] = '1';

        const sarici = document.createElement('span');
        sarici.className = 'oz-tarih';
        native.parentNode.insertBefore(sarici, native);

        const metin = document.createElement('input');
        metin.type = 'text';
        metin.inputMode = 'numeric';
        metin.autocomplete = 'off';
        metin.maxLength = 10;
        metin.placeholder = native.placeholder || 'gg.aa.yyyy';
        metin.setAttribute('aria-haspopup', 'dialog');
        metin.setAttribute('aria-expanded', 'false');
        ['aria-label', 'aria-describedby', 'title'].forEach((a) => {
            if (native.hasAttribute(a)) metin.setAttribute(a, native.getAttribute(a));
        });
        if (native.getAttribute('style')) metin.setAttribute('style', native.getAttribute('style'));

        // Native önde kalır: sınıfla arayan kod (querySelector('.x')) yine native'i bulur
        sarici.appendChild(native);
        sarici.appendChild(metin);
        native.classList.add('oz-tarih-gizli');
        native.tabIndex = -1;
        native.setAttribute('aria-hidden', 'true');

        const k = { native, metin, sarici };
        native._ozTarih = k;
        nitelikleriAktar(k);
        ikonuYerlestir(k);

        // Kod native.value'yu değiştirdiğinde görünen alan da değişsin
        Object.defineProperty(native, 'value', {
            configurable: true,
            get() { return DEGER.get.call(this); },
            set(v) { DEGER.set.call(this, v); yansit(k); },
        });

        // <label for> native'e odaklar; odağı görünen alana ver
        native.addEventListener('focus', () => metin.focus());

        // Gizli native'in uyarı balonu görünmez; uyarıyı görünen alan gösterir.
        // Native'in invalid'i iptal edilince tarayıcı sıradaki geçersiz öğeye,
        // yani hemen arkasındaki görünen alana geçer.
        native.addEventListener('invalid', (e) => {
            e.preventDefault();
            if (metin.validity.valid) metin.setCustomValidity(native.validationMessage);
            k.sarici.classList.add('is-hata');
        });

        metin.addEventListener('click', () => { if (aktif !== k) ac(k); });
        metin.addEventListener('input', () => {
            const once = metin.value;
            const yeni = maskele(once);
            if (yeni !== once) metin.value = yeni;
            k.sarici.classList.toggle('is-bos', yeni === '');
            k.yazildi = true;
            metniIsle(k, false);
        });
        metin.addEventListener('blur', () => {
            metniIsle(k, true);
            if (aktif === k) kapat();
        });
        metin.addEventListener('keydown', (e) => klavye(k, e));

        if (native.form && !native.form.dataset.ozTarihReset) {
            native.form.dataset.ozTarihReset = '1';
            native.form.addEventListener('reset', function () {
                const form = this;
                setTimeout(() => form.querySelectorAll('input[type="date"]').forEach((n) => n._ozTarih && yansit(n._ozTarih)), 0);
            });
        }

        // Nitelik değişikliklerini (disabled, min, max, value özniteliği, sınıf) izle
        new MutationObserver((kayitlar) => {
            if (kayitlar.some((r) => r.attributeName === 'value')) yansit(k);
            nitelikleriAktar(k);
            if (aktif === k) ciz();
        }).observe(native, { attributes: true, attributeFilter: ['disabled', 'readonly', 'required', 'min', 'max', 'value', 'class', 'hidden'] });

        yansit(k);
    }

    function klavye(k, e) {
        const acik = aktif === k && !panel.hidden;
        if (!acik) {
            if (e.key === 'ArrowDown') { e.preventDefault(); ac(k); }
            if (e.key === 'Enter') metniIsle(k, true);
            return;
        }
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); kapat(); return; }
        if (e.key === 'Tab') { kapat(); return; }
        if (gorunum !== 'gun') return;

        const adim = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];
        if (adim !== undefined) {
            e.preventDefault();
            odak = gunEkle(odak || bugun(), adim);
        } else if (e.key === 'PageUp' || e.key === 'PageDown') {
            e.preventDefault();
            const yon = e.key === 'PageUp' ? -1 : 1;
            const o = odak || bugun();
            odak = e.shiftKey
                ? new Date(o.getFullYear() + yon, o.getMonth(), Math.min(o.getDate(), 28))
                : new Date(o.getFullYear(), o.getMonth() + yon, Math.min(o.getDate(), 28));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            // Takvim açıldıktan sonra elle yazıldıysa onu, yoksa okla gezilen günü al
            if (k.yazildi) metniIsle(k, true);
            else if (odak && aralikta(odak, sinirlar(k))) degerYaz(k, odak);
            kapat();
            return;
        } else {
            return;
        }
        sayfa = new Date(odak.getFullYear(), odak.getMonth(), 1);
        ciz();
    }

    // Dışa tıklama paneli kapatır
    document.addEventListener('mousedown', (e) => {
        if (!aktif || panel.hidden) return;
        if (panel.contains(e.target) || aktif.metin.contains(e.target)) return;
        kapat();
    });

    // Kaydırılan kap (modal, yan panel) içindeyse panel alanla birlikte gitsin
    window.addEventListener('scroll', konumla, { passive: true, capture: true });
    window.addEventListener('resize', konumla, { passive: true });

    function tarama(kapsam) {
        const kok = kapsam || document;
        if (kok instanceof HTMLInputElement) { kur(kok); return; }
        kok.querySelectorAll('input[type="date"]').forEach(kur);
    }

    function baslat() {
        tarama(document);
        // Modal ve JS ile eklenen satırlardaki alanlar
        new MutationObserver((kayitlar) => {
            kayitlar.forEach((r) => r.addedNodes.forEach((n) => {
                if (n.nodeType === 1) tarama(n);
            }));
        }).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', baslat);
    } else {
        baslat();
    }

    window.ozelTarihKur = kur;
    window.ozelTarihTara = tarama;
})();
