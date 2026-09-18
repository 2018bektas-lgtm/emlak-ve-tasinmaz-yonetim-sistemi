<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Taşınmaz Listesi · {{ $olusturma->format('d.m.Y H:i') }}</title>
    <style>
        @page { size: A3 landscape; margin: 8mm; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 6mm; color: #0e1726; font-size: 9pt; }
        .baslik {
            background: #0e1726;
            color: #d8bc8c;
            padding: 10px 14px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .baslik h1 { margin: 0; font-size: 13pt; }
        .baslik span { font-size: 8.5pt; font-weight: 500; opacity: 0.85; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; vertical-align: middle; word-break: break-word; }
        thead th { background: #eef1f5; font-weight: 700; text-align: center; font-size: 8pt; }
        td { font-size: 7.5pt; }
        tbody tr:nth-child(even) { background: #fafbfc; }
        .num { text-align: center; font-family: 'Courier New', monospace; }
        .yazdir-btn {
            position: fixed;
            top: 10px; right: 10px;
            padding: 8px 14px;
            background: #0e1726;
            color: #d8bc8c;
            border: 0;
            border-radius: 6px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(14, 23, 38, 0.3);
            z-index: 100;
        }
        .yazdir-btn:hover { background: #16223c; }
        @media print { .yazdir-btn { display: none; } }
    </style>
</head>
<body>
    <button class="yazdir-btn" onclick="window.print()">🖨 Yazdır / PDF olarak kaydet</button>

    <div class="baslik">
        <h1>Taşınmaz Listesi</h1>
        <span>{{ $olusturma->format('d.m.Y H:i') }} · {{ $tasinmazlar->count() }} kayıt · {{ count($basliklar) }} kolon</span>
    </div>

    @if (empty($basliklar))
        <p style="text-align:center; padding:40px; color:#97a0af; font-style:italic;">
            Görünecek kolon seçili değil. Liste sayfasından kolonları seçip tekrar deneyin.
        </p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width:32px">#</th>
                    @foreach ($basliklar as $anahtar => $etiket)
                        <th>{{ $etiket }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($tasinmazlar as $i => $t)
                    <tr>
                        <td class="num">{{ $i + 1 }}</td>
                        @foreach ($basliklar as $anahtar => $etiket)
                            <td>{{ $kolonUret($t, $anahtar) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($basliklar) + 1 }}" style="text-align:center; padding:20px; color:#97a0af; font-style:italic">
                            Kayıt bulunamadı.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif
</body>
</html>
