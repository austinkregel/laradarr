<?php

namespace Tests\Unit\Services;

use App\Services\TraktTvService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TraktTvServiceTest extends TestCase
{
    public function test_find_watched_shows_refreshes_token_on_unauthorized_once(): void
    {
        config()->set('services.trakt.base_url', 'https://api.trakt.tv');
        config()->set('services.trakt.client_id', 'client');
        config()->set('services.trakt.client_secret', 'secret');
        config()->set('services.trakt.redirect', 'urn:ietf:wg:oauth:2.0:oob');
        config()->set('services.trakt.access_token', 'expired-access');
        config()->set('services.trakt.refresh_token', 'refresh-token');

        Http::fake([
            'https://api.trakt.tv/sync/watched/shows*' => Http::sequence()
                ->push('', 401)
                ->push([
                    [
                        'show' => [
                            'title' => 'Example',
                            'year' => 2024,
                            'ids' => ['trakt' => 123, 'slug' => 'example'],
                        ],
                        'seasons' => [
                            [
                                'number' => 1,
                                'episodes' => [
                                    ['number' => 1, 'last_watched_at' => '2024-01-01T00:00:00.000Z'],
                                ],
                            ],
                        ],
                    ],
                ], 200),
            'https://api.trakt.tv/oauth/token*' => Http::response([
                'access_token' => 'new-access',
                'refresh_token' => 'new-refresh',
                'expires_in' => 3600,
                'created_at' => time(),
            ], 200),
        ]);

        $service = app(TraktTvService::class);
        $shows = $service->findWatchedShows();

        $this->assertCount(1, $shows);
        $this->assertSame(123, $shows->first()->id);
        $this->assertCount(1, $shows->first()->episodes);
    }
}








