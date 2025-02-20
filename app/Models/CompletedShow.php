<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompletedShow extends Model
{
    protected $fillable = [
        'show_id',
        'user_id',

        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function show()
    {
        return $this->belongsTo(Show::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
