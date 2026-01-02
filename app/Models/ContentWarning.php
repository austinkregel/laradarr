<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentWarning extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
    ];

    public function shows()
    {
        return $this->belongsToMany(Show::class, 'show_content_warnings')
            ->withPivot(['severity'])
            ->withTimestamps();
    }

    public function movies()
    {
        return $this->belongsToMany(Movie::class, 'movie_content_warnings')
            ->withPivot(['severity'])
            ->withTimestamps();
    }
}



