<?php

namespace App\Http\Requests;

use App\Enums\SatisDurumu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTasinmazRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // Lokasyon
            'mahalle_id' => ['required', 'integer', 'exists:mahalleler,id'],
            'mudurluk_id' => ['nullable', 'integer', 'exists:mudurlukler,id'],
            'ada' => ['required', 'string', 'max:20'],
            'parsel' => ['required', 'string', 'max:20'],

            // Fiziksel
            'alan' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'nitelik' => ['required', 'string', 'max:100'],

            // Imar
            'imar_durumu_id' => ['required', 'integer', 'exists:imar_durumlari,id'],
            'emsal' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'yenaz_yencok' => ['required', 'string', 'max:50'],
            'imar_notu' => ['nullable', 'string', 'max:5000'],

            'uzeri_bina_var_mi' => ['sometimes', 'boolean'],
            'kayit_tipi' => ['required', 'in:bos_parsel,kat_mulkiyetli,kat_mulkiyetsiz_bina'],

            // Kategori (arsa seviyesi)
            'kategori' => ['nullable', 'array'],
            'kategori.muhasebe_kayit_id' => ['required', 'integer', 'exists:muhasebe_kayitlari,id'],
            'kategori.kayit_turu_id' => ['required', 'integer', 'exists:kayit_turleri,id'],
            'kategori.mevcut_kullanim_sekli' => ['required', 'string', 'max:150'],

            // Ekbilgi (arsa seviyesi)
            'ekbilgi' => ['nullable', 'array'],
            'ekbilgi.isgal_durumu' => ['required', 'in:yok,var,kismi'],
            'ekbilgi.satis_durumu' => ['required', Rule::enum(SatisDurumu::class)],
            'ekbilgi.meclis_satis_karari_var' => ['sometimes', 'boolean'],
            'ekbilgi.tahsis_var' => ['sometimes', 'boolean'],
            'ekbilgi.ust_hakki_var' => ['sometimes', 'boolean'],
            'ekbilgi.kira_var' => ['sometimes', 'boolean'],
            'ekbilgi.aciklama' => ['nullable', 'string', 'max:5000'],

            // Tapu
            'tapu' => ['required', 'array'],
            'tapu.takbis_zemin_no' => ['required', 'string', 'max:30'],
            'tapu.cilt_no' => ['required', 'string', 'max:20'],
            'tapu.sayfa_no' => ['required', 'string', 'max:20'],
            'tapu.tapu_durumu' => ['required', 'in:aktif,pasif'],
            'tapu.tapu_tarihi' => ['nullable', 'date'],
            'tapu.tapu_kaydi_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],

            // Konum
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'koordinat' => ['nullable', 'json'],

            // Resimler
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'silinen_resimler' => ['nullable', 'array'],
            'silinen_resimler.*' => ['integer', 'exists:resimler,id'],

            // Meclis Satış Kararı — ekbilgi.meclis_satis_karari_var işaretliyken zorunlu.
            'meclis_karari' => ['nullable', 'array'],
            'meclis_karari.karar_no' => ['required_if_accepted:ekbilgi.meclis_satis_karari_var', 'nullable', 'string', 'max:50'],
            'meclis_karari.karar_tarihi' => ['required_if_accepted:ekbilgi.meclis_satis_karari_var', 'nullable', 'date'],
            'meclis_karari.karar_ozeti' => ['nullable', 'string', 'max:2000'],
            'meclis_karari.karar_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],

            // Yapı — tek BBN (kat_mulkiyetli tipinde)
            'yapi' => ['nullable', 'array'],
            'yapi.blok_no' => ['nullable', 'string', 'max:20'],
            'yapi.kat_no' => ['nullable', 'string', 'max:10'],
            'yapi.bagimsiz_bolum_no' => ['nullable', 'string', 'max:20'],
            'yapi.nitelik' => ['nullable', 'string', 'max:50'],
            'yapi.brut_alan' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'yapi.net_alan' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'yapi.oda_sayisi' => ['nullable', 'string', 'max:10'],
            'yapi.cephe' => ['nullable', 'string', 'max:50'],
            'yapi.aciklama' => ['nullable', 'string', 'max:500'],

            // Yapılar — çoklu BBN (kat_mulkiyetsiz_bina tipinde)
            'yapilar' => ['nullable', 'array'],
            'yapilar.*.blok_no' => ['nullable', 'string', 'max:20'],
            'yapilar.*.kat_no' => ['nullable', 'string', 'max:10'],
            'yapilar.*.bagimsiz_bolum_no' => ['nullable', 'string', 'max:20'],
            'yapilar.*.nitelik' => ['nullable', 'string', 'max:50'],
            'yapilar.*.brut_alan' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'yapilar.*.net_alan' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'yapilar.*.oda_sayisi' => ['nullable', 'string', 'max:10'],
            'yapilar.*.cephe' => ['nullable', 'string', 'max:50'],
            'yapilar.*.aciklama' => ['nullable', 'string', 'max:500'],

            // Hisseler modaldan AJAX ile yönetilir — form'a girmez.
        ];
    }

    public function messages(): array
    {
        return [
            'mahalle_id.required' => 'Mahalle seçimi zorunludur.',
            'ada.required' => 'Ada zorunludur.',
            'parsel.required' => 'Parsel zorunludur.',
            'alan.required' => 'Alan zorunludur.',
            'nitelik.required' => 'Nitelik zorunludur.',
            'imar_durumu_id.required' => 'İmar durumu zorunludur.',
            'emsal.required' => 'Emsal zorunludur.',
            'yenaz_yencok.required' => 'Yen az / Yen çok zorunludur.',
            'kategori.muhasebe_kayit_id.required' => 'Muhasebe kaydı seçimi zorunludur.',
            'kategori.kayit_turu_id.required' => 'Kayıt türü seçimi zorunludur.',
            'kategori.mevcut_kullanim_sekli.required' => 'Mevcut kullanım şekli zorunludur.',
            'ekbilgi.isgal_durumu.required' => 'İşgal durumu seçimi zorunludur.',
            'ekbilgi.satis_durumu.required' => 'Satış durumu zorunludur.',
            'tapu.required' => 'Tapu bilgileri zorunludur.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $kategori = $this->input('kategori');
        if (! is_array($kategori)) {
            $kategori = [];
        }

        $ekbilgi = $this->input('ekbilgi');
        if (! is_array($ekbilgi)) {
            $ekbilgi = [];
        }
        foreach (['meclis_satis_karari_var', 'tahsis_var', 'ust_hakki_var', 'kira_var'] as $f) {
            $ekbilgi[$f] = filter_var($ekbilgi[$f] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        if (empty($ekbilgi['satis_durumu'])) {
            $ekbilgi['satis_durumu'] = SatisDurumu::Envanterde->value;
        }

        $tapu = $this->input('tapu');
        if (! is_array($tapu)) {
            $tapu = [];
        }

        $kayitTipi = $this->input('kayit_tipi');

        // Yapı — tek BBN, sadece kat_mulkiyetli tipinde geçerli
        $yapi = $this->input('yapi');
        if ($kayitTipi === 'kat_mulkiyetli' && is_array($yapi)) {
            $yapi['brut_alan'] = $this->trNumericCevir($yapi['brut_alan'] ?? null);
            $yapi['net_alan'] = $this->trNumericCevir($yapi['net_alan'] ?? null);
            foreach ($yapi as $k => $v) {
                if ($v === '') {
                    $yapi[$k] = null;
                }
            }
            $anlamli = collect($yapi)->contains(fn ($v) => filled($v));
            $yapi = $anlamli ? $yapi : null;
        } else {
            $yapi = null;
        }

        // Yapılar — çoklu BBN
        $yapilar = $this->input('yapilar');
        if ($kayitTipi === 'kat_mulkiyetsiz_bina' && is_array($yapilar)) {
            $temiz = [];
            foreach ($yapilar as $y) {
                if (! is_array($y)) {
                    continue;
                }
                $y['brut_alan'] = $this->trNumericCevir($y['brut_alan'] ?? null);
                $y['net_alan'] = $this->trNumericCevir($y['net_alan'] ?? null);
                foreach ($y as $k => $v) {
                    if ($v === '') {
                        $y[$k] = null;
                    }
                }
                if (collect($y)->contains(fn ($v) => filled($v))) {
                    $temiz[] = $y;
                }
            }
            $yapilar = $temiz ?: null;
        } else {
            $yapilar = null;
        }

        $uzeriBinaVarMi = $kayitTipi && $kayitTipi !== 'bos_parsel';

        // Meclis kararı — checkbox işaretli değilse alanları temizle
        $meclisKarari = $this->input('meclis_karari');
        if (! is_array($meclisKarari) || ! ($ekbilgi['meclis_satis_karari_var'] ?? false)) {
            $meclisKarari = null;
        } else {
            foreach ($meclisKarari as $k => $v) {
                if ($v === '') {
                    $meclisKarari[$k] = null;
                }
            }
        }

        $this->merge([
            'uzeri_bina_var_mi' => $uzeriBinaVarMi,
            'kayit_tipi' => $kayitTipi,
            'alan' => $this->trNumericCevir($this->input('alan')),
            'emsal' => $this->trNumericCevir($this->input('emsal')),
            'kategori' => $kategori,
            'ekbilgi' => $ekbilgi,
            'tapu' => $tapu,
            'yapi' => $yapi,
            'yapilar' => $yapilar,
            'meclis_karari' => $meclisKarari,
        ]);
    }

    protected function trNumericCevir(mixed $deger): mixed
    {
        if ($deger === null || $deger === '') {
            return $deger;
        }
        if (is_int($deger) || is_float($deger)) {
            return $deger;
        }
        $s = preg_replace('/[\s\x{00A0}\x{202F}]/u', '', trim((string) $deger)) ?? trim((string) $deger);
        if (preg_match('/^(.+)[.,](\d{1,2})$/', $s, $m)) {
            $s = str_replace(['.', ','], '', $m[1]).'.'.$m[2];
        } else {
            $s = str_replace(['.', ','], '', $s);
        }

        return is_numeric($s) ? $s : $deger;
    }
}
