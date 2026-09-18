@extends('layouts.panel')

@section('title', 'Hisse Satışı — ' . $tasinmaz->ada . '/' . $tasinmaz->parsel)

@section('content')
@if (session('basari'))
    <div class="flash-success">{{ session('basari') }}</div>
@endif

{{-- ============ HEADER: taşınmaz özet + süreç aşaması ============ --}}
<section class="hs-basbaslik">
    <div class="hs-basbaslik-sol">
        <div class="hs-basbaslik-ust">
            <span class="hs-basbaslik-etiket">Hisse Satışı</span>
            <span class="tl-pill {{ $basvurular->first()->durum->pill() }}">{{ $basvurular->first()->durum->etiket() }}</span>
        </div>
        <h2>{{ optional($tasinmaz->ilce)->ad ?? '—' }} · {{ optional($tasinmaz->mahalle)->ad ?? '—' }}</h2>
        <div class="hs-basbaslik-alt">
            <span>Ada <strong>{{ $tasinmaz->ada }}</strong></span>
            <span>Parsel <strong>{{ $tasinmaz->parsel }}</strong></span>
            <span>Alan <strong>{{ $tasinmaz->alan !== null ? number_format((float) $tasinmaz->alan, 2, ',', '.').' m²' : '—' }}</strong></span>
            <span>Kurum Hissesi <strong>{{ number_format($aktifHisseToplami, 2, ',', '.') }} m²</strong></span>
        </div>
    </div>
    <div class="hs-basbaslik-sag">
        <a href="{{ route('panel.hisse-satisi.liste') }}" class="btn-cancel">← Liste</a>
        <a href="{{ route('panel.hisse-satisi.olustur', $tasinmaz->id) }}" class="btn-submit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
            Yeni Başvuru
        </a>
    </div>
</section>

{{-- ============ Süreç aşaması güncelleme ============ --}}
<form method="POST" action="{{ route('panel.hisse-satisi.durum', $grupNo) }}" class="hs-durum-bar">
    @csrf
    @method('PUT')
    <label>Süreç Aşaması:</label>
    <select name="durum" class="form-select" id="hs-grup-durum">
        @foreach ($durumSecenekleri as $d)
            <option value="{{ $d->value }}" @selected($basvurular->first()->durum === $d)>{{ $d->etiket() }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn-submit" style="padding:8px 16px;">Güncelle</button>
</form>

{{-- ============ SEKMELER (referans birebir sıra) ============ --}}
<div class="hs-tab" role="tablist">
    <button type="button" class="hs-tab-oge is-aktif" data-hs-tab="basvurular">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        Hisse Başvuruları <span class="hs-tab-sayac">{{ $basvurular->count() }}</span>
    </button>
    <button type="button" class="hs-tab-oge" data-hs-tab="malikler">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Hissedar (Malik) Listesi <span class="hs-tab-sayac">{{ $tasinmaz->malikler->count() }}</span>
    </button>
    <button type="button" class="hs-tab-oge" data-hs-tab="gorus">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Taşınmaz Görüş <span class="hs-tab-sayac">{{ $goruslar->count() }}</span>
    </button>
    <button type="button" class="hs-tab-oge" data-hs-tab="imar">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21V11h6v10M9 15h.01M15 15h.01"/></svg>
        İmar Durum <span class="hs-tab-sayac">{{ $imarEvraklari->count() }}</span>
    </button>
    <button type="button" class="hs-tab-oge" data-hs-tab="tebligatlar">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 8h18"/></svg>
        Tebligat Gönderilen Vatandaşlar <span class="hs-tab-sayac">{{ $tebligatlar->count() }}</span>
    </button>
    <button type="button" class="hs-tab-oge" data-hs-tab="encumen">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></svg>
        Encümen Bilgileri <span class="hs-tab-sayac">{{ $encumenler->count() }}</span>
    </button>
    <button type="button" class="hs-tab-oge" data-hs-tab="satis">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 1 0 0 7h5a3.5 3.5 0 1 1 0 7H6"/></svg>
        Satış Tebligatı Gönderilenler <span class="hs-tab-sayac">{{ $satisTebligatlari->count() }}</span>
    </button>
    <button type="button" class="hs-tab-oge" data-hs-tab="tapu">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V10"/><path d="M2 10l10-6 10 6"/><path d="M9 22V12h6v10"/></svg>
        Tapu Tescili <span class="hs-tab-sayac">{{ $satisTapulari->count() }}</span>
    </button>
</div>

{{-- ============ 1) Hisse Başvuruları ============ --}}
<div class="hs-panel is-aktif" data-hs-panel="basvurular">
    <div class="tl-tablo-wrap">
        <table class="tl-tablo">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Ad Soyad</th>
                    <th>TC Kimlik</th>
                    <th>GSM</th>
                    <th>Başvuru Tarihi</th>
                    <th>Tapu Hisse (m²)</th>
                    <th>Talep (m²)</th>
                    <th>Evrak</th>
                    <th>Ön Tebligat</th>
                    <th>Satış Tebligatı</th>
                    <th>Ödendi</th>
                    <th>Tapu Tescili</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                @php
                    // Bu grubun ilgili tebligat/satış/tapu kayıtlarını TC üzerinden başvurulara eşle
                    $tebligatTcMap = $tebligatlar->keyBy('tc_kimlik');
                    $satisBasvuruMap = $satisTebligatlari->keyBy('basvuru_id');
                    $tapuBasvuruMap = $satisTapulari->keyBy('basvuru_id');
                @endphp
                @foreach ($basvurular as $b)
                    @php
                        $t = $tebligatTcMap->get($b->tc_kimlik);
                        $st = $satisBasvuruMap->get($b->id);
                        $tp = $tapuBasvuruMap->get($b->id);
                    @endphp
                    <tr>
                        <td class="td-mono">{{ $loop->iteration }}</td>
                        <td><strong>{{ $b->ad_soyad }}</strong></td>
                        <td class="td-mono">{{ $b->tc_kimlik }}</td>
                        <td class="td-mono">{{ $b->gsm_no ?? '—' }}</td>
                        <td>{{ optional($b->basvuru_tarihi)->format('d.m.Y') }}</td>
                        <td class="td-mono">{{ $b->tapu_hisse !== null ? number_format((float) $b->tapu_hisse, 2, ',', '.') : '—' }}</td>
                        <td class="td-mono">{{ $b->talep_edilen_hisse !== null ? number_format((float) $b->talep_edilen_hisse, 2, ',', '.') : '—' }}</td>
                        <td>
                            @if ($b->evrakUrl())
                                <a href="{{ $b->evrakUrl() }}" target="_blank" class="td-link">PDF</a>
                            @else — @endif
                        </td>
                        {{-- Ön Tebligat durumu --}}
                        <td>
                            @if (! $t)
                                <span class="tl-pill tl-pill-mute">Yok</span>
                            @elseif ($t->tebligat_ulasmadi)
                                <span class="tl-pill tl-pill-danger">Ulaşmadı</span>
                            @elseif ($t->ulastigi_tarihi)
                                <span class="tl-pill tl-pill-ok">Ulaştı · {{ $t->ulastigi_tarihi->format('d.m.Y') }}</span>
                            @else
                                <span class="tl-pill tl-pill-warn">Gönderildi</span>
                            @endif
                        </td>
                        {{-- Satış Tebligatı durumu --}}
                        <td>
                            @if (! $st)
                                <span class="tl-pill tl-pill-mute">Yok</span>
                            @elseif ($st->tebligat_ulasmadi)
                                <span class="tl-pill tl-pill-danger">Ulaşmadı</span>
                            @elseif ($st->ulastigi_tarihi)
                                <span class="tl-pill tl-pill-ok">Ulaştı · {{ $st->ulastigi_tarihi->format('d.m.Y') }}</span>
                            @else
                                <span class="tl-pill tl-pill-warn">Gönderildi</span>
                            @endif
                        </td>
                        {{-- Ödendi durumu --}}
                        <td>
                            @if (! $st)
                                —
                            @elseif ($st->odedi)
                                <span class="tl-pill tl-pill-ok">Ödendi</span>
                            @else
                                <span class="tl-pill tl-pill-warn">Bekliyor</span>
                            @endif
                        </td>
                        {{-- Tapu Tescili --}}
                        <td>
                            @if ($tp)
                                <span class="tl-pill tl-pill-ok">Tamamlandı · {{ optional($tp->tescil_tarihi)->format('d.m.Y') }}</span>
                            @else
                                <span class="tl-pill tl-pill-mute">—</span>
                            @endif
                        </td>
                        <td>
                            <button type="button" class="btn-cancel hs-duzenle-btn"
                                onclick="hsDuzenleAc('basvuru', {{ Js::from([
                                    "id" => $b->id,
                                    "ad_soyad" => $b->ad_soyad,
                                    "tc_kimlik" => $b->tc_kimlik,
                                    "gsm_no" => $b->gsm_no,
                                    "basvuru_tarihi" => optional($b->basvuru_tarihi)->toDateString(),
                                    "tapu_hisse" => $b->tapu_hisse,
                                    "talep_edilen_hisse" => $b->talep_edilen_hisse,
                                    "aciklama" => $b->aciklama,
                                ]) }})">Düzenle</button>
                            <button type="button" class="btn-cancel" style="padding:4px 10px;font-size:0.75rem;" onclick="hsBasvuruSil({{ $b->id }})">Sil</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- ============ 2) Hissedar (Malik) Listesi ============ --}}
