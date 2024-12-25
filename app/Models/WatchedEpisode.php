<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatchedEpisode extends Model
{
    protected $fillable = [
        'episode_id',
        'season_id',
        'user_id',

        'watched_at',
    ];

    protected $casts = [
        'watched_at' => 'datetime',
    ];

    public function episode()
    {
        return $this->belongsTo(Episode::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }
}
