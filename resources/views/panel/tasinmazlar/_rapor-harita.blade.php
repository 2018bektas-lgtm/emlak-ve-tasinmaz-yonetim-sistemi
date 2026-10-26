{{--
    Rapor haritası: katmanlar aynı kutuyla üst üste, parsel sınırı SVG ile.
    $g: TasinmazController::raporGorseli çıktısı · $noktaMi · $sinif · $olcekGizle
--}}
@php
    $sinif = $sinif ?? '';
    $olcekGizle = $olcekGizle ?? false;
@endphp
@if ($g)
    <div class="rh {{ $sinif }}">
        @foreach ($g['katmanlar'] as $ad => $karolar)
            <div class="rh-katman rh-{{ $ad }}">
                @foreach ($karolar as $k)
                    <img src="{{ $k['url'] }}" alt="" data-rapor-gorsel onerror="this.classList.add('is-hata')"
                         style="left:{{ $k['sol'] }}%;top:{{ $k['ust'] }}%;width:{{ $k['en'] }}%;height:{{ $k['boy'] }}%">
                @endforeach
            </div>
        @endforeach

        <svg class="rh-cizim" viewBox="0 0 {{ $g['w'] }} {{ $g['h'] }}" preserveAspectRatio="none" aria-hidden="true">
            @if ($noktaMi)
                <circle cx="{{ $g['merkez'][0] }}" cy="{{ $g['merkez'][1] }}" r="16" class="rh-nokta-hale"/>
                <circle cx="{{ $g['merkez'][0] }}" cy="{{ $g['merkez'][1] }}" r="7" class="rh-nokta"/>
            @else
                <path d="{{ $g['yol'] }}" class="rh-hale"/>
                <path d="{{ $g['yol'] }}" class="rh-sinir"/>
            @endif
        </svg>

        @unless ($olcekGizle)
            <div class="rh-kuzey" aria-label="Kuzey">
                <svg viewBox="0 0 24 32" width="16" height="22"><path d="M12 2 20 28 12 22 4 28z" fill="currentColor"/><path d="M12 2v20l-8 6z" fill="rgba(255,255,255,.45)"/></svg>
                <b>K</b>
            </div>
            {{-- Kutunun genişliği haritaya göre yüzde: çubuk görselle aynı ölçekte --}}
            <div class="rh-olcek" style="width: {{ round($g['olcek']['oran'] * 100, 2) }}%">
                <span class="rh-olcek-cubuk"></span>
                <span class="rh-olcek-etiket">{{ $g['olcek']['etiket'] }}</span>
            </div>
        @endunless
    </div>
@else
    <div class="rh rh-bos {{ $sinif }}">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14M15 6v14"/></svg>
        <span>Bu taşınmaz için konum kaydı yok</span>
    </div>
@endif