<div class="hs-panel" data-hs-panel="malikler">
    {{-- Toplu Excel Yükleme + Şablon --}}
    <div class="hs-toplu-yukle">
        <div class="hs-toplu-yukle-bilgi">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 10h8M8 14h8M8 18h5"/></svg>
            <div>
                <strong>Toplu Malik Ekleme</strong>
                <p>Excel dosyasından birden çok hissedarı tek seferde ekleyin. Aynı TC ile zaten varsa güncellenir.</p>
            </div>
        </div>
        <div class="hs-toplu-yukle-eylem">
            <a href="{{ route('panel.hisse-satisi.malik.sablon') }}" class="btn-cancel hs-toplu-sablon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Şablon İndir
            </a>
            <form method="POST" action="{{ route('panel.hisse-satisi.malik.toplu', $tasinmaz->id) }}" enctype="multipart/form-data" class="hs-toplu-form">
                @csrf
                <label class="hs-toplu-dosya">
                    <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required onchange="this.form.querySelector('button').disabled = !this.files.length; this.nextElementSibling.textContent = this.files.length ? this.files[0].name : 'Excel dosyası seç';">
                    <span>Excel dosyası seç</span>
                </label>
                <button type="submit" class="btn-submit" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Yükle
                </button>
            </form>
        </div>
    </div>

    <div class="hs-tekil-baslik">Tek Malik Ekle</div>
    <form method="POST" action="{{ route('panel.hisse-satisi.malik.kaydet', $tasinmaz->id) }}" class="hs-mini-form">
        @csrf
        <input type="text" name="ad_soyad" placeholder="Ad Soyad" class="form-input" required maxlength="150">
        <input type="text" name="tc_kimlik" placeholder="TC" class="form-input" minlength="11" maxlength="11" inputmode="numeric">
        <input type="text" name="tapu_hisse" placeholder="Tapu Hisse (m²)" class="form-input" inputmode="decimal">
        <input type="text" name="gsm_no" placeholder="GSM" class="form-input" maxlength="20">
        <input type="text" name="adres" placeholder="Adres" class="form-input" maxlength="500">
        <button type="submit" class="btn-submit">+ Malik</button>
    </form>

    @if ($tasinmaz->malikler->isEmpty())
        <p class="td-bos">Bu taşınmaza tanımlı hissedar yok. Ön tebligat çıkarabilmek için önce hissedarları ekleyin.</p>
    @else
        <div class="tl-tablo-wrap">
            <table class="tl-tablo">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Ad Soyad</th>
                        <th>TC</th>
                        <th>Tapu Hisse (m²)</th>
                        <th>GSM</th>
                        <th>Adres</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tasinmaz->malikler as $m)
                        <tr>
                            <td class="td-mono">{{ $loop->iteration }}</td>
                            <td>{{ $m->ad_soyad }}</td>
                            <td class="td-mono">{{ $m->tc_kimlik ?? '—' }}</td>
                            <td class="td-mono">{{ $m->tapu_hisse !== null ? number_format((float) $m->tapu_hisse, 2, ',', '.') : '—' }}</td>
                            <td class="td-mono">{{ $m->gsm_no ?? '—' }}</td>
                            <td>{{ $m->adres ?? '—' }}</td>
                            <td>
                                <button type="button" class="btn-cancel hs-duzenle-btn"
                                    onclick="hsDuzenleAc('malik', {{ Js::from([
                                        "id" => $m->id,
                                        "ad_soyad" => $m->ad_soyad,
                                        "tc_kimlik" => $m->tc_kimlik,
                                        "tapu_hisse" => $m->tapu_hisse,
                                        "gsm_no" => $m->gsm_no,
                                        "adres" => $m->adres,
                                    ]) }})">Düzenle</button>
                                <button type="button" class="btn-cancel" style="padding:4px 10px;font-size:0.75rem;" onclick="hsMalikSil({{ $m->id }})">Sil</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ============ Taşınmaz Görüş ============ --}}
