@extends('layouts.panel')

@section('title', 'Lojman — '.$lojman->ad)

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/lojman.css') }}?v=1">
@endpush

@php
    use App\Models\Lojman;
    use App\Models\LojmanBasvuru;
    use App\Models\LojmanEvrak;

    $u = auth()->user();
    $duzenleyebilir = $u->izinVarMi('lojman.duzenle');
    $silebilir = $u->izinVarMi('lojman.sil');
    $durum = $lojman->efektifDurum();
    $aktif = $lojman->aktifTahsis();
    $gecmis = $lojman->tahsisler->reject(fn ($t) => $aktif && $t->id === $aktif->id);
    $toplamGun = (int) $lojman->tahsisler->sum(fn ($t) => $t->gunSayisi() ?? 0);
    $tarih = fn ($d) => $d ? $d->format('d.m.Y') : '—';
    $tahsisAcik = $errors->hasAny(['ad_soyad', 'sicil_no', 'baslangic_tarihi', 'bitis_tarihi', 'basvuru_id', 'karar_tarihi']);
    $tahsisYapilabilir = ! in_array($lojman->durum, Lojman::TAHSIS_KAPALI, true) || $aktif;
@endphp

@section('content')
@if (session('basari'))<div class="flash-success">{{ session('basari') }}</div>@endif
@if ($errors->any())<div class="flash-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Lojman · #{{ $lojman->id }}</p>
        <h2>{{ $lojman->ad }}</h2>
        <small class="lj-hero-alt">
            <span class="lj-durum is-{{ $durum }}">{{ Lojman::DURUMLAR[$durum] }}</span>
            @if ($lojman->tip)<span class="lj-tip">{{ $lojman->tip }}</span>@endif
            <span>{{ $lojman->mahalle?->ad }} · Ada {{ $lojman->ada }} / Parsel {{ $lojman->parsel }}</span>
        </small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.lojman.index') }}" class="btn-cancel">← Liste</a>
        @if ($duzenleyebilir)
            <a href="{{ route('panel.lojman.duzenle', $lojman->id) }}" class="btn-cancel">Düzenle</a>
        @endif
        @if ($silebilir && $lojman->tahsisler->isEmpty())
            <form method="POST" action="{{ route('panel.lojman.sil', $lojman->id) }}" class="lj-satir-ici" onsubmit="return confirm('Lojman ve lojmana ait evraklar silinsin mi? Başvurular kalır.');">
                @csrf @method('DELETE')
                <button type="submit" class="btn-cancel lj-btn-sil">Sil</button>
            </form>
        @endif
    </div>
</section>

<section class="hs-hesap" aria-label="Özet">
    <div class="hs-hesap-kutu {{ $durum === 'dolu' ? 'is-vurgu' : '' }}">
        <span>Şu An</span>
        <strong class="lj-hesap-ad">{{ $aktif ? $aktif->ad_soyad : Lojman::DURUMLAR[$durum] }}</strong>
        <small>
            @if ($aktif)
                {{ $aktif->baslamadiMi() ? $tarih($aktif->baslangic_tarihi).'’de başlayacak' : $tarih($aktif->baslangic_tarihi).'’den beri · '.$aktif->gunSayisi().' gün' }}
            @else
                Aktif tahsis yok
            @endif
        </small>
    </div>
    <div class="hs-hesap-kutu">
        <span>Özellik</span>
        <strong>{{ $lojman->oda_sayisi !== null ? $lojman->oda_sayisi.'+1' : '—' }} <em>{{ $lojman->alan_m2 ? number_format((float) $lojman->alan_m2, 0, ',', '.').' m²' : '' }}</em></strong>
        <small>{{ $lojman->konumKisa() ?: 'Blok / daire girilmemiş' }}</small>
    </div>
    <div class="hs-hesap-kutu">
        <span>Tahsis Geçmişi</span>
        <strong>{{ $lojman->tahsisler->count() }}</strong>
        <small>Toplam {{ number_format($toplamGun, 0, ',', '.') }} gün kullanım</small>
    </div>
    <div class="hs-hesap-kutu">
        <span>Bekleyen Başvuru</span>
        <strong>{{ $tahsisAdaylari->count() }}</strong>
        <small>{{ $tahsisAdaylari->where('durum', 'onaylandi')->count() }} onaylı · genel başvurular dahil</small>
    </div>
    <div class="hs-hesap-kutu">
        <span>Evrak</span>
        <strong>{{ $lojman->evraklar->count() }}</strong>
        <small>{{ $lojman->evraklar->whereNotNull('basvuru_id')->count() }} tanesi başvurulardan</small>
    </div>
