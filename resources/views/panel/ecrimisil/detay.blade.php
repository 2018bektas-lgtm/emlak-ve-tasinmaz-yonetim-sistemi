@extends('layouts.panel')

@section('title', 'İşgal Kaydı — '.$kayit->ad_soyad_unvan)

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/ecrimisil.css') }}?v=1">
@endpush

@php
    use App\Models\EcrimisilIsgalci;

    $u = auth()->user();
    $duzenleyebilir = $u->izinVarMi('ecrimisil.duzenle');
    $silebilir = $u->izinVarMi('ecrimisil.sil');
    $durum = $kayit->durum();
    $toplamGun = $kayit->toplamIsgalGunu();
    $raporToplam = (float) $kayit->raporlar->sum('fiyat');
    $sonRapor = $kayit->raporlar->first();
    $acikTutanak = $kayit->tutanaklar->first(fn ($t) => ! $t->isgal_bitis_tarihi || $t->isgal_bitis_tarihi->gte(now()->startOfDay()));
    $ilkBaslangic = $kayit->tutanaklar->pluck('isgal_baslangic_tarihi')->filter()->min();
    $tsn = $kayit->tasinmaz;
    $tarih = fn ($d) => $d ? $d->format('d.m.Y') : '—';
    $para = fn ($v) => number_format((float) $v, 2, ',', '.');
    $haritaVeri = [
        'alan' => $kayit->koordinat,
        'tasinmaz' => $tsn?->koordinat?->koordinat,
        'nokta' => $kayit->lat !== null ? [(float) $kayit->lat, (float) $kayit->lng] : ($tsn?->koordinat?->lat ? [(float) $tsn->koordinat->lat, (float) $tsn->koordinat->lng] : null),
    ];
    $haritaVar = $haritaVeri['alan'] || $haritaVeri['tasinmaz'] || $haritaVeri['nokta'];
    // Düzenleme pencerelerini dolduran veriler
    $tutanakVerisi = fn ($t) => [
        'id' => $t->id, 'seri_no' => $t->seri_no, 'aciklama' => $t->aciklama,
        'tutanak_tarihi' => $t->tutanak_tarihi?->toDateString(),
        'isgal_baslangic_tarihi' => $t->isgal_baslangic_tarihi?->toDateString(),
        'isgal_bitis_tarihi' => $t->isgal_bitis_tarihi?->toDateString(),
    ];
    $raporVerisi = fn ($r) => [
        'id' => $r->id, 'rapor_no' => $r->rapor_no, 'aciklama' => $r->aciklama,
        'rapor_tarihi' => $r->rapor_tarihi?->toDateString(),
        'fiyat' => $r->fiyat !== null ? $para($r->fiyat) : '',
        'dosya' => (bool) $r->dosya_yolu,
    ];
    $resimVerisi = $kayit->resimler->map(fn ($r) => ['url' => asset('storage/'.$r->dosya_yolu), 'alt' => $r->aciklama])->values();
@endphp

@section('content')
@if (session('basari'))
    <div class="flash-success">{{ session('basari') }}</div>
@endif
@if ($errors->any())
    <div class="flash-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Ecrimisil · Kayıt #{{ $kayit->id }}</p>
        <h2>{{ $kayit->ad_soyad_unvan }}</h2>
        <small class="ec-hero-alt">
            <span class="ec-durum is-{{ $durum }}">{{ EcrimisilIsgalci::DURUMLAR[$durum] }}</span>
            @if ($kayit->nitelik)<span class="ec-nitelik">{{ $kayit->nitelik }}</span>@endif
            @if ($tsn)<span>Ada {{ $tsn->ada }} / Parsel {{ $tsn->parsel }} · {{ $tsn->mahalle?->ad }}</span>@endif
        </small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.ecrimisil.index') }}" class="btn-cancel">← Liste</a>
        @if ($duzenleyebilir)
            <a href="{{ route('panel.ecrimisil.duzenle', $kayit->id) }}" class="btn-cancel">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg>
                Düzenle
            </a>
        @endif
        @if ($silebilir)
            <form method="POST" action="{{ route('panel.ecrimisil.sil', $kayit->id) }}" class="ec-satir-ici"
                  onsubmit="return confirm('Bu işgal kaydı; tüm tutanakları, raporları ve resimleriyle birlikte kalıcı olarak silinecek. Devam edilsin mi?');">
                @csrf @method('DELETE')
                <button type="submit" class="btn-cancel ec-btn-sil">Sil</button>
            </form>
        @endif
    </div>