<div class="hs-panel" data-hs-panel="gorus">
    <form method="POST" action="{{ route('panel.hisse-satisi.gorus.kaydet', $grupNo) }}" enctype="multipart/form-data" class="hs-secim-form">
        @csrf
        <div class="hs-secim-baslik">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Yeni Görüş Kaydı
        </div>
        <div class="hs-form-grid">
            <div class="hs-secim-alan">
                <label>Görüş İstenen Şube <span style="color:#c93030">*</span></label>
                <input type="text" name="gorus_sube" class="form-input" required maxlength="200" placeholder="Örn. Emlak İstimlak Şubesi">
            </div>
            <div class="hs-secim-alan">
                <label>Giden Evrak Tarihi</label>
                <input type="date" name="giden_tarih" class="form-input">
            </div>
            <div class="hs-secim-alan">
                <label>Giden Evrak Sayısı</label>
                <input type="text" name="giden_yazi" class="form-input" maxlength="100">
            </div>
            <div class="hs-secim-alan">
                <label>Giden Evrak (PDF)</label>
                <input type="file" name="giden_evrak" accept="application/pdf" class="form-input">
            </div>
            <div class="hs-secim-alan">
                <label>Gelen Evrak Tarihi</label>
                <input type="date" name="gelen_tarih" class="form-input">
            </div>
            <div class="hs-secim-alan">
                <label>Gelen Evrak Sayısı</label>
                <input type="text" name="gelen_yazi" class="form-input" maxlength="100">
            </div>
            <div class="hs-secim-alan">
                <label>Gelen Evrak (PDF)</label>
                <input type="file" name="gelen_evrak" accept="application/pdf" class="form-input">
            </div>
            <div class="hs-secim-alan">
                <label>Engel Durumu</label>
                <select name="engel_var_yok" class="form-select">
                    <option value="">— Seçilmedi —</option>
                    <option value="Yok">Engel Yok</option>
                    <option value="Var">Engel Var</option>
                </select>
            </div>
        </div>
        <div class="hs-secim-alt">
            <button type="submit" class="btn-submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Görüş Kaydet
            </button>
        </div>
    </form>

    @if ($goruslar->isEmpty())
        <p class="td-bos">Henüz görüş kaydı yok.</p>
    @else
        <div class="tl-tablo-wrap">
            <table class="tl-tablo">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Şube</th>
                        <th>Giden Tarih</th>
                        <th>Giden Sayı</th>
                        <th>Giden Evrak</th>
                        <th>Gelen Tarih</th>
                        <th>Gelen Sayı</th>
                        <th>Gelen Evrak</th>
                        <th>Engel</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($goruslar as $g)
                        <tr>
                            <td class="td-mono">{{ $loop->iteration }}</td>
                            <td><strong>{{ $g->gorus_sube }}</strong></td>
                            <td>{{ optional($g->giden_tarih)->format('d.m.Y') ?: '—' }}</td>
                            <td class="td-mono">{{ $g->giden_yazi ?? '—' }}</td>
                            <td>@if ($g->giden_evrak)<a class="td-link" href="{{ asset('storage/'.$g->giden_evrak) }}" target="_blank">PDF</a>@else — @endif</td>
                            <td>{{ optional($g->gelen_tarih)->format('d.m.Y') ?: '—' }}</td>
                            <td class="td-mono">{{ $g->gelen_yazi ?? '—' }}</td>
                            <td>@if ($g->gelen_evrak)<a class="td-link" href="{{ asset('storage/'.$g->gelen_evrak) }}" target="_blank">PDF</a>@else — @endif</td>
                            <td>
                                @if ($g->engel_var_yok === 'Var')
                                    <span class="tl-pill tl-pill-danger">Engel Var</span>
                                @elseif ($g->engel_var_yok === 'Yok')
                                    <span class="tl-pill tl-pill-ok">Engel Yok</span>
                                @else — @endif
                            </td>
                            <td>
                                <button type="button" class="btn-cancel hs-duzenle-btn"
                                    onclick="hsDuzenleAc('gorus', {{ Js::from([
                                        "id" => $g->id,
                                        "gorus_sube" => $g->gorus_sube,
                                        "giden_tarih" => optional($g->giden_tarih)->toDateString(),
                                        "giden_yazi" => $g->giden_yazi,
                                        "gelen_tarih" => optional($g->gelen_tarih)->toDateString(),
                                        "gelen_yazi" => $g->gelen_yazi,
                                        "engel_var_yok" => $g->engel_var_yok,
                                    ]) }})">Düzenle</button>
                                <button type="button" class="btn-cancel" style="padding:4px 10px;font-size:0.75rem;" onclick="hsGorusSil({{ $g->id }})">Sil</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ============ İmar Durum ============ --}}
