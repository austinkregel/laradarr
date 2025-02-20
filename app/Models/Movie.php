<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    public $fillable = [
        'radarr_id',
        'name',
        'runtime',
        'movie_file_id',
        'is_available',
        'aliases',
        'in_cinemas',
        'imdb_id',
        'added_at',
        'path',
    ];

    public $casts = [
        'is_available' => 'boolean',
        'added_at' => 'datetime',
    ];
}
