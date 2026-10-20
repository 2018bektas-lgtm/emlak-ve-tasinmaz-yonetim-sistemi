@extends('layouts.panel')

@section('title', 'Emsal İçe Aktar')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/emsal.css') }}?v=3">
@endpush

@php
    $gruplar = [
        'Konum' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'],
        'Satış' => ['K', 'L', 'T'],
        'Belge' => ['M', 'N', 'O', 'P', 'Q', 'R', 'S'],
        'Bağımsız bölüm' => ['U', 'V', 'W', 'X', 'Y', 'Z'],
    ];
@endphp

@section('content')
@if (session('basari'))<div class="flash-success" role="status">{{ session('basari') }}</div>@endif
@if (session('hata'))<div class="flash-error" role="alert">{{ session('hata') }}</div>@endif
@if ($errors->any())
    <div class="flash-error" role="alert"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
@if (session('import_hatalari'))
    <div class="em-ice-hata" role="alert">
        <strong>{{ count(session('import_hatalari')) }} satır işlenemedi</strong>
        <ul>@foreach (session('import_hatalari') as $h)<li>{{ $h }}</li>@endforeach</ul>
    </div>
@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Değerleme · Emsal</p>
        <h2>Excel’den yükle</h2>
        <small>Şablonu doldurup bırakın. Her satır bir emsal olur.</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.emsal.index') }}" class="btn-cancel">Emsaller</a>
    </div>
</section>

<div class="em-ice">
    <form method="POST" action="{{ route('panel.emsal.ice-aktar.yukle') }}" enctype="multipart/form-data" class="em-ice-yukle">
        @csrf
        <div class="em-ice-ust">
            <div>
                <p class="em-ice-kicker">Dosya</p>
                <h3>Emsalleri içeri al</h3>
            </div>
            <a href="{{ route('panel.emsal.sablon') }}" class="em-ice-sablon">Şablonu indir</a>
        </div>

        <label class="em-ice-alan" data-dropzone>
            <input type="file" name="dosya" accept=".xlsx,.xls,.csv" required>
            <span class="em-ice-bos">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V5"/><path d="M8 8l4-4 4 4"/><path d="M4 16.5V19a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-2.5"/></svg>
                <strong>Dosyayı buraya bırakın</strong>
                <span>veya seçmek için tıklayın</span>
            </span>
            <span class="em-ice-ad" data-dosya-ad></span>
        </label>

        <div class="em-ice-alt">
            <span>.xlsx, .xls, .csv · en fazla 20 MB</span>
            <button type="submit" class="btn-submit">Yükle</button>
        </div>
    </form>

    <aside class="em-ice-notlar" aria-label="Yükleme notları">
        <p class="em-ice-kicker">Nasıl eşleşir</p>
        <div>
            <h3>Konum</h3>
            <p>İl, ilçe ve mahalle sistemdeki adla bulunur. Parsel yoksa emsal parseli açılır.</p>
        </div>
        <div>
            <h3>Tür</h3>
            <p>Encümen kararı, resmî senet veya mahkeme kararı. Türe ait olmayan belge kolonları yazılmaz.</p>
        </div>
        <div>
            <h3>Tekrar</h3>
            <p>Aynı parsel, tür, tarih, fiyat ve belge numarası varsa satır atlanır.</p>
        </div>
    </aside>
</div>

<section class="em-ice-kolonlar">
    <div class="em-ice-ust">
        <div>
            <p class="em-ice-kicker">Şablon</p>
            <h3>Kolonlar</h3>
        </div>
        <p>İşaretli alanlar zorunlu. Tür ve imar durumu listeden seçilir. İl, ilçe ve mahalle ad olarak yazılır.</p>
    </div>
    <div class="em-ice-gruplar">
        @foreach ($gruplar as $baslik => $harfler)
            <div>
                <h4>{{ $baslik }}</h4>
                <ul>
                    @foreach ($harfler as $harf)
                        @php
                            $etiket = $kolonlar[$harf] ?? $harf;
                            $zorunlu = str_ends_with($etiket, '*');
                            $liste = in_array($harf, ['A', 'H'], true);
                        @endphp
                        <li>
                            <span>{{ $harf }}</span>
                            <b @class(['is-zorunlu' => $zorunlu])>{{ rtrim($etiket, ' *') }}@if ($liste)<em>liste</em>@endif</b>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</section>

<script>
document.querySelectorAll('[data-dropzone]').forEach(function (dz) {
    const input = dz.querySelector('input[type=file]');
    const ad = dz.querySelector('[data-dosya-ad]');
    const goster = function () {
        const dosya = input.files[0];
        dz.classList.toggle('is-dolu', !!dosya);
        ad.textContent = dosya ? dosya.name : '';
    };
    input.addEventListener('change', goster);
    ['dragenter', 'dragover'].forEach(function (e) {
        dz.addEventListener(e, function () { dz.classList.add('is-hover'); });
    });
    ['dragleave', 'drop'].forEach(function (e) {
        dz.addEventListener(e, function () { dz.classList.remove('is-hover'); });
    });
});
</script>
@endsection