<div class="hs-panel" data-hs-panel="imar">
    <form method="POST" action="{{ route('panel.hisse-satisi.imar.kaydet', $grupNo) }}" enctype="multipart/form-data" class="hs-secim-form">
        @csrf
        <div class="hs-secim-baslik">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14"/></svg>
            Yeni İmar Kaydı
        </div>
        <div class="hs-form-grid">
            <div class="hs-secim-alan">
                <label>Giden Evrak Sayısı</label>
                <input type="text" name="imar_giden_yazi" class="form-input" maxlength="100">
            </div>
            <div class="hs-secim-alan">
                <label>Giden Evrak Tarihi</label>
                <input type="date" name="imar_giden_tarih" class="form-input">
            </div>
            <div class="hs-secim-alan">
                <label>Giden Evrak (PDF)</label>
                <input type="file" name="imar_giden_evrak" accept="application/pdf" class="form-input">
            </div>
            <div class="hs-secim-alan">
                <label>Gelen Evrak Sayısı</label>
                <input type="text" name="imar_gelen_yazi" class="form-input" maxlength="100">
            </div>
            <div class="hs-secim-alan">
                <label>Gelen Evrak Tarihi</label>
                <input type="date" name="imar_gelen_tarih" class="form-input">
            </div>
            <div class="hs-secim-alan">
                <label>Gelen Evrak (PDF)</label>
                <input type="file" name="imar_gelen_evrak" accept="application/pdf" class="form-input">
            </div>
            <div class="hs-secim-alan">
                <label>İmar Durumu</label>
                <input type="text" name="imar_durum" class="form-input" maxlength="150" placeholder="Konut / Ticaret / Yeşil Alan…">
            </div>
            <div class="hs-secim-alan">
                <label>Emsal</label>
                <input type="text" name="emsal" class="form-input" maxlength="30" placeholder="Örn. 1.50">
            </div>
            <div class="hs-secim-alan">
                <label>Yençok</label>
                <input type="text" name="yencok" class="form-input" maxlength="30" placeholder="Örn. Serbest / 5 kat">
            </div>
            <div class="hs-secim-alan" style="grid-column:1 / -1;">
                <label>Plan Notları</label>
                <textarea name="plan_notlari" class="form-input" rows="3" maxlength="5000"></textarea>
            </div>
        </div>
        <div class="hs-secim-alt">
            <button type="submit" class="btn-submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                İmar Kaydet
            </button>
        </div>
    </form>

    @if ($imarEvraklari->isEmpty())
        <p class="td-bos">Henüz imar kaydı yok.</p>
    @else
        <div class="tl-tablo-wrap">
            <table class="tl-tablo">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Giden Tarih</th>
                        <th>Giden Sayı</th>
                        <th>Giden Evrak</th>
                        <th>Gelen Tarih</th>
                        <th>Gelen Sayı</th>
                        <th>Gelen Evrak</th>
                        <th>İmar Durum</th>
                        <th>Emsal</th>
                        <th>Yençok</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($imarEvraklari as $i)
                        <tr>
                            <td class="td-mono">{{ $loop->iteration }}</td>
                            <td>{{ optional($i->imar_giden_tarih)->format('d.m.Y') ?: '—' }}</td>
                            <td class="td-mono">{{ $i->imar_giden_yazi ?? '—' }}</td>
                            <td>@if ($i->imar_giden_evrak)<a class="td-link" href="{{ asset('storage/'.$i->imar_giden_evrak) }}" target="_blank">PDF</a>@else — @endif</td>
                            <td>{{ optional($i->imar_gelen_tarih)->format('d.m.Y') ?: '—' }}</td>
                            <td class="td-mono">{{ $i->imar_gelen_yazi ?? '—' }}</td>
                            <td>@if ($i->imar_gelen_evrak)<a class="td-link" href="{{ asset('storage/'.$i->imar_gelen_evrak) }}" target="_blank">PDF</a>@else — @endif</td>
                            <td>{{ $i->imar_durum ?? '—' }}</td>
                            <td class="td-mono">{{ $i->emsal ?? '—' }}</td>
                            <td class="td-mono">{{ $i->yencok ?? '—' }}</td>
                            <td>
                                <button type="button" class="btn-cancel hs-duzenle-btn"
                                    onclick="hsDuzenleAc('imar', {{ Js::from([
                                        "id" => $i->id,
                                        "imar_giden_yazi" => $i->imar_giden_yazi,
                                        "imar_giden_tarih" => optional($i->imar_giden_tarih)->toDateString(),
                                        "imar_gelen_yazi" => $i->imar_gelen_yazi,
                                        "imar_gelen_tarih" => optional($i->imar_gelen_tarih)->toDateString(),
                                        "imar_durum" => $i->imar_durum,
                                        "emsal" => $i->emsal,
                                        "yencok" => $i->yencok,
                                        "plan_notlari" => $i->plan_notlari,
                                    ]) }})">Düzenle</button>
                                <button type="button" class="btn-cancel" style="padding:4px 10px;font-size:0.75rem;" onclick="hsImarSil({{ $i->id }})">Sil</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ============ 3) Tebligat Gönderilenler ============ --}}
