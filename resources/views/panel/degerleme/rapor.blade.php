@php
    $tl = fn ($n) => number_format((float) $n, 2, ',', '.');
    $alan = $kayit->degerlenenAlan();
    $imar = $tasinmaz->imar;
    $bb = $kayit->tip === 'bb';
    $baslik = $bb ? 'BAĞIMSIZ BÖLÜM DEĞERLEME RAPORU' : 'ARSA DEĞERLEME RAPORU';
    $satirlar = fn (array $a) => collect($a)->reject(fn ($v) => $v === null || $v === '');
@endphp
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $baslik }} · {{ $kayit->rapor_no ?: '#'.$kayit->id }}</title>
    <style>
        @page { size: A4 portrait; margin: 16mm 15mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Times New Roman', Times, serif; margin: 0; padding: 10mm; color: #111827; font-size: 11.5pt; background: #f1f5f9; }
        .sayfa { max-width: 190mm; margin: 0 auto; background: #fff; padding: 14mm 14mm 16mm; box-shadow: 0 6px 30px rgba(15, 23, 42, 0.12); }
        header { text-align: center; border-bottom: 2px solid #0f766e; padding-bottom: 10px; margin-bottom: 18px; }
        header .kurum { font-size: 10pt; letter-spacing: 0.08em; color: #475569; text-transform: uppercase; }
        header h1 { margin: 6px 0 4px; font-size: 16pt; color: #0f766e; letter-spacing: 0.02em; }
        header .ust { font-size: 10pt; color: #64748b; }
        h2 { font-size: 12pt; color: #0f766e; margin: 18px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 9px; text-align: left; vertical-align: top; }
        th { width: 38%; background: #f0fdfa; font-weight: 700; color: #334155; }
        .sonuc { margin-top: 18px; border: 2px solid #0f766e; padding: 12px 14px; display: flex; justify-content: space-between; align-items: baseline; }
        .sonuc span { font-weight: 700; color: #334155; }
        .sonuc strong { font-size: 16pt; color: #0f766e; font-family: Arial, sans-serif; }
        .yazi { font-size: 10.5pt; color: #334155; margin-top: 4px; }
        .imza { margin-top: 36px; display: flex; justify-content: flex-end; }
        .imza div { width: 60mm; text-align: center; border-top: 1px solid #94a3b8; padding-top: 6px; font-size: 10.5pt; }
        .alt { margin-top: 26px; font-size: 8.5pt; color: #94a3b8; text-align: center; }
        .yazdir-btn { position: fixed; top: 12px; right: 12px; padding: 9px 16px; background: #0f766e; color: #fff; border: 0; border-radius: 8px; font: 700 10pt Arial, sans-serif; cursor: pointer; box-shadow: 0 4px 12px rgba(15, 118, 110, 0.35); }
        @media print {
            body { background: #fff; padding: 0; }
            .sayfa { box-shadow: none; padding: 0; max-width: none; }
            .yazdir-btn { display: none; }
        }
    </style>
</head>
<body>
<button type="button" class="yazdir-btn" onclick="window.print()">Yazdır / PDF</button>
<div class="sayfa">
    <header>
        <div class="kurum">{{ config('app.name') }} · Değerleme</div>
        <h1>{{ $baslik }}</h1>
        <div class="ust">Rapor No: <strong>{{ $kayit->rapor_no ?: '—' }}</strong> &nbsp;|&nbsp; Tarih: <strong>{{ $kayit->rapor_tarihi?->format('d.m.Y') ?? '—' }}</strong></div>
    </header>

    <h2>1. Konum Bilgileri</h2>
    <table>
        @foreach ($satirlar(['İl' => $tasinmaz->il?->ad, 'İlçe' => $tasinmaz->ilce?->ad, 'Mahalle' => $tasinmaz->mahalle?->ad, 'Ada' => $tasinmaz->ada, 'Parsel' => $tasinmaz->parsel, 'Taşınmaz No' => $tasinmaz->id]) as $k => $v)
            <tr><th>{{ $k }}</th><td>{{ $v }}</td></tr>
        @endforeach
    </table>

    <h2>2. Taşınmaz Bilgileri</h2>
    <table>
        @foreach ($satirlar([
            'Kayıt Tipi' => $tasinmaz->kayit_tipi?->etiket(),
            'Nitelik' => $tasinmaz->nitelik,
            'Parsel Alanı' => $tasinmaz->alan !== null ? $tl($tasinmaz->alan).' m²' : null,
            'İmar Durumu' => $imar?->imarDurumu?->ad,
            'Emsal' => $imar?->emsal !== null ? $tl($imar->emsal) : null,
            'Yençok / Yenaz' => $imar?->yenaz_yencok,
            'Tapu (Cilt / Sayfa)' => $tasinmaz->tapu ? trim(($tasinmaz->tapu->cilt_no ?? '').' / '.($tasinmaz->tapu->sayfa_no ?? ''), ' /') : null,
        ]) as $k => $v)
            <tr><th>{{ $k }}</th><td>{{ $v }}</td></tr>
        @endforeach
    </table>

    @if ($bb)
        <h2>3. Bağımsız Bölüm Bilgileri</h2>
        <table>
            @foreach ($satirlar(['BB No' => $kayit->bb_no, 'Kat' => $kayit->kat, 'Nitelik' => $kayit->bb_nitelik, 'Oda Sayısı' => $kayit->oda_sayisi, 'Brüt Alan' => $kayit->brut_alan !== null ? $tl($kayit->brut_alan).' m²' : null]) as $k => $v)
                <tr><th>{{ $k }}</th><td>{{ $v }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>{{ $bb ? 4 : 3 }}. Değerleme</h2>
    <table>
        @foreach ($satirlar([
            'Değerlenen Alan' => $alan !== null ? $tl($alan).' m²' : null,
            'Birim Değer' => $kayit->birim_fiyat !== null ? $tl($kayit->birim_fiyat).' TL/m²' : null,
            'Rapor Tarihi' => $kayit->rapor_tarihi?->format('d.m.Y'),
        ]) as $k => $v)
            <tr><th>{{ $k }}</th><td>{{ $v }}</td></tr>
        @endforeach
    </table>
    <div class="sonuc"><span>Takdir Edilen Toplam Değer</span><strong>{{ $tl($kayit->toplam_bedel) }} TL</strong></div>

    @if ($kayit->aciklama)
        <h2>Açıklama</h2>
        <p class="yazi">{!! nl2br(e($kayit->aciklama)) !!}</p>
    @endif

    <div class="imza">
        <div>{{ $kayit->hazirlayan ? $kayit->hazirlayan->ad.' '.$kayit->hazirlayan->soyad : '' }}<br><small>Hazırlayan</small></div>
    </div>
    <div class="alt">Değerleme #{{ $kayit->id }} · {{ now()->format('d.m.Y H:i') }} tarihinde sistemden alınmıştır.</div>
</div>
</body>
</html>