</section>

<section class="hs-hesap" aria-label="Özet">
    <div class="hs-hesap-kutu {{ $durum === 'devam-eden' ? 'is-vurgu' : '' }}">
        <span>İşgal Durumu</span>
        <strong class="ec-hesap-durum">{{ EcrimisilIsgalci::DURUMLAR[$durum] }}</strong>
        <small>
            @if ($acikTutanak)
                {{ $tarih($acikTutanak->isgal_baslangic_tarihi) }}'den beri
            @elseif ($durum === 'sonlanmis')
                Son bitiş {{ $tarih($kayit->tutanaklar->pluck('isgal_bitis_tarihi')->filter()->max()) }}
            @else
                Tutanak girildiğinde belirlenir
            @endif
        </small>
    </div>
    <div class="hs-hesap-kutu">
        <span>Toplam İşgal Süresi</span>
        <strong>{{ number_format($toplamGun, 0, ',', '.') }} <em>gün</em></strong>
        <small>{{ $ilkBaslangic ? 'İlk başlangıç '.$tarih($ilkBaslangic) : 'Başlangıç tarihi yok' }}</small>
    </div>
    <div class="hs-hesap-kutu">
        <span>Tutanak</span>
        <strong>{{ $kayit->tutanaklar->count() }}</strong>
        <small>{{ $kayit->tutanaklar->last()?->tutanak_tarihi ? 'Son '.$tarih($kayit->tutanaklar->last()->tutanak_tarihi) : '—' }}</small>
    </div>
    <div class="hs-hesap-kutu">
        <span>Rapor Bedeli</span>
        <strong>{{ $para($raporToplam) }} <em>₺</em></strong>
        <small>{{ $kayit->raporlar->count() }} rapor{{ $sonRapor?->rapor_tarihi ? ' · son '.$tarih($sonRapor->rapor_tarihi) : '' }}</small>
    </div>
    <div class="hs-hesap-kutu">
        <span>Resim</span>
        <strong>{{ $kayit->resimler->count() }}</strong>
        <small>{{ $kayit->resimler->whereNotNull('tutanak_id')->count() }} tanesi tutanağa bağlı</small>
    </div>
</section>

