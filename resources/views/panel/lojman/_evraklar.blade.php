{{--
    Evrak listesi + yükleme formu (lojman ve başvuru detayında ortak).
    Değişkenler: $evraklar, $basvuruId (?int), $lojmanId (?int)
--}}
@php
    use App\Models\LojmanEvrak;

    $u = auth()->user();
    $evrakAcik = $errors->hasAny(['kategori', 'evrak_no', 'evrak_tarihi', 'dosya']);
@endphp

@forelse ($evraklar as $e)
    <div class="lj-evrak">
        <span class="lj-evrak-ikon is-{{ $e->kategori }}">{{ mb_substr(LojmanEvrak::KATEGORILER[$e->kategori] ?? '?', 0, 1) }}</span>
        <div class="lj-evrak-metin">
            <strong>{{ $e->evrak_no ? 'No '.$e->evrak_no : 'Evrak #'.$e->id }}</strong>
            <small>
                {{ LojmanEvrak::KATEGORILER[$e->kategori] ?? $e->kategori }} · {{ $e->evrak_tarihi?->format('d.m.Y') ?? 'tarihsiz' }}
                @if (! $basvuruId && $e->basvuru) · <a class="lj-link" href="{{ route('panel.lojman.basvuru-detay', $e->basvuru_id) }}">{{ $e->basvuru->ad_soyad }} başvurusu</a>@endif
            </small>
            @if ($e->aciklama)<p class="lj-metin">{{ $e->aciklama }}</p>@endif
        </div>
        <div class="lj-oge-aksiyon">
            @if ($e->dosya_yolu)
                <a class="btn-cancel hs-mini-btn" href="{{ asset('storage/'.$e->dosya_yolu) }}" target="_blank" rel="noopener">Aç</a>
            @else
                <span class="lj-soluk" title="Yalnız kayıt; dosya eklenmemiş">dosyasız</span>
            @endif
            @if ($u->izinVarMi('lojman.sil') && (! $lojmanId || ! $e->basvuru_id || $basvuruId))
                <button type="button" class="btn-cancel hs-mini-btn lj-btn-sil" data-sil="{{ url('/panel/lojman/evrak/'.$e->id) }}" data-soru="Evrak ve dosyası silinsin mi?">Sil</button>
            @endif
        </div>
    </div>
@empty
    <div class="lj-bos-kucuk">Henüz evrak yok.</div>
@endforelse

@if ($u->izinVarMi('lojman.duzenle'))
    <details class="lj-ekle" @if ($evrakAcik) open @endif>
        <summary>+ Evrak Ekle</summary>
        <form method="POST" action="{{ route('panel.lojman.evrak.yukle') }}" enctype="multipart/form-data" class="lj-ekle-form">
            @csrf
            @if ($basvuruId)<input type="hidden" name="basvuru_id" value="{{ $basvuruId }}">@endif
            @if ($lojmanId && ! $basvuruId)<input type="hidden" name="lojman_id" value="{{ $lojmanId }}">@endif
            <div class="lj-form-grid">
                <div class="form-field">
                    <label>Kategori *</label>
                    <select name="kategori" class="form-select" required>
                        @foreach (LojmanEvrak::KATEGORILER as $k => $v)<option value="{{ $k }}" @selected(old('kategori', $basvuruId ? 'basvuru' : 'tahsis') === $k)>{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="form-field"><label>Evrak No</label><input type="text" name="evrak_no" class="form-input" maxlength="100" value="{{ old('evrak_no') }}"></div>
                <div class="form-field"><label>Evrak Tarihi</label><input type="date" name="evrak_tarihi" class="form-input" max="{{ now()->toDateString() }}" value="{{ old('evrak_tarihi', now()->toDateString()) }}"></div>
                <div class="form-field"><label>Dosya</label><input type="file" name="dosya" class="form-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"><span class="hint">PDF, Word ya da görsel · en fazla 20 MB</span></div>
            </div>
            <div class="form-field"><label>Açıklama</label><textarea name="aciklama" class="form-textarea" rows="2" maxlength="2000">{{ old('aciklama') }}</textarea></div>
            <div class="lj-ekle-alt"><button type="submit" class="btn-submit">Evrakı Kaydet</button></div>
        </form>
    </details>
@endif
