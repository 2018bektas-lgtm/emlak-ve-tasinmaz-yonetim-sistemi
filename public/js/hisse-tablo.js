/* Hisse yönetimi — tablo + modal (olustur / duzenle ortak) */
(function () {
    const shell     = document.getElementById('hisse-shell');
    const body      = document.getElementById('hisse-tablo-body');
    const bosEl     = document.getElementById('hisse-tablo-bos');
    const ekleBtn   = document.getElementById('hisse-ekle-btn');
    const hiddenEl  = document.getElementById('hisse-hidden-alan');
    const modal     = document.getElementById('hisse-modal');
    const kaydetBtn = document.getElementById('hisse-modal-kaydet');
    if (!shell || !body || !ekleBtn || !hiddenEl || !modal || !kaydetBtn) return;

    const baslikEl = document.getElementById('hisse-modal-baslik');
    const btnMetni = modal.querySelector('[data-modal-btn]');
    const mesajEl  = document.getElementById('hisse-modal-mesaj');
    const hisseBase = (shell.dataset.hisseBase || '').replace(/\/$/, '');
    const canliMi = !!hisseBase;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value || '';

    let hisseler = [];
    let duzenlenIdx = null;

    function trFormat(n) {
        if (!Number.isFinite(n)) return '';
        return n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function trParse(s) {
        if (s == null || s === '') return null;
        s = String(s).trim().replace(/\s/g, '');
        s = s.includes('.') && s.includes(',')
            ? s.replace(/\./g, '').replace(',', '.')
            : s.replace(',', '.');
        const n = parseFloat(s);
        return Number.isFinite(n) ? n : null;
    }
    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c =>
            ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    }
    function alanOku() {
        const el = document.querySelector('input[name="alan"]');
        return el ? trParse(el.value) : null;
    }
    function hisseAlan(h) {
        const pay = trParse(h.hisse_pay);
        const payda = trParse(h.hisse_payda);
        const alan = alanOku();
        if (pay === null || payda === null || payda === 0 || alan === null) return null;
        return (pay / payda) * alan;
    }
    function tarihGoster(iso) {
        if (!iso) return '—';
        const p = String(iso).split('-');
        return (p.length === 3) ? `${p[2]}.${p[1]}.${p[0]}` : iso;
    }
    function payGoster(v) {
        if (v === null || v === undefined || v === '') return '—';
        const n = trParse(String(v).replace('.', ','));
        return n !== null ? String(n).replace('.', ',') : String(v);
    }
    function sonrakiNo() {
        const nums = hisseler
            .map(h => parseInt(h.hisse_no, 10))
            .filter(n => Number.isFinite(n) && n > 0);
        return (nums.length ? Math.max.apply(null, nums) : 0) + 1;
    }
    function taslakKaynak() {
        if (modal.hidden) return hisseler.slice();
        const draft = modalOku();
        if (duzenlenIdx === null) return hisseler.concat([draft]);
        return hisseler.map((h, i) => (i === duzenlenIdx ? draft : h));
    }

    function render() {
        body.innerHTML = '';
        bosEl.hidden = hisseler.length > 0;

        hisseler.forEach((h, i) => {
            const dusen = hisseAlan(h);
            const durumEt = h.hisse_durum === 'pasif' ? 'Pasif' : 'Aktif';
            const durumCls = h.hisse_durum === 'pasif' ? 'tl-pill-mute' : 'tl-pill-ok';
            const islemEt = h.islem_tipi === 'alis' ? 'Alış'
                        : (h.islem_tipi === 'satis' ? 'Satış' : '—');
            const tr = document.createElement('tr');
            tr.dataset.idx = String(i);
            tr.innerHTML =
                '<td class="hisse-td-no">' + (i + 1) + '</td>' +
                '<td class="hisse-td-payda">' +
                    '<span class="hisse-payda-goster">' + esc(payGoster(h.hisse_pay)) + ' <em>/</em> ' + esc(payGoster(h.hisse_payda)) + '</span>' +
                '</td>' +
                '<td class="hisse-td-alan">' + (dusen !== null ? trFormat(dusen) : '<span class="hisse-td-mute">—</span>') + '</td>' +
                '<td><span class="tl-pill ' + durumCls + '">' + durumEt + '</span></td>' +
                '<td>' + islemEt + '</td>' +
                '<td>' + esc(tarihGoster(h.edinme_tarihi)) + '</td>' +
                '<td class="hisse-td-eylem">' +
                    '<button type="button" class="hisse-td-btn hisse-td-btn-edit" data-duzenle="' + i + '" title="Düzenle">' +
                        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5 a2.121 2.121 0 0 1 3 3 L7 19 l-4 1 1-4z"/></svg>' +
                    '</button>' +
                    '<button type="button" class="hisse-td-btn hisse-td-btn-del" data-sil="' + i + '" title="Sil">' +
                        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>' +
                    '</button>' +
                '</td>';
            body.appendChild(tr);
        });

        hiddenSync();
        toplamGuncelle();
    }

    function hiddenSync() {
        hiddenEl.innerHTML = '';
        if (canliMi) return;
        hisseler.forEach((h, i) => {
            const kayit = Object.assign({}, h);
            const yuz = hisseAlan(h);
            if (yuz !== null) kayit.hisse_yuzolcum = yuz.toFixed(2);
            Object.keys(kayit).forEach(function (k) {
                const v = kayit[k];
                if (v === null || v === undefined || v === '') return;
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'hisseler[' + i + '][' + k + ']';
                inp.value = v;
                hiddenEl.appendChild(inp);
            });
        });
    }

    function toplamHesapla(liste) {
        const alan = alanOku();
        let toplam = 0, aktif = 0;
        liste.forEach(function (h) {
            if (h.hisse_durum === 'pasif') return;
            const v = hisseAlan(h);
            if (Number.isFinite(v)) { toplam += v; aktif++; }
        });
        return { alan: alan, toplam: toplam, aktif: aktif };
    }

    function toplamGuncelle() {
        const barEl = document.getElementById('hisse-toplam-bar');
        if (!barEl) return;
        const o = toplamHesapla(taslakKaynak());
        if (o.aktif === 0 || o.alan === null || o.alan === 0) { barEl.hidden = true; return; }
        barEl.hidden = false;
        const degerEl = document.getElementById('hisse-toplam-deger');
        const tabanEl = document.getElementById('hisse-toplam-taban');
        const oranEl = document.getElementById('hisse-toplam-oran');
        if (degerEl) degerEl.textContent = trFormat(o.toplam);
        if (tabanEl) tabanEl.textContent = trFormat(o.alan) + ' m²';
        const oran = (o.toplam / o.alan) * 100;
        if (oranEl) oranEl.textContent = '(%' + oran.toFixed(1).replace('.', ',') + ')';
        const asildi = o.toplam > o.alan + 0.005;
        barEl.classList.toggle('is-asildi', asildi);
        const u = document.getElementById('hisse-toplam-uyari');
        if (u) u.hidden = !asildi;
        modalEtkiGuncelle(o, asildi);
    }

    function modalEtkiGuncelle(o, asildi) {
        const el = modal.querySelector('[data-modal-etki]');
        if (!el) return;
        if (modal.hidden || o.aktif === 0 || o.alan === null) {
            el.hidden = true;
            return;
        }
        el.hidden = false;
        el.classList.toggle('is-asildi', asildi);
        const d = el.querySelector('[data-modal-etki-deger]');
        const t = el.querySelector('[data-modal-etki-taban]');
        const u = el.querySelector('[data-modal-etki-uyari]');
        if (d) d.textContent = trFormat(o.toplam);
        if (t) t.textContent = trFormat(o.alan) + ' m²';
        if (u) u.hidden = !asildi;
    }

    function alanGoster(el, k, ham) {
        let v = ham ?? '';
        if (v === '' || v === null) return '';
        if (el.dataset.trNumeric !== undefined) {
            const n = trParse(String(v).replace('.', ','));
            return n !== null ? trFormat(n) : v;
        }
        if (k === 'hisse_pay' || k === 'hisse_payda') {
            const n = trParse(String(v).replace('.', ','));
            return n !== null ? String(n).replace('.', ',') : v;
        }
        return v;
    }

    function modalAc(idx) {
        if (idx === undefined) idx = null;
        duzenlenIdx = idx;
        const yeni = idx === null;
        if (baslikEl) baslikEl.textContent = yeni ? 'Hisse Ekle' : ('Hisse #' + (idx + 1) + ' Düzenle');
        if (btnMetni) btnMetni.textContent = yeni ? (canliMi ? 'Kaydet' : 'Ekle') : 'Güncelle';
        modalMesaj('');
        const h = yeni
            ? { hisse_durum: 'aktif', hisse_no: sonrakiNo() }
            : (hisseler[idx] || {});
        modal.querySelectorAll('[data-fld]').forEach(function (el) {
            const k = el.dataset.fld;
            el.value = alanGoster(el, k, h[k]);
            if (el.tagName === 'SELECT') {
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        modalHesapGuncelle();
        toplamGuncelle();
        setTimeout(function () {
            const hedef = modal.querySelector('[data-fld="hisse_pay"]');
            if (hedef) { hedef.focus(); hedef.select(); }
        }, 30);
    }

    function modalKapat() {
        modal.hidden = true;
        document.body.style.overflow = '';
        duzenlenIdx = null;
        toplamGuncelle();
    }

    function modalOku() {
        const obj = {};
        modal.querySelectorAll('[data-fld]').forEach(function (el) {
            const k = el.dataset.fld;
            let v = el.value.trim();
            if (v !== '' && el.dataset.trNumeric !== undefined) {
                const n = trParse(v);
                v = n !== null ? n.toFixed(2) : v;
            } else if (v !== '' && (k === 'hisse_pay' || k === 'hisse_payda')) {
                const n = trParse(v);
                v = n !== null ? String(n) : v;
            }
            obj[k] = v === '' ? null : v;
        });
        return obj;
    }

    function modalHesapGuncelle() {
        const el = modal.querySelector('[data-modal-hesap]');
        if (!el) return;
        const payEl = modal.querySelector('[data-fld="hisse_pay"]');
        const paydaEl = modal.querySelector('[data-fld="hisse_payda"]');
        const pay = trParse(payEl ? payEl.value : '');
        const payda = trParse(paydaEl ? paydaEl.value : '');
        const alan = alanOku();
        if (pay === null || payda === null || payda === 0 || alan === null) {
            el.textContent = '—';
        } else {
            el.textContent = trFormat((pay / payda) * alan);
        }
        toplamGuncelle();
    }

    function modalMesaj(txt, tur) {
        if (!mesajEl) return;
        if (!txt) { mesajEl.hidden = true; mesajEl.textContent = ''; return; }
        mesajEl.textContent = txt;
        mesajEl.className = 'modal-mesaj is-' + (tur || 'error');
        mesajEl.hidden = false;
    }

    function kaydetBtnDurum(busy) {
        kaydetBtn.disabled = !!busy;
        kaydetBtn.style.opacity = busy ? '0.65' : '';
    }

    function kaydet() {
        const h = modalOku();
        const dolu = Object.keys(h).some(function (k) {
            return h[k] !== null && h[k] !== '';
        });
        if (!dolu) { modalKapat(); return; }
        if (canliMi && (h.hisse_pay === null || h.hisse_payda === null)) {
            modalMesaj('Pay ve payda zorunludur.', 'error');
            return;
        }
        if (duzenlenIdx !== null && hisseler[duzenlenIdx] && hisseler[duzenlenIdx].id) {
            h.id = hisseler[duzenlenIdx].id;
        }
        if (canliMi) {
            sunucuyaKaydet(h);
            return;
        }
        yerelKaydet(h);
    }

    function yerelKaydet(h) {
        if (duzenlenIdx === null) hisseler.push(h);
        else hisseler[duzenlenIdx] = h;
        render();
        modalKapat();
    }

    function sunucuyaKaydet(h) {
        const guncelle = !!h.id;
        const url = guncelle ? (hisseBase + '/' + h.id) : hisseBase;
        const govde = Object.assign({}, h);
        delete govde.id;
        delete govde.hisse_yuzolcum;
        kaydetBtnDurum(true);
        modalMesaj('Kaydediliyor...', 'info');
        fetch(url, {
            method: guncelle ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(govde),
        }).then(function (r) {
            return r.json().then(function (veri) {
                return { ok: r.ok, status: r.status, veri: veri };
            });
        }).then(function (sonuc) {
            if (!sonuc.ok) {
                const hatalar = sonuc.veri && sonuc.veri.errors;
                let msg = (sonuc.veri && sonuc.veri.message) || 'Kayıt başarısız.';
                if (hatalar) {
                    msg = Object.keys(hatalar).map(function (k) { return hatalar[k][0]; }).join(' ');
                }
                modalMesaj(msg, 'error');
                return;
            }
            const kayit = (sonuc.veri && sonuc.veri.hisse) || h;
            if (duzenlenIdx === null) hisseler.push(kayit);
            else hisseler[duzenlenIdx] = kayit;
            render();
            modalKapat();
        }).catch(function (e) {
            modalMesaj('İstek başarısız: ' + e.message, 'error');
        }).finally(function () {
            kaydetBtnDurum(false);
        });
    }

    function sunucudanSil(h, idx) {
        if (!h || !h.id) {
            hisseler.splice(idx, 1);
            render();
            return;
        }
        fetch(hisseBase + '/' + h.id, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).then(function (r) {
            if (!r.ok) throw new Error('Silinemedi (HTTP ' + r.status + ')');
            hisseler.splice(idx, 1);
            render();
        }).catch(function (e) {
            alert(e.message || 'Hisse silinemedi.');
        });
    }

    ekleBtn.addEventListener('click', function () { modalAc(); });

    body.addEventListener('click', function (e) {
        const dBtn = e.target.closest('[data-duzenle]');
        if (dBtn) { e.stopPropagation(); modalAc(parseInt(dBtn.dataset.duzenle, 10)); return; }
        const sBtn = e.target.closest('[data-sil]');
        if (sBtn) {
            e.stopPropagation();
            const idx = parseInt(sBtn.dataset.sil, 10);
            if (confirm('Hisse #' + (idx + 1) + ' silinsin mi?')) {
                if (canliMi) sunucudanSil(hisseler[idx], idx);
                else { hisseler.splice(idx, 1); render(); }
            }
            return;
        }
        const tr = e.target.closest('tr[data-idx]');
        if (tr) modalAc(parseInt(tr.dataset.idx, 10));
    });

    kaydetBtn.addEventListener('click', kaydet);

    modal.querySelectorAll('[data-hisse-modal-kapat]').forEach(function (el) {
        el.addEventListener('click', modalKapat);
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) modalKapat();
    });
    modal.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        const tag = (e.target && e.target.tagName) || '';
        if (tag === 'TEXTAREA' || tag === 'BUTTON') return;
        e.preventDefault();
        kaydet();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || modal.hidden) return;
        if (document.querySelector('.aselect.is-acik')) return;
        modalKapat();
    });

    modal.querySelectorAll('[data-fld="hisse_pay"], [data-fld="hisse_payda"]').forEach(function (el) {
        el.addEventListener('input', modalHesapGuncelle);
    });
    const durumEl = modal.querySelector('[data-fld="hisse_durum"]');
    if (durumEl) durumEl.addEventListener('change', toplamGuncelle);

    modal.querySelectorAll('[data-tr-numeric]').forEach(function (el) {
        el.addEventListener('blur', function () {
            const n = trParse(el.value);
            if (n !== null) el.value = trFormat(n);
        });
    });

    const alanInp = document.querySelector('input[name="alan"]');
    if (alanInp) {
        ['input', 'blur'].forEach(function (ev) {
            alanInp.addEventListener(ev, function () {
                render();
                if (!modal.hidden) modalHesapGuncelle();
            });
        });
    }

    try {
        const eskiler = JSON.parse(shell.dataset.eski || '[]');
        if (Array.isArray(eskiler)) {
            hisseler = eskiler.filter(function (h) { return h && typeof h === 'object'; });
        }
    } catch (e) { /* yut */ }
    render();
})();
