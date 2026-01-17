<?php
declare(strict_types=1);

namespace App\Contracts;

use App\Services\DTOs\QBittorrent\TorrentDTO;
use Illuminate\Support\Collection;

interface QBittorrentServiceContract
{
    public function login(): bool;

    /** @return Collection<int, TorrentDTO> */
    public function getTorrents(array $filters = []): Collection;

    public function reannounce(string $hash): bool;

    public function resume(string $hash): bool;

    public function forceResume(string $hash): bool;

    /** @return array<int, array{name: string, size: int, progress: float, priority: int, is_seed: bool, piece_range: array{0: int, 1: int}}> */
    public function getTorrentFiles(string $hash): array;

    public function getTorrentProperties(string $hash): ?array;

    public function deleteTorrent(string $hash, bool $deleteFiles = false): bool;
}





