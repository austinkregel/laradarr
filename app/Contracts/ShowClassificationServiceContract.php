<?php
declare(strict_types=1);

namespace App\Contracts;

use App\Models\Show;

interface ShowClassificationServiceContract
{
    public function classifyAndAttach(Show $show): void;
}



