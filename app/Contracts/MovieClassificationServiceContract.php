<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Movie;

interface MovieClassificationServiceContract
{
    public function classifyAndAttach(Movie $movie): void;
}

