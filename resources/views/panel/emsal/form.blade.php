@extends('layouts.panel')

@section('title', $emsal ? 'Emsal Düzenle' : 'Yeni Emsal')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/degerleme.css') }}?v=3">
    <link rel="stylesheet" href="{{ asset('css/emsal.css') }}?v=1">
@endpush

@php
    $p = $emsal?->parsel;
    // Haritadan gelindiyse alanlar ön dolgudan beslenir
    $ilk = fn (string $alan) => $p?->{$alan} ?? ($onDolgu[$alan] ?? null);
    $secili = old('tip', $emsal?->tip?->value ?? \App\Enums\EmsalTipi::Encumen->value);
    $bolumler = old('bolumler', $emsal?->bagimsizBolumler?->map(fn ($b) => $b->only(['bb_no', 'kat', 'cephe', 'brut_alan', 'nitelik', 'bb_durumu']))->all() ?? []);
@endphp

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Değerleme · Emsal</p>
        <h2>{{ $emsal ? 'Emsal Düzenle' : 'Yeni Emsal' }}</h2>
        <small>Emsal parseli kurum envanterine eklenmez; aynı ada/parsel daha önce girildiyse mevcut parsele bağlanır.</small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.emsal.index') }}" class="btn-cancel">Listeye dön</a>
    </div>
</section>