</section>

<div class="lj-detay-grid">
    <aside class="lj-yan">
        <section class="lj-kart">
            <h3 class="lj-kart-baslik">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V6a2 2 0 012-2h10a2 2 0 012 2v15"/><path d="M9 9h1M14 9h1M9 13h1M14 13h1M10 21v-4h4v4"/></svg>
                Lojman
            </h3>
            <dl class="lj-bilgi">
                <div><dt>Ad</dt><dd><strong>{{ $lojman->ad }}</strong></dd></div>
                <div><dt>Blok / Daire</dt><dd>{{ $lojman->blok ?? '—' }} / {{ $lojman->daire_no ?? '—' }}</dd></div>
                <div><dt>Kat</dt><dd>{{ $lojman->kat === null ? '—' : ($lojman->kat < 0 ? 'Bodrum '.abs($lojman->kat) : ($lojman->kat === 0 ? 'Zemin' : $lojman->kat)) }}</dd></div>
                <div><dt>Oda / Alan</dt><dd>{{ $lojman->oda_sayisi !== null ? $lojman->oda_sayisi.'+1' : '—' }} · {{ $lojman->alan_m2 ? number_format((float) $lojman->alan_m2, 2, ',', '.').' m²' : '—' }}</dd></div>
                <div><dt>Tahsis Türü</dt><dd>{{ $lojman->tip ?? '—' }}</dd></div>
                @if ($lojman->aciklama)<div><dt>Açıklama</dt><dd class="lj-metin">{{ $lojman->aciklama }}</dd></div>@endif
            </dl>
        </section>

        <section class="lj-kart">
            <h3 class="lj-kart-baslik">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-7-7-7-13a7 7 0 0114 0c0 6-7 13-7 13z"/><circle cx="12" cy="9" r="2.6"/></svg>
                Konum & Taşınmaz
            </h3>
            <dl class="lj-bilgi">
                <div><dt>İl / İlçe</dt><dd>{{ $lojman->il?->ad }} / {{ $lojman->ilce?->ad }}</dd></div>
                <div><dt>Mahalle</dt><dd>{{ $lojman->mahalle?->ad ?? '—' }}</dd></div>
                <div><dt>Ada / Parsel</dt><dd class="td-mono">{{ $lojman->ada }} / {{ $lojman->parsel }}</dd></div>
                <div><dt>Taşınmaz</dt><dd>
                    @if ($lojman->tasinmaz)
                        <a href="{{ $lojman->tasinmaz->rota('detay') }}" class="lj-link"><strong>#{{ $lojman->tasinmaz->id }} · {{ $lojman->tasinmaz->kayit_tipi?->etiket() }}</strong></a>
                    @else
                        <span class="lj-uyari-yazi">Taşınmaz kaydıyla eşleşmedi</span>
                    @endif
                </dd></div>
            </dl>
        </section>
    </aside>

    <div class="lj-ana">
        {{-- Mevcut tahsis --}}
        <section class="lj-kart" id="tahsis">
            <h3 class="lj-kart-baslik">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Tahsis
                <span class="lj-kart-sayi">{{ $lojman->tahsisler->count() }}</span>
            </h3>

            @if ($aktif)
                <article class="lj-oge is-aktif">
                    <div class="lj-oge-ust">
                        <div>
                            <strong>{{ $aktif->ad_soyad }}</strong>
                            <small>{{ $aktif->sicil_no ? 'Sicil '.$aktif->sicil_no.' · ' : '' }}{{ $tarih($aktif->baslangic_tarihi) }} – {{ $aktif->bitis_tarihi ? $tarih($aktif->bitis_tarihi) : 'süresiz' }}</small>
                        </div>
                        <div class="lj-oge-aksiyon">
                            <span class="lj-rozet is-aktif">{{ $aktif->baslamadiMi() ? 'Başlamadı' : 'Oturuyor · '.$aktif->gunSayisi().' gün' }}</span>
                            @if ($silebilir)
                                <button type="button" class="btn-cancel hs-mini-btn lj-btn-sil" data-sil="{{ url('/panel/lojman/tahsis/'.$aktif->id) }}" data-soru="Tahsis kaydı silinsin mi? Yanlış girilmiş kayıtlar içindir; oturan ayrıldıysa “Sonlandır” kullanın.">Sil</button>
                            @endif
                        </div>
                    </div>
                    <div class="lj-oge-grid">
                        <div><span>Karar No</span><b>{{ $aktif->karar_no ?? '—' }}</b></div>
                        <div><span>Karar Tarihi</span><b>{{ $tarih($aktif->karar_tarihi) }}</b></div>
                        <div><span>Başvuru</span><b>@if ($aktif->basvuru)<a class="lj-link" href="{{ route('panel.lojman.basvuru-detay', $aktif->basvuru_id) }}">#{{ $aktif->basvuru_id }}</a>@else Doğrudan @endif</b></div>
                    </div>
                    @if ($aktif->aciklama)<p class="lj-metin">{{ $aktif->aciklama }}</p>@endif
                    @if ($duzenleyebilir && ! $aktif->baslamadiMi())
                        <form method="POST" action="{{ route('panel.lojman.tahsis.sonlandir', $aktif->id) }}" class="lj-sonlandir"
                              onsubmit="return confirm('Tahsis seçilen tarihte sonlandırılsın mı? Lojman boşa düşer.');">
                            @csrf @method('PATCH')
                            <label>Çıkış tarihi
                                <input type="date" name="bitis_tarihi" class="form-input" value="{{ now()->toDateString() }}" min="{{ $aktif->baslangic_tarihi->toDateString() }}">
                            </label>
                            <button type="submit" class="btn-submit">Tahsisi Sonlandır</button>
                        </form>
                    @endif
                </article>
            @else
                <div class="lj-bos-kucuk">
                    @if ($tahsisYapilabilir)
                        Lojman boş. Aşağıdan yeni tahsis girebilirsiniz.
                    @else
                        Lojman “{{ Lojman::DURUMLAR[$lojman->durum] }}” durumunda; tahsis için önce durumunu Boş yapın.
                    @endif
                </div>
            @endif

            {{-- Bitiş tarihi belli bir tahsis varsa sonraki dönem için önceden tahsis girilebilir --}}
            @if ($duzenleyebilir && (! $aktif || $aktif->bitis_tarihi) && $tahsisYapilabilir)
                <details class="lj-ekle" @if ($tahsisAcik) open @endif>
                    <summary>+ {{ $aktif ? 'Sonraki Dönem İçin Tahsis' : 'Yeni Tahsis' }}</summary>
                    <form method="POST" action="{{ route('panel.lojman.tahsis.kaydet', $lojman->id) }}" class="lj-ekle-form">
                        @csrf
                        <div class="form-field">
                            <label>Başvurudan Seç</label>
                            <select name="basvuru_id" class="form-select" id="lj-tahsis-basvuru">
                                <option value="">— Başvurusuz (doğrudan tahsis) —</option>
                                @foreach ($tahsisAdaylari as $b)
                                    <option value="{{ $b->id }}" data-ad="{{ $b->ad_soyad }}" data-sicil="{{ $b->sicil_no }}" @selected(old('basvuru_id') == $b->id)>
                                        {{ $b->ad_soyad }} · {{ LojmanBasvuru::DURUMLAR[$b->durum] }} · {{ $tarih($b->basvuru_tarihi) }}{{ $b->lojman_id ? '' : ' · genel başvuru' }}{{ $b->tahsis_turu ? ' · '.$b->tahsis_turu : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="hint">Onaylı başvurular üstte, başvuru tarihine göre sıralı. Seçince ad ve sicil dolar.</span>
                        </div>
                        <div class="lj-form-grid">
                            <div class="form-field"><label>Ad Soyad *</label><input type="text" name="ad_soyad" id="lj-tahsis-ad" class="form-input" maxlength="200" value="{{ old('ad_soyad') }}"></div>
                            <div class="form-field"><label>Sicil No</label><input type="text" name="sicil_no" id="lj-tahsis-sicil" class="form-input" maxlength="50" value="{{ old('sicil_no') }}"></div>
                            <div class="form-field"><label>Başlangıç *</label><input type="date" name="baslangic_tarihi" class="form-input" required value="{{ old('baslangic_tarihi', now()->toDateString()) }}"></div>
                            <div class="form-field"><label>Bitiş</label><input type="date" name="bitis_tarihi" class="form-input" value="{{ old('bitis_tarihi') }}"><span class="hint">Süresizse boş bırakın.</span></div>
                            <div class="form-field"><label>Karar No</label><input type="text" name="karar_no" class="form-input" maxlength="100" value="{{ old('karar_no') }}"></div>
                            <div class="form-field"><label>Karar Tarihi</label><input type="date" name="karar_tarihi" class="form-input" max="{{ now()->toDateString() }}" value="{{ old('karar_tarihi') }}"></div>
                        </div>
                        <div class="form-field"><label>Açıklama</label><textarea name="aciklama" class="form-textarea" rows="2" maxlength="2000">{{ old('aciklama') }}</textarea></div>
                        <div class="lj-ekle-alt"><button type="submit" class="btn-submit">Tahsisi Kaydet</button></div>
                    </form>
                </details>
            @endif

            @if ($gecmis->isNotEmpty())
                <h4 class="lj-alt-baslik">Geçmiş</h4>
                <ol class="lj-zaman">
                    @foreach ($gecmis as $t)
                        <li class="{{ $t->aktifMi() ? 'is-ileri' : '' }}">
                            <div class="lj-zaman-ust">
                                <strong>{{ $t->ad_soyad }}</strong>
                                <time>{{ $tarih($t->baslangic_tarihi) }} – {{ $t->bitis_tarihi ? $tarih($t->bitis_tarihi) : 'süresiz' }}</time>
                            </div>
                            <small>
                                {{ $t->aktifMi() ? 'Planlanmış' : $t->gunSayisi().' gün' }}{{ $t->sicil_no ? ' · Sicil '.$t->sicil_no : '' }}{{ $t->karar_no ? ' · Karar '.$t->karar_no : '' }}
                                @if ($t->basvuru_id) · <a class="lj-link" href="{{ route('panel.lojman.basvuru-detay', $t->basvuru_id) }}">Başvuru #{{ $t->basvuru_id }}</a>@endif
                                @if ($silebilir) · <button type="button" class="lj-bag-btn" data-sil="{{ url('/panel/lojman/tahsis/'.$t->id) }}" data-soru="Geçmiş tahsis kaydı silinsin mi?">sil</button>@endif
                            </small>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        {{-- Başvurular --}}
        <section class="lj-kart" id="basvurular">
            <h3 class="lj-kart-baslik">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
                Bu Lojmana Başvurular
                <span class="lj-kart-sayi">{{ $lojman->basvurular->count() }}</span>
            </h3>
            @forelse ($lojman->basvurular as $b)
                <a class="lj-satir" href="{{ route('panel.lojman.basvuru-detay', $b->id) }}">
                    <div>
                        <strong>{{ $b->ad_soyad }}</strong>
                        <small>{{ $tarih($b->basvuru_tarihi) }}{{ $b->unvan ? ' · '.$b->unvan : '' }}{{ $b->birim ? ' · '.$b->birim : '' }}</small>
                    </div>
                    <span class="lj-bdurum is-{{ $b->durum }}">{{ LojmanBasvuru::DURUMLAR[$b->durum] }}</span>
                </a>
            @empty
                <div class="lj-bos-kucuk">Bu lojmana özel başvuru yok{{ $tahsisAdaylari->isNotEmpty() ? '; tahsis formunda genel başvurular da listelenir.' : '.' }}</div>
            @endforelse
            @if ($u->izinVarMi('lojman.olustur') && $lojman->durum !== 'kullanim-disi')
                <a href="{{ route('panel.lojman.basvuru.olustur', ['lojman_id' => $lojman->id]) }}" class="lj-ekle-link">+ Bu lojmana başvuru ekle</a>
            @endif
        </section>

        {{-- Evraklar --}}
        <section class="lj-kart" id="evraklar">
            <h3 class="lj-kart-baslik">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                Evraklar
                <span class="lj-kart-sayi">{{ $lojman->evraklar->count() }}</span>
            </h3>
            @include('panel.lojman._evraklar', ['evraklar' => $lojman->evraklar, 'basvuruId' => null, 'lojmanId' => $lojman->id])
        </section>
    </div>
</div>

<div class="hs-bildirim" id="hs-bildirim" role="status" aria-live="polite"></div>
@include('panel.lojman._ortak-js')

<script>
document.getElementById('lj-tahsis-basvuru')?.addEventListener('change', function () {
    const o = this.selectedOptions[0];
    if (!o || !o.value) return;
    document.getElementById('lj-tahsis-ad').value = o.dataset.ad || '';
    document.getElementById('lj-tahsis-sicil').value = o.dataset.sicil || '';
});
</script>
@endsection
