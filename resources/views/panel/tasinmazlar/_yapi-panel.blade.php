@php
    // Politika: her taşınmaz kaydı en fazla tek bir bağımsız bölüm bilgisi taşır.
    // BBN yalnızca FİZİKSEL/KADASTRAL alanları tutar; muhasebe/kayıt türü/kullanım
    // ve satış/işgal/tahsis/kira/üst hakkı/meclis kararı taşınmaz (arsa) seviyesinde
    // Bölüm 05 (Kategori) ve Bölüm 06 (Ekbilgi) altında girilir.
    $yapiMevcut = $yapiMevcut ?? null;
    $nitelikler = $nitelikler ?? ['Mesken', 'Dükkan', 'Depo', 'İşyeri', 'Büro', 'Ortak Alan'];

    $yapi = old('yapi', $yapiMevcut ? [
        'blok_no' => $yapiMevcut->blok_no,
        'kat_no' => $yapiMevcut->kat_no,
        'bagimsiz_bolum_no' => $yapiMevcut->bagimsiz_bolum_no,
        'nitelik' => $yapiMevcut->nitelik,
        'brut_alan' => $yapiMevcut->brut_alan,
        'net_alan' => $yapiMevcut->net_alan,
        'oda_sayisi' => $yapiMevcut->oda_sayisi,
        'cephe' => $yapiMevcut->cephe,
        'aciklama' => $yapiMevcut->aciklama,
    ] : []);

    $al = fn (string $k, mixed $vars = null) => data_get($yapi, $k, $vars);
@endphp

<div class="yapi-tekil">
    <p class="yapi-tekil-hint">
        Bir taşınmaz kaydı en fazla <strong>tek bağımsız bölüm</strong> bilgisi taşır. Aynı ada/parselde başka daire/dükkan varsa <em>ayrı taşınmaz kaydı</em> açın.
        Alanları boş bırakırsanız BBN oluşturulmaz. Muhasebe, kayıt türü, kullanım ve durum bilgileri arsa için <strong>Bölüm 05–06</strong>'da girilir.
    </p>

    <div class="form-row form-row-4">
        <div class="form-field {{ $errors->has('yapi.blok_no') ? 'has-error' : '' }}">
            <label for="yapi_blok_no">Blok No</label>
            <input id="yapi_blok_no" type="text" name="yapi[blok_no]" class="form-input" maxlength="20" placeholder="A" value="{{ $al('blok_no') }}">
            @error('yapi.blok_no')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div class="form-field {{ $errors->has('yapi.kat_no') ? 'has-error' : '' }}">
            <label for="yapi_kat_no">Kat No</label>
            <input id="yapi_kat_no" type="text" name="yapi[kat_no]" class="form-input" maxlength="10" placeholder="Zemin · 1" value="{{ $al('kat_no') }}">
            @error('yapi.kat_no')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div class="form-field {{ $errors->has('yapi.bagimsiz_bolum_no') ? 'has-error' : '' }}">
            <label for="yapi_bbn">Bağımsız Bölüm No</label>
            <input id="yapi_bbn" type="text" name="yapi[bagimsiz_bolum_no]" class="form-input" maxlength="20" placeholder="12" value="{{ $al('bagimsiz_bolum_no') }}">
            @error('yapi.bagimsiz_bolum_no')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div class="form-field {{ $errors->has('yapi.nitelik') ? 'has-error' : '' }}">
            <label for="yapi_nitelik">Bölüm Niteliği</label>
            <select id="yapi_nitelik" name="yapi[nitelik]" class="form-select">
                <option value="">— Seçiniz —</option>
                @foreach ($nitelikler as $n)
                    <option value="{{ $n }}" @selected($al('nitelik') === $n)>{{ $n }}</option>
                @endforeach
            </select>
            @error('yapi.nitelik')<span class="error">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="form-row form-row-4">
        <div class="form-field {{ $errors->has('yapi.brut_alan') ? 'has-error' : '' }}">
            <label for="yapi_brut">Brüt Alan (m²)</label>
            <input id="yapi_brut" type="text" name="yapi[brut_alan]" data-tr-numeric class="form-input" inputmode="decimal" placeholder="120,50"
                   value="{{ $al('brut_alan') !== null ? str_replace('.', ',', (string) $al('brut_alan')) : '' }}">
            @error('yapi.brut_alan')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div class="form-field {{ $errors->has('yapi.net_alan') ? 'has-error' : '' }}">
            <label for="yapi_net">Net Alan (m²)</label>
            <input id="yapi_net" type="text" name="yapi[net_alan]" data-tr-numeric class="form-input" inputmode="decimal" placeholder="98,00"
                   value="{{ $al('net_alan') !== null ? str_replace('.', ',', (string) $al('net_alan')) : '' }}">
            @error('yapi.net_alan')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div class="form-field {{ $errors->has('yapi.oda_sayisi') ? 'has-error' : '' }}">
            <label for="yapi_oda">Oda</label>
            <input id="yapi_oda" type="text" name="yapi[oda_sayisi]" class="form-input" maxlength="10" placeholder="3+1" value="{{ $al('oda_sayisi') }}">
            @error('yapi.oda_sayisi')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div class="form-field {{ $errors->has('yapi.cephe') ? 'has-error' : '' }}">
            <label for="yapi_cephe">Cephe</label>
            <input id="yapi_cephe" type="text" name="yapi[cephe]" class="form-input" maxlength="50" placeholder="Kuzey, Doğu" value="{{ $al('cephe') }}">
            @error('yapi.cephe')<span class="error">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="form-row">
        <div class="form-field {{ $errors->has('yapi.aciklama') ? 'has-error' : '' }}">
            <label for="yapi_aciklama">Bölüm Açıklaması</label>
            <input id="yapi_aciklama" type="text" name="yapi[aciklama]" class="form-input" maxlength="500" placeholder="Birime özel not, kısa açıklama..." value="{{ $al('aciklama') }}">
            @error('yapi.aciklama')<span class="error">{{ $message }}</span>@enderror
        </div>
    </div>
</div>