<div class="hs-panel" data-hs-panel="tebligatlar">
    @if ($secilebilirMalikler->isNotEmpty())
        <form method="POST" action="{{ route('panel.hisse-satisi.tebligat.kaydet', $grupNo) }}" class="hs-secim-form">
            @csrf
            <div class="hs-secim-baslik">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 8h18"/></svg>
                Tebligat Ekleme — Hissedar seçin
            </div>
            <div class="tl-tablo-wrap">
                <table class="tl-tablo hs-secim-tablo">
                    <thead>
                        <tr>
                            <th style="width:44px;">
                                <input type="checkbox" class="hs-tumu-sec" data-hedef="tebligat-cb" title="Tümünü seç">
                            </th>
                            <th>Ad Soyad</th>
                            <th>TC Kimlik No</th>
                            <th>Tapu Hisse (m²)</th>
                            <th>GSM</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($secilebilirMalikler as $m)
                            <tr>
                                <td><input type="checkbox" class="tebligat-cb" name="malik_ids[]" value="{{ $m->id }}"></td>
                                <td><strong>{{ $m->ad_soyad }}</strong></td>
                                <td class="td-mono">{{ $m->tc_kimlik ?? '—' }}</td>
                                <td class="td-mono">{{ $m->tapu_hisse !== null ? number_format((float) $m->tapu_hisse, 2, ',', '.') : '—' }}</td>
                                <td class="td-mono">{{ $m->gsm_no ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="hs-secim-alt">
                <div class="hs-secim-alan">
                    <label>Tebligat Tarihi</label>
                    <input type="date" name="tebligat_tarihi" class="form-input">
                </div>
                <div class="hs-secim-alan">
                    <label>Ulaştığı Tarih</label>
                    <input type="date" name="ulastigi_tarihi" class="form-input">
                </div>
                <button type="submit" class="btn-submit">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                    Seçilenlere Tebligat Çıkar
                </button>
            </div>
        </form>
    @else
        <p class="hs-info">Tebligat çıkarılabilecek başka hissedar kalmadı. Malikler sekmesinden yeni hissedar ekleyebilirsiniz.</p>
    @endif

    @if ($tebligatlar->isEmpty())
        <p class="td-bos">Ön tebligat kaydı yok.</p>
    @else
        <div class="tl-tablo-wrap" style="margin-top:16px;">
            <table class="tl-tablo">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Ad Soyad</th>
                        <th>TC Kimlik No</th>
                        <th>Tapu Hissesi (m²)</th>
                        <th>Tebligat Tarihi</th>
                        <th>Ulaştığı Tebligat Tarihi</th>
                        <th>Kalan Gün</th>
                        <th>Başvurdu</th>
                        <th>Tebligat Ulaşmadı</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tebligatlar as $t)
                        @php $kg = $t->kalanGun(); @endphp
                        <tr data-teb-id="{{ $t->id }}">
                            <td class="td-mono">{{ $loop->iteration }}</td>
                            <td>{{ $t->ad_soyad }}</td>
                            <td class="td-mono">{{ $t->tc_kimlik }}</td>
                            <td class="td-mono">{{ $t->tapu_hisse !== null ? number_format((float) $t->tapu_hisse, 2, ',', '.') : '—' }}</td>
                            <td>{{ optional($t->tebligat_tarihi)->format('d.m.Y') ?: '—' }}</td>
                            <td>
                                <input type="date" class="form-input hs-inline-date"
                                    value="{{ optional($t->ulastigi_tarihi)->toDateString() }}"
                                    onchange="hsTebligatUlastigi({{ $t->id }}, this)">
                            </td>
                            <td class="hs-kalan-gun">
                                @if ($kg === null) —
                                @elseif ($kg > 0) <span class="tl-pill tl-pill-warn">{{ $kg }} gün</span>
                                @else <span class="tl-pill tl-pill-danger">Süre doldu</span>
                                @endif
                            </td>
                            <td>
                                <label class="hs-switch">
                                    <input type="checkbox" @checked($t->basvurdu) onchange="hsTebligatBasvurdu({{ $t->id }}, this)">
                                    <span class="hs-switch-slider"></span>
                                </label>
                            </td>
                            <td>
                                <label class="hs-switch">
                                    <input type="checkbox" @checked($t->tebligat_ulasmadi) onchange="hsTebligatUlasmadi({{ $t->id }}, this)">
                                    <span class="hs-switch-slider"></span>
                                </label>
                            </td>
                            <td>
                                <button type="button" class="btn-cancel hs-duzenle-btn"
                                    onclick="hsDuzenleAc('tebligat', {{ Js::from([
                                        "id" => $t->id,
                                        "ad_soyad" => $t->ad_soyad,
                                        "tc_kimlik" => $t->tc_kimlik,
                                        "tapu_hisse" => $t->tapu_hisse,
                                        "tebligat_tarihi" => optional($t->tebligat_tarihi)->toDateString(),
                                        "ulastigi_tarihi" => optional($t->ulastigi_tarihi)->toDateString(),
                                    ]) }})">Düzenle</button>
                                <button type="button" class="btn-cancel" style="padding:4px 10px;font-size:0.75rem;" onclick="hsTebligatSil({{ $t->id }})">Sil</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ============ 4) Encümen Bilgileri ============ --}}
