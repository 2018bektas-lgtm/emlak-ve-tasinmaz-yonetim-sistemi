{{--
    Kolon seçim paneli — listelerde ortak.

    Taşınmaz ve hisse listeleri bu işaretlemeyi kendi içlerinde taşıyor;
    yeni listeler bu parçayı dahil eder. Beklediği değişkenler:
      $kolonGruplari   : KolonlarSinifi::gruplar()
      $gorunurKolonlar : seçili anahtarlar
      $secilenSayi     : seçili sayısı
      $toplamKolon     : katalog sayısı
    Kaydetme işini sayfanın kendi betiği yapar (data-kp-* kancaları).
--}}
@php
    $kolonAnahtarIkonlar = [
        'pin' => '<path d="M12 22s-7-7-7-13a7 7 0 0 1 14 0c0 6-7 13-7 13z"/><circle cx="12" cy="9" r="2.6"/>',
        'ruler' => '<path d="M2 15 L15 2 l7 7 L9 22z"/><path d="M6 15 h1 M9 12 h1 M12 9 h1 M15 6 h1"/>',
        'layers' => '<path d="M12 3 L2 8 l10 5 10-5z"/><path d="M2 13 l10 5 10-5"/><path d="M2 18 l10 5 10-5"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'coin' => '<circle cx="12" cy="12" r="9"/><path d="M15 9.5a3 3 0 0 0-3-1.5c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2a3 3 0 0 1-3-1.5"/>',
        'flag' => '<path d="M4 15V3h16l-3 4 3 4H4"/><path d="M4 22v-7"/>',
        'note' => '<path d="M4 4h16v12l-4 4H4z"/><path d="M20 16h-4v4"/>',
        'building' => '<rect x="5" y="3" width="14" height="18" rx="1.5"/><path d="M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1"/>',
    ];
@endphp

<button type="button" class="btn-cancel kp-tetik" id="kp-tetik" aria-haspopup="dialog">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="6" height="18" rx="1"/><rect x="11" y="3" width="10" height="8" rx="1"/><rect x="11" y="13" width="10" height="8" rx="1"/></svg>
    Kolonlar
    <span class="kp-tetik-sayi" data-kp-sayac>{{ $secilenSayi }}<em>/{{ $toplamKolon }}</em></span>
</button>

<div class="kp-backdrop" id="kp-backdrop" hidden>
    <div class="kp-panel" role="dialog" aria-modal="true" aria-label="Kolon seçimi">
        <div class="kp-head">
            <div>
                <h3>Kolonları Yönet</h3>
                <small><span data-kp-sayac>{{ $secilenSayi }}</span> / {{ $toplamKolon }} kolon seçili</small>
            </div>
            <button type="button" class="kp-kapat" id="kp-kapat" aria-label="Kapat">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="kp-ara-wrap">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="search" id="kp-ara" placeholder="Kolon ara..." autocomplete="off">
            <button type="button" class="kp-ara-temizle" id="kp-ara-temizle" aria-label="Aramayı temizle" hidden>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="kp-toplu">
            <button type="button" class="kp-mini-btn" data-kp-eylem="tumu">Tümünü seç</button>
            <button type="button" class="kp-mini-btn" data-kp-eylem="hicbiri">Hiçbirini seçme</button>
            <button type="button" class="kp-mini-btn" data-kp-eylem="varsayilan">Varsayılan</button>
        </div>
        <div class="kp-liste" id="kp-liste">
            @foreach ($kolonGruplari as $grupAd => $grup)
                @php $grupKolonlar = $grup['kolonlar']; @endphp
                @if (empty($grupKolonlar)) @continue @endif
                <div class="kp-grup" data-kp-grup>
                    <div class="kp-grup-head">
                        <span class="kp-grup-ikon">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $kolonAnahtarIkonlar[$grup['ikon']] ?? '' !!}</svg>
                        </span>
                        <span class="kp-grup-ad">{{ $grupAd }}</span>
                        <span class="kp-grup-sayac" data-kp-grup-sayac>0/{{ count($grupKolonlar) }}</span>
                        <button type="button" class="kp-grup-toggle" data-kp-grup-toggle title="Bu grubun tümünü aç/kapa">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                        </button>
                    </div>
                    <div class="kp-grup-icerik">
                        @foreach ($grupKolonlar as $anahtar => $etiket)
                            <label class="kp-secim" data-kp-anahtar="{{ $anahtar }}">
                                <input type="checkbox" class="kp-cb" value="{{ $anahtar }}" @checked(in_array($anahtar, $gorunurKolonlar, true))>
                                <span class="kp-secim-kutucuk" aria-hidden="true">
                                    <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                                </span>
                                <span class="kp-secim-ad">{{ $etiket }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
            <p class="kp-bos" id="kp-bos" hidden>Aramanıza uyan kolon yok.</p>
        </div>
        <div class="kp-alt">
            <span class="kp-alt-durum" id="kp-durum" hidden></span>
            <button type="button" class="btn-cancel" id="kp-vazgec">Kapat</button>
        </div>
    </div>
</div>
