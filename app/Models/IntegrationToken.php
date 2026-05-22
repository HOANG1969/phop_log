<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationToken extends Model
{
    protected $fillable = [
        'provider',
        'access_token',
        'refresh_token',
        'expires_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
