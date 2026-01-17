<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualImportFlag extends Model
{
    protected $fillable = [
        'torrent_hash',
        'torrent_name',
        'content_path',
        'file_paths',
        'reason',
        'resolved',
        'resolved_at',
    ];

    protected $casts = [
        'file_paths' => 'array',
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];
}





