<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Credential extends Model
{
    protected $fillable = [
        'service',
        'key',
        'value',
        'is_enabled',
        'expires_at',
        'last_used_at',
    ];

    protected $casts = [
        'value' => 'encrypted',
        'is_enabled' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];
}





