<?php

namespace App\Models;

/**
 * Thin wrapper around Spatie's tag model so the rest of the app can typehint
 * `App\Models\Tag` consistently.
 */
class Tag extends \Spatie\Tags\Tag
{
    public function shows()
    {
        // If we use tags for shows, we'll attach them via Spatie's taggables relation.
        return $this->morphedByMany(Show::class, 'taggable');
    }
}







