<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTasinmazHisseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'hisse_no' => ['nullable', 'integer', 'min:1'],
            'hisse_pay' => ['required', 'numeric', 'min:0'],
            'hisse_payda' => ['required', 'numeric', 'gt:0'],
            'edinme_sekli' => ['nullable', 'string', 'max:100'],
            'yevmiye_no' => ['nullable', 'integer', 'min:0'],
            'edinme_tarihi' => ['nullable', 'date'],
            'kayitlardan_cikis' => ['nullable', 'string', 'max:150'],
            'kayitlardan_cikistarihi' => ['nullable', 'date'],
            'hisse_durum' => ['nullable', 'in:aktif,pasif'],
            'islem_tipi' => ['nullable', 'in:alis,satis'],
            'maliyet_bedeli' => ['nullable', 'numeric', 'min:0'],
            'rayic_bedel' => ['nullable', 'numeric', 'min:0'],
            'emlak_vd' => ['nullable', 'numeric', 'min:0'],
            'iz_bedeli' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'hisse_pay.required' => 'Pay zorunludur.',
            'hisse_pay.numeric' => 'Pay sayısal olmalıdır.',
            'hisse_payda.required' => 'Payda zorunludur.',
            'hisse_payda.numeric' => 'Payda sayısal olmalıdır.',
            'hisse_payda.gt' => 'Payda 0\'dan büyük olmalıdır.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $veri = $this->all();
        foreach (['hisse_pay', 'hisse_payda', 'maliyet_bedeli', 'rayic_bedel', 'emlak_vd', 'iz_bedeli'] as $k) {
            if (array_key_exists($k, $veri)) {
                $veri[$k] = $this->trNumericCevir($veri[$k]);
            }
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
