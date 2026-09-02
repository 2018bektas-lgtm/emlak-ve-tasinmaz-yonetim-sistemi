<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTasinmazRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // Lokasyon — sadece mahalle_id yeter; il/ilce observer ile doldurulur.
            'mahalle_id' => ['required', 'integer', 'exists:mahalleler,id'],
            'ada' => ['required', 'string', 'max:20'],
            'parsel' => ['required', 'string', 'max:20'],

            // Fiziksel
            'alan' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'nitelik' => ['required', 'string', 'max:100'],

            // Imar (tasinmaz_imarlar) — referansa uygun zorunlu
            'imar_durumu_id' => ['required', 'integer', 'exists:imar_durumlari,id'],
            'emsal' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'yenaz_yencok' => ['required', 'string', 'max:50'],
            'imar_notu' => ['nullable', 'string', 'max:5000'],

            // Siniflandirma — referansa uygun zorunlu
            'muhasebe_kayit_id' => ['required', 'integer', 'exists:muhasebe_kayitlari,id'],
            'kayit_turu_id' => ['required', 'integer', 'exists:kayit_turleri,id'],
            'mevcut_kullanim_sekli' => ['required', 'string', 'max:150'],
            'isgal_durumu' => ['required', 'in:yok,var,kismi'],
            'uzeri_bina_var_mi' => ['sometimes', 'boolean'],
            'meclis_satis_karari_var' => ['sometimes', 'boolean'],

            'aciklama' => ['nullable', 'string', 'max:5000'],

            // Bagimsiz bolum (opsiyonel, en fazla bir)
            'bagimsiz_bolum' => ['nullable', 'array'],
            'bagimsiz_bolum.blok_no' => ['nullable', 'string', 'max:20'],
            'bagimsiz_bolum.kat_no' => ['nullable', 'required_with:bagimsiz_bolum.bagimsiz_bolum_no,bagimsiz_bolum.nitelik', 'string', 'max:10'],
            'bagimsiz_bolum.bagimsiz_bolum_no' => ['nullable', 'required_with:bagimsiz_bolum.kat_no,bagimsiz_bolum.nitelik', 'string', 'max:20'],
            'bagimsiz_bolum.nitelik' => ['nullable', 'required_with:bagimsiz_bolum.kat_no,bagimsiz_bolum.bagimsiz_bolum_no', 'string', 'max:50'],
            'bagimsiz_bolum.brut_alan' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'bagimsiz_bolum.net_alan' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'bagimsiz_bolum.oda_sayisi' => ['nullable', 'string', 'max:10'],
            'bagimsiz_bolum.cephe' => ['nullable', 'string', 'max:50'],
            'bagimsiz_bolum.mevcut_kullanim_sekli' => ['nullable', 'string', 'max:150'],
            'bagimsiz_bolum.isgal_durumu' => ['nullable', 'in:yok,var,kismi'],

            // Tapu (polimorfik 1:1) — referansa uygun zorunlu
            'tapu' => ['required', 'array'],
            'tapu.takbis_zemin_no' => ['required', 'string', 'max:30'],
            'tapu.cilt_no' => ['required', 'string', 'max:20'],
            'tapu.sayfa_no' => ['required', 'string', 'max:20'],
            'tapu.tapu_durumu' => ['required', 'in:aktif,pasif'],
            'tapu.tapu_tarihi' => ['nullable', 'date'],
            'tapu.tapu_kaydi_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],

            // Konum haritasi (opsiyonel, ikisi de dolu ya da ikisi de bos)
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'koordinat' => ['nullable', 'json'],

            // Resimler (opsiyonel, max 20 adet, dosya basina 5 MB)
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            // Hisseler (opsiyonel, birden fazla eklenebilir)
            'hisseler' => ['nullable', 'array'],
            'hisseler.*.hisse_no' => ['nullable', 'integer', 'min:1'],
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
            'nitelik.required' => 'Nitelik zorunludur (Arsa, Tarla, Bahçe vb).',
            'imar_durumu_id.required' => 'İmar durumu seçimi zorunludur.',
            'imar_durumu_id.exists' => 'Seçilen imar durumu geçerli değil.',
            'emsal.required' => 'Emsal (KAKS) zorunludur.',
            'emsal.numeric' => 'Emsal sayısal olmalıdır.',
            'emsal.max' => 'Emsal en fazla 999,99 olabilir.',
            'yenaz_yencok.required' => 'Yen az / Yen çok bilgisi zorunludur.',
            'yenaz_yencok.max' => 'Yen az / Yen çok en fazla 50 karakter olabilir.',
            'muhasebe_kayit_id.required' => 'Muhasebe kaydı seçimi zorunludur.',
            'muhasebe_kayit_id.exists' => 'Geçersiz muhasebe kaydı.',
            'kayit_turu_id.required' => 'Kayıt türü seçimi zorunludur.',
            'kayit_turu_id.exists' => 'Geçersiz kayıt türü.',
            'mevcut_kullanim_sekli.required' => 'Mevcut kullanım şekli seçimi zorunludur.',
            'isgal_durumu.required' => 'İşgal durumu seçimi zorunludur.',
            'isgal_durumu.in' => 'İşgal durumu yok, var veya kısmi olmalıdır.',
            'tapu.required' => 'Tapu bilgileri zorunludur.',
            'tapu.takbis_zemin_no.required' => 'TAKBİS zemin numarası zorunludur.',
            'tapu.cilt_no.required' => 'Cilt numarası zorunludur.',
            'tapu.sayfa_no.required' => 'Sayfa numarası zorunludur.',
            'tapu.tapu_durumu.required' => 'Tapu durumu (aktif / pasif) zorunludur.',
            'lat.between' => 'Enlem -90 ile 90 arasında olmalıdır.',
            'lng.between' => 'Boylam -180 ile 180 arasında olmalıdır.',
            'lat.required_with' => 'Boylam girildiyse enlem de zorunludur.',
            'lng.required_with' => 'Enlem girildiyse boylam da zorunludur.',
            'koordinat.json' => 'Koordinat verisi geçerli JSON değil.',
            'bagimsiz_bolum.kat_no.required_with' => 'Bağımsız bölüm kat no zorunludur.',
            'bagimsiz_bolum.bagimsiz_bolum_no.required_with' => 'Bağımsız bölüm no zorunludur.',
            'bagimsiz_bolum.nitelik.required_with' => 'Bağımsız bölüm niteliği zorunludur.',
            'tapu.takbis_zemin_no.required_with' => 'TAKBİS zemin no zorunludur.',
            'tapu.cilt_no.required_with' => 'Cilt no zorunludur.',
            'tapu.sayfa_no.required_with' => 'Sayfa no zorunludur.',
            'tapu.tapu_kaydi_pdf.mimes' => 'Tapu kaydı PDF olmalıdır.',
            'tapu.tapu_kaydi_pdf.max' => 'Tapu PDF en fazla 10 MB olabilir.',
            'images.max' => 'En fazla 20 resim yükleyebilirsiniz.',
            'images.*.mimes' => 'Resim dosyaları JPG, PNG veya WebP olmalıdır.',
            'images.*.max' => 'Her resim en fazla 5 MB olabilir.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $bbn = $this->input('bagimsiz_bolum');
        if (is_array($bbn)) {
            $bbn['brut_alan'] = $this->trNumericCevir($bbn['brut_alan'] ?? null);
            $bbn['net_alan'] = $this->trNumericCevir($bbn['net_alan'] ?? null);
            $bbn['blok_no'] = filled($bbn['blok_no'] ?? null) ? $bbn['blok_no'] : null;
            $dolu = collect($bbn)->contains(fn ($deger) => filled($deger));
            $bbn = $dolu ? $bbn : null;
        } else {
            $bbn = null;
        }

        // Tapu artik zorunlu — bos alanlari null olarak birak, required kural yakalar
        $tapu = $this->input('tapu');
        if (! is_array($tapu)) {
            $tapu = [];
        }

        // Hisseler dizisindeki TR numeric alanlari normalize et + bos satirlari at
        $hisseler = $this->input('hisseler');
        if (is_array($hisseler)) {
            $temiz = [];
            foreach ($hisseler as $h) {
                if (! is_array($h)) continue;
                foreach (['hisse_yuzolcum', 'maliyet_bedeli', 'rayic_bedel', 'emlak_vd', 'iz_bedeli'] as $k) {
                    $h[$k] = $this->trNumericCevir($h[$k] ?? null);
                }
                // En az bir anlamli deger varsa kabul et
                $dolu = collect($h)->contains(fn ($v) => filled($v));
                if ($dolu) $temiz[] = $h;
            }
            $hisseler = $temiz ?: null;
        } else {
            $hisseler = null;
        }

        $this->merge([
            'uzeri_bina_var_mi' => $this->boolean('uzeri_bina_var_mi'),
            'meclis_satis_karari_var' => $this->boolean('meclis_satis_karari_var'),
            'alan' => $this->trNumericCevir($this->input('alan')),
            'emsal' => $this->trNumericCevir($this->input('emsal')),
            'bagimsiz_bolum' => $bbn,
            'tapu' => $tapu,
            'hisseler' => $hisseler,
        ]);
    }

    /**
     * "690,00" | "690.00" | "1.234,56" | "1,234.56" → 690 / 1234.56
     */
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
