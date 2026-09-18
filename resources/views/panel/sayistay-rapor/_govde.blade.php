@php
    $rotalar = [
        'kayitli' => ['ad' => 'Kayıtlı', 'route' => 'panel.sayistay-rapor.kayitli', 'ek' => 'EK-2'],
        'kayitsiz' => ['ad' => 'Kayıtsız', 'route' => 'panel.sayistay-rapor.kayitsiz', 'ek' => 'EK-3'],
        'orta-mallari' => ['ad' => 'Orta Malları', 'route' => 'panel.sayistay-rapor.ortamallari', 'ek' => 'EK-4'],
        'genel-hizmet' => ['ad' => 'Genel Hizmet', 'route' => 'panel.sayistay-rapor.genelhizmet', 'ek' => 'EK-5'],
    ];
    $simdikiRotaAd = request()->route()?->getName();
@endphp

<section class="page-hero sr-hero">
    <div class="page-hero-text">
        <h2>{{ $baslik }} <span class="sr-ek-rozet">{{ $ek }}</span></h2>
        <small>ÜLKESİ / İLİ : TÜRKİYE / {{ $secilenIlceAd ? mb_strtoupper($secilenIlceAd, 'UTF-8') : 'ANKARA' }} · Toplam {{ $tasinmazlar->total() }} taşınmaz</small>
    </div>
    <div class="page-hero-actions">
        <button type="button" class="btn-cancel sr-btn" id="sr-yazdir-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/></svg>
            Yazdır
        </button>
        <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except('page'), ['excel' => 1])) }}" class="btn-submit sr-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/></svg>
            Excel İndir
        </a>
    </div>
</section>

{{-- Rapor tipleri (tab) --}}
<div class="sr-tab">
    @foreach ($rotalar as $slug => $r)
        <a href="{{ route($r['route']) }}" class="sr-tab-oge {{ $simdikiRotaAd === $r['route'] ? 'is-aktif' : '' }}">
            <span class="sr-tab-ek">{{ $r['ek'] }}</span>
            <span class="sr-tab-ad">{{ $r['ad'] }}</span>
        </a>
    @endforeach
</div>

