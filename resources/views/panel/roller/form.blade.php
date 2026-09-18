@extends('layouts.panel')

@section('title', $rol->exists ? 'Rol Düzenle' : 'Yeni Rol')

@push('head')
<style>
    .yzk-modul { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 14px; overflow: hidden; }
    .yzk-modul-head {
        display: flex; align-items: center; gap: 12px;
        padding: 14px 16px; background: linear-gradient(135deg, var(--modul-r1, #eff6ff) 0%, var(--modul-r2, #dbeafe) 100%);
        border-bottom: 1px solid #e5e7eb;
    }
    .yzk-modul-ikon {
        width: 40px; height: 40px; border-radius: 10px; background: var(--modul-c, #2563eb); color: #fff;
        display: flex; align-items: center; justify-content: center; flex: 0 0 40px;
    }
    .yzk-modul-ikon svg { width: 20px; height: 20px; }
    .yzk-modul-baslik { flex: 1; min-width: 0; }
    .yzk-modul-ad { font-size: 1rem; font-weight: 700; color: #0e1726; }
    .yzk-modul-alt { font-size: .74rem; color: #4b5563; margin-top: 2px; }
    .yzk-modul-sayac { font-size: .74rem; color: #4b5563; font-weight: 600; }
    .yzk-modul-toggle {
        background: rgba(255,255,255,.8); border: 1px solid rgba(0,0,0,.08);
        color: #1e40af; font-weight: 600; font-size: .74rem;
        padding: 6px 12px; border-radius: 6px; cursor: pointer;
    }
    .yzk-modul-toggle:hover { background: #fff; }
    .yzk-modul-toggle svg { width: 12px; height: 12px; }

    .yzk-modul-body { padding: 12px; }
    .yzk-grup { margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #e5e7eb; }
    .yzk-grup:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
    .yzk-grup-baslik {
        display: flex; align-items: center; justify-content: space-between;
        font-size: .82rem; font-weight: 700; color: #374151;
        text-transform: uppercase; letter-spacing: .04em;
        margin-bottom: 10px; padding: 6px 4px;
    }
    .yzk-grup-baslik .say {
        margin-left: auto; margin-right: 8px;
        background: #f3f4f6; color: #6b7280;
        padding: 2px 8px; border-radius: 999px; font-size: .68rem; font-weight: 700;
    }

    .yzk-izin-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 8px; }
    .yzk-izin {
        display: flex; align-items: flex-start; gap: 10px;
        padding: 10px 12px; background: #f9fafb; border: 1.5px solid #e5e7eb;
        border-radius: 8px; cursor: pointer; transition: all .12s ease;
    }
    .yzk-izin:hover { background: #eff6ff; border-color: #93c5fd; }
    .yzk-izin.is-secili { background: #ecfdf5; border-color: #34d399; }
    .yzk-izin input[type="checkbox"] {
        margin: 3px 0 0; width: 16px; height: 16px; accent-color: #059669;
        cursor: pointer; flex: 0 0 16px;
    }
    .yzk-izin-meta { flex: 1; min-width: 0; }
    .yzk-izin-ad { font-size: .85rem; font-weight: 600; color: #111827; }
    .yzk-izin-kod { font-size: .68rem; color: #6b7280; font-family: 'JetBrains Mono', ui-monospace, monospace; margin-top: 2px; }
    .yzk-izin.is-secili .yzk-izin-ad { color: #065f46; }
</style>
@endpush

@section('content')
@if ($errors->any())
    <div class="flash-error" role="alert">Lütfen aşağıdaki alanları kontrol edin.</div>
@endif

@php
    // Modül tanımları — her modul birden fazla `grup` kapsayabilir
    $moduller = [
        'tasinmaz' => [
            'ad' => 'Taşınmaz Yönetimi',
            'alt' => 'Taşınmaz kayıtları, hisseleri, yapıları ve harita.',
            'renk' => '#2563eb', 'renk1' => '#eff6ff', 'renk2' => '#dbeafe',
            'ikon' => '<path d="M3 21h18M5 21V7l7-5 7 5v14"/><path d="M9 21v-6h6v6"/>',
            'gruplar' => ['tasinmaz' => 'Taşınmaz', 'hisse' => 'Hisse', 'yapi' => 'Yapı / BBN', 'harita' => 'Harita'],
        ],
        'satis' => [
            'ad' => 'Satış İşlemleri',
            'alt' => 'Hisse satışı süreçleri, ecrimisil ve lojman tahsis.',
            'renk' => '#f59e0b', 'renk1' => '#fffbeb', 'renk2' => '#fef3c7',
            'ikon' => '<circle cx="12" cy="12" r="9"/><path d="M15 9.5c-.5-1-1.5-1.5-3-1.5-1.7 0-3 1-3 2 0 2.5 6 1.5 6 4 0 1-1.3 2-3 2-1.5 0-2.5-.5-3-1.5M12 6v2M12 16v2"/>',
            'gruplar' => ['hisse-satisi' => 'Hisse Satışı', 'ecrimisil' => 'Ecrimisil', 'lojman' => 'Lojman'],
        ],
        'rapor' => [
            'ad' => 'Raporlama',
            'alt' => 'Sayıştay raporları ve genel rapor görüntüleme.',
            'renk' => '#8b5cf6', 'renk1' => '#f5f3ff', 'renk2' => '#ede9fe',
            'ikon' => '<path d="M4 20V10M10 20V4M16 20V13M22 20H2"/>',
            'gruplar' => ['rapor' => 'Raporlar'],
        ],
        'sistem' => [
            'ad' => 'Sistem Yönetimi',
            'alt' => 'Kullanıcılar, roller ve müdürlükler.',
            'renk' => '#0e1726', 'renk1' => '#f3f4f6', 'renk2' => '#e5e7eb',
            'ikon' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c1-4 4-6 6.5-6s5.5 2 6.5 6"/><circle cx="17" cy="9" r="2.5"/><path d="M15 14c3 0 5 2 6 6"/>',
            'gruplar' => ['kullanici' => 'Kullanıcı', 'rol' => 'Rol', 'mudurluk' => 'Müdürlük'],
        ],
    ];
    $adminKilit = $rol->exists && $rol->kod === 'admin';
    $sistemKilit = $rol->exists && $rol->sistem_mi;
    $izinlerGruplu = $izinler->groupBy('grup');
    $seciliSet = collect(old('izinler', $seciliIzinler->all()))->map(fn ($v) => (int) $v)->all();
@endphp

<form method="POST" action="{{ $rol->exists ? route('panel.roller.guncelle', $rol) : route('panel.roller.store') }}" class="form-shell" id="yzk-form">
    @csrf
    @if ($rol->exists) @method('PUT') @endif

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">01</span>
            <div class="form-section-head-text">
                <h3>{{ $rol->exists ? 'Rolü düzenle' : 'Yeni rol' }}</h3>
                <small>Kod benzersiz olmalıdır. Sistem rolleri silinemez.</small>
            </div>
        </div>

        <div class="form-row form-row-3">
            <div class="form-field {{ $errors->has('ad') ? 'has-error' : '' }}">
                <label for="ad">Ad <span class="required">*</span></label>
                <input id="ad" name="ad" type="text" class="form-input" value="{{ old('ad', $rol->ad) }}" required maxlength="100">
                @error('ad')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field {{ $errors->has('kod') ? 'has-error' : '' }}">
                <label for="kod">Kod <span class="required">*</span></label>
                <input id="kod" name="kod" type="text" class="form-input" value="{{ old('kod', $rol->kod) }}" required maxlength="40" {{ $sistemKilit ? 'readonly' : '' }} placeholder="ornek-rol">
                @error('kod')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="sira">Sıra</label>
                <input id="sira" name="sira" type="number" class="form-input" value="{{ old('sira', $rol->sira ?? 0) }}" min="0">
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label for="aciklama">Açıklama</label>
                <textarea id="aciklama" name="aciklama" class="form-textarea" rows="2">{{ old('aciklama', $rol->aciklama) }}</textarea>
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">02</span>
            <div class="form-section-head-text">
                <h3>İzinler — Modül bazlı</h3>
                <small>
                    @if ($adminKilit)
                        Admin rolü tüm izinlere otomatik sahiptir; işaretler değiştirilemez.
                    @else
                        Her modül kartındaki "Hepsi" düğmesiyle grup içi izinleri topluca seçebilirsiniz.
                    @endif
                </small>
            </div>
            <div style="margin-left:auto;display:flex;gap:6px;">
                <button type="button" class="btn-cancel" style="padding:6px 12px;font-size:.78rem;" onclick="yzkTumu(true)" @disabled($adminKilit)>Tümünü Seç</button>
                <button type="button" class="btn-cancel" style="padding:6px 12px;font-size:.78rem;" onclick="yzkTumu(false)" @disabled($adminKilit)>Tümünü Bırak</button>
            </div>
        </div>

        @foreach ($moduller as $modulKod => $mod)
            @php
                $modulIzinIdleri = collect();
                foreach ($mod['gruplar'] as $gk => $ge) {
                    if (isset($izinlerGruplu[$gk])) {
                        $modulIzinIdleri = $modulIzinIdleri->concat($izinlerGruplu[$gk]->pluck('id'));
                    }
                }
                if ($modulIzinIdleri->isEmpty()) continue;
                $seciliMd = $modulIzinIdleri->intersect($seciliSet)->count();
                $toplamMd = $modulIzinIdleri->count();
            @endphp
            <div class="yzk-modul" data-modul="{{ $modulKod }}" style="--modul-c: {{ $mod['renk'] }}; --modul-r1: {{ $mod['renk1'] }}; --modul-r2: {{ $mod['renk2'] }};">
                <div class="yzk-modul-head">
                    <span class="yzk-modul-ikon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $mod['ikon'] !!}</svg>
                    </span>
                    <div class="yzk-modul-baslik">
                        <div class="yzk-modul-ad">{{ $mod['ad'] }}</div>
                        <div class="yzk-modul-alt">{{ $mod['alt'] }}</div>
                    </div>
                    <span class="yzk-modul-sayac" data-modul-sayac="{{ $modulKod }}">{{ $seciliMd }} / {{ $toplamMd }}</span>
                    @if (! $adminKilit)
                        <button type="button" class="yzk-modul-toggle" onclick="yzkModulToggle('{{ $modulKod }}', true)">✓ Tümü</button>
                        <button type="button" class="yzk-modul-toggle" onclick="yzkModulToggle('{{ $modulKod }}', false)">✕ Hiç</button>
                    @endif
                </div>
                <div class="yzk-modul-body">
                    @foreach ($mod['gruplar'] as $grupKod => $grupAd)
                        @if (! isset($izinlerGruplu[$grupKod])) @continue @endif
                        @php
                            $grupIzinler = $izinlerGruplu[$grupKod];
                            $seciliGr = $grupIzinler->pluck('id')->intersect($seciliSet)->count();
                        @endphp
                        <div class="yzk-grup" data-grup="{{ $grupKod }}">
                            <div class="yzk-grup-baslik">
                                <span>{{ $grupAd }}</span>
                                <span class="say" data-grup-sayac="{{ $grupKod }}">{{ $seciliGr }} / {{ $grupIzinler->count() }}</span>
                                @if (! $adminKilit)
                                    <button type="button" class="yzk-modul-toggle" onclick="yzkGrupToggle('{{ $grupKod }}', true)" style="padding:3px 8px;font-size:.7rem;">Hepsi</button>
                                @endif
                            </div>
                            <div class="yzk-izin-grid">
                                @foreach ($grupIzinler as $izin)
                                    @php $isSec = in_array($izin->id, $seciliSet); @endphp
                                    <label class="yzk-izin {{ $isSec ? 'is-secili' : '' }}" data-izin-id="{{ $izin->id }}" data-modul="{{ $modulKod }}" data-grup="{{ $grupKod }}">
                                        <input type="checkbox" name="izinler[]" value="{{ $izin->id }}" @checked($isSec) @disabled($adminKilit)>
                                        <span class="yzk-izin-meta">
                                            <span class="yzk-izin-ad">{{ $izin->ad }}</span>
                                            <span class="yzk-izin-kod">{{ $izin->kod }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>

    <div class="form-actions">
        <a href="{{ route('panel.roller.index') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">Kaydet</button>
    </div>
</form>

<script>
(function () {
    // Checkbox değiştikçe kartın rengi + sayaçlar güncellensin
    document.querySelectorAll('.yzk-izin input[type=checkbox]').forEach(cb => {
        cb.addEventListener('change', () => {
            const lbl = cb.closest('.yzk-izin');
            lbl.classList.toggle('is-secili', cb.checked);
            sayaclariGuncelle();
        });
    });

    function sayaclariGuncelle() {
        document.querySelectorAll('[data-grup-sayac]').forEach(el => {
            const grup = el.dataset.grupSayac;
            const cbs = document.querySelectorAll(`.yzk-izin[data-grup="${grup}"] input[type=checkbox]`);
            const secili = Array.from(cbs).filter(c => c.checked).length;
            el.textContent = `${secili} / ${cbs.length}`;
        });
        document.querySelectorAll('[data-modul-sayac]').forEach(el => {
            const modul = el.dataset.modulSayac;
            const cbs = document.querySelectorAll(`.yzk-izin[data-modul="${modul}"] input[type=checkbox]`);
            const secili = Array.from(cbs).filter(c => c.checked).length;
            el.textContent = `${secili} / ${cbs.length}`;
        });
    }

    window.yzkGrupToggle = function (grupKod, hepsi) {
        document.querySelectorAll(`.yzk-izin[data-grup="${grupKod}"] input[type=checkbox]`).forEach(cb => {
            cb.checked = hepsi;
            cb.dispatchEvent(new Event('change'));
        });
    };
    window.yzkModulToggle = function (modulKod, hepsi) {
        document.querySelectorAll(`.yzk-izin[data-modul="${modulKod}"] input[type=checkbox]`).forEach(cb => {
            cb.checked = hepsi;
            cb.dispatchEvent(new Event('change'));
        });
    };
    window.yzkTumu = function (hepsi) {
        document.querySelectorAll('.yzk-izin input[type=checkbox]').forEach(cb => {
            cb.checked = hepsi;
            cb.dispatchEvent(new Event('change'));
        });
    };
})();
</script>
@endsection
