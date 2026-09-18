@php
    /**
     * Meclis Satış Kararı — checkbox işaretlendiğinde açılan detay alanları.
     * Politika: bir taşınmazın aktif tek satış kararı tutulur; işaret kaldırılırsa
     * mevcut kayıt(lar) silinir. Silme sırasında model booted ile
     * `meclis_satis_karari_var` bayrağı otomatik false olur.
     */
    $meclisKarariMevcut = $meclisKarariMevcut ?? null; // MeclisKarari | null
    $checkboxAdi = $checkboxAdi ?? 'ekbilgi[meclis_satis_karari_var]';
    $isaretli = old(
        str_replace(['[', ']'], ['.', ''], $checkboxAdi),
        $meclisKarariMevcut !== null || ($ekbilgiMevcut->meclis_satis_karari_var ?? false)
    );

    $mk = old('meclis_karari', $meclisKarariMevcut ? [
        'karar_no' => $meclisKarariMevcut->karar_no,
        'karar_tarihi' => optional($meclisKarariMevcut->karar_tarihi)->format('Y-m-d'),
        'karar_ozeti' => $meclisKarariMevcut->karar_ozeti,
    ] : []);
@endphp

<div class="meclis-alanlar" data-meclis-panel {{ $isaretli ? '' : 'hidden' }}>
    <div class="meclis-baslik">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
        <span>Meclis Satış Kararı Bilgileri</span>
    </div>

    <div class="form-row form-row-3">
        <div class="form-field {{ $errors->has('meclis_karari.karar_no') ? 'has-error' : '' }}">
            <label for="mk_karar_no">Karar No <span class="required">*</span></label>
            <input id="mk_karar_no" type="text" name="meclis_karari[karar_no]" class="form-input" maxlength="50"
                   placeholder="2024/125" value="{{ $mk['karar_no'] ?? '' }}">
            @error('meclis_karari.karar_no')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div class="form-field {{ $errors->has('meclis_karari.karar_tarihi') ? 'has-error' : '' }}">
            <label for="mk_karar_tarihi">Karar Tarihi <span class="required">*</span></label>
            <input id="mk_karar_tarihi" type="date" name="meclis_karari[karar_tarihi]" class="form-input"
                   value="{{ $mk['karar_tarihi'] ?? '' }}">
            @error('meclis_karari.karar_tarihi')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div class="form-field {{ $errors->has('meclis_karari.karar_pdf') ? 'has-error' : '' }}">
            <label for="mk_karar_pdf">Karar PDF</label>
            <input id="mk_karar_pdf" type="file" name="meclis_karari[karar_pdf]" class="form-input" accept="application/pdf">
            @if ($meclisKarariMevcut && $meclisKarariMevcut->karar_pdf)
                <a href="{{ asset('storage/'.$meclisKarariMevcut->karar_pdf) }}" target="_blank" rel="noopener" class="meclis-pdf-link">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                    Mevcut PDF'i aç
                </a>
            @endif
            @error('meclis_karari.karar_pdf')<span class="error">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="form-row">
        <div class="form-field {{ $errors->has('meclis_karari.karar_ozeti') ? 'has-error' : '' }}">
            <label for="mk_karar_ozeti">Karar Özeti</label>
            <textarea id="mk_karar_ozeti" name="meclis_karari[karar_ozeti]" class="form-textarea" rows="2" maxlength="2000"
                      placeholder="Karar konusu, gerekçe, kısa özet...">{{ $mk['karar_ozeti'] ?? '' }}</textarea>
            @error('meclis_karari.karar_ozeti')<span class="error">{{ $message }}</span>@enderror
        </div>
    </div>
</div>

<script>
(function () {
    const cbList = document.querySelectorAll('input[type="checkbox"][name="{{ $checkboxAdi }}"]');
    const panel = document.currentScript.previousElementSibling;
    if (! panel || ! panel.matches('[data-meclis-panel]')) return;
    cbList.forEach(cb => {
        cb.addEventListener('change', () => {
            panel.hidden = ! cb.checked;
        });
    });
})();
</script>
