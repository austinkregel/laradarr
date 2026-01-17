<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieRecommendation extends Model
{
    protected $fillable = [
        'user_id',
        'movie_id',
        'score',
        'computed_at',
    ];

    protected $casts = [
        'score' => 'float',
        'computed_at' => 'datetime',
    ];

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}






