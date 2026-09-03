<?php

namespace App\Http\Controllers;

use App\Models\Kullanici;
use App\Models\Mudurluk;
use App\Models\Rol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KullaniciController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $rolId = $request->query('rol_id');
        $mudurlukId = $request->query('mudurluk_id');

        $kullanicilar = Kullanici::query()
            ->with(['rol:id,kod,ad', 'mudurluk:id,ad'])
            ->when($q !== '', function ($s) use ($q) {
                $s->where(function ($w) use ($q) {
                    $w->where('ad', 'like', "%{$q}%")
                        ->orWhere('soyad', 'like', "%{$q}%")
                        ->orWhere('mail', 'like', "%{$q}%")
                        ->orWhere('kullanici_adi', 'like', "%{$q}%");
                });
            })
            ->when($rolId, fn ($s) => $s->where('rol_id', $rolId))
            ->when($mudurlukId, fn ($s) => $s->where('mudurluk_id', $mudurlukId))
            ->orderBy('ad')
            ->orderBy('soyad')
            ->paginate(20)
            ->withQueryString();

        return view('panel.kullanicilar.index', [
            'kullanicilar' => $kullanicilar,
            'roller' => Rol::query()->orderBy('sira')->get(['id', 'ad']),
            'mudurlukler' => Mudurluk::query()->orderBy('sira')->get(['id', 'ad']),
            'filtre' => [
                'q' => $q,
                'rol_id' => $rolId,
                'mudurluk_id' => $mudurlukId,
            ],
        ]);
    }

    public function create(): View
    {
        return view('panel.kullanicilar.form', [
            'kullanici' => new Kullanici(['aktif_mi' => true]),
            'roller' => Rol::query()->orderBy('sira')->get(['id', 'kod', 'ad']),
            'mudurlukler' => Mudurluk::query()->aktif()->orderBy('sira')->get(['id', 'ad']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $veri = $this->dogrula($request);
        $this->adminRoluKorumasi($veri['rol_id'] ?? null);

        Kullanici::query()->create($veri);

        return redirect()
            ->route('panel.kullanicilar.index')
            ->with('basari', 'Kullanıcı oluşturuldu.');
    }

    public function edit(Kullanici $kullanici): View
    {
        return view('panel.kullanicilar.form', [
            'kullanici' => $kullanici,
            'roller' => Rol::query()->orderBy('sira')->get(['id', 'kod', 'ad']),
            'mudurlukler' => Mudurluk::query()->orderBy('sira')->get(['id', 'ad']),
        ]);
    }

    public function update(Request $request, Kullanici $kullanici): RedirectResponse
    {
        $veri = $this->dogrula($request, $kullanici);

        if (empty($veri['sifre'])) {
            unset($veri['sifre']);
        }

        if ($kullanici->id === auth()->id()) {
            unset($veri['aktif_mi']);
        }

        if (array_key_exists('rol_id', $veri) && (int) $veri['rol_id'] !== (int) $kullanici->rol_id) {
            $this->adminRoluKorumasi($veri['rol_id']);
            $this->sonAdminKorumasi($kullanici);
        }

        $kullanici->update($veri);

        return redirect()
            ->route('panel.kullanicilar.index')
            ->with('basari', $kullanici->getAdSoyad().' güncellendi.');
    }

    public function destroy(Kullanici $kullanici): RedirectResponse
    {
        if ($kullanici->id === auth()->id()) {
            return back()->with('hata', 'Kendi hesabınızı silemezsiniz.');
        }

        $this->sonAdminKorumasi($kullanici);

        $ad = $kullanici->getAdSoyad();
        $kullanici->delete();

        return redirect()
            ->route('panel.kullanicilar.index')
            ->with('basari', $ad.' silindi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function dogrula(Request $request, ?Kullanici $kullanici = null): array
    {
        $sifreKurali = $kullanici
            ? ['nullable', 'string', 'min:8']
            : ['required', 'string', 'min:8'];

        $veri = $request->validate([
            'ad' => ['required', 'string', 'max:100'],
            'soyad' => ['required', 'string', 'max:100'],
            'mail' => ['required', 'email', 'max:150', Rule::unique('kullanicilar', 'mail')->ignore($kullanici)],
            'kullanici_adi' => ['required', 'string', 'max:60', Rule::unique('kullanicilar', 'kullanici_adi')->ignore($kullanici)],
            'sifre' => $sifreKurali,
            'rol_id' => ['required', 'integer', 'exists:roller,id'],
            'mudurluk_id' => ['nullable', 'integer', 'exists:mudurlukler,id'],
            'aktif_mi' => ['sometimes', 'boolean'],
        ], [
            'ad.required' => 'Ad zorunludur.',
            'soyad.required' => 'Soyad zorunludur.',
            'mail.required' => 'E-posta zorunludur.',
            'mail.unique' => 'Bu e-posta zaten kayıtlı.',
            'kullanici_adi.required' => 'Kullanıcı adı zorunludur.',
            'kullanici_adi.unique' => 'Bu kullanıcı adı zaten kayıtlı.',
            'sifre.required' => 'Şifre zorunludur.',
            'sifre.min' => 'Şifre en az 8 karakter olmalıdır.',
            'rol_id.required' => 'Rol seçimi zorunludur.',
        ]);

        $veri['aktif_mi'] = $request->boolean('aktif_mi');
        if (array_key_exists('mudurluk_id', $veri) && $veri['mudurluk_id'] === '') {
            $veri['mudurluk_id'] = null;
        }

        return $veri;
    }

    private function adminRoluKorumasi(mixed $rolId): void
    {
        if (auth()->user()?->adminMi()) {
            return;
        }

        $kod = Rol::query()->whereKey($rolId)->value('kod');
        if ($kod === 'admin') {
            abort(403, 'Admin rolü yalnızca admin tarafından atanabilir.');
        }
    }

    private function sonAdminKorumasi(Kullanici $kullanici): void
    {
        if (! $kullanici->adminMi()) {
            return;
        }

        $digerAdmin = Kullanici::query()
            ->where('id', '!=', $kullanici->id)
            ->where('aktif_mi', true)
            ->whereHas('rol', fn ($q) => $q->where('kod', 'admin'))
            ->exists();

        if (! $digerAdmin) {
            abort(403, 'Sistemde en az bir aktif admin kalmalıdır.');
        }
    }
}
