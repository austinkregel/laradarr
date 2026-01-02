<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\LidarrServiceContract;
use App\Services\DTOs\Lidarr\AlbumDTO;
use App\Services\DTOs\Lidarr\ArtistDTO;
use Illuminate\Support\Collection;

class LidarrService extends BaseApiClient implements LidarrServiceContract
{
    protected function serviceKey(): string
    {
        return 'lidarr';
    }

    protected function defaultQuery(): array
    {
        /** @var \App\Contracts\CredentialStoreContract $store */
        $store = app(\App\Contracts\CredentialStoreContract::class);
        $apiKey = $store->get('lidarr', 'api_key') ?? config('services.lidarr.api_key');
        
        return [
            'apikey' => (string) $apiKey,
        ];
    }

    protected function apiPrefix(): string
    {
        return (string) config('services.lidarr.api_prefix', '/api/v1');
    }

    /** @return Collection<int, ArtistDTO> */
    public function getArtists(): Collection
    {
        $json = $this->requestJson('GET', $this->apiPrefix() . '/artist');

        return collect($json)
            ->filter(fn ($row) => is_array($row) && isset($row['id']))
            ->map(fn (array $row) => ArtistDTO::fromArray($row))
            ->values();
    }

    public function getArtist(int $artistId): ArtistDTO
    {
        $json = $this->requestJson('GET', $this->apiPrefix() . "/artist/{$artistId}");
        $this->ensureKeys($json, ['id'], 'lidarr.getArtist');
        return ArtistDTO::fromArray($json);
    }

    /** @return Collection<int, AlbumDTO> */
    public function getAlbums(int $artistId): Collection
    {
        $json = $this->requestJson('GET', $this->apiPrefix() . '/album', query: ['artistId' => $artistId]);

        return collect($json)
            ->filter(fn ($row) => is_array($row) && isset($row['id']))
            ->map(fn (array $row) => AlbumDTO::fromArray($row))
            ->values();
    }
}




