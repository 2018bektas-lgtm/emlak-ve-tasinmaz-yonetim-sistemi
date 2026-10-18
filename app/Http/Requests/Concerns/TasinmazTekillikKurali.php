<?php

namespace App\Http\Requests\Concerns;

use App\Enums\KayitTipi;
use App\Support\TasinmazTekillik;
use Illuminate\Validation\Validator;

/**
 * Store/Update taşınmaz isteklerinde "aynı kayıt iki kez açılamaz" kontrolü.
 * Kurallar için bkz. App\Support\TasinmazTekillik.
 */
trait TasinmazTekillikKurali
{
    protected function tekillikKontrolu(Validator $validator, ?int $haricId = null): void
    {
        // Kimliği oluşturan alanlar zaten hatalıysa çakışma aramaya gerek yok.
        $kimlikAlanlari = ['mahalle_id', 'ada', 'parsel', 'kayit_tipi', 'yapi.bagimsiz_bolum_no', 'yapi.blok_no'];
        foreach ($kimlikAlanlari as $alan) {
            if ($validator->errors()->has($alan)) {
                return;
            }
        }

        $kayitTipi = (string) $this->input('kayit_tipi');

        // Kat mülkiyetsiz binada aynı blok + BBN no listede iki kez olamaz.
        if ($kayitTipi === KayitTipi::KatMulkiyetsizBina->value) {
            $yapilar = (array) ($this->input('yapilar') ?? []);
            foreach (TasinmazTekillik::tekrarEdenYapilar($yapilar) as $i) {
                $y = array_values($yapilar)[$i];
                $validator->errors()->add(
                    "yapilar.{$i}.bagimsiz_bolum_no",
                    'Bu binada aynı bağımsız bölüm iki kez girilmiş: '
                        .TasinmazTekillik::etiket($y['blok_no'] ?? null, $y['bagimsiz_bolum_no'] ?? null).'.'
                );
            }
        }

        $blok = $this->input('yapi.blok_no');
        $bbn = $this->input('yapi.bagimsiz_bolum_no');

        $cakisan = TasinmazTekillik::cakisanKayit(
            (int) $this->input('mahalle_id'),
            (string) $this->input('ada'),
            (string) $this->input('parsel'),
            $kayitTipi,
            $blok,
            $bbn,
            $haricId,
        );

        if (! $cakisan) {
            return;
        }

        if ($kayitTipi === KayitTipi::KatMulkiyetli->value) {
            $validator->errors()->add(
                'yapi.bagimsiz_bolum_no',
                // Mesajda kullanıcının yazımı değil, kayıtlı değerler gösterilir
                'Bu bağımsız bölüm zaten kayıtlı ('.TasinmazTekillik::etiket($cakisan->yapi?->blok_no, $cakisan->yapi?->bagimsiz_bolum_no)
                    .', Taşınmaz #'.$cakisan->id.'). Aynı ada/parselde farklı blok veya BB No girin.'
            );

            return;
        }

        $validator->errors()->add(
            'parsel',
            'Bu ada/parsel zaten kayıtlı (Taşınmaz #'.$cakisan->id.'). Aynı parsel ikinci kez boş parsel veya kat mülkiyetsiz bina olarak açılamaz.'
        );
    }
}
