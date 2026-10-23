<?php

namespace App\Http\Controllers;

use App\Models\Kullanici;
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
        $durum = in_array($request->query('durum'), ['aktif', 'pasif'], true) ? $request->query('durum') : null;

        $kullanicilar = Kullanici::query()
            ->with(['rol:id,kod,ad'])
            ->when($q !== '', function ($s) use ($q) {
                $s->where(function ($w) use ($q) {
                    $w->whereLike('ad', "%{$q}%")
                        ->orWhereLike('soyad', "%{$q}%")
                        ->orWhereLike('mail', "%{$q}%")
                        ->orWhereLike('kullanici_adi', "%{$q}%");
                });
            })
            ->when($rolId, fn ($s) => $s->where('rol_id', $rolId))
            ->when($durum, fn ($s) => $s->where('aktif_mi', $durum === 'aktif'))
            ->orderBy('ad')
            ->orderBy('soyad')
            ->paginate(20)
            ->withQueryString();

        return view('panel.kullanicilar.index', [
            'kullanicilar' => $kullanicilar,
            'roller' => Rol::query()->withCount('kullanicilar')->orderBy('sira')->get(['id', 'kod', 'ad']),
            'sayac' => [
                'toplam' => Kullanici::query()->count(),
                'aktif' => Kullanici::query()->where('aktif_mi', true)->count(),
                'pasif' => Kullanici::query()->where('aktif_mi', false)->count(),
                'rolsuz' => Kullanici::query()->whereNull('rol_id')->count(),
            ],
            'filtre' => [
                'q' => $q,
                'rol_id' => $rolId,
                'durum' => $durum,
            ],
        ]);
    }

    public function create(): View
    {
        return view('panel.kullanicilar.form', [
            'kullanici' => new Kullanici(['aktif_mi' => true]),
            'roller' => $this->rolSecenekleri(),
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
        $this->adminHesapKorumasi($kullanici);

        return view('panel.kullanicilar.form', [
            'kullanici' => $kullanici,
            'roller' => $this->rolSecenekleri(),
        ]);
    }

    public function update(Request $request, Kullanici $kullanici): RedirectResponse
    {
        $this->adminHesapKorumasi($kullanici);
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
        // Rol aynı kalsa da son aktif admin pasifleştirilemez
        if (array_key_exists('aktif_mi', $veri) && ! $veri['aktif_mi'] && $kullanici->aktif_mi) {
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
        $this->adminHesapKorumasi($kullanici);

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

        return $veri;
    }

    /** Rol seçim kartları: açıklama ve izin sayısıyla. */
    private function rolSecenekleri()
    {
        return Rol::query()->withCount('izinler')->orderBy('sira')->get(['id', 'kod', 'ad', 'aciklama', 'sistem_mi']);
    }

    /**
     * Admin hesabını yalnız admin düzenleyebilir/silebilir. Önceden admin
     * rolünü atamak engelliydi ama kullanıcı yönetimi izni olan biri admin
     * hesabının şifresini değiştirip onu pasifleştirebiliyordu.
     */
    private function adminHesapKorumasi(Kullanici $kullanici): void
    {
        if ($kullanici->adminMi() && ! auth()->user()?->adminMi()) {
            abort(403, 'Admin hesabını yalnızca admin düzenleyebilir.');
        }
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
