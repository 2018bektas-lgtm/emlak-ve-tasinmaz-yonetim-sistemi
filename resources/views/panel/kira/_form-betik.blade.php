{{-- Kira formlarının ortak davranışı: süre ipucu, kira bedelleri, il → ilçe → mahalle.
     Dosya formu ve kira alanı formu ortak kullanır; ilgili alan sayfada yoksa o parça çalışmaz. --}}
<script>
(function () {
    const liste = document.getElementById('bedelListe');
    const bas = document.getElementById('baslangic_tarihi');
    const bit = document.getElementById('bitis_tarihi');

    // Tarihlerden süreyi tahmin edip ipucu olarak göster
    function sureyiGoster() {
        const ipucu = document.getElementById('sureIpucu');
        if (!ipucu) return;
        if (!bas.value || !bit.value) { ipucu.textContent = ''; return; }
        const gun = (new Date(bit.value) - new Date(bas.value)) / 86400000;
        if (gun <= 0) { ipucu.textContent = ''; return; }
        ipucu.textContent = 'Tarihlere göre ' + (gun / 365).toFixed(1) + ' yıl (' + Math.round(gun / 30.44) + ' ay)';
    }
    if (bas && bit) {
        [bas, bit].forEach(function (a) { a.addEventListener('change', sureyiGoster); });
        sureyiGoster();
    }

    // ---- Kira bedelleri ----
    if (liste) {
        const TL = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' });
        const SIL_IKON = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/></svg>';
        const satirlar = function () { return Array.from(liste.querySelectorAll('.kr-bedel-satir')); };
        const sayi = function (girdi) { const n = parseFloat(girdi.value); return Number.isFinite(n) ? n : null; };
        const yuvarla = function (n) { return (Math.round(n * 100) / 100).toFixed(2); };

        // Yerel gün; toISOString saat dilimi yüzünden bir gün kaydırabiliyor
        const isoGun = function (d) {
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        };
        const gunOku = function (s) {
            const p = (s || '').split('-').map(Number);
            return p.length === 3 && p[0] ? new Date(p[0], p[1] - 1, p[2]) : null;
        };

        const yenidenNumarala = function () {
            satirlar().forEach(function (satir, i) {
                satir.querySelector('.kr-yil-no').textContent = (i + 1) + '. yıl';
                satir.querySelectorAll('input[name]').forEach(function (g) {
                    g.name = g.name.replace(/bedeller\[\d+\]/, 'bedeller[' + i + ']');
                });
                // İlk yılın artışı olmaz
                satir.querySelector('[data-oran]').placeholder = i === 0 ? 'İlk yıl' : '—';
            });
            hesapla();
        };

        /** Satırların aylık tutarı ve alttaki toplamlar. */
        const hesapla = function () {
            let toplam = 0;
            let adet = 0;
            satirlar().forEach(function (satir) {
                const b = sayi(satir.querySelector('[data-bedel]'));
                satir.querySelector('[data-aylik]').textContent = b !== null ? TL.format(b / 12) : '—';
                if (b !== null) { toplam += b; adet++; }
            });
            document.getElementById('bedelToplam').textContent = adet ? TL.format(toplam) : '—';
            document.getElementById('bedelOrtalama').textContent = adet ? TL.format(toplam / (adet * 12)) : '—';
        };

        /** Oranı girilmiş yılların bedelini önceki yıldan yeniden hesaplar (sıradan sonrası). */
        const zincir = function (baslangic) {
            const s = satirlar();
            for (let i = Math.max(1, baslangic); i < s.length; i++) {
                const onceki = sayi(s[i - 1].querySelector('[data-bedel]'));
                const oran = sayi(s[i].querySelector('[data-oran]'));
                if (onceki === null || oran === null) continue;
                s[i].querySelector('[data-bedel]').value = yuvarla(onceki * (1 + oran / 100));
            }
        };

        const satirKur = function (deger) {
            deger = deger || {};
            const satir = document.createElement('div');
            satir.className = 'kr-bedel-satir';
            satir.innerHTML =
                '<span class="kr-yil-no"></span>' +
                '<div class="kr-donem">' +
                    '<input type="date" name="bedeller[0][donem_baslangic]" class="form-input" aria-label="Dönem başlangıcı">' +
                    '<span class="kr-donem-ayrac">–</span>' +
                    '<input type="date" name="bedeller[0][donem_bitis]" class="form-input" aria-label="Dönem bitişi">' +
                '</div>' +
                '<div class="kr-ek"><input type="number" name="bedeller[0][yillik_bedel]" class="form-input td-mono" step="0.01" min="0" placeholder="0,00" aria-label="Yıllık bedel" data-bedel><span>₺</span></div>' +
                '<div class="kr-ek"><input type="number" name="bedeller[0][tufe_orani]" class="form-input td-mono" step="0.01" placeholder="—" aria-label="Artış oranı" data-oran><span>%</span></div>' +
                '<span class="kr-aylik td-mono" data-aylik>—</span>' +
                '<button type="button" class="kr-satir-sil" data-bedel-sil aria-label="Bu yılı kaldır" title="Bu yılı kaldır">' + SIL_IKON + '</button>';
            // Değerler özellik olarak atanır; HTML'e gömülmez
            satir.querySelector('[name$="[donem_baslangic]"]').value = deger.bas || '';
            satir.querySelector('[name$="[donem_bitis]"]').value = deger.bit || '';
            satir.querySelector('[data-bedel]').value = deger.bedel || '';
            satir.querySelector('[data-oran]').value = deger.oran !== undefined ? deger.oran : '';
            // Gizli (devre dışı) bir bölümde üretilen satır da devre dışı doğar
            if (liste.closest('[data-kira-grup][hidden]')) {
                satir.querySelectorAll('input').forEach(function (g) { g.disabled = true; });
            }
            liste.appendChild(satir);
            return satir;
        };

        document.getElementById('bedelEkle').addEventListener('click', function () {
            // Yeni yıl, öncekinin dönemini bir yıl ileri taşır
            const s = satirlar();
            const son = s[s.length - 1];
            const deger = {};
            const sonBit = son && gunOku(son.querySelector('[name$="[donem_bitis]"]').value);
            if (sonBit) {
                const yeniBas = new Date(sonBit.getFullYear(), sonBit.getMonth(), sonBit.getDate() + 1);
                const yeniBit = new Date(yeniBas.getFullYear() + 1, yeniBas.getMonth(), yeniBas.getDate() - 1);
                const sozBit = gunOku(bit.value);
                deger.bas = isoGun(yeniBas);
                // Sözleşme içinde kalan son dönem sözleşme bitişinde kesilir; sözleşme
                // dışına taşan yıl (uzatma) tam yıl olarak açılır, ters dönem oluşmaz
                deger.bit = isoGun(sozBit && yeniBas <= sozBit && yeniBit > sozBit ? sozBit : yeniBit);
            }
            const yeni = satirKur(deger);
            yenidenNumarala();
            yeni.querySelector('[data-bedel]').focus();
        });

        liste.addEventListener('click', function (e) {
            const sil = e.target.closest('[data-bedel-sil]');
            if (!sil) return;
            const satir = sil.closest('.kr-bedel-satir');
            if (satirlar().length === 1) {
                // En az bir yıl kalmalı; son satır silinmez, temizlenir
                satir.querySelectorAll('input').forEach(function (g) { g.value = ''; });
            } else {
                satir.remove();
            }
            yenidenNumarala();
        });

        liste.addEventListener('input', function (e) {
            const satir = e.target.closest('.kr-bedel-satir');
            if (!satir) return;
            const s = satirlar();
            const i = s.indexOf(satir);
            if (e.target.matches('[data-oran]')) {
                zincir(i);
            } else if (e.target.matches('[data-bedel]')) {
                // Bedel elle değişti: bu yılın oranı güncellenir, sonraki yıllar kendi oranıyla kayar
                const onceki = i > 0 ? sayi(s[i - 1].querySelector('[data-bedel]')) : null;
                const b = sayi(e.target);
                if (onceki && b !== null) s[i].querySelector('[data-oran]').value = yuvarla((b / onceki - 1) * 100);
                zincir(i + 1);
            }
            hesapla();
        });

        // Yıl sayısı sözleşme süresinden önerilir (kullanıcı elle değiştirmediyse)
        const uretYil = document.getElementById('uretYilSayisi');
        let uretYilElle = false;
        uretYil.addEventListener('input', function () { uretYilElle = true; });
        const yilSayisiOner = function () {
            const b1 = gunOku(bas.value), b2 = gunOku(bit.value);
            if (uretYilElle || !b1 || !b2 || b2 <= b1) return;
            uretYil.value = Math.min(30, Math.max(1, Math.ceil(((b2 - b1) / 86400000 + 1) / 365.25 - 0.01)));
        };
        [bas, bit].forEach(function (a) { a.addEventListener('change', yilSayisiOner); });
        yilSayisiOner();

        const uretMesaj = function (metin) {
            const m = document.getElementById('uretMesaj');
            m.textContent = metin || '';
            m.hidden = !metin;
        };

        // İlk yıl + artış oranından tüm yılları üret; dönemler kira başlangıcından yürür
        document.getElementById('uretBtn').addEventListener('click', function () {
            const ilk = parseFloat(document.getElementById('uretIlkBedel').value);
            const yil = parseInt(uretYil.value, 10);
            const artis = parseFloat(document.getElementById('uretArtis').value) || 0;
            if (!(ilk > 0) || !(yil > 0)) { uretMesaj('İlk yıl bedelini ve yıl sayısını girin.'); return; }
            uretMesaj(bas.value ? '' : 'Kira başlangıcı girilmediği için dönem tarihleri boş bırakıldı; kayıtta sözleşmeden hesaplanır.');

            const b1 = gunOku(bas.value), b2 = gunOku(bit.value);
            satirlar().forEach(function (s) { s.remove(); });
            let bedel = ilk;
            for (let y = 0; y < yil; y++) {
                const deger = { bedel: yuvarla(bedel), oran: y > 0 ? String(artis) : '' };
                if (b1) {
                    const d = new Date(b1.getFullYear() + y, b1.getMonth(), b1.getDate());
                    const s = new Date(d.getFullYear() + 1, d.getMonth(), d.getDate() - 1);
                    deger.bas = isoGun(d);
                    deger.bit = isoGun(b2 && s > b2 ? b2 : s);
                }
                satirKur(deger);
                bedel = bedel * (1 + artis / 100);
            }
            yenidenNumarala();
        });

        yenidenNumarala();
    }

    // ---- İl → ilçe → mahalle ----
    const ilSecim = document.getElementById('il_id');
    const ilceSecim = document.getElementById('ilce_id');
    const mahalleSecim = document.getElementById('mahalle_id');
    if (!ilSecim || !ilceSecim || !mahalleSecim) return;

    async function doldur(secim, url, bosMetin) {
        secim.innerHTML = '<option value="">Yükleniyor…</option>';
        secim.disabled = true;
        try {
            const yanit = await fetch(url, { headers: { Accept: 'application/json' } });
            const veri = await yanit.json();
            secim.innerHTML = '<option value="">' + bosMetin + '</option>';
            veri.forEach(function (s) {
                const o = document.createElement('option');
                o.value = s.id;
                o.textContent = s.ad;
                // Mahalle listesinde TKGM kimliği de gelir; harita kutusu kullanır
                if (s.tkgm_id) o.dataset.tkgm = s.tkgm_id;
                secim.appendChild(o);
            });
        } catch (e) {
            secim.innerHTML = '<option value="">Liste alınamadı</option>';
        }
        secim.disabled = false;
    }

    ilSecim.addEventListener('change', function () {
        mahalleSecim.innerHTML = '<option value="">Önce ilçe seçin</option>';
        if (!this.value) { ilceSecim.innerHTML = '<option value="">Önce il seçin</option>'; return; }
        doldur(ilceSecim, '{{ route('panel.ajax.ilceler', ['il' => '__ID__']) }}'.replace('__ID__', this.value), 'Seçin');
    });

    ilceSecim.addEventListener('change', function () {
        if (!this.value) { mahalleSecim.innerHTML = '<option value="">Önce ilçe seçin</option>'; return; }
        doldur(mahalleSecim, '{{ route('panel.ajax.mahalleler', ['ilce' => '__ID__']) }}'.replace('__ID__', this.value), 'Seçin');
    });
})();
</script>
