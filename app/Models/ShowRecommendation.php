<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowRecommendation extends Model
{
    protected $fillable = [
        'user_id',
        'show_id',
        'score',
        'computed_at',
    ];

    protected $casts = [
        'score' => 'float',
        'computed_at' => 'datetime',
    ];

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}






