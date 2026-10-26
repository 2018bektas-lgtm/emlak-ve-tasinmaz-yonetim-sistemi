{{--
    İç sayfaların sol şeridi: kapakla aynı lacivert zemin, bölüm numarası,
    başlık, sayfanın öne çıkan rakamları ve künye.
    $no · $baslik · $alt · $vurgular: list<[etiket, değer, birim|null]> · $kunye · $sayfaNo
--}}
<aside class="yan">
    <div class="yan-marka">
        <span class="yan-isaret">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9v12h14V9"/><path d="M9 21v-6h6v6"/></svg>
        </span>
        <span>Taşınmaz Raporu</span>
    </div>

    <div class="yan-bolum">
        <span class="yan-no">{{ $no }}</span>
        <h2>{{ $baslik }}</h2>
        <p>{{ $alt }}</p>
    </div>

    <dl class="yan-vurgu">
        @foreach ($vurgular as [$etiket, $deger, $birim])
            <div>
                <dt>{{ $etiket }}</dt>
                <dd class="{{ mb_strlen((string) $deger) > 14 ? 'is-uzun' : '' }}">{{ $deger }}@if ($birim)<em>{{ $birim }}</em>@endif</dd>
            </div>
        @endforeach
    </dl>

    <div class="yan-kunye">
        <b>{{ $kunye }}</b>
        <span>{{ $sayfaNo }}</span>
    </div>
</aside>
