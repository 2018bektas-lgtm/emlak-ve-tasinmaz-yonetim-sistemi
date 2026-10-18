@extends('layouts.panel')

@section('title', $rol->exists ? 'Rol Düzenle' : 'Yeni Rol')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/yonetim.css') }}?v=2">
@endpush

@php
    $moduller = \App\Support\YetkiModulleri::tamListe($izinler->pluck('grup')->unique());
    $adminKilit = $rol->exists && $rol->kod === 'admin';
    $sistemKilit = $rol->exists && $rol->sistem_mi;
    $izinlerGruplu = $izinler->groupBy('grup');
    $seciliSet = collect(old('izinler', $seciliIzinler->all()))->map(fn ($v) => (int) $v)->all();
    $eylemler = ['goruntule' => 'Görüntüle', 'olustur' => 'Oluştur', 'duzenle' => 'Düzenle', 'sil' => 'Sil'];
    $eylemOf = fn ($izin) => \Illuminate\Support\Str::after($izin->kod, '.');
@endphp

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Sistem Yönetimi · {{ $rol->exists ? 'Rol' : 'Yeni Rol' }}</p>
        <h2>{{ $rol->exists ? $rol->ad : 'Yeni Rol' }}</h2>
        <small>
            @if ($adminKilit)
                Admin rolü sistemdeki tüm izinlere otomatik sahiptir; izinleri değiştirilemez.
            @else
                Rolün adını ve izinlerini belirleyin. Bu role atanan herkes seçilen izinlere sahip olur.
            @endif
        </small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.roller.index') }}" class="btn-cancel">← Roller</a>
    </div>
</section>

@if ($errors->any())
    <div class="flash-error" role="alert"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ $rol->exists ? route('panel.roller.guncelle', $rol) : route('panel.roller.store') }}" class="form-shell ym-form" id="ym-rol-form">
    @csrf
    @if ($rol->exists) @method('PUT') @endif

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">1</span>
            <div class="form-section-head-text">
                <h3>Rol Bilgileri</h3>
                <small>Kod benzersiz olmalı; küçük harf, rakam, tire ve alt çizgi. Sistem rollerinin kodu değiştirilemez ve silinemezler.</small>
            </div>
        </div>
        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="ad">Ad <span class="required">*</span></label>
                <input id="ad" name="ad" type="text" class="form-input" value="{{ old('ad', $rol->ad) }}" required maxlength="100" placeholder="Örn. Emlak Uzmanı">
                @error('ad')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="kod">Kod <span class="required">*</span></label>
                <input id="kod" name="kod" type="text" class="form-input td-mono" value="{{ old('kod', $rol->kod) }}" required maxlength="40" pattern="[a-z0-9_-]+" {{ $sistemKilit ? 'readonly' : '' }} placeholder="emlak-uzmani">
                @error('kod')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="sira">Sıra</label>
                <input id="sira" name="sira" type="number" class="form-input" value="{{ old('sira', $rol->sira ?? 0) }}" min="0">
                <span class="hint">Listelerde gösterim sırası.</span>
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label for="aciklama">Açıklama</label>
                <textarea id="aciklama" name="aciklama" class="form-textarea" rows="2" maxlength="2000" placeholder="Bu rol kimler için, neler yapabilir?">{{ old('aciklama', $rol->aciklama) }}</textarea>
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head ym-izin-bas">
            <span class="form-section-num">2</span>
            <div class="form-section-head-text">
                <h3>İzinler <span class="ym-izin-toplam" id="ym-izin-toplam">{{ $adminKilit ? $izinler->count() : count($seciliSet) }} / {{ $izinler->count() }}</span></h3>
                <small>Oluşturma, düzenleme ya da silme izni verilen bölümün görüntüleme izni kendiliğinden verilir.</small>
            </div>
            @unless ($adminKilit)
                <div class="ym-izin-hizli">
                    <button type="button" class="btn-cancel hs-mini-btn" data-toplu="goruntule">Yalnız görüntüleme</button>
                    <button type="button" class="btn-cancel hs-mini-btn" data-toplu="hepsi">Tümünü seç</button>
                    <button type="button" class="btn-cancel hs-mini-btn" data-toplu="hic">Temizle</button>
                </div>
            @endunless
        </div>

        <div class="ym-moduller {{ $adminKilit ? 'is-kilitli' : '' }}">
            @foreach ($moduller as $modulKod => $mod)
                @php
                    $gruplar = collect($mod['gruplar'])->filter(fn ($ad, $g) => isset($izinlerGruplu[$g]));
                    if ($gruplar->isEmpty()) continue;
                @endphp
                <div class="ym-modul" data-modul="{{ $modulKod }}" style="--modul: {{ $mod['renk'] }}">
                    <div class="ym-modul-ust">
                        <span class="ym-modul-ikon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $mod['ikon'] !!}</svg></span>
                        <div class="ym-modul-baslik">
                            <strong>{{ $mod['ad'] }}</strong>
                            <small>{{ $mod['alt'] }}</small>
                        </div>
                        <span class="ym-modul-sayi" data-modul-sayi></span>
                        @unless ($adminKilit)
                            <button type="button" class="ym-mini" data-modul-tum="{{ $modulKod }}">Tümü</button>
                        @endunless
                    </div>
                    <div class="ym-matris-sarma">
                        <table class="ym-matris">
                            {{-- Sabit sütunlar: tüm modüllerde aynı hizada --}}
                            <colgroup><col class="ym-k-bolum">@foreach ($eylemler as $e)<col class="ym-k-eylem">@endforeach<col class="ym-k-diger"></colgroup>
                            <thead>
                                <tr>
                                    <th>Bölüm</th>
                                    @foreach ($eylemler as $eAd)<th>{{ $eAd }}</th>@endforeach
                                    <th>Diğer</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($gruplar as $grupKod => $grupAd)
                                    @php $grupIzin = $izinlerGruplu[$grupKod]->keyBy(fn ($i) => $eylemOf($i)); @endphp
                                    <tr data-grup="{{ $grupKod }}">
                                        <th scope="row">
                                            @unless ($adminKilit)
                                                <button type="button" class="ym-satir-btn" data-grup-tum="{{ $grupKod }}" title="Bu bölümün tüm izinleri">{{ $grupAd }}</button>
                                            @else
                                                {{ $grupAd }}
                                            @endunless
                                        </th>
                                        @foreach ($eylemler as $eKod => $eAd)
                                            <td>
                                                @if ($izin = $grupIzin->get($eKod))
                                                    <label class="ym-onay" title="{{ $izin->ad }} ({{ $izin->kod }})">
                                                        <input type="checkbox" name="izinler[]" value="{{ $izin->id }}" data-eylem="{{ $eKod }}"
                                                               @checked($adminKilit || in_array($izin->id, $seciliSet)) @disabled($adminKilit)>
                                                        <span></span>
                                                    </label>
                                                @else
                                                    <span class="ym-yok">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                            <td class="ym-diger">
                                                @foreach ($grupIzin->reject(fn ($i, $e) => array_key_exists($e, $eylemler)) as $izin)
                                                    <label class="ym-cip">
                                                        <input type="checkbox" name="izinler[]" value="{{ $izin->id }}" data-eylem="diger"
                                                               @checked($adminKilit || in_array($izin->id, $seciliSet)) @disabled($adminKilit)>
                                                        <span>{{ $izin->ad }}</span>
                                                    </label>
                                                @endforeach
                                            </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ route('panel.roller.index') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            {{ $rol->exists ? 'Değişiklikleri Kaydet' : 'Rolü Oluştur' }}
        </button>
    </div>
