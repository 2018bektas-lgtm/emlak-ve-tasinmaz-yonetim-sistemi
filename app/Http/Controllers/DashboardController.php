<?php

namespace App\Http\Controllers;

use App\Enums\HisseBasvuruDurumu;
use App\Models\EcrimisilIsgalci;
use App\Models\HisseBasvuru;
use App\Models\Lojman;
use App\Models\LojmanBasvuru;
use App\Models\LojmanTahsis;
use App\Models\Tasinmaz;
use App\Models\TasinmazHisse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Özet: her modülün sayaçları, bekleyen işler ve son kayıtlar. Yalnızca
 * kullanıcının görüntüleme izni olan modüller hesaplanır ve gösterilir.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $k = auth()->user();
        $ozet = [];
        $sonKayitlar = collect();

        if ($k->izinVarMi('tasinmaz.goruntule')) {
            $ozet['tasinmaz'] = [
                'toplam' => Tasinmaz::count(),
                'hisseli' => TasinmazHisse::where('hisse_durum', 'aktif')->distinct()->count('tasinmaz_id'),
                'bu_ay' => Tasinmaz::where('created_at', '>=', now()->startOfMonth())->count(),
            ];
            $sonKayitlar = $sonKayitlar->merge(
                Tasinmaz::with('mahalle:id,ad')->latest('id')->limit(5)->get()->map(fn ($t) => [
                    'tur' => 'tasinmaz', 'etiket' => 'Taşınmaz',
                    'baslik' => trim(($t->mahalle?->ad ?? '').' '.$t->ada.'/'.$t->parsel),
                    'alt' => $t->nitelik ?: 'Taşınmaz kaydı',
                    'tarih' => $t->created_at, 'url' => $t->rota('detay'),
                ])
            );
        }

        if ($k->izinVarMi('hisse-satisi.goruntule')) {
            $acik = HisseBasvuru::whereNotIn('durum', [HisseBasvuruDurumu::Tamamlandi->value, HisseBasvuruDurumu::Reddedildi->value]);
            $asamalar = (clone $acik)->selectRaw('durum, count(distinct grup_no) as c')->groupBy('durum')->pluck('c', 'durum');
            $ozet['hisse'] = [
                'islemde' => (clone $acik)->distinct('grup_no')->count('grup_no'),
                'tamamlanan' => HisseBasvuru::where('durum', HisseBasvuruDurumu::Tamamlandi->value)->distinct('grup_no')->count('grup_no'),
                'bu_ay' => HisseBasvuru::where('basvuru_tarihi', '>=', now()->startOfMonth()->toDateString())->distinct('grup_no')->count('grup_no'),
                'asamalar' => collect(HisseBasvuruDurumu::siralanmis())
                    ->reject(fn ($d) => in_array($d, [HisseBasvuruDurumu::Tamamlandi, HisseBasvuruDurumu::Reddedildi], true))
                    ->map(fn ($d) => ['ad' => $d->etiket(), 'sayi' => (int) ($asamalar[$d->value] ?? 0), 'durum' => $d->value])
                    ->values(),
            ];
            $sonKayitlar = $sonKayitlar->merge(
                HisseBasvuru::latest('id')->limit(5)->get()->map(fn ($b) => [
                    'tur' => 'hisse', 'etiket' => 'Hisse Satışı',
                    'baslik' => $b->ad_soyad,
                    'alt' => $b->durum->etiket(),
                    'tarih' => $b->created_at, 'url' => route('panel.hisse-satisi.detay', $b->grup_no),
                ])
            );
        }

        if ($k->izinVarMi('ecrimisil.goruntule')) {
            $ozet['ecrimisil'] = [
                'toplam' => EcrimisilIsgalci::count(),
                'devam' => EcrimisilIsgalci::query()->durumu('devam-eden')->count(),
                'tutanaksiz' => EcrimisilIsgalci::query()->durumu('yeni')->count(),
            ];
            $sonKayitlar = $sonKayitlar->merge(
                EcrimisilIsgalci::latest('id')->limit(5)->get()->map(fn ($e) => [
                    'tur' => 'ecrimisil', 'etiket' => 'Ecrimisil',
                    'baslik' => $e->ad_soyad_unvan,
                    'alt' => $e->nitelik ?: 'İşgal kaydı',
                    'tarih' => $e->created_at, 'url' => route('panel.ecrimisil.detay', $e->id),
                ])
            );
        }

        if ($k->izinVarMi('lojman.goruntule')) {
            $durumlar = Lojman::selectRaw('durum, count(*) as c')->groupBy('durum')->pluck('c', 'durum');
            $ozet['lojman'] = [
                'toplam' => (int) $durumlar->sum(),
                'durumlar' => collect(Lojman::DURUMLAR)->map(fn ($ad, $kod) => ['ad' => $ad, 'sayi' => (int) ($durumlar[$kod] ?? 0)]),
                'acik_basvuru' => LojmanBasvuru::whereIn('durum', LojmanBasvuru::ACIK_DURUMLAR)->count(),
                'biten_tahsis' => LojmanTahsis::with('lojman:id,ad,blok,daire_no')
                    ->whereNotNull('bitis_tarihi')
                    ->whereBetween('bitis_tarihi', [now()->toDateString(), now()->addDays(60)->toDateString()])
                    ->orderBy('bitis_tarihi')->limit(5)->get(),
            ];
            $sonKayitlar = $sonKayitlar->merge(
                LojmanBasvuru::latest('id')->limit(5)->get()->map(fn ($b) => [
                    'tur' => 'lojman', 'etiket' => 'Lojman',
                    'baslik' => $b->ad_soyad,
                    'alt' => 'Başvuru · '.(LojmanBasvuru::DURUMLAR[$b->durum] ?? $b->durum),
                    'tarih' => $b->created_at, 'url' => route('panel.lojman.basvuru-detay', $b->id),
                ])
            );
        }

        return view('panel.dashboard', [
            'ozet' => $ozet,
            'sonKayitlar' => $this->sirala($sonKayitlar),
        ]);
    }

    /** Modüllerin son kayıtlarını tarihe göre birleştirir. */
    private function sirala(Collection $kayitlar): Collection
    {
        return $kayitlar->filter(fn ($s) => $s['tarih'])->sortByDesc('tarih')->take(8)->values();
    }
}
