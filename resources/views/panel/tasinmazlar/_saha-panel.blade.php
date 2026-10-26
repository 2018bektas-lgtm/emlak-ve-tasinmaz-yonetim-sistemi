{{--
    Bilgi panelinin resmî imar, risk-kısıt ve altyapı-arazi bölümleri
    (bkz. TasinmazController::haritaSaha). Ayrı istenir, yer tutucunun yerine geçer.
--}}
@php
    $durumAd = ['risk' => 'Risk', 'dikkat' => 'Dikkat', 'iyi' => 'Uygun', 'bilgi' => 'Bilgi', 'veri-yok' => 'Veri yok'];
    $eimar = $saha['eimar'] ?? null;
    $resmi = $eimar['veri'] ?? null;
    $sistemImar = $tasinmaz->imar?->imarDurumu?->ad;
    $buyuk = fn ($s) => mb_strtoupper(strtr((string) $s, ['i' => 'İ', 'ı' => 'I']), 'UTF-8');
    $imarUyusmuyor = $resmi && $resmi['kullanim'] && $sistemImar && $buyuk($resmi['kullanim']) !== $buyuk($sistemImar);
@endphp

{{-- Resmî imar --}}
<details class="tsb-bolum tsb-saha" open>
    <summary>
        <span class="tsb-ikon is-turuncu"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13l2 2 4-4"/></svg></span>
        <span class="tsb-bolum-ad">Resmî İmar (e-İmar)</span>
        @if ($imarUyusmuyor)
            <span class="tsb-bolum-meta is-uyari">Kayıtla uyuşmuyor</span>
        @elseif ($resmi)
            <span class="tsb-bolum-meta">Yürürlükte</span>
        @else
            <span class="tsb-bolum-meta is-bos">Plan yok</span>
        @endif
    </summary>
    @if (! $saha)
        <p class="tsb-bos">Konum kaydı olmadığı için sorgulanamadı.</p>
    @elseif ($resmi)
        <div class="tsb-resmi-imar">
            <span>Plan kullanımı</span>
            <strong>{{ $resmi['kullanim'] ?? '—' }}</strong>
            @if ($resmi['alt_kullanim'])<small>{{ $resmi['alt_kullanim'] }}</small>@endif
        </div>
        @if ($imarUyusmuyor)
            <p class="tsb-uyari">Sistemdeki imar kaydı <b>{{ $sistemImar }}</b>; yürürlükteki plan farklı gösteriyor. Kaydın güncellenmesi gerekebilir.</p>
        @endif
        <dl class="tsb-liste">
            <div><dt>KAKS / Emsal</dt><dd class="is-mono">{{ $resmi['kaks'] ?? '—' }}</dd></div>
            <div><dt>TAKS</dt><dd class="is-mono">{{ $resmi['taks'] ?? '—' }}</dd></div>
            <div><dt>Kat Adedi</dt><dd>{{ $resmi['kat_adedi'] ?? '—' }}</dd></div>
            <div><dt>Hmax</dt><dd>{{ $resmi['hmax'] ?? '—' }}</dd></div>
            <div><dt>Yapı Düzeni</dt><dd>{{ $resmi['yapi_duzeni'] ?? '—' }}</dd></div>
            <div><dt>Çekme (ön/yan/arka)</dt><dd>{{ $resmi['cekme'] ?? '—' }}</dd></div>
            @if ($resmi['plan_adasi'])<div><dt>Plan Adası</dt><dd>{{ $resmi['plan_adasi'] }}</dd></div>@endif
        </dl>
    @else
        <p class="tsb-bos">{{ match ($eimar['durum'] ?? 0) {
            404 => 'Bu noktada yürürlükte uygulama imar planı yok (plan dışı alan).',
            422 => 'Ankara BB e-İmar kapsamı dışında.',
            default => 'e-İmar servisine şu an ulaşılamadı.',
        } }}</p>
    @endif
</details>

@foreach ([
    ['Risk & Kısıtlar', 'is-kirmizi', '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>', $saha ? array_merge($saha['risk']['maddeler'], $saha['kisitlar']) : []],
    ['Altyapı & Arazi', 'is-mavi', '<path d="M2 20h20M5 20V9l7-5 7 5v11"/><path d="M9 20v-5h6v5"/>', $saha['altyapi'] ?? []],
] as [$baslik, $renk, $ikon, $maddeler])
    @php
        $riskSayi = collect($maddeler)->where('durum', 'risk')->count();
        $dikkatSayi = collect($maddeler)->where('durum', 'dikkat')->count();
    @endphp
    <details class="tsb-bolum tsb-saha" {{ $riskSayi ? 'open' : '' }}>
        <summary>
            <span class="tsb-ikon {{ $renk }}"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">{!! $ikon !!}</svg></span>
            <span class="tsb-bolum-ad">{{ $baslik }}</span>
            @if (! $saha)
                <span class="tsb-say is-bos">—</span>
            @else
                <span class="tsb-say {{ $riskSayi ? 'is-risk' : ($dikkatSayi ? 'is-uyari' : 'is-iyi') }}">{{ $riskSayi ?: ($dikkatSayi ?: '✓') }}</span>
            @endif
        </summary>
        @if (! $saha)
            <p class="tsb-bos">Konum kaydı olmadığı için analiz yapılamıyor.</p>
        @else
            <ul class="tsb-maddeler">
                @foreach ($maddeler as $m)
                    <li class="is-{{ $m['durum'] }}">
                        <span class="tsb-madde-durum">{{ $durumAd[$m['durum']] ?? $m['durum'] }}</span>
                        <div>
                            <b>{{ $m['ad'] }}</b>
                            <strong>{{ $m['deger'] }}</strong>
                            <small>{{ $m['aciklama'] }}</small>
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($baslik === 'Altyapı & Arazi' && $saha['arazi'])
                @php $a = $saha['arazi']; @endphp
                <div class="tsb-arazi">
                    <div class="tsb-pusula" aria-hidden="true">
                        <span>K</span>
                        @if ($a['bakiAci'] !== null)<i style="transform: rotate({{ $a['bakiAci'] }}deg)"></i>@endif
                    </div>
                    <dl class="tsb-liste">
                        <div><dt>Eğim</dt><dd>%{{ number_format($a['egim'], 1, ',', '') }} · {{ $a['egimSinifi'] }}</dd></div>
                        <div><dt>Bakı</dt><dd>{{ $a['baki'] }}</dd></div>
                        <div><dt>Rakım</dt><dd>{{ $a['rakim'] }} m <small>({{ $a['rakimMin'] }}–{{ $a['rakimMax'] }})</small></dd></div>
                    </dl>
                </div>
            @endif
            @if ($baslik === 'Risk & Kısıtlar')
                <p class="tsb-cevre-not">Kaynak: Ankara BB (hidroloji, jeolojik etüt, itfaiye, acil toplanma, KUDEM, orman meşceresi), USGS deprem kataloğu · {{ \Illuminate\Support\Carbon::parse($saha['tarih'])->format('d.m.Y') }}</p>
            @else
                <p class="tsb-cevre-not">Kaynak: ASKİ, ABB plana göre yol genişliği, Open-Meteo yükseklik modeli (90 m; eğim yaklaşık değerdir).</p>
            @endif
        @endif
    </details>
@endforeach
