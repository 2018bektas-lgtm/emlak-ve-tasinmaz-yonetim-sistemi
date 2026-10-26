{{--
    Bilgi panelinin Çevresel Analiz bölümü (bkz. TasinmazController::haritaCevre).
    Panel açıldıktan sonra ayrı istenir ve yer tutucunun yerine geçer.
--}}
@php
    $mesafeYazi = fn ($m) => $m >= 1000 ? number_format($m / 1000, 1, ',', '.').' km' : $m.' m';
    $renkPuan = fn ($p) => $p === null ? '#a3adbd' : ($p >= 75 ? '#16a34a' : ($p >= 55 ? '#65a30d' : ($p >= 35 ? '#d97706' : '#dc2626')));
@endphp
<details class="tsb-bolum tsb-cevre" open>
    <summary>
        <span class="tsb-ikon is-yesil"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 12l4-3"/><path d="M12 3v2M21 12h-2M5 12H3"/></svg></span>
        <span class="tsb-bolum-ad">Çevresel Analiz</span>
        @if ($cevre && ! isset($cevre['hata']))
            <span class="tsb-say" style="background: {{ $renkPuan($cevre['genel']) }}">{{ $cevre['genel'] }}</span>
        @else
            <span class="tsb-say is-bos">—</span>
        @endif
    </summary>

    @if (! $cevre)
        <p class="tsb-bos">Konum kaydı olmadığı için çevre analizi yapılamıyor.</p>
    @elseif (isset($cevre['hata']))
        <p class="tsb-bos">{{ $cevre['hata'] }} Birazdan tekrar deneyin.</p>
    @else
        <div class="tsb-endeks">
            @php $cevreUz = 2 * M_PI * 30; @endphp
            <svg viewBox="0 0 72 72" class="tsb-endeks-halka" aria-hidden="true">
                <circle cx="36" cy="36" r="30" class="zemin"/>
                <circle cx="36" cy="36" r="30" class="dolu" style="stroke: {{ $renkPuan($cevre['genel']) }}"
                        stroke-dasharray="{{ round($cevreUz * $cevre['genel'] / 100, 1) }} {{ round($cevreUz, 1) }}"/>
            </svg>
            <div class="tsb-endeks-sayi"><b>{{ $cevre['genel'] }}</b><small>/ 100</small></div>
            <div class="tsb-endeks-metin">
                <span class="tsb-endeks-ust">Konum Endeksi</span>
                <strong style="color: {{ $renkPuan($cevre['genel']) }}">{{ $cevre['seviye'] }}</strong>
                <p>{{ $cevre['ozet'] }}</p>
            </div>
        </div>

        <div class="tsb-kategoriler">
            @foreach ($cevre['kategoriler'] as $kod => $k)
                <div class="tsb-kat {{ $k['veriVar'] ? '' : 'is-yok' }}" style="--kat: {{ $k['renk'] }}">
                    <span class="tsb-kat-ikon">@include('panel.tasinmazlar._cevre-ikon', ['kod' => $kod, 'boy' => 15])</span>
                    <div class="tsb-kat-govde">
                        <div class="tsb-kat-ust">
                            <b>{{ $k['ad'] }}</b>
                            <span>{{ $k['veriVar'] ? $k['puan'] : '—' }}</span>
                        </div>
                        <span class="tsb-kat-cubuk"><i style="width: {{ $k['puan'] ?? 0 }}%"></i></span>
                        <small>
                            @if (! $k['veriVar'])
                                Veri şu an alınamadı
                            @elseif ($k['enYakin'])
                                En yakın {{ $k['enYakin']['ad'] }} · {{ $mesafeYazi($k['enYakin']['mesafe']) }} · 1 km'de {{ $k['sayi1000'] }}
                            @else
                                1 km içinde yok
                            @endif
                        </small>
                    </div>
                </div>
            @endforeach
        </div>

        <dl class="tsb-liste tsb-cevre-ek">
            <div><dt>Merkeze Uzaklık</dt><dd>{{ $mesafeYazi((int) round($cevre['merkezUzaklik'])) }} <small>(Kızılay)</small></dd></div>
            <div><dt>Rakım</dt><dd>{{ $cevre['rakim'] !== null ? number_format($cevre['rakim'], 0, ',', '.').' m' : '—' }}</dd></div>
            <div><dt>1 km'deki Taşınmazımız</dt><dd>{{ number_format($cevre['portfoy'], 0, ',', '.') }}</dd></div>
        </dl>
        <p class="tsb-cevre-not">
            Erişilebilirlik göstergesidir, değer tahmini değildir. Kaynak: Ankara BB (EGO, kent rehberi){{ $cevre['kaynaklar']['osm'] ? ', © OpenStreetMap katkıcıları' : '' }} · {{ \Illuminate\Support\Carbon::parse($cevre['tarih'])->format('d.m.Y') }}
        </p>
    @endif
</details>
