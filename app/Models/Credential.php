<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Credential extends Model
{
    protected $fillable = [
        'user_id',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}