<div class="ec-detay-grid">
    {{-- ==== SOL: kimlik, konum, sorumlu ==== --}}
    <aside class="ec-yan">
        <section class="ec-kart">
            <h3 class="ec-kart-baslik">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                İşgalci
            </h3>
            <dl class="ec-bilgi">
                <div><dt>Ad Soyad / Ünvan</dt><dd><strong>{{ $kayit->ad_soyad_unvan }}</strong></dd></div>
                <div><dt>{{ strlen((string) $kayit->tc_vergi_no) === 10 ? 'Vergi No' : 'TC Kimlik No' }}</dt><dd class="td-mono">{{ $kayit->tc_vergi_no ?? '—' }}</dd></div>
                <div><dt>İşgal Niteliği</dt><dd>{{ $kayit->nitelik ?? '—' }}</dd></div>
                <div><dt>Cadde / Sokak</dt><dd>{{ $kayit->cadde_sokak ?? '—' }}</dd></div>
                <div><dt>Adres</dt><dd>{{ $kayit->adres ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="ec-kart">
            <h3 class="ec-kart-baslik">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-7-7-7-13a7 7 0 0114 0c0 6-7 13-7 13z"/><circle cx="12" cy="9" r="2.6"/></svg>
                Konum
                @if ($kayit->koordinat)<span class="ec-kart-sayi">Alan çizili</span>@endif
            </h3>
            @if ($haritaVar)
                <div class="ec-mini-harita" id="ec-mini-harita" data-veri="{{ json_encode($haritaVeri) }}"></div>
            @else
                <div class="ec-bos-kucuk">Haritada alan çizilmemiş{{ $duzenleyebilir ? ' — Düzenle ekranından ekleyebilirsiniz.' : '.' }}</div>
            @endif
            @if ($tsn)
                <dl class="ec-bilgi">
                    <div><dt>Taşınmaz</dt><dd><a href="{{ $tsn->rota('detay') }}" class="ec-link"><strong>Ada {{ $tsn->ada }} / Parsel {{ $tsn->parsel }}</strong></a></dd></div>
                    <div><dt>İl / İlçe</dt><dd>{{ $tsn->il?->ad }} / {{ $tsn->ilce?->ad }}</dd></div>
                    <div><dt>Mahalle</dt><dd>{{ $tsn->mahalle?->ad ?? '—' }}</dd></div>
                </dl>
            @else
                <div class="ec-bos-kucuk">Taşınmaz bağlı değil.</div>
            @endif
        </section>

        <section class="ec-kart">
            <h3 class="ec-kart-baslik">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"/><path d="M4 21c0-4 4-7 8-7s8 3 8 7"/></svg>
                Sorumlu
            </h3>
            <dl class="ec-bilgi">
                <div><dt>Kullanıcı</dt><dd>
                    @if ($kayit->kullanici)
                        <strong>{{ $kayit->kullanici->ad }} {{ $kayit->kullanici->soyad }}</strong>
                        @if ($kayit->kullanici->mail)<br><small>{{ $kayit->kullanici->mail }}</small>@endif
                    @else
                        <span class="ec-soluk">Atanmadı</span>
                    @endif
                </dd></div>
                <div><dt>Kayıt</dt><dd>{{ $kayit->created_at?->format('d.m.Y H:i') }}</dd></div>
                @if ($kayit->aciklama)
                    <div><dt>Açıklama</dt><dd class="ec-metin">{{ $kayit->aciklama }}</dd></div>
                @endif
            </dl>
        </section>
    </aside>

    {{-- ==== SAĞ: tutanak, rapor, resim ==== --}}
    <div class="ec-ana">
        {{-- Tutanaklar --}}
        <section class="ec-kart" id="tutanaklar">
            <h3 class="ec-kart-baslik">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="12" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M9 11h6M9 15h4"/></svg>
                Tutanaklar
                <span class="ec-kart-sayi">{{ $kayit->tutanaklar->count() }}</span>
            </h3>

            @forelse ($kayit->tutanaklar as $t)
                @php
                    $gun = $t->isgalGunSayisi();
                    $acik = ! $t->isgal_bitis_tarihi || $t->isgal_bitis_tarihi->gte(now()->startOfDay());
                @endphp
                <article class="ec-oge {{ $acik ? 'is-acik' : '' }}">
                    <div class="ec-oge-ust">
                        <div>
                            <strong>{{ $t->seri_no ? 'Seri '.$t->seri_no : 'Tutanak #'.$t->id }}</strong>
                            <small>Düzenlenme {{ $tarih($t->tutanak_tarihi) }}</small>
                        </div>
                        <div class="ec-oge-aksiyon">
                            @if ($gun !== null)
                                <span class="ec-gun-rozet {{ $acik ? 'is-devam' : '' }}">{{ number_format($gun, 0, ',', '.') }} gün{{ $acik ? ' · sürüyor' : '' }}</span>
                            @endif
                            @if ($duzenleyebilir)
                                <button type="button" class="hs-mini-btn btn-cancel" data-duzenle="tutanak"
                                        data-kayit="{{ json_encode($tutanakVerisi($t)) }}">Düzenle</button>
                            @endif
                            @if ($silebilir)
                                <button type="button" class="hs-mini-btn btn-cancel ec-btn-sil" data-sil="{{ url('/panel/ecrimisil/tutanak/'.$t->id) }}" data-soru="Tutanak silinsin mi? Bağlı resimler kayıtta kalır.">Sil</button>
                            @endif
                        </div>
                    </div>
                    <div class="ec-oge-grid">
                        <div><span>İşgal Başlangıcı</span><b>{{ $tarih($t->isgal_baslangic_tarihi) }}</b></div>
                        <div><span>İşgal Bitişi</span><b>{{ $t->isgal_bitis_tarihi ? $tarih($t->isgal_bitis_tarihi) : 'Devam ediyor' }}</b></div>
                        <div><span>Bağlı Resim</span><b>{{ $t->resimler->count() }}</b></div>
                    </div>
                    @if ($t->aciklama)<p class="ec-metin">{{ $t->aciklama }}</p>@endif
                </article>
            @empty
                <div class="ec-bos-kucuk">Henüz tutanak yok. İşgal durumu ve süresi tutanaklardan hesaplanır.</div>
            @endforelse

            @if ($duzenleyebilir)
                <details class="ec-ekle" @if ($errors->hasAny(['seri_no', 'tutanak_tarihi', 'isgal_baslangic_tarihi', 'isgal_bitis_tarihi']) && ! old('_duzenlenen')) open @endif>
                    <summary>+ Yeni Tutanak</summary>
                    <form method="POST" action="{{ route('panel.ecrimisil.tutanak.kaydet', $kayit->id) }}" class="ec-ekle-form">
                        @csrf
                        <div class="ec-form-grid">
                            <div class="form-field"><label>Seri No</label><input type="text" name="seri_no" class="form-input" maxlength="100" value="{{ old('_duzenlenen') ? '' : old('seri_no') }}"></div>
                            <div class="form-field"><label>Tutanak Tarihi</label><input type="date" name="tutanak_tarihi" class="form-input" max="{{ now()->toDateString() }}" value="{{ old('_duzenlenen') ? '' : old('tutanak_tarihi', now()->toDateString()) }}"></div>
                            <div class="form-field"><label>İşgal Başlangıcı</label><input type="date" name="isgal_baslangic_tarihi" class="form-input" max="{{ now()->toDateString() }}" value="{{ old('_duzenlenen') ? '' : old('isgal_baslangic_tarihi') }}"></div>
                            <div class="form-field"><label>İşgal Bitişi</label><input type="date" name="isgal_bitis_tarihi" class="form-input" value="{{ old('_duzenlenen') ? '' : old('isgal_bitis_tarihi') }}"><span class="hint">İşgal sürüyorsa boş bırakın.</span></div>
                        </div>
                        <div class="form-field"><label>Açıklama</label><textarea name="aciklama" class="form-textarea" rows="2" maxlength="2000">{{ old('_duzenlenen') ? '' : old('aciklama') }}</textarea></div>
                        <div class="ec-ekle-alt"><button type="submit" class="btn-submit">Tutanağı Kaydet</button></div>
                    </form>
                </details>
            @endif
        </section>

        {{-- Raporlar --}}
        <section class="ec-kart" id="raporlar">
            <h3 class="ec-kart-baslik">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h5"/></svg>
                Raporlar
                <span class="ec-kart-sayi">{{ $kayit->raporlar->count() }}</span>
            </h3>

            @forelse ($kayit->raporlar as $r)
                <article class="ec-oge">
                    <div class="ec-oge-ust">
                        <div>
                            <strong>{{ $r->rapor_no ? 'Rapor '.$r->rapor_no : 'Rapor #'.$r->id }}</strong>
                            <small>{{ $tarih($r->rapor_tarihi) }}</small>
                        </div>
                        <div class="ec-oge-aksiyon">
                            @if ($r->fiyat !== null)<span class="ec-tutar">{{ $para($r->fiyat) }} ₺</span>@endif
                            @if ($r->dosya_yolu)
                                <a class="hs-mini-btn btn-cancel" href="{{ asset('storage/'.$r->dosya_yolu) }}" target="_blank" rel="noopener">Dosya</a>
                            @endif
                            @if ($duzenleyebilir)
                                <button type="button" class="hs-mini-btn btn-cancel" data-duzenle="rapor"
                                        data-kayit="{{ json_encode($raporVerisi($r)) }}">Düzenle</button>
                            @endif
                            @if ($silebilir)
                                <button type="button" class="hs-mini-btn btn-cancel ec-btn-sil" data-sil="{{ url('/panel/ecrimisil/rapor/'.$r->id) }}" data-soru="Rapor ve eki silinsin mi?">Sil</button>
                            @endif
                        </div>
                    </div>
                    @if ($r->aciklama)<p class="ec-metin">{{ $r->aciklama }}</p>@endif
                </article>
            @empty
                <div class="ec-bos-kucuk">Henüz rapor yok.</div>
            @endforelse

            @if ($duzenleyebilir)
                <details class="ec-ekle" @if ($errors->hasAny(['rapor_no', 'rapor_tarihi', 'fiyat', 'dosya']) && ! old('_duzenlenen')) open @endif>
                    <summary>+ Yeni Rapor</summary>
                    <form method="POST" action="{{ route('panel.ecrimisil.rapor.kaydet', $kayit->id) }}" enctype="multipart/form-data" class="ec-ekle-form">
                        @csrf
                        <div class="ec-form-grid">
                            <div class="form-field"><label>Rapor No</label><input type="text" name="rapor_no" class="form-input" maxlength="100"></div>
                            <div class="form-field"><label>Rapor Tarihi</label><input type="date" name="rapor_tarihi" class="form-input" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}"></div>
                            <div class="form-field"><label>Bedel (₺)</label><input type="text" name="fiyat" class="form-input td-mono" inputmode="decimal" placeholder="12.500,00"></div>
                            <div class="form-field"><label>Ek (PDF / Word)</label><input type="file" name="dosya" class="form-input" accept=".pdf,.doc,.docx"><span class="hint">En fazla 20 MB.</span></div>
                        </div>
                        <div class="form-field"><label>Açıklama</label><textarea name="aciklama" class="form-textarea" rows="2" maxlength="5000"></textarea></div>
                        <div class="ec-ekle-alt"><button type="submit" class="btn-submit">Raporu Kaydet</button></div>
                    </form>
                </details>
            @endif
        </section>

        {{-- Resimler --}}
        <section class="ec-kart" id="resimler">
            <h3 class="ec-kart-baslik">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                Resimler
                <span class="ec-kart-sayi">{{ $kayit->resimler->count() }}</span>
            </h3>

            @if ($kayit->resimler->isNotEmpty())
                <div class="ec-galeri">
                    @foreach ($kayit->resimler as $i => $r)
                        <figure class="ec-resim">
                            <button type="button" class="ec-resim-ac" data-lightbox="{{ $i }}" aria-label="Büyüt">
                                <img src="{{ asset('storage/'.$r->dosya_yolu) }}" alt="{{ $r->aciklama ?? 'İşgal resmi' }}" loading="lazy">
                            </button>
                            @if ($r->tutanak || $r->aciklama)
                                <figcaption>
                                    @if ($r->tutanak)<span class="ec-resim-etiket">{{ $r->tutanak->seri_no ? 'Seri '.$r->tutanak->seri_no : 'Tutanak #'.$r->tutanak->id }}</span>@endif
                                    {{ $r->aciklama }}
                                </figcaption>
                            @endif
                            @if ($silebilir)
                                <button type="button" class="ec-resim-sil" data-sil="{{ url('/panel/ecrimisil/resim/'.$r->id) }}" data-soru="Resim silinsin mi?" aria-label="Resmi sil">×</button>
                            @endif
                        </figure>
                    @endforeach
                </div>
            @else
                <div class="ec-bos-kucuk">Henüz resim yok.</div>
            @endif

            @if ($duzenleyebilir)
                <details class="ec-ekle" @if ($errors->hasAny(['resimler', 'resimler.*', 'tutanak_id'])) open @endif>
                    <summary>+ Resim Yükle</summary>
                    <form method="POST" action="{{ route('panel.ecrimisil.resim.yukle', $kayit->id) }}" enctype="multipart/form-data" class="ec-ekle-form">
                        @csrf
                        <label class="ec-dosya-birak" data-dosya-alan>
                            <input type="file" name="resimler[]" accept="image/jpeg,image/png,image/webp" multiple required>
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                            <span data-varsayilan="Resimleri sürükleyin ya da seçmek için tıklayın">Resimleri sürükleyin ya da seçmek için tıklayın</span>
                            <small>JPG, PNG, WEBP · her biri en fazla 8 MB · tek seferde 20 adet</small>
                        </label>
                        <div class="ec-form-grid">
                            <div class="form-field">
                                <label>Tutanağa Bağla</label>
                                <select name="tutanak_id" class="form-select">
                                    <option value="">— Bağlama —</option>
                                    @foreach ($kayit->tutanaklar as $t)
                                        <option value="{{ $t->id }}">{{ $t->seri_no ? 'Seri '.$t->seri_no : 'Tutanak #'.$t->id }} · {{ $tarih($t->tutanak_tarihi) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-field ec-genis"><label>Açıklama</label><input type="text" name="aciklama" class="form-input" maxlength="300"></div>
                        </div>
                        <div class="ec-ekle-alt"><button type="submit" class="btn-submit">Yükle</button></div>
                    </form>
                </details>
            @endif
        </section>
    </div>
</div>

{{-- ==== Düzenleme pencereleri ==== --}}
@if ($duzenleyebilir)
    <div class="hs-modal-backdrop" id="ec-modal-tutanak" hidden>
        <div class="hs-modal" role="dialog" aria-modal="true" aria-labelledby="ec-modal-tutanak-baslik">
            <div class="hs-modal-baslik">
                <h3 id="ec-modal-tutanak-baslik">Tutanağı Düzenle</h3>
                <button type="button" class="hs-modal-kapat" data-kapat>✕</button>
            </div>
            <form method="POST" action="">
                @csrf @method('PUT')
                <input type="hidden" name="_duzenlenen" value="1">
                <div class="hs-modal-govde">
                    <div class="ec-form-grid">
                        <div class="form-field"><label>Seri No</label><input type="text" name="seri_no" class="form-input" maxlength="100"></div>
                        <div class="form-field"><label>Tutanak Tarihi</label><input type="date" name="tutanak_tarihi" class="form-input" max="{{ now()->toDateString() }}"></div>
                        <div class="form-field"><label>İşgal Başlangıcı</label><input type="date" name="isgal_baslangic_tarihi" class="form-input" max="{{ now()->toDateString() }}"></div>
                        <div class="form-field"><label>İşgal Bitişi</label><input type="date" name="isgal_bitis_tarihi" class="form-input"><span class="hint">Sürüyorsa boş bırakın.</span></div>
                    </div>
                    <div class="form-field"><label>Açıklama</label><textarea name="aciklama" class="form-textarea" rows="3" maxlength="2000"></textarea></div>
                </div>
                <div class="hs-modal-alt">
                    <button type="button" class="btn-cancel" data-kapat>Vazgeç</button>
                    <button type="submit" class="btn-submit">Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <div class="hs-modal-backdrop" id="ec-modal-rapor" hidden>
        <div class="hs-modal" role="dialog" aria-modal="true" aria-labelledby="ec-modal-rapor-baslik">
            <div class="hs-modal-baslik">
                <h3 id="ec-modal-rapor-baslik">Raporu Düzenle</h3>
                <button type="button" class="hs-modal-kapat" data-kapat>✕</button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_duzenlenen" value="1">
                <div class="hs-modal-govde">
                    <div class="ec-form-grid">
                        <div class="form-field"><label>Rapor No</label><input type="text" name="rapor_no" class="form-input" maxlength="100"></div>
                        <div class="form-field"><label>Rapor Tarihi</label><input type="date" name="rapor_tarihi" class="form-input" max="{{ now()->toDateString() }}"></div>
                        <div class="form-field"><label>Bedel (₺)</label><input type="text" name="fiyat" class="form-input td-mono" inputmode="decimal"></div>
                        <div class="form-field"><label>Yeni Ek (isteğe bağlı)</label><input type="file" name="dosya" class="form-input" accept=".pdf,.doc,.docx"></div>
                    </div>
                    <label class="ec-onay" data-dosya-kaldir><input type="checkbox" name="dosya_kaldir" value="1"> Mevcut eki kaldır</label>
                    <div class="form-field"><label>Açıklama</label><textarea name="aciklama" class="form-textarea" rows="3" maxlength="5000"></textarea></div>
                </div>
                <div class="hs-modal-alt">
                    <button type="button" class="btn-cancel" data-kapat>Vazgeç</button>
                    <button type="submit" class="btn-submit">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
@endif

<div class="ec-lightbox" id="ec-lightbox" hidden>
    <button type="button" class="ec-lightbox-btn is-kapat" data-lb="kapat" aria-label="Kapat">✕</button>
    <button type="button" class="ec-lightbox-btn is-onceki" data-lb="onceki" aria-label="Önceki">‹</button>
    <figure>
        <img id="ec-lightbox-img" src="" alt="">
        <figcaption id="ec-lightbox-alt"></figcaption>
    </figure>
    <button type="button" class="ec-lightbox-btn is-sonraki" data-lb="sonraki" aria-label="Sonraki">›</button>
</div>

<div class="hs-bildirim" id="hs-bildirim" role="status" aria-live="polite"></div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script>
(function () {
    'use strict';
    const CSRF = @json(csrf_token());
    const URL_TUTANAK = @json(url('/panel/ecrimisil/tutanak'));
    const URL_RAPOR = @json(url('/panel/ecrimisil/rapor'));
    const RESIMLER = @json($resimVerisi);

    function bildir(mesaj) {
        const kutu = document.getElementById('hs-bildirim');
        kutu.textContent = mesaj;
        kutu.classList.add('is-acik');
        clearTimeout(kutu._t);
        kutu._t = setTimeout(() => kutu.classList.remove('is-acik'), 4200);
    }

    // ---- Silme (tutanak / rapor / resim) ----
    document.addEventListener('click', async e => {
        const btn = e.target.closest('[data-sil]');
        if (!btn) return;
        if (!confirm(btn.dataset.soru || 'Silinsin mi?')) return;
        btn.disabled = true;
        try {
            const r = await fetch(btn.dataset.sil, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } });
            if (!r.ok) throw new Error((await r.json().catch(() => ({}))).message || 'Silinemedi (' + r.status + ').');
            location.reload();
        } catch (err) {
            btn.disabled = false;
            bildir(err.message);
        }
    });

    // ---- Düzenleme pencereleri ----
    function modalAc(tur, veri) {
        const m = document.getElementById('ec-modal-' + tur);
        if (!m) return;
        const form = m.querySelector('form');
        form.action = (tur === 'tutanak' ? URL_TUTANAK : URL_RAPOR) + '/' + veri.id;
        Object.entries(veri).forEach(([k, v]) => {
            const alan = form.elements[k];
            if (alan && alan.type !== 'file' && alan.type !== 'checkbox') alan.value = v ?? '';
        });
        const kaldir = form.querySelector('[data-dosya-kaldir]');
        if (kaldir) { kaldir.hidden = !veri.dosya; kaldir.querySelector('input').checked = false; }
        m.hidden = false;
        form.querySelector('input:not([type=hidden])')?.focus();
    }
    function modalKapat(m) { m.hidden = true; }
    document.addEventListener('click', e => {
        const ac = e.target.closest('[data-duzenle]');
        if (ac) { modalAc(ac.dataset.duzenle, JSON.parse(ac.dataset.kayit)); return; }
        const kapat = e.target.closest('[data-kapat]');
        if (kapat) { modalKapat(kapat.closest('.hs-modal-backdrop')); return; }
        if (e.target.classList?.contains('hs-modal-backdrop')) modalKapat(e.target);
    });

    // ---- Resim büyütme ----
    const lb = document.getElementById('ec-lightbox');
    let lbSira = 0;
    function lbGoster(i) {
        if (!RESIMLER.length) return;
        lbSira = (i + RESIMLER.length) % RESIMLER.length;
        document.getElementById('ec-lightbox-img').src = RESIMLER[lbSira].url;
        document.getElementById('ec-lightbox-alt').textContent = (RESIMLER[lbSira].alt || '') + ' · ' + (lbSira + 1) + '/' + RESIMLER.length;
        lb.hidden = false;
    }
    document.addEventListener('click', e => {
        const ac = e.target.closest('[data-lightbox]');
        if (ac) { lbGoster(+ac.dataset.lightbox); return; }
        const b = e.target.closest('[data-lb]');
        if (b) { b.dataset.lb === 'kapat' ? (lb.hidden = true) : lbGoster(lbSira + (b.dataset.lb === 'onceki' ? -1 : 1)); return; }
        if (e.target === lb) lb.hidden = true;
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            lb.hidden = true;
            document.querySelectorAll('.hs-modal-backdrop:not([hidden])').forEach(modalKapat);
        }
        if (!lb.hidden && e.key === 'ArrowLeft') lbGoster(lbSira - 1);
        if (!lb.hidden && e.key === 'ArrowRight') lbGoster(lbSira + 1);
    });

    // ---- Dosya bırakma alanı: seçilen dosyaları göster ----
    document.querySelectorAll('[data-dosya-alan]').forEach(alan => {
        const input = alan.querySelector('input[type=file]');
        const yazi = alan.querySelector('span');
        input.addEventListener('change', () => {
            const n = input.files.length;
            alan.classList.toggle('is-dolu', n > 0);
            yazi.textContent = n === 0 ? yazi.dataset.varsayilan : (n === 1 ? input.files[0].name : n + ' resim seçildi');
        });
        ['dragenter', 'dragover'].forEach(t => alan.addEventListener(t, () => alan.classList.add('is-uzerinde')));
        ['dragleave', 'drop'].forEach(t => alan.addEventListener(t, () => alan.classList.remove('is-uzerinde')));
    });

    // ---- Küçük konum haritası ----
    const mh = document.getElementById('ec-mini-harita');
    if (mh && typeof L !== 'undefined') {
        const v = JSON.parse(mh.dataset.veri || '{}');
        const map = L.map(mh, { zoomControl: false, attributionControl: false, scrollWheelZoom: false, dragging: !L.Browser.mobile })
            .setView([39.93, 32.86], 12);
        L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { subdomains: ['mt0', 'mt1', 'mt2', 'mt3'], maxZoom: 20 }).addTo(map);
        const grup = L.featureGroup().addTo(map);
        const ciz = (g, stil) => {
            if (!g) return;
            if (typeof g === 'string') { try { g = JSON.parse(g); } catch (e) { return; } }
            if (g.type) L.geoJSON({ type: 'Feature', geometry: g }, { style: stil }).addTo(grup);
            else if (Array.isArray(g) && Array.isArray(g[0])) L.polygon(g.map(p => [p[1], p[0]]), stil).addTo(grup);
        };
        ciz(v.tasinmaz, { color: '#facc15', weight: 2, dashArray: '6 5', fillOpacity: .06 });
        ciz(v.alan, { color: '#ff6a3d', weight: 2.5, fillColor: '#ff6a3d', fillOpacity: .3 });
        if (grup.getLayers().length) map.fitBounds(grup.getBounds(), { padding: [18, 18], maxZoom: 19 });
        else if (v.nokta) { L.circleMarker(v.nokta, { radius: 8, color: '#ff6a3d', fillOpacity: .7 }).addTo(map); map.setView(v.nokta, 17); }
    }
})();
</script>
@endpush
