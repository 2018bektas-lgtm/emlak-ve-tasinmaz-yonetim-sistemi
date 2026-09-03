<?php

namespace App\Http\Requests;

use App\Enums\SatisDurumu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTasinmazRequest extends FormRequest
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

            // Kategori (arsa seviyesi — hasOne)
            'kategori' => ['nullable', 'array'],
            'kategori.muhasebe_kayit_id' => ['required', 'integer', 'exists:muhasebe_kayitlari,id'],
            'kategori.kayit_turu_id' => ['required', 'integer', 'exists:kayit_turleri,id'],
            'kategori.mevcut_kullanim_sekli' => ['required', 'string', 'max:150'],

            // Ekbilgi (arsa seviyesi — hasOne)
            'ekbilgi' => ['nullable', 'array'],
            'ekbilgi.isgal_durumu' => ['required', 'in:yok,var,kismi'],
            'ekbilgi.satis_durumu' => ['required', Rule::enum(SatisDurumu::class)],
            'ekbilgi.meclis_satis_karari_var' => ['sometimes', 'boolean'],
            'ekbilgi.tahsis_var' => ['sometimes', 'boolean'],
            'ekbilgi.ust_hakki_var' => ['sometimes', 'boolean'],
            'ekbilgi.kira_var' => ['sometimes', 'boolean'],
            'ekbilgi.aciklama' => ['nullable', 'string', 'max:5000'],

            // Yapılar (BBN'ler — hasMany, opsiyonel; modaldan da AJAX ile eklenebilir)
            'yapilar' => ['nullable', 'array'],
            'yapilar.*.blok_no' => ['nullable', 'string', 'max:20'],
            'yapilar.*.kat_no' => ['nullable', 'string', 'max:10'],
            'yapilar.*.bagimsiz_bolum_no' => ['nullable', 'string', 'max:20'],
            'yapilar.*.nitelik' => ['nullable', 'string', 'max:50'],
            'yapilar.*.brut_alan' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'yapilar.*.net_alan' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'yapilar.*.oda_sayisi' => ['nullable', 'string', 'max:10'],
            'yapilar.*.cephe' => ['nullable', 'string', 'max:50'],
            'yapilar.*.muhasebe_kayit_id' => ['nullable', 'integer', 'exists:muhasebe_kayitlari,id'],
            'yapilar.*.kayit_turu_id' => ['nullable', 'integer', 'exists:kayit_turleri,id'],
            'yapilar.*.mevcut_kullanim_sekli' => ['nullable', 'string', 'max:150'],
            'yapilar.*.isgal_durumu' => ['nullable', 'in:yok,var,kismi'],
            'yapilar.*.satis_durumu' => ['nullable', Rule::enum(SatisDurumu::class)],
            'yapilar.*.meclis_satis_karari_var' => ['sometimes', 'boolean'],
            'yapilar.*.tahsis_var' => ['sometimes', 'boolean'],
            'yapilar.*.ust_hakki_var' => ['sometimes', 'boolean'],
            'yapilar.*.kira_var' => ['sometimes', 'boolean'],
            'yapilar.*.aciklama' => ['nullable', 'string', 'max:5000'],

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

            // Hisseler
            'hisseler' => ['nullable', 'array'],
            'hisseler.*.hisse_no' => ['nullable', 'integer', 'min:1'],
            'hisseler.*.hisse_pay' => ['nullable', 'numeric', 'min:0'],
            'hisseler.*.hisse_payda' => ['nullable', 'numeric', 'gt:0'],
            'hisseler.*.hisse_yuzolcum' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'hisseler.*.edinme_sekli' => ['nullable', 'string', 'max:100'],
            'hisseler.*.yevmiye_no' => ['nullable', 'integer', 'min:0'],
            'hisseler.*.edinme_tarihi' => ['nullable', 'date'],
            'hisseler.*.kayitlardan_cikis' => ['nullable', 'string', 'max:150'],
            'hisseler.*.kayitlardan_cikistarihi' => ['nullable', 'date'],
            'hisseler.*.hisse_durum' => ['nullable', 'in:aktif,pasif'],
            'hisseler.*.islem_tipi' => ['nullable', 'in:alis,satis'],
            'hisseler.*.maliyet_bedeli' => ['nullable', 'numeric', 'min:0'],
            'hisseler.*.rayic_bedel' => ['nullable', 'numeric', 'min:0'],
            'hisseler.*.emlak_vd' => ['nullable', 'numeric', 'min:0'],
            'hisseler.*.iz_bedeli' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'mahalle_id.required' => 'Mahalle seçimi zorunludur.',
            'mahalle_id.exists' => 'Seçilen mahalle geçerli değil.',
            'ada.required' => 'Ada bilgisi zorunludur.',
            'parsel.required' => 'Parsel bilgisi zorunludur.',
            'alan.required' => 'Alan (m²) zorunludur.',
            'alan.numeric' => 'Alan sayısal olmalıdır.',
            'nitelik.required' => 'Nitelik zorunludur.',
            'imar_durumu_id.required' => 'İmar durumu seçimi zorunludur.',
            'emsal.required' => 'Emsal (KAKS) zorunludur.',
            'yenaz_yencok.required' => 'Yen az / Yen çok bilgisi zorunludur.',
            'kategori.muhasebe_kayit_id.required' => 'Muhasebe kaydı seçimi zorunludur.',
            'kategori.kayit_turu_id.required' => 'Kayıt türü seçimi zorunludur.',
            'kategori.mevcut_kullanim_sekli.required' => 'Mevcut kullanım şekli zorunludur.',
            'ekbilgi.isgal_durumu.required' => 'İşgal durumu seçimi zorunludur.',
            'ekbilgi.satis_durumu.required' => 'Satış durumu zorunludur.',
            'tapu.required' => 'Tapu bilgileri zorunludur.',
            'tapu.takbis_zemin_no.required' => 'TAKBİS zemin numarası zorunludur.',
            'tapu.cilt_no.required' => 'Cilt numarası zorunludur.',
            'tapu.sayfa_no.required' => 'Sayfa numarası zorunludur.',
            'tapu.tapu_durumu.required' => 'Tapu durumu (aktif / pasif) zorunludur.',
            'lat.between' => 'Enlem -90 ile 90 arasında olmalıdır.',
            'lng.between' => 'Boylam -180 ile 180 arasında olmalıdır.',
            'tapu.tapu_kaydi_pdf.mimes' => 'Tapu kaydı PDF olmalıdır.',
            'tapu.tapu_kaydi_pdf.max' => 'Tapu PDF en fazla 10 MB olabilir.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Kategori grubu — düz alanlarsa iç grup yapısına toparla (geriye uyumluluk)
        $kategori = $this->input('kategori');
        if (! is_array($kategori)) {
            $kategori = [];
        }

        // Ekbilgi grubu — flag'leri boolean'a çevir
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

        // Yapılar
        $yapilar = $this->input('yapilar');
        if (is_array($yapilar)) {
            $temiz = [];
            foreach ($yapilar as $y) {
                if (! is_array($y)) {
                    continue;
                }
                $y['brut_alan'] = $this->trNumericCevir($y['brut_alan'] ?? null);
                $y['net_alan'] = $this->trNumericCevir($y['net_alan'] ?? null);
                foreach (['meclis_satis_karari_var', 'tahsis_var', 'ust_hakki_var', 'kira_var'] as $flag) {
                    $y[$flag] = filter_var($y[$flag] ?? false, FILTER_VALIDATE_BOOLEAN);
                }
                if (empty($y['satis_durumu'])) {
                    $y['satis_durumu'] = SatisDurumu::Envanterde->value;
                }
                foreach ($y as $k => $v) {
                    if ($v === '') {
                        $y[$k] = null;
                    }
                }
                $temiz[] = $y;
            }
            $yapilar = $temiz ?: null;
        } else {
            $yapilar = null;
        }

        $tapu = $this->input('tapu');
        if (! is_array($tapu)) {
            $tapu = [];
        }

        $hisseler = $this->input('hisseler');
        if (is_array($hisseler)) {
            $temiz = [];
            foreach ($hisseler as $h) {
                if (! is_array($h)) {
                    continue;
                }
                foreach (['hisse_pay', 'hisse_payda', 'hisse_yuzolcum', 'maliyet_bedeli', 'rayic_bedel', 'emlak_vd', 'iz_bedeli'] as $k) {
                    $h[$k] = $this->trNumericCevir($h[$k] ?? null);
                }
                if (collect($h)->contains(fn ($v) => filled($v))) {
                    $temiz[] = $h;
                }
            }
            $hisseler = $temiz ?: null;
        } else {
            $hisseler = null;
        }

        $this->merge([
            'uzeri_bina_var_mi' => $this->boolean('uzeri_bina_var_mi'),
            'alan' => $this->trNumericCevir($this->input('alan')),
            'emsal' => $this->trNumericCevir($this->input('emsal')),
            'kategori' => $kategori,
            'ekbilgi' => $ekbilgi,
            'yapilar' => $yapilar,
            'tapu' => $tapu,
            'hisseler' => $hisseler,
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