<div class="hs-panel" data-hs-panel="encumen">
    <form method="POST" action="{{ route('panel.hisse-satisi.encumen.kaydet', $grupNo) }}" enctype="multipart/form-data" class="hs-mini-form hs-mini-form-lg">
        @csrf
        <input type="text" name="karar_no" placeholder="Karar No" class="form-input" maxlength="100">
        <input type="text" name="kayit_no" placeholder="Kayıt No" class="form-input" maxlength="100">
        <input type="text" name="birim_fiyat" placeholder="Birim Fiyat (₺/m²)" class="form-input" inputmode="decimal">
        <input type="date" name="gelen_tarih" class="form-input" title="Gelen Evrak Tarihi">
        <input type="date" name="giden_tarih" class="form-input" title="Giden Evrak Tarihi">
        <label class="hs-dosya">
            <span>Gelen Evrak</span>
            <input type="file" name="gelen_evrak" accept="application/pdf">
        </label>
        <label class="hs-dosya">
            <span>Giden Evrak</span>
            <input type="file" name="giden_evrak" accept="application/pdf">
        </label>
        <input type="text" name="aciklama" placeholder="Açıklama" class="form-input" maxlength="2000">
        <button type="submit" class="btn-submit">+ Encümen Kararı</button>
    </form>

    @if ($encumenler->isEmpty())
        <p class="td-bos">Encümen kararı yok.</p>
    @else
        <div class="tl-tablo-wrap" style="margin-top:16px;">
            <table class="tl-tablo">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Karar No</th>
                        <th>Kayıt No</th>
                        <th>Birim Fiyat</th>
                        <th>Gelen</th>
                        <th>Giden</th>
                        <th>Evraklar</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($encumenler as $e)
                        <tr>
                            <td class="td-mono">{{ $loop->iteration }}</td>
                            <td>{{ $e->karar_no ?? '—' }}</td>
                            <td>{{ $e->kayit_no ?? '—' }}</td>
                            <td class="td-mono">{{ $e->birim_fiyat !== null ? number_format((float) $e->birim_fiyat, 2, ',', '.').' ₺' : '—' }}</td>
                            <td>{{ optional($e->gelen_tarih)->format('d.m.Y') ?: '—' }}</td>
                            <td>{{ optional($e->giden_tarih)->format('d.m.Y') ?: '—' }}</td>
                            <td>
                                @if ($e->gelen_evrak)<a class="td-link" href="{{ asset('storage/'.$e->gelen_evrak) }}" target="_blank">Gelen</a>@endif
                                @if ($e->gelen_evrak && $e->giden_evrak) · @endif
                                @if ($e->giden_evrak)<a class="td-link" href="{{ asset('storage/'.$e->giden_evrak) }}" target="_blank">Giden</a>@endif
                                @if (! $e->gelen_evrak && ! $e->giden_evrak) — @endif
                            </td>
                            <td>
                                <button type="button" class="btn-cancel hs-duzenle-btn"
                                    onclick="hsDuzenleAc('encumen', {{ Js::from([
                                        "id" => $e->id,
                                        "karar_no" => $e->karar_no,
                                        "kayit_no" => $e->kayit_no,
                                        "birim_fiyat" => $e->birim_fiyat,
                                        "gelen_tarih" => optional($e->gelen_tarih)->toDateString(),
                                        "giden_tarih" => optional($e->giden_tarih)->toDateString(),
                                        "aciklama" => $e->aciklama,
                                    ]) }})">Düzenle</button>
                                <button type="button" class="btn-cancel" style="padding:4px 10px;font-size:0.75rem;" onclick="hsEncumenSil({{ $e->id }})">Sil</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ============ 5) Satış Tebligatı Gönderilenler ============ --}}
<div class="hs-panel" data-hs-panel="satis">
    @if ($satisSecilebilirBasvurular->isNotEmpty())
        <form method="POST" action="{{ route('panel.hisse-satisi.satis-tebligat.kaydet', $grupNo) }}" class="hs-secim-form">
            @csrf
            <div class="hs-secim-baslik">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 1 0 0 7h5a3.5 3.5 0 1 1 0 7H6"/></svg>
                Satış Tebligatı Ekleme — Başvuranları seçin
            </div>
            <div class="tl-tablo-wrap">
                <table class="tl-tablo hs-secim-tablo">
                    <thead>
                        <tr>
                            <th style="width:44px;">
                                <input type="checkbox" class="hs-tumu-sec" data-hedef="satis-cb" title="Tümünü seç">
                            </th>
                            <th>TC Kimlik No</th>
                            <th>Ad Soyad</th>
                            <th>Tapu Hisse</th>
                            <th>Talep Edilen</th>
                            <th>Düşen Yüzölçüm</th>
                            <th>Toplam Bedel</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($satisSecilebilirBasvurular as $b)
                            @php $h = $basvuruHesap[$b->id] ?? ['talep'=>0,'dusen'=>0,'bedel'=>0]; @endphp
                            <tr>
                                <td><input type="checkbox" class="satis-cb" name="basvuru_ids[]" value="{{ $b->id }}"></td>
                                <td class="td-mono">{{ $b->tc_kimlik }}</td>
                                <td><strong>{{ $b->ad_soyad }}</strong></td>
                                <td class="td-mono">{{ $b->tapu_hisse !== null ? number_format((float) $b->tapu_hisse, 2, ',', '.') : '—' }}</td>
                                <td class="td-mono">{{ $b->talep_edilen_hisse !== null ? number_format((float) $b->talep_edilen_hisse, 2, ',', '.') : '—' }}</td>
                                <td>
                                    <input type="text" name="hisseye_dusen_yuzolcum[{{ $b->id }}]"
                                        value="{{ $h['dusen'] > 0 ? number_format($h['dusen'], 2, '.', '') : '' }}"
                                        class="form-input hs-tablo-input" inputmode="decimal" placeholder="m²">
                                </td>
                                <td>
                                    <input type="text" name="toplam_bedel[{{ $b->id }}]"
                                        value="{{ $h['bedel'] > 0 ? number_format($h['bedel'], 2, '.', '') : '' }}"
                                        class="form-input hs-tablo-input" inputmode="decimal" placeholder="₺">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="hs-secim-alt">
                <div class="hs-secim-alan">
                    <label>Tebligat Tarihi</label>
                    <input type="date" name="tebligat_tarihi" class="form-input" required>
                </div>
                <div class="hs-secim-alan">
                    <label>Ulaştığı Tarih</label>
                    <input type="date" name="ulastigi_tarihi" class="form-input">
                </div>
                <div class="hs-secim-alan">
                    <label>Birim Fiyat (₺/m²)</label>
                    <input type="text" name="birim_fiyat" value="{{ $encumenBirimFiyat > 0 ? number_format($encumenBirimFiyat, 2, '.', '') : '' }}" class="form-input" inputmode="decimal">
                </div>
                <button type="submit" class="btn-submit">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                    Seçilenlere Satış Tebligatı Çıkar
                </button>
            </div>
        </form>
    @else
        <p class="hs-info">Tüm başvurulara satış tebligatı çıkarılmış.</p>
    @endif

    @if ($satisTebligatlari->isEmpty())
        <p class="td-bos">Satış tebligatı yok.</p>
    @else
        <div class="tl-tablo-wrap" style="margin-top:16px;">
            <table class="tl-tablo">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Ad Soyad</th>
                        <th>TC Kimlik No</th>
                        <th>Verilen Hisse (m²)</th>
                        <th>Birim Fiyat (₺)</th>
                        <th>Toplam Bedel (₺)</th>
                        <th>Tebligat Tarihi</th>
                        <th>Ulaştığı Tebligat Tarihi</th>
                        <th>Kalan Gün</th>
                        <th>Ödedi</th>
                        <th>Tebligat Ulaşmadı</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($satisTebligatlari as $st)
                        @php $kg = $st->kalanGun(); @endphp
                        <tr data-satis-id="{{ $st->id }}">
                            <td class="td-mono">{{ $loop->iteration }}</td>
                            <td><strong>{{ optional($st->basvuru)->ad_soyad ?? '—' }}</strong></td>
                            <td class="td-mono">{{ optional($st->basvuru)->tc_kimlik ?? '—' }}</td>
                            <td class="td-mono">{{ number_format((float) $st->hisseye_dusen_yuzolcum, 2, ',', '.') }}</td>
                            <td class="td-mono">{{ $st->birim_fiyat !== null ? number_format((float) $st->birim_fiyat, 2, ',', '.') : '—' }}</td>
                            <td class="td-mono"><strong>{{ number_format((float) $st->toplam_bedel, 2, ',', '.') }}</strong></td>
                            <td>{{ optional($st->tebligat_tarihi)->format('d.m.Y') }}</td>
                            <td>
                                <input type="date" class="form-input hs-inline-date"
                                    value="{{ optional($st->ulastigi_tarihi)->toDateString() }}"
                                    onchange="hsSatisUlastigi({{ $st->id }}, this)">
                            </td>
                            <td class="hs-kalan-gun">
                                @if ($kg === null) —
                                @elseif ($kg > 0) <span class="tl-pill tl-pill-warn">{{ $kg }} gün</span>
                                @else <span class="tl-pill tl-pill-danger">Süre doldu</span>
                                @endif
                            </td>
                            <td>
                                <label class="hs-switch">
                                    <input type="checkbox" @checked($st->odedi) onchange="hsSatisOdedi({{ $st->id }}, this)">
                                    <span class="hs-switch-slider"></span>
                                </label>
                            </td>
                            <td>
                                <label class="hs-switch">
                                    <input type="checkbox" @checked($st->tebligat_ulasmadi) onchange="hsSatisUlasmadi({{ $st->id }}, this)">
                                    <span class="hs-switch-slider"></span>
                                </label>
                            </td>
                            <td>
                                <button type="button" class="btn-cancel hs-duzenle-btn"
                                    onclick="hsDuzenleAc('satis', {{ Js::from([
                                        "id" => $st->id,
                                        "tebligat_tarihi" => optional($st->tebligat_tarihi)->toDateString(),
                                        "ulastigi_tarihi" => optional($st->ulastigi_tarihi)->toDateString(),
                                        "hisseye_dusen_yuzolcum" => $st->hisseye_dusen_yuzolcum,
                                        "birim_fiyat" => $st->birim_fiyat,
                                        "toplam_bedel" => $st->toplam_bedel,
                                        "aciklama" => $st->aciklama,
                                    ]) }})">Düzenle</button>
                                <button type="button" class="btn-cancel" style="padding:4px 10px;font-size:0.75rem;" onclick="hsSatisSil({{ $st->id }})">Sil</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ============ 6) Tapu Tescili ============ --}}
