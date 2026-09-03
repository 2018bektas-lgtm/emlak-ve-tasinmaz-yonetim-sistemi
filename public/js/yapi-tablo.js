/* Taşınmaz yapıları (BBN'ler) — tablo + modal (olustur taslak / duzenle AJAX)
 * State-based: JS bir "yapı" listesi tutar; hidden inputları form submit için
 * `yapilar[i][...]` isimleriyle senkronize eder; edit ekranında canlı mod (AJAX). */
(function () {
    const shell = document.getElementById('yapi-shell');
    const body = document.getElementById('yapi-tablo-body');
    const bosEl = document.getElementById('yapi-tablo-bos');
    const ekleBtn = document.getElementById('yapi-ekle-btn');
    const hiddenEl = document.getElementById('yapi-hidden-alan');
    const modal = document.getElementById('yapi-modal');
    const kaydetBtn = document.getElementById('yapi-modal-kaydet');
    if (!shell || !body || !ekleBtn || !hiddenEl || !modal || !kaydetBtn) return;

    const baslikEl = document.getElementById('yapi-modal-baslik');
    const btnMetni = modal.querySelector('[data-yapi-modal-btn]');
    const mesajEl = document.getElementById('yapi-modal-mesaj');
    const yapiBase = (shell.dataset.yapiBase || '').replace(/\/$/, '');
    const canliMi = !!yapiBase;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value || '';

    const SATIS_ETIKET = {
        envanterde: 'Envanterde',
        hazirlik: 'Hazırlık',
        satista: 'Satışta',
        satildi: 'Satıldı',
        iptal: 'İptal',
    };
    const SATIS_PILL = {
        envanterde: 'tl-pill-mute',
        hazirlik: 'tl-pill-warn',
        satista: 'tl-pill-danger',
        satildi: 'tl-pill-ok',
        iptal: 'tl-pill-mute',
    };

    let kayitlar = [];
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
    function adres(h) {
        const p = [h.blok_no, h.kat_no, h.bagimsiz_bolum_no].filter(Boolean);
        return p.length ? p.join(' / ') : '—';
    }

    function render() {
        body.innerHTML = '';
        bosEl.hidden = kayitlar.length > 0;
        kayitlar.forEach(function (h, i) {
            const sd = h.satis_durumu || 'envanterde';
            const net = trParse(h.net_alan);
            const rozetler = [];
            if (String(h.kira_var) === '1' || h.kira_var === true || h.kira_var === 1) rozetler.push('Kira');
            if (String(h.tahsis_var) === '1' || h.tahsis_var === true || h.tahsis_var === 1) rozetler.push('Tahsis');
            if (String(h.ust_hakki_var) === '1' || h.ust_hakki_var === true || h.ust_hakki_var === 1) rozetler.push('Üst Hakkı');
            if (String(h.meclis_satis_karari_var) === '1' || h.meclis_satis_karari_var === true || h.meclis_satis_karari_var === 1) rozetler.push('Meclis');
            const durumHtml = rozetler.length
                ? rozetler.map(function (r) { return '<span class="tl-pill tl-pill-info" style="margin-right:2px">' + esc(r) + '</span>'; }).join('')
                : '<span class="tl-pill tl-pill-mute">—</span>';

            const tr = document.createElement('tr');
            tr.dataset.idx = String(i);
            tr.innerHTML =
                '<td class="hisse-td-no">' + (i + 1) + '</td>' +
                '<td>' + esc(adres(h)) + '</td>' +
                '<td>' + esc(h.nitelik || '—') + '</td>' +
                '<td class="hisse-td-alan">' + (net !== null ? trFormat(net) : '<span class="hisse-td-mute">—</span>') + '</td>' +
                '<td><span class="tl-pill ' + (SATIS_PILL[sd] || 'tl-pill-mute') + '">' + esc(SATIS_ETIKET[sd] || sd) + '</span></td>' +
                '<td>' + durumHtml + '</td>' +
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
    }

    function hiddenSync() {
        hiddenEl.innerHTML = '';
        if (canliMi) return;
        kayitlar.forEach(function (h, i) {
            Object.keys(h).forEach(function (k) {
                if (k === 'id') return;
                const v = h[k];
                if (v === null || v === undefined || v === '') return;
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'yapilar[' + i + '][' + k + ']';
                inp.value = v;
                hiddenEl.appendChild(inp);
            });
        });
    }

    function modalMesaj(txt, tur) {
        if (!mesajEl) return;
        if (!txt) { mesajEl.hidden = true; mesajEl.textContent = ''; return; }
        mesajEl.textContent = txt;
        mesajEl.className = 'modal-mesaj is-' + (tur || 'error');
        mesajEl.hidden = false;
    }

    function alanGoster(el, k, ham) {
        let v = ham ?? '';
        if (el.type === 'checkbox') return v;
        if (v === '' || v === null) return '';
        if (el.dataset.trNumeric !== undefined) {
            const n = trParse(String(v).replace('.', ','));
            return n !== null ? trFormat(n) : v;
        }
        return v;
    }

    function modalAc(idx) {
        if (idx === undefined) idx = null;
        duzenlenIdx = idx;
        const yeni = idx === null;
        if (baslikEl) baslikEl.textContent = yeni ? 'Bağımsız Bölüm Ekle' : 'Bağımsız Bölüm Düzenle';
        if (btnMetni) btnMetni.textContent = yeni ? (canliMi ? 'Kaydet' : 'Ekle') : 'Güncelle';
        modalMesaj('');
        const h = yeni ? { satis_durumu: 'envanterde' } : (kayitlar[idx] || {});
        modal.querySelectorAll('[data-fld]').forEach(function (el) {
            const k = el.dataset.fld;
            if (el.type === 'checkbox') {
                el.checked = String(h[k]) === '1' || h[k] === true || h[k] === 1;
                return;
            }
            el.value = alanGoster(el, k, h[k]);
            if (el.tagName === 'SELECT') el.dispatchEvent(new Event('change', { bubbles: true }));
        });
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        setTimeout(function () {
            modal.querySelector('[data-fld="kat_no"]')?.focus();
        }, 30);
    }

    function modalKapat() {
        modal.hidden = true;
        document.body.style.overflow = '';
        duzenlenIdx = null;
    }

    function modalOku() {
        const obj = {};
        modal.querySelectorAll('[data-fld]').forEach(function (el) {
            const k = el.dataset.fld;
            if (el.type === 'checkbox') {
                obj[k] = el.checked ? 1 : 0;
                return;
            }
            let v = el.value.trim();
            if (v !== '' && el.dataset.trNumeric !== undefined) {
                const n = trParse(v);
                v = n !== null ? n.toFixed(2) : v;
            }
            obj[k] = v === '' ? null : v;
        });
        return obj;
    }

    function kaydetBtnDurum(busy) {
        kaydetBtn.disabled = !!busy;
        kaydetBtn.style.opacity = busy ? '0.65' : '';
    }

    function kaydet() {
        const h = modalOku();
        if (!h.kat_no && !h.bagimsiz_bolum_no && !h.nitelik && !h.blok_no) {
            modalMesaj('En az kat / bölüm no / nitelik / blok no doldurun.', 'error');
            return;
        }
        if (duzenlenIdx !== null && kayitlar[duzenlenIdx] && kayitlar[duzenlenIdx].id) {
            h.id = kayitlar[duzenlenIdx].id;
        }
        if (canliMi) { sunucuyaKaydet(h); return; }
        yerelKaydet(h);
    }

    function yerelKaydet(h) {
        if (duzenlenIdx === null) kayitlar.push(h);
        else kayitlar[duzenlenIdx] = h;
        render();
        modalKapat();
    }

    function sunucuyaKaydet(h) {
        const guncelle = !!h.id;
        const url = guncelle ? (yapiBase + '/' + h.id) : yapiBase;
        const govde = Object.assign({}, h);
        delete govde.id;
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
            return r.json().then(function (veri) { return { ok: r.ok, veri: veri }; });
        }).then(function (sonuc) {
            if (!sonuc.ok) {
                const hatalar = sonuc.veri && sonuc.veri.errors;
                let msg = (sonuc.veri && sonuc.veri.message) || 'Kayıt başarısız.';
                if (hatalar) msg = Object.keys(hatalar).map(function (k) { return hatalar[k][0]; }).join(' ');
                modalMesaj(msg, 'error');
                return;
            }
            const kayit = (sonuc.veri && sonuc.veri.yapi) || h;
            if (duzenlenIdx === null) kayitlar.push(kayit);
            else kayitlar[duzenlenIdx] = kayit;
            render();
            modalKapat();
        }).catch(function (e) {
            modalMesaj('İstek başarısız: ' + e.message, 'error');
        }).finally(function () { kaydetBtnDurum(false); });
    }

    function sunucudanSil(h, idx) {
        if (!h || !h.id) { kayitlar.splice(idx, 1); render(); return; }
        fetch(yapiBase + '/' + h.id, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).then(function (r) {
            if (!r.ok) throw new Error('Silinemedi (HTTP ' + r.status + ')');
            kayitlar.splice(idx, 1);
            render();
        }).catch(function (e) {
            alert(e.message || 'Bağımsız bölüm silinemedi.');
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
            if (confirm('Bu bağımsız bölüm silinsin mi?')) {
                if (canliMi) sunucudanSil(kayitlar[idx], idx);
                else { kayitlar.splice(idx, 1); render(); }
            }
            return;
        }
        const tr = e.target.closest('tr[data-idx]');
        if (tr) modalAc(parseInt(tr.dataset.idx, 10));
    });
    kaydetBtn.addEventListener('click', kaydet);
    modal.querySelectorAll('[data-yapi-modal-kapat]').forEach(function (el) {
        el.addEventListener('click', modalKapat);
    });
    modal.addEventListener('click', function (e) { if (e.target === modal) modalKapat(); });
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
    modal.querySelectorAll('[data-tr-numeric]').forEach(function (el) {
        el.addEventListener('blur', function () {
            const n = trParse(el.value);
            if (n !== null) el.value = trFormat(n);
        });
    });

    try {
        const eskiler = JSON.parse(shell.dataset.eski || '[]');
        if (Array.isArray(eskiler)) kayitlar = eskiler.filter(function (h) { return h && typeof h === 'object'; });
    } catch (e) { /* yut */ }
    render();
})();
