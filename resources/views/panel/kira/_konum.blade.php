{{-- Konum ve sınır bölümü — dosya formu ve kira alanı formu ortak kullanır.
     Parametreler: $no, $v, $baslik, $aciklama, $kmhAyar (null → harita yok),
     $iller, $ilceler, $mahalleler, $imarDurumlari, $envanterIpucu --}}
<section class="form-section">
    <div class="form-section-head">
        <span class="form-section-num">{{ $no }}</span>
        <div class="form-section-head-text">
            <h3>{{ $baslik }}</h3>
            <small>{{ $aciklama }}</small>
        </div>
    </div>

    {{-- Sınır: çizilir, ada/parselden getirilir ya da haritadan seçilir.
         Çıktı formdaki gizli "koordinat" alanına GeoJSON olarak yazılır. --}}
    @if ($kmhAyar)
        <div class="form-row">
            <div class="form-field">
                <div class="kmh" data-kmh="{{ json_encode($kmhAyar) }}"></div>
                @isset($haritaIpucu)<span class="hint">{!! $haritaIpucu !!}</span>@endisset
            </div>
        </div>
    @endif

    <div class="form-row form-row-3">
        <div class="form-field">
            <label for="il_id">İl</label>
            <select id="il_id" name="il_id" class="form-select">
                <option value="">Seçin</option>
                @foreach ($iller as $i)
                    <option value="{{ $i->id }}" @selected((string) old('il_id', $v['il_id'] ?? null) === (string) $i->id)>{{ $i->ad }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-field">
            <label for="ilce_id">İlçe</label>
            <select id="ilce_id" name="ilce_id" class="form-select">
                <option value="">Önce il seçin</option>
                @foreach ($ilceler as $i)
                    <option value="{{ $i->id }}" @selected((string) old('ilce_id', $v['ilce_id'] ?? null) === (string) $i->id)>{{ $i->ad }}</option>
                @endforeach
            </select>
            @error('ilce_id')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div class="form-field">
            <label for="mahalle_id">Mahalle</label>
            <select id="mahalle_id" name="mahalle_id" class="form-select">
                <option value="">Önce ilçe seçin</option>
                @foreach ($mahalleler as $m)
                    {{-- data-tkgm: haritadaki "Ada/Parselden Getir" TKGM mahalle kimliğini buradan okur --}}
                    <option value="{{ $m->id }}" data-tkgm="{{ $m->tkgm_id }}" @selected((string) old('mahalle_id', $v['mahalle_id'] ?? null) === (string) $m->id)>{{ $m->ad }}</option>
                @endforeach
            </select>
            @error('mahalle_id')<span class="error">{{ $message }}</span>@enderror
        </div>
    </div>
    <div class="form-row form-row-3">
        <div class="form-field">
            <label for="ada">Ada</label>
            <input id="ada" name="ada" class="form-input td-mono" value="{{ old('ada', $v['ada'] ?? null) }}" maxlength="20" inputmode="numeric">
        </div>
        <div class="form-field">
            <label for="parsel">Parsel</label>
            <input id="parsel" name="parsel" class="form-input td-mono" value="{{ old('parsel', $v['parsel'] ?? null) }}" maxlength="20" inputmode="numeric">
        </div>
        <div class="form-field">
            <label for="yuzolcum">Parsel Yüzölçümü (m²)</label>
            <input id="yuzolcum" name="yuzolcum" type="number" class="form-input td-mono" value="{{ old('yuzolcum', $v['yuzolcum'] ?? null) }}" step="0.01" min="0">
        </div>
    </div>
    <div class="form-row form-row-2">
        <div class="form-field">
            <label for="imar_durumu_id">İmar Durumu</label>
            <select id="imar_durumu_id" name="imar_durumu_id" class="form-select">
                <option value="">Seçin</option>
                @foreach ($imarDurumlari as $id)
                    <option value="{{ $id->id }}" @selected((string) old('imar_durumu_id', $v['imar_durumu_id'] ?? null) === (string) $id->id)>{{ $id->ad }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-field">
            <label for="tasinmaz_id">Envanter Kaydı</label>
            <input id="tasinmaz_id" name="tasinmaz_id" type="number" class="form-input td-mono" value="{{ old('tasinmaz_id', $v['tasinmaz_id'] ?? null) }}" min="1" placeholder="Taşınmaz kayıt no">
            <span class="hint">{{ $envanterIpucu ?? 'Envanterdeki taşınmaza bağlamak için.' }}</span>
            @error('tasinmaz_id')<span class="error">{{ $message }}</span>@enderror
        </div>
    </div>
    <div class="form-row">
        <div class="form-field">
            <label for="adres">Kiralanan Yerin Adresi</label>
            <textarea id="adres" name="adres" class="form-textarea" rows="2" maxlength="1000">{{ old('adres', $v['adres'] ?? null) }}</textarea>
        </div>
    </div>
</section>
