<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Kullanici extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\KullaniciFactory> */
    use HasFactory, Notifiable;

    protected $table = 'kullanicilar';

    protected $fillable = [
        'ad',
        'soyad',
        'mail',
        'kullanici_adi',
        'sifre',
    ];

    protected $hidden = [
        'sifre',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'mail_dogrulama_tarihi' => 'datetime',
            'sifre' => 'hashed',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->sifre;
    }

    public function getAuthPasswordName(): string
    {
        return 'sifre';
    }

    public function getAdSoyad(): string
    {
        return trim($this->ad.' '.$this->soyad);
    }
}
