<?php
declare(strict_types=1);

namespace App\Contracts;

use App\Models\Movie;
use App\Models\Show;

interface MetadataSyncServiceContract
{
    public function syncShowMetadata(Show $show): Show;

    public function syncMovieMetadata(Movie $movie): Movie;
}

