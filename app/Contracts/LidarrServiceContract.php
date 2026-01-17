<?php
declare(strict_types=1);

namespace App\Contracts;

use App\Services\DTOs\Lidarr\AlbumDTO;
use App\Services\DTOs\Lidarr\ArtistDTO;
use Illuminate\Support\Collection;

interface LidarrServiceContract
{
    /** @return Collection<int, ArtistDTO> */
    public function getArtists(): Collection;

    public function getArtist(int $artistId): ArtistDTO;

    /** @return Collection<int, AlbumDTO> */
    public function getAlbums(int $artistId): Collection;
}





