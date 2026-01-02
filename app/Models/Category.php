<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'user_id',
        'color',
        'icon',
    ];

    public function shows()
    {
        return $this->belongsToMany(Show::class, 'show_categories')->withTimestamps();
    }

    public function movies()
    {
        return $this->belongsToMany(Movie::class, 'movie_categories')->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}



