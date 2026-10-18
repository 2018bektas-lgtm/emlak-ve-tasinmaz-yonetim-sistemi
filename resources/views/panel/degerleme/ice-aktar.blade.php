@extends('layouts.panel')

@section('title', 'Değerleme İçe Aktar')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/araclar.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=3">
@endpush

@section('content')
@if (session('basari'))<div class="flash-success" role="status">{{ session('basari') }}</div>@endif
@if (session('hata'))<div class="flash-error" role="alert">{{ session('hata') }}</div>@endif
@if ($errors->any())
    <div class="flash-error" role="alert"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
@if (session('import_hatalari'))
    <div class="ti-hatalar">
        <div class="ti-hatalar-baslik">{{ count(session('import_hatalari')) }} satır / dosya işlenemedi</div>
        <ul class="ti-hatalar-liste">@foreach (session('import_hatalari') as $h)<li>{{ $h }}</li>@endforeach</ul>
    </div>
@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Değerleme · İçe Aktarım</p>
        <h2>Toplu Değerleme Yükleme</h2>
        <small>Mevcut raporları Excel’den aktarın, ardından rapor PDF’lerini rapor numarasıyla eşleştirerek yükleyin.</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.degerleme.index') }}" class="btn-cancel">← Değerlemeler</a>
    </div>
</section>

<div class="ti-grid">
    <section class="ti-kart">
        <div class="ti-kart-head">
            <span class="ti-kart-ikon ti-kart-ikon-navy"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg></span>
            <div class="ti-kart-baslik">
                <h3>1 · Excel’den Değerleme</h3>
                <small>Arsa ve bağımsız bölüm raporları aynı dosyada</small>
            </div>
            <a href="{{ route('panel.degerleme.sablon') }}" class="ti-kart-sablon">Şablon</a>
        </div>
        <form method="POST" action="{{ route('panel.degerleme.ice-aktar.yukle') }}" enctype="multipart/form-data" class="ti-form">
            @csrf
            <label class="ti-dropzone" data-dropzone>
                <input type="file" name="dosya" accept=".xlsx,.xls,.csv" required>
                <span class="ti-dropzone-icin">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M12 18v-6M9 15l3-3 3 3"/></svg>
                    <p><strong>Excel dosyasını sürükleyin</strong> veya seçmek için tıklayın</p>
                    <span class="ti-dropzone-alt">.xlsx · .xls · .csv (maks. 20 MB)</span>
                </span>
                <span class="ti-dosya-ad" data-dosya-ad></span>
            </label>
            <button type="submit" class="btn-submit ti-yukle-btn">İçe Aktar</button>
        </form>
        <ul class="ti-ipuclari">
            <li>Taşınmaz <strong>İlçe + Mahalle + Ada + Parsel</strong> ile envanterde bulunur; kayıtlı değilse satır atlanır.</li>
            <li><strong>BB No</strong> boşsa arsa, doluysa o bağımsız bölümün değerlemesi açılır.</li>
            <li>Aynı taşınmaz + tür + BB + rapor no zaten varsa satır <em>yinelenmez</em>.</li>
        </ul>
    </section>

    <section class="ti-kart">
        <div class="ti-kart-head">
            <span class="ti-kart-ikon ti-kart-ikon-gold"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15h6"/></svg></span>
            <div class="ti-kart-baslik">
                <h3>2 · Rapor PDF’lerini Eşleştir</h3>
                <small>{{ $evraksizSayi }} raporun PDF’i eksik</small>
            </div>
        </div>
        <form method="POST" action="{{ route('panel.degerleme.evrak-eslestir') }}" enctype="multipart/form-data" class="ti-form">
            @csrf
            <label class="ti-dropzone" data-dropzone>
                <input type="file" name="dosyalar[]" accept="application/pdf" multiple required>
                <span class="ti-dropzone-icin">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M12 18v-6M9 15l3-3 3 3"/></svg>
                    <p><strong>PDF’leri sürükleyin</strong> veya seçin (çoklu)</p>
                    <span class="ti-dropzone-alt">Dosya adı = rapor no · en fazla 200 dosya, her biri 20 MB</span>
                </span>
                <span class="ti-dosya-ad" data-dosya-ad></span>
            </label>
            <label class="dg-kaldir"><input type="checkbox" name="uzerine_yaz" value="1"> PDF’i olan raporların dosyasını da değiştir</label>
            <button type="submit" class="btn-submit ti-yukle-btn">PDF’leri Eşleştir</button>
        </form>
        <ul class="ti-ipuclari">
            <li>Rapor no <strong>2026/15</strong> → dosya adı <strong>2026-15.pdf</strong> (/, boşluk, _ ve - eşdeğer sayılır).</li>
            <li>Aynı rapor numaralı birden çok kayıt (bir binanın BB’leri) aynı PDF’i paylaşır.</li>
        </ul>
    </section>
</div>

<section class="td-section">
    <div class="td-section-head"><div><h3>Şablon kolonları</h3><small>İlk satır başlıktır; * zorunlu</small></div></div>
    <div class="dg-kolonlar">
        @foreach ($kolonlar as $harf => $ad)
            <span><b>{{ $harf }}</b>{{ $ad }}</span>
        @endforeach
    </div>
</section>

<script>
document.querySelectorAll('[data-dropzone]').forEach(function (dz) {
    const input = dz.querySelector('input[type=file]');
    const ad = dz.querySelector('[data-dosya-ad]');
    const goster = () => {
        const n = input.files.length;
        dz.classList.toggle('is-dolu', n > 0);
        ad.textContent = n === 1 ? input.files[0].name : (n ? n + ' dosya seçildi' : '');
    };
    input.addEventListener('change', goster);
    ['dragenter', 'dragover'].forEach(e => dz.addEventListener(e, () => dz.classList.add('is-hover')));
    ['dragleave', 'drop'].forEach(e => dz.addEventListener(e, () => dz.classList.remove('is-hover')));
});
</script>
@endsection
