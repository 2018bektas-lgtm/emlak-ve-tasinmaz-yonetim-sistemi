{{--
    Ecrimisil kayıt formu — olustur ve duzenle sayfalarında ortak.
    Değişkenler: $kayit (?EcrimisilIsgalci), $tasinmaz (?Tasinmaz), $kullanicilar
--}}
@php
    $duzenleme = $kayit !== null;
    $eskiKoordinat = old('koordinat', $kayit?->koordinat ? json_encode($kayit->koordinat) : null);
    $refKoord = null;
    if ($tasinmaz && $tasinmaz->koordinat) {
        $refKoord = ['koordinat' => $tasinmaz->koordinat->koordinat, 'lat' => $tasinmaz->koordinat->lat, 'lng' => $tasinmaz->koordinat->lng];
    }
    $seciliKullanici = old('kullanici_id', $kayit?->kullanici_id);
    // Kayıtta artık pasif olan biri atanmışsa listede kaybolmasın
    if ($kayit?->kullanici_id && ! $kullanicilar->contains('id', $kayit->kullanici_id) && $kayit->kullanici) {
        $kullanicilar = $kullanicilar->push($kayit->kullanici);
    }
@endphp

<form method="POST" action="{{ $duzenleme ? route('panel.ecrimisil.guncelle', $kayit->id) : route('panel.ecrimisil.kaydet') }}" id="ec-form" class="ec-form">
    @csrf
    @if ($duzenleme) @method('PUT') @endif

    {{-- ==== İşgal alanı haritası ==== --}}
    <section class="ec-harita-kart" id="ec-harita-kart">
        <div class="ec-harita-ust">
            <div class="form-section-head ec-harita-baslik">
                <span class="form-section-num">1</span>
                <div class="form-section-head-text">
                    <h3>İşgal Alanı</h3>
                    <small>Alanı haritada çizin ya da KML yükleyin; içinde kalan taşınmaz kendiliğinden bağlanır. İsteğe bağlıdır.</small>
                </div>
            </div>
            <div class="ec-arac" role="toolbar" aria-label="Çizim araçları">
                <button type="button" class="ec-arac-btn" id="ec-btn-poligon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 8l3 12h14l3-12z"/></svg>
                    <span>Alan Çiz</span>
                </button>
                <label class="ec-arac-btn" for="ec-kml-input">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                    <span>KML</span>
                </label>
                <input type="file" id="ec-kml-input" accept=".kml" hidden>
                <button type="button" class="ec-arac-btn" id="ec-btn-konum" title="Konumuma git">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/></svg>
                    <span>Konum</span>
                </button>
                <button type="button" class="ec-arac-btn" id="ec-btn-tam-ekran" title="Tam ekran">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
                    <span>Tam Ekran</span>
                </button>
                <button type="button" class="ec-arac-btn is-tehlike" id="ec-btn-sil" hidden>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M6 6l1 14a2 2 0 002 2h6a2 2 0 002-2l1-14"/></svg>
                    <span>Alanı Sil</span>
                </button>
                <span class="ec-alan-hap" id="ec-mt-durum">Alan: <b>—</b></span>
            </div>
        </div>

        <div id="ec-harita" class="ec-harita"
             data-eski="{{ $eskiKoordinat }}"
             data-ref="{{ $refKoord ? json_encode($refKoord) : '' }}"
             data-api-bul="{{ route('panel.ecrimisil.tasinmaz-bul') }}"
             data-api-mevcut="{{ route('panel.ecrimisil.mevcut-ecrimisiller') }}"
             data-haric="{{ $kayit?->id }}"
             data-csrf="{{ csrf_token() }}"></div>

        <div class="ec-ipucu" id="ec-ipucu" aria-live="polite">“Alan Çiz”e basıp haritaya tıklayarak köşe ekleyin; ilk noktaya ya da çift tıklayarak kapatın.</div>
        <div class="ec-lejant">
            <span><i class="is-cizim"></i>Bu kaydın alanı</span>
            <span><i class="is-ref"></i>Bağlı taşınmaz sınırı</span>
            <span><i class="is-devam-eden"></i>Diğer kayıtlar · işgal sürüyor</span>
            <span><i class="is-sonlanmis"></i>Sonlanmış</span>
            <span><i class="is-yeni"></i>Tutanaksız</span>
        </div>

        <input type="hidden" name="koordinat" id="ec-koordinat" value="{{ $eskiKoordinat }}">
        <input type="hidden" name="lat" id="ec-lat" value="{{ old('lat', $kayit?->lat) }}">
        <input type="hidden" name="lng" id="ec-lng" value="{{ old('lng', $kayit?->lng) }}">
        @error('koordinat')<span class="error">{{ $message }}</span>@enderror
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">2</span>
            <div class="form-section-head-text">
                <h3>İşgalci</h3>
                <small>Gerçek kişi için T.C. Kimlik No (11 hane), şirket için Vergi No (10 hane); kontrol haneleri doğrulanır.</small>
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field">
                <label for="ad_soyad_unvan">Ad Soyad / Şirket Ünvanı <span class="required">*</span></label>
                <input type="text" name="ad_soyad_unvan" id="ad_soyad_unvan" class="form-input" value="{{ old('ad_soyad_unvan', $kayit?->ad_soyad_unvan) }}" required maxlength="200">
                @error('ad_soyad_unvan')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="tc_vergi_no">TC Kimlik / Vergi No</label>
                <input type="text" name="tc_vergi_no" id="tc_vergi_no" class="form-input td-mono" value="{{ old('tc_vergi_no', $kayit?->tc_vergi_no) }}" maxlength="11" inputmode="numeric" pattern="[0-9]{10,11}" placeholder="11 hane TC veya 10 hane VKN">
                @error('tc_vergi_no')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field">
                <label for="nitelik">İşgal Niteliği</label>
                <input type="text" name="nitelik" id="nitelik" class="form-input" value="{{ old('nitelik', $kayit?->nitelik) }}" maxlength="150" list="ec-nitelikler" placeholder="Bakkal, büfe, depo, otopark...">
                <datalist id="ec-nitelikler">
                    @foreach (['Büfe', 'Bakkal', 'Kafe / Restoran', 'Depo', 'Otopark', 'Bahçe / Tarım', 'Konut', 'Satış Ofisi', 'Reklam Panosu', 'Şantiye'] as $n)<option value="{{ $n }}">@endforeach
                </datalist>
            </div>
            <div class="form-field">
                <label for="cadde_sokak">Cadde / Sokak</label>
                <input type="text" name="cadde_sokak" id="cadde_sokak" class="form-input" value="{{ old('cadde_sokak', $kayit?->cadde_sokak) }}" maxlength="200">
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label for="adres">Açık Adres</label>
                <input type="text" name="adres" id="adres" class="form-input" value="{{ old('adres', $kayit?->adres) }}" maxlength="500">
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">3</span>
            <div class="form-section-head-text">
                <h3>Taşınmaz & Sorumlu</h3>
                <small>Aynı taşınmaza birden fazla işgal kaydı açılabilir.</small>
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field">
                <label>Bağlı Taşınmaz</label>
                <input type="hidden" name="tasinmaz_id" id="tasinmaz_id" value="{{ old('tasinmaz_id', $tasinmaz?->id) }}">
                <input type="hidden" name="tasinmaz_elle" id="tasinmaz_elle" value="{{ old('tasinmaz_elle', 0) }}">
                <div class="ec-tsn-bilgi" id="ec-tsn-bilgi">
                    @if ($tasinmaz)
                        <div class="ec-tsn-durum is-bulundu">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                            <div>
                                <strong>Ada {{ $tasinmaz->ada }} / Parsel {{ $tasinmaz->parsel }}</strong>
                                <small>{{ $tasinmaz->il?->ad }} · {{ $tasinmaz->ilce?->ad }} · {{ $tasinmaz->mahalle?->ad }}</small>
                            </div>
                        </div>
                    @else
                        <div class="ec-tsn-durum is-bos">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 8l10 6 10-6z"/><path d="M2 12l10 6 10-6"/></svg>
                            <div>Haritada alan çizin; içinde bir taşınmaz varsa kendiliğinden bağlanır.</div>
                        </div>
                    @endif
                </div>
                <button type="button" class="ec-baglanti-kaldir" id="ec-tsn-kaldir" @if (! $tasinmaz) hidden @endif>Bağlantıyı kaldır</button>
                @error('tasinmaz_id')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="kullanici_id">Sorumlu Kullanıcı</label>
                <select name="kullanici_id" id="kullanici_id" class="form-select">
                    <option value="">— Atanmadı —</option>
                    @foreach ($kullanicilar as $k)
                        <option value="{{ $k->id }}" @selected($seciliKullanici == $k->id)>{{ $k->ad }} {{ $k->soyad }}</option>
                    @endforeach
                </select>
                <span class="hint">Takipten sorumlu personel. Aktif kullanıcılar listelenir.</span>
                @error('kullanici_id')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label for="aciklama">Açıklama</label>
                <textarea name="aciklama" id="aciklama" class="form-textarea" rows="3" maxlength="5000">{{ old('aciklama', $kayit?->aciklama) }}</textarea>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ $duzenleme ? route('panel.ecrimisil.detay', $kayit->id) : route('panel.ecrimisil.index') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            {{ $duzenleme ? 'Değişiklikleri Kaydet' : 'Kaydet ve Devam Et' }}
        </button>
    </div>
</form>

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
    <script src="{{ asset('js/ecrimisil-cizim.js') }}?v=1"></script>
@endpush
