<?php

namespace App\Http\Controllers;

use App\Models\Mudurluk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MudurlukController extends Controller
{
    public function index(): View
    {
        $mudurlukler = Mudurluk::query()
            ->withCount(['kullanicilar', 'tasinmazlar'])
            ->orderBy('sira')
            ->orderBy('ad')
            ->get();

        return view('panel.mudurlukler.index', compact('mudurlukler'));
    }

    public function create(): View
    {
        return view('panel.mudurlukler.form', [
            'mudurluk' => new Mudurluk([
                'aktif_mi' => true,
                'sira' => (int) Mudurluk::query()->max('sira') + 1,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Mudurluk::query()->create($this->dogrula($request));

        return redirect()
            ->route('panel.mudurlukler.index')
            ->with('basari', 'Müdürlük oluşturuldu.');
    }

    public function edit(Mudurluk $mudurluk): View
    {
        return view('panel.mudurlukler.form', compact('mudurluk'));
    }

    public function update(Request $request, Mudurluk $mudurluk): RedirectResponse
    {
        $mudurluk->update($this->dogrula($request, $mudurluk));

        return redirect()
            ->route('panel.mudurlukler.index')
            ->with('basari', $mudurluk->ad.' güncellendi.');
    }

    public function destroy(Mudurluk $mudurluk): RedirectResponse
    {
        if ($mudurluk->kullanicilar()->exists() || $mudurluk->tasinmazlar()->exists()) {
            return back()->with('hata', 'Bu müdürlüğe bağlı kullanıcı veya taşınmaz var. Silinemez.');
        }

        $ad = $mudurluk->ad;
        $mudurluk->delete();

        return redirect()
            ->route('panel.mudurlukler.index')
            ->with('basari', $ad.' silindi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function dogrula(Request $request, ?Mudurluk $mudurluk = null): array
    {
        $veri = $request->validate([
            'kod' => [
                'required',
                'string',
                'max:40',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('mudurlukler', 'kod')->ignore($mudurluk),
            ],
            'ad' => ['required', 'string', 'max:150'],
            'aciklama' => ['nullable', 'string', 'max:2000'],
            'sira' => ['nullable', 'integer', 'min:0'],
            'aktif_mi' => ['sometimes', 'boolean'],
        ], [
            'kod.required' => 'Müdürlük kodu zorunludur.',
            'kod.regex' => 'Kod yalnızca küçük harf, rakam, tire ve alt çizgi içerebilir.',
            'kod.unique' => 'Bu kod zaten kullanılıyor.',
            'ad.required' => 'Müdürlük adı zorunludur.',
        ]);

        $veri['aktif_mi'] = $request->boolean('aktif_mi');

        return $veri;
    }
}
