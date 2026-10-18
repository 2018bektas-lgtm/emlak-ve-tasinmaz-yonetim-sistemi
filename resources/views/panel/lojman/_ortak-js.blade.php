{{-- Silme düğmeleri (data-sil / data-soru / data-sonra) ve bildirim kutusu --}}
<script>
(function () {
    'use strict';
    const CSRF = @json(csrf_token());
    window.ljBildir = function (mesaj) {
        const kutu = document.getElementById('hs-bildirim');
        if (!kutu) return;
        kutu.textContent = mesaj;
        kutu.classList.add('is-acik');
        clearTimeout(kutu._t);
        kutu._t = setTimeout(() => kutu.classList.remove('is-acik'), 4200);
    };
    document.addEventListener('click', async e => {
        const btn = e.target.closest('[data-sil]');
        if (!btn) return;
        if (!confirm(btn.dataset.soru || 'Silinsin mi?')) return;
        btn.disabled = true;
        try {
            const r = await fetch(btn.dataset.sil, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' } });
            const v = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(v.mesaj || v.message || 'Silinemedi (' + r.status + ').');
            if (btn.dataset.sonra) location.href = btn.dataset.sonra; else location.reload();
        } catch (err) {
            btn.disabled = false;
            window.ljBildir(err.message);
        }
    });
})();
</script>
