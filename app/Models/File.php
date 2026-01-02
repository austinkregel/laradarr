<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    /** @use HasFactory<\Database\Factories\FileFactory> */
    use HasFactory, SoftDeletes;

    public $fillable = [
        'path',
        'name',
        'extension',
        'mime_type',
        'disk',
        'visibility',
        'size',
        'md5_checksum',
        'permissions',
        'owner',
        'group',
        'created_on',
        'last_modified',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'json',
            'created_on' => 'datetime',
            'last_modified' => 'datetime',
            'permissions' => 'integer',
            'size' => 'integer',
        ];
    }
}
