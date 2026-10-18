@extends('layouts.panel')

@section('title', 'Yeni Hisse Satış Başvurusu')

@section('shellClass', 'is-cam')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/hisse-satis.css') }}?v=2">
@endpush

@section('content')
<section class="page-hero">
    <div class="page-hero-text">
        <p class="hs-eyebrow">Hisse Satışı · Yeni Başvuru</p>
        <h2>{{ optional($tasinmaz->ilce)->ad ?? '—' }} · {{ optional($tasinmaz->mahalle)->ad ?? '—' }}</h2>
        <small>
            Ada <strong>{{ $tasinmaz->ada }}</strong> · Parsel <strong>{{ $tasinmaz->parsel }}</strong>
            · Kurum hissesi <strong>{{ number_format((float) $tasinmaz->hisseler->where('hisse_durum', 'aktif')->sum('hisse_yuzolcum'), 2, ',', '.') }} m²</strong>
        </small>
    </div>
    <div class="page-hero-actions">
        <a href="{{ route('panel.hisse-satisi.index') }}" class="btn-cancel">← Geri</a>
    </div>
</section>

@if ($mevcutGrup)
    <div class="hs-info hs-info-dosya">
        <strong>Açık dosya var:</strong> Bu başvuru taşınmazın açık dosyasına
        (<a href="{{ route('panel.hisse-satisi.detay', $mevcutGrup->grup_no) }}">Dosya #{{ $mevcutGrup->grup_no }}</a>) eklenecek
        ve diğer başvurularla birlikte yürüyecek.
    </div>
@else
    <div class="hs-info hs-info-dosya">
        Bu taşınmaz için açık bir dosya yok — başvuru <strong>yeni bir dosya</strong> açacak.
    </div>
@endif

@if ($errors->any())
    <div class="flash-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@if ($tasinmaz->malikler->isEmpty())
    <div class="hs-info" style="margin-bottom:16px;">
        <strong>Not:</strong> Bu taşınmaza henüz hissedar (malik) tanımlanmamış. Başvuru sonrası detay ekranındaki
        <em>Malikler</em> sekmesinden diğer hissedarları ekleyerek toplu ön tebligat çıkarabilirsiniz.
    </div>
@else
    <div class="hs-info" style="margin-bottom:16px;">
        Bu taşınmaza tanımlı <strong>{{ $tasinmaz->malikler->count() }}</strong> hissedar var.
        Başvurunun ardından bu hissedarlara toplu tebligat çıkarabilirsiniz.
    </div>
@endif

<form method="POST" action="{{ route('panel.hisse-satisi.kaydet', $tasinmaz->id) }}" enctype="multipart/form-data" class="form-shell hs-form">
    @csrf
    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">01</span>
            <div class="form-section-head-text">
                <h3>Başvuran Kişi</h3>
                <small>T.C. Kimlik No kontrol haneleriyle doğrulanır; aynı kişi açık dosyaya ikinci kez başvuramaz.</small>
            </div>
        </div>
        @if ($tasinmaz->malikler->isNotEmpty())
            @php
                $malikVerisi = $tasinmaz->malikler->map(fn ($m) => [
                    'id' => $m->id, 'ad' => $m->ad_soyad, 'tc' => $m->tc_kimlik,
                    'hisse' => $m->tapu_hisse, 'gsm' => $m->gsm_no,
                ])->values();
            @endphp
            <div class="form-row">
                <div class="form-field">
                    <label for="hs-malik-sec">Hissedarlardan seç</label>
                    <select id="hs-malik-sec" class="form-select" data-malikler="{{ $malikVerisi->toJson() }}">
                        <option value="">— Listeden seçin ya da aşağıya elle girin —</option>
                        @foreach ($tasinmaz->malikler as $m)
                            <option value="{{ $m->id }}">{{ $m->ad_soyad }}{{ $m->tc_kimlik ? ' · '.$m->tc_kimlik : '' }}</option>
                        @endforeach
                    </select>
                    <span class="hint">Başvuran taşınmazın hissedarıysa seçin — ad, TC, GSM ve tapu hissesi doldurulur.</span>
                </div>
            </div>
        @endif
        <div class="form-row form-row-2">
            <div class="form-field">
                <label>Ad Soyad <span class="required">*</span></label>
                <input type="text" name="ad_soyad" id="hs-ad" value="{{ old('ad_soyad') }}" class="form-input" required maxlength="150">
            </div>
            <div class="form-field">
                <label>TC Kimlik No <span class="required">*</span></label>
                <input type="text" name="tc_kimlik" id="hs-tc" value="{{ old('tc_kimlik') }}" class="form-input" required minlength="11" maxlength="11" pattern="[0-9]{11}" inputmode="numeric">
                @error('tc_kimlik')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field">
                <label>GSM No</label>
                <input type="text" name="gsm_no" id="hs-gsm" value="{{ old('gsm_no') }}" class="form-input" maxlength="20" placeholder="5xx xxx xx xx">
            </div>
            <div class="form-field">
                <label>Başvuru Tarihi <span class="required">*</span></label>
                <input type="date" name="basvuru_tarihi" value="{{ old('basvuru_tarihi', now()->format('Y-m-d')) }}" class="form-input" required>
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <span class="form-section-num">02</span>
            <div class="form-section-head-text">
                <h3>Hisse & Evrak</h3>
                <small>Tapudaki hisse (m²) ve talep edilen miktar. PDF evrak isteğe bağlı.</small>
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-field">
                <label>Tapudaki Hisse (m²)</label>
                <input type="text" name="tapu_hisse" id="hs-tapu-hisse" value="{{ old('tapu_hisse') }}" class="form-input" inputmode="decimal" placeholder="450,00">
            </div>
            <div class="form-field">
                <label>Talep Edilen Hisse (m²)</label>
                <input type="text" name="talep_edilen_hisse" value="{{ old('talep_edilen_hisse') }}" class="form-input" inputmode="decimal" placeholder="200,00">
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label>Başvuru Evrağı (PDF)</label>
                <input type="file" name="basvuru_evrak" class="form-input" accept="application/pdf">
                <span class="hint">Maks. 10 MB, sadece PDF.</span>
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <label>Açıklama</label>
                <textarea name="aciklama" class="form-textarea" rows="3" maxlength="5000">{{ old('aciklama') }}</textarea>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ route('panel.hisse-satisi.index') }}" class="btn-cancel">Vazgeç</a>
        <button type="submit" class="btn-submit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            Başvuruyu Kaydet
        </button>
    </div>
</form>

<script>
(function () {
    const sec = document.getElementById('hs-malik-sec');
    if (! sec) return;
    const malikler = JSON.parse(sec.dataset.malikler || '[]');
    const tr = n => (n === null || n === undefined || n === '') ? '' : Number(n).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    sec.addEventListener('change', () => {
        const m = malikler.find(x => String(x.id) === sec.value);
        if (! m) return;
        document.getElementById('hs-ad').value = m.ad || '';
        document.getElementById('hs-tc').value = m.tc || '';
        document.getElementById('hs-gsm').value = m.gsm || '';
        document.getElementById('hs-tapu-hisse').value = tr(m.hisse);
    });
})();
</script>
@endsection