<div class="hs-panel" data-hs-panel="tapu">
    @if ($tapuBekleyenBasvurular->isNotEmpty())
        <div class="hs-info" style="margin-bottom:12px;">
            <strong>{{ $tapuBekleyenBasvurular->count() }}</strong> başvuru ödeme yapmış, tapu tescili bekliyor. Her biri için ayrı bir tescil kaydı oluşturun.
        </div>
        @foreach ($tapuBekleyenBasvurular as $b)
            <form method="POST" action="{{ route('panel.hisse-satisi.tapu.kaydet', $b->id) }}" enctype="multipart/form-data" class="hs-tapu-form">
                @csrf
                <div class="hs-tapu-form-baslik">
                    {{ $b->ad_soyad }} <small>TC {{ $b->tc_kimlik }}</small>
                </div>
                <div class="hs-tapu-form-govde">
                    <input type="date" name="tescil_tarihi" value="{{ now()->toDateString() }}" class="form-input" required title="Tescil Tarihi">
                    <input type="text" name="yevmiye_no" placeholder="Yevmiye No" class="form-input" maxlength="100">
                    <input type="text" name="tescil_edilen_yuzolcum" value="{{ number_format($basvuruHesap[$b->id]['dusen'] ?? 0, 2, '.', '') }}" placeholder="Tescil m²" class="form-input" inputmode="decimal">
                    <label class="hs-dosya">
                        <span>Yeni Tapu Senedi PDF</span>
                        <input type="file" name="tescil_evrak" accept="application/pdf">
                    </label>
                    <input type="text" name="aciklama" placeholder="Açıklama" class="form-input" maxlength="2000">
                    <button type="submit" class="btn-submit">Tapu Tescilini Kaydet</button>
                </div>
            </form>
        @endforeach
    @else
        <p class="hs-info">Tapu tescili için hazır ödeme kaydı yok. Ödeme tamamlandığında bu sekmede yeni malik adına tescil evrağı yüklenir.</p>
    @endif

    @if ($satisTapulari->isEmpty())
        <p class="td-bos" style="margin-top:12px;">Henüz tapu tescili yok.</p>
    @else
        <div class="tl-tablo-wrap" style="margin-top:16px;">
            <table class="tl-tablo">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Yeni Malik</th>
                        <th>Tescil Tarihi</th>
                        <th>Yevmiye No</th>
                        <th>Tescil m²</th>
                        <th>Evrak</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($satisTapulari as $t)
                        <tr>
                            <td class="td-mono">{{ $loop->iteration }}</td>
                            <td>{{ optional($t->basvuru)->ad_soyad ?? '—' }} <small class="td-mono">TC {{ optional($t->basvuru)->tc_kimlik }}</small></td>
                            <td>{{ optional($t->tescil_tarihi)->format('d.m.Y') }}</td>
                            <td class="td-mono">{{ $t->yevmiye_no ?? '—' }}</td>
                            <td class="td-mono">{{ $t->tescil_edilen_yuzolcum !== null ? number_format((float) $t->tescil_edilen_yuzolcum, 2, ',', '.') : '—' }}</td>
                            <td>
                                @if ($t->tescil_evrak)
                                    <a href="{{ asset('storage/'.$t->tescil_evrak) }}" target="_blank" class="td-link">Tapu PDF</a>
                                @else — @endif
                            </td>
                            <td>
                                <button type="button" class="btn-cancel hs-duzenle-btn"
                                    onclick="hsDuzenleAc('tapu', {{ Js::from([
                                        "id" => $t->id,
                                        "tescil_tarihi" => optional($t->tescil_tarihi)->toDateString(),
                                        "yevmiye_no" => $t->yevmiye_no,
                                        "tescil_edilen_yuzolcum" => $t->tescil_edilen_yuzolcum,
                                        "aciklama" => $t->aciklama,
                                    ]) }})">Düzenle</button>
                                <button type="button" class="btn-cancel" style="padding:4px 10px;font-size:0.75rem;" onclick="hsTapuSil({{ $t->id }})">Sil</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@include('panel.hisse-satisi._duzenle-modallar')