</form>

<script>
(function () {
    const form = document.getElementById('ym-rol-form');
    const kutular = () => Array.from(form.querySelectorAll('input[name="izinler[]"]:not(:disabled)'));
    const satirKutulari = tr => Array.from(tr.querySelectorAll('input[name="izinler[]"]'));

    function sayaclar() {
        form.querySelectorAll('.ym-modul').forEach(m => {
            const k = Array.from(m.querySelectorAll('input[name="izinler[]"]'));
            m.querySelector('[data-modul-sayi]').textContent = k.filter(x => x.checked).length + ' / ' + k.length;
        });
        const hepsi = Array.from(form.querySelectorAll('input[name="izinler[]"]'));
        document.getElementById('ym-izin-toplam').textContent = hepsi.filter(x => x.checked).length + ' / ' + hepsi.length;
    }

    // Kural: düzenle/oluştur/sil seçilirse görüntüle de seçilir; görüntüle kaldırılırsa diğerleri de kalkar
    form.addEventListener('change', e => {
        const k = e.target;
        if (k.name !== 'izinler[]') return;
        const tr = k.closest('tr');
        const gor = tr.querySelector('input[data-eylem="goruntule"]');
        if (gor && k !== gor && k.checked) gor.checked = true;
        if (gor && k === gor && !k.checked) satirKutulari(tr).forEach(x => { x.checked = false; });
        sayaclar();
    });

    form.addEventListener('click', e => {
        const grupBtn = e.target.closest('[data-grup-tum]');
        const modulBtn = e.target.closest('[data-modul-tum]');
        const topluBtn = e.target.closest('[data-toplu]');
        if (grupBtn) {
            const k = satirKutulari(grupBtn.closest('tr'));
            const hepsiSecili = k.every(x => x.checked);
            k.forEach(x => { x.checked = !hepsiSecili; });
        } else if (modulBtn) {
            const k = Array.from(modulBtn.closest('.ym-modul').querySelectorAll('input[name="izinler[]"]'));
            const hepsiSecili = k.every(x => x.checked);
            k.forEach(x => { x.checked = !hepsiSecili; });
        } else if (topluBtn) {
            const tur = topluBtn.dataset.toplu;
            kutular().forEach(x => { x.checked = tur === 'hepsi' || (tur === 'goruntule' && x.dataset.eylem === 'goruntule'); });
        } else {
            return;
        }
        sayaclar();
    });

    sayaclar();
})();
</script>
@endsection
