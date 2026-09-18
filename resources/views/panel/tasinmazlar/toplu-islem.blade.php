@extends('layouts.panel')

@section('title', 'Toplu İşlemler')

@section('content')

@if (session('basari'))
    <div class="flash-success">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12 l5 5 L20 7"/></svg>
        {{ session('basari') }}
    </div>
@endif

@if ($errors->any())
    <div class="flash-error">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8 v4 M12 16 v0.01"/></svg>
        Dosya yüklenemedi. Lütfen Excel/CSV formatında (.xlsx, .xls, .csv) ve en fazla 20 MB olacak şekilde tekrar deneyin.
    </div>
@endif

@if (session()->has('import_hatalari'))
    <div class="ti-hatalar">
        <div class="ti-hatalar-baslik">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span>{{ count(session('import_hatalari')) }} satır işlenemedi</span>
        </div>
        <ul class="ti-hatalar-liste">
            @foreach (session('import_hatalari') as $h)
                <li>{{ $h }}</li>
            @endforeach
        </ul>
    </div>
@endif

<section class="page-hero">
    <div class="page-hero-text">
        <h2>Toplu İşlemler</h2>
        <small>Excel/CSV üzerinden çoklu taşınmaz veya hisse içe aktarımı. Şablonu indirin, doldurun, yükleyin.</small>
    </div>
</section>

<div class="ti-grid">
    {{-- Taşınmaz --}}
    <div class="ti-kart">
        <div class="ti-kart-head">
            <span class="ti-kart-ikon ti-kart-ikon-navy">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V6h9v15"/><path d="M14 21V10h5v11"/></svg>
            </span>
            <div class="ti-kart-baslik">
                <h3>Taşınmaz İçe Aktar</h3>
                <small>Ada · Parsel · Alan · TAKBİS · İmar · Kategori · Ekbilgi</small>
            </div>
            <a href="{{ route('panel.tasinmazlar.toplu-islem.tasinmaz-sablon') }}" class="ti-kart-sablon" title="Şablonu indir">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Şablon
            </a>
        </div>
        <form method="POST" action="{{ route('panel.tasinmazlar.toplu-islem.tasinmaz-yukle') }}" enctype="multipart/form-data" class="ti-form">
            @csrf
            <label class="ti-dropzone">
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
                <div class="ti-dropzone-icin">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 12 15 15"/></svg>
                    <p><strong>Dosyayı sürükleyin</strong> veya seçmek için tıklayın</p>
                    <span class="ti-dropzone-alt">.xlsx · .xls · .csv (maks. 20 MB)</span>
                </div>
                <div class="ti-dosya-ad" data-tabla></div>
            </label>
            <button type="submit" class="btn-submit ti-yukle-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                İçe Aktar
            </button>
        </form>
        <ul class="ti-ipuclari">
            <li>Aynı mahalle + ada + parsel varsa güncellenir, yoksa yeni oluşturulur.</li>
            <li>İl / İlçe / Mahalle sistemde mevcut olmalı; İmar Durumu yoksa otomatik oluşturulur.</li>
            <li>Muhasebe ve Kayıt Türü için <strong>kod</strong> (örn. <em>1.1.1</em>) veya <strong>ad</strong> yazın.</li>
        </ul>
    </div>

    {{-- Hisse --}}
    <div class="ti-kart">
        <div class="ti-kart-head">
            <span class="ti-kart-ikon ti-kart-ikon-gold">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 L2 7 l10 5 10-5z"/><path d="M2 12 l10 5 10-5"/><path d="M2 17 l10 5 10-5"/></svg>
            </span>
            <div class="ti-kart-baslik">
                <h3>Hisse İçe Aktar</h3>
                <small>Mahalle TKGM · Ada · Parsel + Pay/Payda · Edinme · Bedeller</small>
            </div>
            <a href="{{ route('panel.tasinmazlar.toplu-islem.hisse-sablon') }}" class="ti-kart-sablon" title="Şablonu indir">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Şablon
            </a>
        </div>
        <form method="POST" action="{{ route('panel.tasinmazlar.toplu-islem.hisse-yukle') }}" enctype="multipart/form-data" class="ti-form">
            @csrf
            <label class="ti-dropzone">
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
                <div class="ti-dropzone-icin">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 12 15 15"/></svg>
                    <p><strong>Dosyayı sürükleyin</strong> veya seçmek için tıklayın</p>
                    <span class="ti-dropzone-alt">.xlsx · .xls · .csv (maks. 20 MB)</span>
                </div>
                <div class="ti-dosya-ad" data-tabla></div>
            </label>
            <button type="submit" class="btn-submit ti-yukle-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                İçe Aktar
            </button>
        </form>
        <ul class="ti-ipuclari">
            <li>Taşınmaz mevcut değilse önce <strong>Taşınmaz İçe Aktar</strong> ile yükleyin.</li>
            <li><strong>Mahalle TKGM ID</strong> + Ada + Parsel eşleşmesi ile taşınmaz bulunur.</li>
            <li>Aynı <em>Hisse No</em> zaten varsa güncellenir; boşsa yeni satır eklenir.</li>
        </ul>
    </div>
</div>

<script>
document.querySelectorAll('.ti-dropzone').forEach(dz => {
    const input = dz.querySelector('input[type="file"]');
    const isim = dz.querySelector('[data-tabla]');
    input.addEventListener('change', () => {
        const f = input.files[0];
        if (f) {
            isim.textContent = f.name + '  ·  ' + (f.size / 1024).toFixed(1) + ' KB';
            dz.classList.add('is-dolu');
        } else {
            isim.textContent = '';
            dz.classList.remove('is-dolu');
        }
    });
    ['dragover', 'dragenter'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('is-hover'); }));
    ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('is-hover'); }));
    dz.addEventListener('drop', e => {
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        }
    });
});
</script>
@endsection
