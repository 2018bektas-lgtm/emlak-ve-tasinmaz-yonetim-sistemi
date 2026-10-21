<?php

namespace App\Http\Controllers;

use App\Enums\KiraBelgeTuru;
use App\Models\KiraAlan;
use App\Models\KiraBelge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Kira belgeleri — dosya ve alan düzeyinde yükleme.
 *
 * Referans sistemde belgeler alan düzeyinde tutuluyor (bir dosyanın üç
 * büfesinin sözleşmesi ayrı); biz ikisini de destekliyoruz: kira_alan_id boşsa
 * belge dosyanın genel belgesi olur.
 */
class KiraBelgeController extends Controller
{
    private const KLASOR = 'kira/belgeler';

    public function yukle(Request $request): RedirectResponse
    {
        $veri = $request->validate([
            'kira_dosya_id' => ['required', 'exists:kira_dosyalari,id'],
            'kira_alan_id' => ['nullable', 'exists:kira_alanlari,id'],
            'tur' => ['required', Rule::enum(KiraBelgeTuru::class)],
            'ad' => ['nullable', 'string', 'max:250'],
            'aciklama' => ['nullable', 'string', 'max:1000'],
            'dosya' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ], [], [
            'dosya' => 'belge dosyası',
            'tur' => 'belge türü',
        ]);

        // Alan verildiyse gerçekten o dosyaya ait olmalı
        if (! empty($veri['kira_alan_id'])) {
            $alan = KiraAlan::findOrFail($veri['kira_alan_id']);
            if ($alan->kira_dosya_id !== (int) $veri['kira_dosya_id']) {
                return back()->with('hata', 'Belge, seçilen alanın dosyasıyla uyuşmuyor.');
            }
        }

        $yuklenen = $request->file('dosya');
        $yol = $yuklenen->store(self::KLASOR.'/'.now()->format('Y/m'), 'public');

        KiraBelge::create([
            'kira_dosya_id' => $veri['kira_dosya_id'],
            'kira_alan_id' => $veri['kira_alan_id'] ?? null,
            'tur' => $veri['tur'],
            // 'ad' boş gönderilince doğrulanmış veride anahtar hiç olmuyor
            'ad' => ($veri['ad'] ?? null) ?: pathinfo($yuklenen->getClientOriginalName(), PATHINFO_FILENAME),
            'dosya_yolu' => $yol,
            'boyut' => $yuklenen->getSize(),
            'aciklama' => $veri['aciklama'] ?? null,
            'yukleyen_id' => $request->user()?->id,
        ]);

        return back()->with('basari', 'Belge yüklendi.');
    }

    public function sil(int $id): RedirectResponse
    {
        $belge = KiraBelge::findOrFail($id);
        $yol = $belge->dosya_yolu;
        $belge->delete();

        // Aynı fiziksel dosya başka kayıtta kullanılmıyorsa diskten de kaldır
        if ($yol && ! KiraBelge::where('dosya_yolu', $yol)->exists()) {
            Storage::disk('public')->delete($yol);
        }

        return back()->with('basari', 'Belge silindi.');
    }
}