<script>
(function () {
    const el = document.getElementById('hs-grup-durum');
    if (! el || typeof window.ozelSelectKur !== 'function') return;
    window.ozelSelectKur(el);
})();

document.querySelectorAll('[data-hs-tab]').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.hsTab;
        document.querySelectorAll('.hs-tab-oge').forEach(b => b.classList.toggle('is-aktif', b === btn));
        document.querySelectorAll('.hs-panel').forEach(p => p.classList.toggle('is-aktif', p.dataset.hsPanel === tab));
    });
});

// Seçim tabloları: üstteki "Tümünü seç" checkbox'ı sütundaki checkbox'ları toggler
document.querySelectorAll('.hs-tumu-sec').forEach(master => {
    master.addEventListener('change', () => {
        const hedef = master.dataset.hedef;
        document.querySelectorAll('.' + hedef).forEach(cb => { cb.checked = master.checked; });
    });
});

const HS = {
    csrf: document.querySelector('meta[name="csrf-token"]').content,
    base: @json(url('/panel/hisse-satisi')),
};

function hsFetch(method, url, body) {
    return fetch(url, {
        method,
        headers: {
            'X-CSRF-TOKEN': HS.csrf,
            'Accept': 'application/json',
            'Content-Type': body ? 'application/json' : undefined,
        },
        body: body ? JSON.stringify(body) : undefined,
    }).then(async r => {
        if (! r.ok) throw new Error('Sunucu hatası');
        return r.json();
    });
}

function hsKalanGunHTML(kg) {
    if (kg === null || kg === undefined) return '—';
    if (kg > 0) return `<span class="tl-pill tl-pill-warn">${kg} gün</span>`;
    return '<span class="tl-pill tl-pill-danger">Süre doldu</span>';
}

function hsAjaxSil(url) {
    if (!confirm('Bu kaydı silmek istediğinize emin misiniz?')) return;
    hsFetch('DELETE', url).then(() => location.reload()).catch(() => alert('Silme başarısız.'));
}

function hsBasvuruSil(id) { hsAjaxSil(`${HS.base}/basvuru/${id}`); }
function hsMalikSil(id) { hsAjaxSil(`${HS.base}/malik/${id}`); }

function hsTebligatSil(id) { hsAjaxSil(`${HS.base}/tebligat/${id}`); }
function hsTebligatBasvurdu(id, cb) {
    hsFetch('PATCH', `${HS.base}/tebligat/${id}/basvurdu`, { basvurdu: cb.checked ? 1 : 0 })
        .catch(() => { cb.checked = !cb.checked; alert('Güncellenemedi'); });
}
function hsTebligatUlasmadi(id, cb) {
    hsFetch('PATCH', `${HS.base}/tebligat/${id}/ulasmadi`, { tebligat_ulasmadi: cb.checked ? 1 : 0 })
        .catch(() => { cb.checked = !cb.checked; alert('Güncellenemedi'); });
}
function hsTebligatUlastigi(id, input) {
    hsFetch('PATCH', `${HS.base}/tebligat/${id}/ulastigi-tarihi`, { ulastigi_tarihi: input.value || null })
        .then(d => {
            const row = document.querySelector(`tr[data-teb-id="${id}"]`);
            if (row) row.querySelector('.hs-kalan-gun').innerHTML = hsKalanGunHTML(d.kalan_gun);
        })
        .catch(() => alert('Güncellenemedi'));
}

function hsEncumenSil(id) { hsAjaxSil(`${HS.base}/encumen/${id}`); }

function hsGorusSil(id) { hsAjaxSil(`${HS.base}/gorus/${id}`); }

function hsImarSil(id) { hsAjaxSil(`${HS.base}/imar/${id}`); }

function hsTapuSil(id) { hsAjaxSil(`${HS.base}/tapu/${id}`); }

function hsSatisSil(id) { hsAjaxSil(`${HS.base}/satis-tebligat/${id}`); }
function hsSatisOdedi(id, cb) {
    hsFetch('PATCH', `${HS.base}/satis-tebligat/${id}/odedi`, { odedi: cb.checked ? 1 : 0 })
        .catch(() => { cb.checked = !cb.checked; alert('Güncellenemedi'); });
}
function hsSatisUlasmadi(id, cb) {
    hsFetch('PATCH', `${HS.base}/satis-tebligat/${id}/ulasmadi`, { tebligat_ulasmadi: cb.checked ? 1 : 0 })
        .catch(() => { cb.checked = !cb.checked; alert('Güncellenemedi'); });
}
function hsSatisUlastigi(id, input) {
    hsFetch('PATCH', `${HS.base}/satis-tebligat/${id}/ulastigi-tarihi`, { ulastigi_tarihi: input.value || null })
        .then(d => {
            const row = document.querySelector(`tr[data-satis-id="${id}"]`);
            if (row) row.querySelector('.hs-kalan-gun').innerHTML = hsKalanGunHTML(d.kalan_gun);
        })
        .catch(() => alert('Güncellenemedi'));
}
</script>
@endsection
