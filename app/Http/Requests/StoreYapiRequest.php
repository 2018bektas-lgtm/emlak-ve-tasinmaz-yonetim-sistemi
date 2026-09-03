<?php

namespace App\Http\Requests;

use App\Enums\SatisDurumu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bir taşınmaza tek yapı (BBN — daire/dükkan/…) ekleme veya güncelleme
 * için validation. Modal-based AJAX endpoint tarafından kullanılır.
 */
class StoreYapiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'sira' => ['nullable', 'integer', 'min:0', 'max:9999'],

            'blok_no' => ['nullable', 'string', 'max:20'],
            'kat_no' => ['nullable', 'string', 'max:10'],
            'bagimsiz_bolum_no' => ['nullable', 'string', 'max:20'],
            'nitelik' => ['nullable', 'string', 'max:50'],
            'brut_alan' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'net_alan' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'oda_sayisi' => ['nullable', 'string', 'max:10'],
            'cephe' => ['nullable', 'string', 'max:50'],

            'muhasebe_kayit_id' => ['nullable', 'integer', 'exists:muhasebe_kayitlari,id'],
            'kayit_turu_id' => ['nullable', 'integer', 'exists:kayit_turleri,id'],
            'mevcut_kullanim_sekli' => ['nullable', 'string', 'max:150'],

            'isgal_durumu' => ['nullable', 'in:yok,var,kismi'],
            'satis_durumu' => ['required', Rule::enum(SatisDurumu::class)],
            'meclis_satis_karari_var' => ['sometimes', 'boolean'],
            'tahsis_var' => ['sometimes', 'boolean'],
            'ust_hakki_var' => ['sometimes', 'boolean'],
            'kira_var' => ['sometimes', 'boolean'],

            'aciklama' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $veri = $this->all();
        foreach (['brut_alan', 'net_alan'] as $k) {
            if (array_key_exists($k, $veri)) {
                $veri[$k] = $this->trNumericCevir($veri[$k]);
            }
        }
        foreach (['meclis_satis_karari_var', 'tahsis_var', 'ust_hakki_var', 'kira_var'] as $k) {
            $veri[$k] = filter_var($veri[$k] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        if (empty($veri['satis_durumu'])) {
            $veri['satis_durumu'] = SatisDurumu::Envanterde->value;
        }
        foreach ($veri as $k => $v) {
            if ($v === '') {
                $veri[$k] = null;
            }
        }
        $this->merge($veri);
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
