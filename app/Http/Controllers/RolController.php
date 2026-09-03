<?php

namespace App\Http\Controllers;

use App\Models\Izin;
use App\Models\Rol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RolController extends Controller
{
    public function index(): View
    {
        $roller = Rol::query()
            ->withCount(['izinler', 'kullanicilar'])
            ->orderBy('sira')
            ->orderBy('id')
            ->get();

        return view('panel.roller.index', compact('roller'));
    }

    public function create(): View
    {
        return view('panel.roller.form', [
            'rol' => new Rol(['sira' => (int) Rol::query()->max('sira') + 1]),
            'izinler' => Izin::query()->orderBy('grup')->orderBy('ad')->get(),
            'seciliIzinler' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $veri = $this->dogrula($request);
        $izinler = $veri['izinler'] ?? [];
        unset($veri['izinler']);
        $veri['sistem_mi'] = false;

        $rol = Rol::query()->create($veri);
        $rol->izinler()->sync($izinler);

        return redirect()
            ->route('panel.roller.index')
            ->with('basari', $rol->ad.' rolü oluşturuldu.');
    }

    public function edit(Rol $rol): View
    {
        return view('panel.roller.form', [
            'rol' => $rol,
            'izinler' => Izin::query()->orderBy('grup')->orderBy('ad')->get(),
            'seciliIzinler' => $rol->izinler()->pluck('izinler.id'),
        ]);
    }

    public function update(Request $request, Rol $rol): RedirectResponse
    {
        $veri = $this->dogrula($request, $rol);
        $izinler = $veri['izinler'] ?? [];
        unset($veri['izinler']);

        if ($rol->sistem_mi) {
            unset($veri['kod'], $veri['sistem_mi']);
        } else {
            $veri['sistem_mi'] = false;
        }

        $rol->update($veri);

        if ($rol->kod !== 'admin') {
            $rol->izinler()->sync($izinler);
        }

        return redirect()
            ->route('panel.roller.index')
            ->with('basari', $rol->ad.' rolü güncellendi.');
    }

    public function destroy(Rol $rol): RedirectResponse
    {
        if ($rol->sistem_mi) {
            return back()->with('hata', 'Sistem rolleri silinemez.');
        }

        if ($rol->kullanicilar()->exists()) {
            return back()->with('hata', 'Bu role atanmış kullanıcılar var. Önce kullanıcıların rolünü değiştirin.');
        }

        $ad = $rol->ad;
        $rol->delete();

        return redirect()
            ->route('panel.roller.index')
            ->with('basari', $ad.' rolü silindi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function dogrula(Request $request, ?Rol $rol = null): array
    {
        return $request->validate([
            'kod' => [
                'required',
                'string',
                'max:40',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('roller', 'kod')->ignore($rol),
            ],
            'ad' => ['required', 'string', 'max:100'],
            'aciklama' => ['nullable', 'string', 'max:2000'],
            'sira' => ['nullable', 'integer', 'min:0'],
            'izinler' => ['nullable', 'array'],
            'izinler.*' => ['integer', 'exists:izinler,id'],
        ], [
            'kod.required' => 'Rol kodu zorunludur.',
            'kod.regex' => 'Rol kodu yalnızca küçük harf, rakam, tire ve alt çizgi içerebilir.',
            'kod.unique' => 'Bu rol kodu zaten kullanılıyor.',
            'ad.required' => 'Rol adı zorunludur.',
        ]);
    }
}
