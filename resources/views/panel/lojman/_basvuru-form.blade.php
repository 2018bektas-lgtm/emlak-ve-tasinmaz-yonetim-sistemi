{{--
    Başvuru alanları — basvuru-olustur ve basvuru-detay ortak.
    Değişkenler: $basvuru (?LojmanBasvuru), $lojmanlar, $lojman (?Lojman, ön seçim), $kompakt (bool)
--}}
@php
    use App\Models\Lojman;
    use App\Models\LojmanBasvuru;

    $tahsisli = $basvuru?->tahsis() !== null;
    $izgara = ($kompakt ?? false) ? 'lj-form-grid' : 'lj-form-grid is-3';
    $seciliLojman = old('lojman_id', $basvuru?->lojman_id ?? $lojman?->id);
@endphp

<div class="form-row form-row-2">
    <div class="form-field">
        <label>Ad Soyad <span class="required">*</span></label>
        <input type="text" name="ad_soyad" class="form-input" value="{{ old('ad_soyad', $basvuru?->ad_soyad) }}" required maxlength="200">
    </div>
    <div class="form-field">
        <label>T.C. Kimlik No</label>
        <input type="text" name="tc_kimlik" class="form-input td-mono" value="{{ old('tc_kimlik', $basvuru?->tc_kimlik) }}" maxlength="11" inputmode="numeric" pattern="[0-9]{11}" placeholder="11 hane">
        @error('tc_kimlik')<span class="error">{{ $message }}</span>@enderror
    </div>
</div>
<div class="{{ $izgara }} lj-basvuru-izgara">
    <div class="form-field"><label>Sicil No</label><input type="text" name="sicil_no" class="form-input td-mono" value="{{ old('sicil_no', $basvuru?->sicil_no) }}" maxlength="50"></div>
    <div class="form-field"><label>Ünvan</label><input type="text" name="unvan" class="form-input" value="{{ old('unvan', $basvuru?->unvan) }}" maxlength="150"></div>
    <div class="form-field"><label>Birim</label><input type="text" name="birim" class="form-input" value="{{ old('birim', $basvuru?->birim) }}" maxlength="200"></div>
    <div class="form-field">
        <label>Lojman Tercihi</label>
        @if ($tahsisli)
            <input type="text" class="form-input" value="{{ $basvuru->lojman?->ad ?? '—' }}" disabled>
            <span class="hint">Tahsis yapıldığı için değiştirilemez.</span>
        @else
            <select name="lojman_id" class="form-select">
                <option value="">— Genel (herhangi bir lojman) —</option>
                @foreach ($lojmanlar as $l)
                    <option value="{{ $l->id }}" @selected($seciliLojman == $l->id)>{{ $l->ad }}{{ $l->tip ? ' · '.$l->tip : '' }}</option>
                @endforeach
            </select>
            @error('lojman_id')<span class="error">{{ $message }}</span>@enderror
        @endif
    </div>
    <div class="form-field">
        <label>Tahsis Türü</label>
        <select name="tahsis_turu" class="form-select">
            <option value="">— Seçin —</option>
            @foreach (Lojman::TIPLER as $t)
                <option value="{{ $t }}" @selected(old('tahsis_turu', $basvuru?->tahsis_turu) === $t)>{{ $t }}</option>
            @endforeach
            @if ($basvuru?->tahsis_turu && ! in_array($basvuru->tahsis_turu, Lojman::TIPLER, true))
                <option value="{{ $basvuru->tahsis_turu }}" selected>{{ $basvuru->tahsis_turu }}</option>
            @endif
        </select>
    </div>
    <div class="form-field">
        <label>Başvuru Tarihi <span class="required">*</span></label>
        <input type="date" name="basvuru_tarihi" class="form-input" value="{{ old('basvuru_tarihi', $basvuru?->basvuru_tarihi?->toDateString() ?? now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
        @error('basvuru_tarihi')<span class="error">{{ $message }}</span>@enderror
    </div>
</div>
<div class="form-field">
    <label>Değerlendirme</label>
    @if ($tahsisli)
        <div class="lj-bilgi-kutu">Bu başvurudan tahsis yapıldı; durum <strong>Tahsis Edildi</strong> olarak kalır. Tahsis silinirse <strong>Onaylandı</strong>ya döner.</div>
    @else
        <div class="lj-secim" role="radiogroup" aria-label="Başvuru durumu">
            @foreach (LojmanBasvuru::ELLE_DURUMLAR as $d)
                <label class="lj-secim-oge is-b-{{ $d }}">
                    <input type="radio" name="durum" value="{{ $d }}" @checked(old('durum', $basvuru?->durum ?? 'basvuruldu') === $d)>
                    <span>{{ LojmanBasvuru::DURUMLAR[$d] }}</span>
                </label>
            @endforeach
        </div>
        <span class="hint">“Tahsis Edildi” seçilmez; tahsis girildiğinde kendiliğinden atanır.</span>
    @endif
</div>
<div class="form-field">
    <label>Açıklama</label>
    <textarea name="aciklama" class="form-textarea" rows="3" maxlength="5000">{{ old('aciklama', $basvuru?->aciklama) }}</textarea>
</div>
