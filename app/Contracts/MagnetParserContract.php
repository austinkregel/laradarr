<?php
declare(strict_types=1);

namespace App\Contracts;

interface MagnetParserContract
{
    /** @return array{title: string|null, size: int|null, infoHash: string|null, trackers: string[]} */
    public static function parse(string $magnetUrl): array;
}