@if ($errors->any())
    <div class="flash-error" role="alert">
        <strong>Kayıt tamamlanmadı.</strong>
        <ul>@foreach ($errors->all() as $hata)<li>{{ $hata }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" enctype="multipart/form-data" class="form-shell" id="emsalForm"
      action="{{ $emsal ? route('panel.emsal.guncelle', $emsal->id) : route('panel.emsal.kaydet') }}">
    @csrf
    @if ($emsal)@method('PUT')@endif

    {{-- 1 · Tür --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">1</span>
            <div class="form-section-head-text">
                <h3>Emsal Türü</h3>
                <small>Satış bedelinin dayandığı belge. Seçime göre aşağıdaki alanlar değişir.</small>
            </div>
        </div>
        <div class="dg-tur-secim" role="radiogroup" aria-label="Emsal türü">
            @foreach ($tipler as $t)
                <label class="dg-tur-kart em-tur-kart" style="--em-renk:{{ $t->renk() }}">
                    <input type="radio" name="tip" value="{{ $t->value }}" @checked($secili === $t->value) data-tur-sec>
                    <span>
                        <strong>{{ $t->etiket() }}</strong>
                        <small>{{ $t->aciklama() }}</small>
                    </span>
                </label>
            @endforeach
        </div>
    </section>

    {{-- 2 · Parsel --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">2</span>
            <div class="form-section-head-text">
                <h3>Parsel</h3>
                <small>Emsalin alındığı taşınmaz. Envantere eklenmez; yalnız emsal kayıtlarında kullanılır.</small>
            </div>
        </div>

        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="il_id">İl <span class="required">*</span></label>
                <select id="il_id" name="il_id" class="form-select" required>
                    <option value="">Seçin</option>
                    @foreach ($iller as $i)
                        <option value="{{ $i->id }}" @selected((string) old('il_id', $ilk('il_id')) === (string) $i->id)>{{ $i->ad }}</option>
                    @endforeach
                </select>
                @error('il_id')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="ilce_id">İlçe <span class="required">*</span></label>
                <select id="ilce_id" name="ilce_id" class="form-select" required>
                    <option value="">Seçin</option>
                    @foreach ($ilceler as $i)
                        <option value="{{ $i->id }}" @selected((string) old('ilce_id', $ilk('ilce_id')) === (string) $i->id)>{{ $i->ad }}</option>
                    @endforeach
                </select>
                @error('ilce_id')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="mahalle_id">Mahalle <span class="required">*</span></label>
                <select id="mahalle_id" name="mahalle_id" class="form-select" required>
                    <option value="">Seçin</option>
                    @foreach ($mahalleler as $m)
                        <option value="{{ $m->id }}" @selected((string) old('mahalle_id', $ilk('mahalle_id')) === (string) $m->id)>{{ $m->ad }}</option>
                    @endforeach
                </select>
                @error('mahalle_id')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>

        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="ada">Ada <span class="required">*</span></label>
                <input id="ada" name="ada" class="form-input td-mono" value="{{ old('ada', $ilk('ada')) }}" maxlength="20" inputmode="numeric" required>
                @error('ada')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="parsel">Parsel <span class="required">*</span></label>
                <input id="parsel" name="parsel" class="form-input td-mono" value="{{ old('parsel', $ilk('parsel')) }}" maxlength="20" inputmode="numeric" required>
                @error('parsel')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="alan">Parsel Alanı (m²)</label>
                <input id="alan" name="alan" type="number" class="form-input td-mono" value="{{ old('alan', $ilk('alan')) }}" step="0.01" min="0">
                @error('alan')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>

        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="imar_durumu_id">İmar Durumu</label>
                <select id="imar_durumu_id" name="imar_durumu_id" class="form-select">
                    <option value="">Seçin</option>
                    @foreach ($imarDurumlari as $id)
                        <option value="{{ $id->id }}" @selected((string) old('imar_durumu_id', $ilk('imar_durumu_id')) === (string) $id->id)>{{ $id->ad }}</option>
                    @endforeach
                </select>
                <span class="hint">Haritada katman rengini bu belirler.</span>
            </div>
            <div class="form-field">
                <label for="emsal">Emsal (KAKS)</label>
                <input id="emsal" name="emsal" type="number" class="form-input td-mono" value="{{ old('emsal', $ilk('emsal')) }}" step="0.01" min="0" max="999.99">
                @error('emsal')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="yenaz_yencok">Yençok / Yenaz</label>
                <input id="yenaz_yencok" name="yenaz_yencok" class="form-input" value="{{ old('yenaz_yencok', $ilk('yenaz_yencok')) }}" maxlength="50" placeholder="Örn. 5 kat">
            </div>
        </div>
    </section>

    {{-- 3 · Satış --}}
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">3</span>
            <div class="form-section-head-text">
                <h3>Satış Bilgileri</h3>
                <small>Birim fiyat değerleme hesaplarında emsal olarak kullanılır.</small>
            </div>
        </div>

        <div class="form-row form-row-3">
            <div class="form-field">
                <label for="tarih">Tarih <span class="required">*</span></label>
                <input id="tarih" name="tarih" type="date" class="form-input" value="{{ old('tarih', $emsal?->tarih?->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
                @error('tarih')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="birim_fiyat">Birim Fiyat (TL/m²) <span class="required">*</span></label>
                <input id="birim_fiyat" name="birim_fiyat" type="number" class="form-input td-mono" value="{{ old('birim_fiyat', $emsal?->birim_fiyat) }}" step="0.01" min="0" required>
                @error('birim_fiyat')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field" data-tur-alan="encumen senet">
                <label for="satilan_hisse">Satılan Hisse</label>
                <input id="satilan_hisse" name="satilan_hisse" class="form-input td-mono" value="{{ old('satilan_hisse', $emsal?->satilan_hisse) }}" maxlength="50" placeholder="Örn. 1/2">
            </div>
        </div>

        <div class="form-row form-row-3">
            <div class="form-field" data-tur-alan="encumen mahkeme">
                <label for="karar_no">Karar No</label>
                <input id="karar_no" name="karar_no" class="form-input td-mono" value="{{ old('karar_no', $emsal?->karar_no) }}" maxlength="100" placeholder="Örn. 2026/118">
            </div>
            <div class="form-field" data-tur-alan="encumen">
                <label for="kayit_no">Kayıt No</label>
                <input id="kayit_no" name="kayit_no" class="form-input td-mono" value="{{ old('kayit_no', $emsal?->kayit_no) }}" maxlength="100">
            </div>
            <div class="form-field" data-tur-alan="senet">
                <label for="yevmiye_no">Yevmiye No</label>
                <input id="yevmiye_no" name="yevmiye_no" class="form-input td-mono" value="{{ old('yevmiye_no', $emsal?->yevmiye_no) }}" maxlength="100">
            </div>
            <div class="form-field" data-tur-alan="senet">
                <label for="satis_durumu">Satış Durumu</label>
                <input id="satis_durumu" name="satis_durumu" class="form-input" value="{{ old('satis_durumu', $emsal?->satis_durumu) }}" maxlength="50" placeholder="Örn. Tamamlandı">
            </div>
            <div class="form-field" data-tur-alan="mahkeme">
                <label for="esas_no">Esas No</label>
                <input id="esas_no" name="esas_no" class="form-input td-mono" value="{{ old('esas_no', $emsal?->esas_no) }}" maxlength="100" placeholder="Örn. 2025/77">
            </div>
            <div class="form-field" data-tur-alan="mahkeme">
                <label for="dava_tarihi">Dava Tarihi</label>
                <input id="dava_tarihi" name="dava_tarihi" type="date" class="form-input" value="{{ old('dava_tarihi', $emsal?->dava_tarihi?->format('Y-m-d')) }}">
                @error('dava_tarihi')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="aciklama">Açıklama</label>
                <textarea id="aciklama" name="aciklama" class="form-textarea" rows="3" maxlength="2000">{{ old('aciklama', $emsal?->aciklama) }}</textarea>
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="evrak">Evrak</label>
                @if ($emsal?->evrak)
                    <span class="dg-evrak-mevcut">
                        <a href="{{ asset('storage/'.$emsal->evrak) }}" target="_blank" rel="noopener">Mevcut evrakı aç ↗</a>
                        <label class="em-kaldir"><input type="checkbox" name="evrak_kaldir" value="1"> Kaldır</label>
                    </span>
                @endif
                <input id="evrak" name="evrak" type="file" class="form-input" accept=".pdf,.jpg,.jpeg,.png">
                <span class="hint">PDF ya da görsel, en fazla 10 MB.</span>
                @error('evrak')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
    </section>

    {{-- 4 · Bağımsız bölümler --}}
    <section class="form-section" data-tur-alan="encumen senet">
        <div class="form-section-head">
            <span class="form-section-num">4</span>
            <div class="form-section-head-text">
                <h3>Bağımsız Bölümler</h3>
                <small>Kat mülkiyetli satışlarda satışa konu bölümler. Arsa satışında boş bırakın.</small>
            </div>
            <button type="button" class="btn-cancel hs-mini-btn dg-sag" id="bolumEkle">+ Bölüm ekle</button>
        </div>
        <div class="tl-tablo-wrap">
            <table class="tl-tablo dg-ic-tablo em-bolum-tablo" id="bolumTablo">
                <thead>
                    <tr><th>BB No</th><th>Kat</th><th>Cephe</th><th class="is-sag">Brüt Alan (m²)</th><th>Nitelik</th><th>Durum</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($bolumler as $i => $b)
                        <tr>
                            <td><input name="bolumler[{{ $i }}][bb_no]" class="form-input td-mono" value="{{ $b['bb_no'] ?? '' }}" maxlength="30"></td>
                            <td><input name="bolumler[{{ $i }}][kat]" class="form-input" value="{{ $b['kat'] ?? '' }}" maxlength="30"></td>
                            <td><input name="bolumler[{{ $i }}][cephe]" class="form-input" value="{{ $b['cephe'] ?? '' }}" maxlength="50"></td>
                            <td><input name="bolumler[{{ $i }}][brut_alan]" type="number" class="form-input td-mono" value="{{ $b['brut_alan'] ?? '' }}" step="0.01" min="0"></td>
                            <td><input name="bolumler[{{ $i }}][nitelik]" class="form-input" value="{{ $b['nitelik'] ?? '' }}" maxlength="100"></td>
                            <td><input name="bolumler[{{ $i }}][bb_durumu]" class="form-input" value="{{ $b['bb_durumu'] ?? '' }}" maxlength="50"></td>
                            <td><button type="button" class="dg-kaldir" data-bolum-sil>Sil</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="hint em-bolum-bos" @if (count($bolumler)) hidden @endif>Henüz bölüm eklenmedi.</p>
    </section>

    <div class="form-actions">
        <a href="{{ route('panel.emsal.index') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">{{ $emsal ? 'Güncelle' : 'Emsali Kaydet' }}</button>
    </div>
</form>

<script>
(function () {
    const form = document.getElementById('emsalForm');
    const govde = document.querySelector('#bolumTablo tbody');
    const bosNot = document.querySelector('.em-bolum-bos');

    // Seçilen türe ait olmayan alanlar gizlenir; gizliyken gönderilmemeleri için
    // devre dışı bırakılır, böylece sunucuya eski türün numaraları gitmez.
    function turUygula() {
        const tur = form.querySelector('[name="tip"]:checked')?.value;
        form.querySelectorAll('[data-tur-alan]').forEach(function (alan) {
            const gorunur = alan.dataset.turAlan.split(' ').includes(tur);
            alan.hidden = !gorunur;
            alan.querySelectorAll('input, select, textarea').forEach(function (girdi) {
                girdi.disabled = !gorunur;
            });
        });
    }

    form.querySelectorAll('[data-tur-sec]').forEach(function (girdi) {
        girdi.addEventListener('change', turUygula);
    });
    turUygula();

    // İl → ilçe → mahalle zinciri
    const ilSecim = document.getElementById('il_id');
    const ilceSecim = document.getElementById('ilce_id');
    const mahalleSecim = document.getElementById('mahalle_id');

    async function doldur(secim, url) {
        secim.innerHTML = '<option value="">Yükleniyor…</option>';
        secim.disabled = true;
        try {
            const yanit = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const veri = await yanit.json();
            secim.innerHTML = '<option value="">Seçin</option>';
            veri.forEach(function (satir) {
                const secenek = document.createElement('option');
                secenek.value = satir.id;
                secenek.textContent = satir.ad;
                secim.appendChild(secenek);
            });
        } catch (e) {
            secim.innerHTML = '<option value="">Liste alınamadı</option>';
        }
        secim.disabled = false;
    }

    ilSecim.addEventListener('change', function () {
        mahalleSecim.innerHTML = '<option value="">Önce ilçe seçin</option>';
        if (!this.value) {
            ilceSecim.innerHTML = '<option value="">Önce il seçin</option>';
            return;
        }
        doldur(ilceSecim, '{{ route('panel.ajax.ilceler', ['il' => '__ID__']) }}'.replace('__ID__', this.value));
    });

    ilceSecim.addEventListener('change', function () {
        if (!this.value) {
            mahalleSecim.innerHTML = '<option value="">Önce ilçe seçin</option>';
            return;
        }
        doldur(mahalleSecim, '{{ route('panel.ajax.mahalleler', ['ilce' => '__ID__']) }}'.replace('__ID__', this.value));
    });

    // Bağımsız bölüm satırları
    let sayac = {{ count($bolumler) }};

    function bosNotuTazele() {
        bosNot.hidden = govde.children.length > 0;
    }

    document.getElementById('bolumEkle').addEventListener('click', function () {
        const alanlar = [
            ['bb_no', 'text', 30, true], ['kat', 'text', 30, false], ['cephe', 'text', 50, false],
            ['brut_alan', 'number', null, true], ['nitelik', 'text', 100, false], ['bb_durumu', 'text', 50, false],
        ];
        const satir = document.createElement('tr');
        alanlar.forEach(function (alan) {
            const hucre = document.createElement('td');
            const girdi = document.createElement('input');
            girdi.type = alan[1];
            girdi.name = 'bolumler[' + sayac + '][' + alan[0] + ']';
            girdi.className = 'form-input' + (alan[3] ? ' td-mono' : '');
            if (alan[2]) girdi.maxLength = alan[2];
            if (alan[1] === 'number') { girdi.step = '0.01'; girdi.min = '0'; }
            hucre.appendChild(girdi);
            satir.appendChild(hucre);
        });
        const silHucre = document.createElement('td');
        const silDugme = document.createElement('button');
        silDugme.type = 'button';
        silDugme.className = 'dg-kaldir';
        silDugme.dataset.bolumSil = '';
        silDugme.textContent = 'Sil';
        silHucre.appendChild(silDugme);
        satir.appendChild(silHucre);
        govde.appendChild(satir);
        sayac++;
        bosNotuTazele();
    });

    govde.addEventListener('click', function (olay) {
        if (olay.target.matches('[data-bolum-sil]')) {
            olay.target.closest('tr').remove();
            bosNotuTazele();
        }
    });

    bosNotuTazele();
})();
</script>
@endsection