{{-- Filtre --}}
<form method="GET" action="{{ url()->current() }}" class="sr-filtre">
    <div class="form-field">
        <label for="ilce_id">İlçe</label>
        <select name="ilce_id" id="ilce_id" class="form-select">
            <option value="">Tüm İlçeler</option>
            @foreach ($ilceler as $ilce)
                <option value="{{ $ilce->id }}" @selected($secilenIlce == $ilce->id)>{{ $ilce->ad }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-field">
        <label for="per_page">Sayfa Başına</label>
        <select name="per_page" id="per_page" class="form-select">
            @foreach ([10, 20, 50, 100, 200] as $n)
                <option value="{{ $n }}" @selected($sayfaBasi == $n)>{{ $n }}</option>
            @endforeach
        </select>
    </div>
    <div class="sr-filtre-btnler">
        <button type="submit" class="btn-submit">Filtrele</button>
        @if ($secilenIlce)
            <a href="{{ url()->current() }}" class="btn-cancel">Sıfırla</a>
        @endif
    </div>
</form>

{{-- Sayıştay tablosu --}}
<div class="sr-tablo-kap" id="sr-yazdir-alan">
    @php
        $baslikYazdir = $baslik.($secilenIlceAd ? ' — '.$secilenIlceAd : '');
    @endphp
    <table class="sr-tablo">
        <thead>
            <tr>
                <th colspan="20" class="sr-tablo-baslik">{{ $baslikYazdir }}</th>
            </tr>
            <tr>
                <th colspan="19" class="sr-tablo-altbaslik">ÜLKESİ / İLİ : TÜRKİYE / {{ $secilenIlceAd ? mb_strtoupper($secilenIlceAd, 'UTF-8') : 'ANKARA' }}</th>
                <th class="sr-tablo-ek">{{ $ek }}</th>
            </tr>
            <tr class="sr-bosluk-satir"><th colspan="20"></th></tr>

            <tr>
                <th rowspan="4">Sıra No</th>
                <th rowspan="4">Takbis Zemin No</th>
                <th rowspan="4">Taşınmaz No</th>
                <th rowspan="4">İlçesi</th>
                <th rowspan="4">Mahallesi/Köyü</th>
                <th rowspan="4">Ada No</th>
                <th rowspan="4">Parsel No</th>
                <th rowspan="4">Blok No</th>
                <th rowspan="4">B.Bölüm No</th>
                <th rowspan="2">Cilt No</th>
                <th rowspan="4">Yüzölçümü</th>
                <th rowspan="4">Pay Oranı</th>
                <th rowspan="4">Cinsi</th>
                <th rowspan="4">Mevcut Kullanım Şekli</th>
                <th colspan="2" rowspan="2">Edinme</th>
                <th>Maliyet Bedeli</th>
                <th colspan="2" rowspan="2">Kayıtlardan Çıkış</th>
                <th rowspan="4">Açıklamalar</th>
            </tr>
            <tr><th>Rayiç Bedeli</th></tr>
            <tr>
                <th>Sayfa No</th>
                <th rowspan="2">Şekli</th>
                <th rowspan="2">Tarihi</th>
                <th>İz Bedeli</th>
                <th rowspan="2">Nedeni</th>
                <th rowspan="2">Tarihi</th>
            </tr>
            <tr>
                <th>Sıra No</th>
                <th>Emlak V.D.</th>
            </tr>
        </thead>
        <tbody>
            @php $sira = ($tasinmazlar->currentPage() - 1) * $tasinmazlar->perPage() + 1; @endphp
            @forelse ($data as $tasinmazId => $satirlar)
                @foreach ($satirlar as $s)
                    <tr>
                        <td rowspan="4">{{ $sira++ }}</td>
                        <td rowspan="4">{{ $s['Takbis Zemin No'] }}</td>
                        <td rowspan="4">{{ $s['Taşınmaz No'] }}</td>
                        <td rowspan="4">{{ $s['İlçesi'] }}</td>
                        <td rowspan="4">{{ $s['Mahallesi/Köyü'] }}</td>
                        <td rowspan="4">{{ $s['Ada No'] }}</td>
                        <td rowspan="4">{{ $s['Parsel No'] }}</td>
                        <td rowspan="4">{{ $s['Blok No'] }}</td>
                        <td rowspan="4">{{ $s['BB No'] }}</td>
                        <td rowspan="2">{{ $s['Cilt No'] }}</td>
                        <td rowspan="4">{{ $s['Alan'] }}</td>
                        <td rowspan="4">{{ $s['Sadeleşmiş Hisse'] }}</td>
                        <td rowspan="4">{{ $s['Cinsi'] }}</td>
                        <td rowspan="4">{{ $s['Mevcut'] }}</td>
                        <td rowspan="4">{{ $s['Edinme Şekli'] }}</td>
                        <td rowspan="4">{{ $s['Edinme Tarihi'] }}</td>
                        <td>{{ $s['Maliyet Bedeli'] }}</td>
                        <td rowspan="4">{{ $s['Kayıttan Çıkış Sebebi'] }}</td>
                        <td rowspan="4">{{ $s['Kayıttan Çıkış Tarihi'] }}</td>
                        <td rowspan="4"></td>
                    </tr>
                    <tr>
                        <td>{{ $s['Rayiç Bedel'] }}</td>
                    </tr>
                    <tr>
                        <td>{{ $s['Sayfa No'] }}</td>
                        <td>{{ $s['İz Bedeli'] }}</td>
                    </tr>
                    <tr>
                        <td>{{ $s['Hisse No'] }}</td>
                        <td>{{ $s['Emlak Vergi'] }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="20" class="sr-bos">Bu kritere uyan kayıt yok.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="sr-sayfalama">
    {{ $tasinmazlar->links() }}
</div>

<script>
document.getElementById('sr-yazdir-btn')?.addEventListener('click', function () {
    const alan = document.getElementById('sr-yazdir-alan');
    const w = window.open('', '', 'width=1200,height=800');
    w.document.write('<!doctype html><html><head><title>{{ addslashes($baslikYazdir) }}</title>');
    w.document.write('<style>');
    w.document.write('@page { size: A3 landscape; margin: 10mm; }');
    w.document.write('body { font-family: Arial, sans-serif; margin: 0; padding: 10px; color: #000; }');
    w.document.write('table { width: 100%; border-collapse: collapse; font-size: 9pt; }');
    w.document.write('th, td { border: 1px solid #000; padding: 3px 4px; text-align: center; vertical-align: middle; }');
    w.document.write('thead th { background: #eef1f5; font-weight: 700; }');
    w.document.write('.sr-tablo-baslik { font-size: 13pt; font-weight: 800; padding: 8px; }');
    w.document.write('.sr-tablo-altbaslik { font-size: 10pt; font-weight: 700; text-align: left; padding: 4px 8px; }');
    w.document.write('.sr-tablo-ek { font-weight: 700; }');
    w.document.write('.sr-bosluk-satir th { border: 0; height: 6px; }');
    w.document.write('</style></head><body>');
    w.document.write(alan.innerHTML);
    w.document.write('</body></html>');
    w.document.close();
    setTimeout(() => { w.print(); w.close(); }, 100);
});
</script>
