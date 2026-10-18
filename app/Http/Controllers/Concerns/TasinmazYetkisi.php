<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Middleware\TasinmazBolumParametresi;
use App\Models\Tasinmaz;

/**
 * Adresteki mahalle (TKGM) + ada + parsel (+ bölüm) ile taşınmazı bulur.
 * Erişim yalnız rol izinleriyle (rota middleware'i) denetlenir; müdürlük
 * ayrımı sistemden kaldırıldı.
 */
trait TasinmazYetkisi
{
    protected function tasinmazBulVeYetkilendir(int $mahalleTkgmId, string $ada, string $parsel): Tasinmaz
    {
        return Tasinmaz::slugIleBul($mahalleTkgmId, $ada, $parsel, $this->adrestekiBolum());
    }

    /** ".../bb/{bolum}" rotalarından gelen bölüm parçası (ör. "A-10"), yoksa null. */
    protected function adrestekiBolum(): ?string
    {
        return request()->attributes->get(TasinmazBolumParametresi::ATTRIBUTE);
    }
}
