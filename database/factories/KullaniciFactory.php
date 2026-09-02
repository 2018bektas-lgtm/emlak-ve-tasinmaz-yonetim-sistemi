<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Kullanici>
 */
class KullaniciFactory extends Factory
{
    protected static ?string $sifre;

    public function definition(): array
    {
        return [
            'ad' => fake()->firstName(),
            'soyad' => fake()->lastName(),
            'mail' => fake()->unique()->safeEmail(),
            'kullanici_adi' => fake()->unique()->userName(),
            'mail_dogrulama_tarihi' => now(),
            'sifre' => static::$sifre ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function dogrulanmamis(): static
    {
        return $this->state(fn (array $attributes) => [
            'mail_dogrulama_tarihi' => null,
        ]);
    }
}
