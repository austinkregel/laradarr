<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Show;
use App\Models\User;
use App\Services\Auth\CredentialStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SonarrControllerTest extends TestCase
{
    use RefreshDatabase;

    private function configureSonarr(): void
    {
        config()->set('services.sonarr.url', 'http://sonarr.test');
        config()->set('services.sonarr.api_key', 'abc');

        // Mock CredentialStore to return null so tests use config values
        $this->mock(CredentialStore::class, function ($mock) {
            $mock->shouldReceive('get')->andReturn(null);
        });
    }

    public function test_release_push_requires_auth(): void
    {
        $response = $this->postJson(route('sonarr.release.push'), [
            'title' => 'Episode',
            'magnetUrl' => 'magnet:?xt=urn:btih:123',
        ]);

        $response->assertStatus(401);
    }

    public function test_release_push_with_magnet_url(): void
    {
        $this->configureSonarr();

        Http::fake([
            'http://sonarr.test/api/v3/release/push*' => Http::response(['id' => 1], 201),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson(route('sonarr.release.push'), [
            'title' => 'Episode',
            'magnetUrl' => 'magnet:?xt=urn:btih:123',
            'publishDate' => '2025-09-01T12:00:00Z',
        ]);

        $response->assertOk();
        $this->assertSame(['id' => 1], $response->json());
    }

    public function test_release_push_sends_full_payload_to_sonarr(): void
    {
        $this->configureSonarr();

        Http::fake([
            'http://sonarr.test/api/v3/release/push*' => Http::response(['id' => 99], 201),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson(route('sonarr.release.push'), [
            'title' => 'Episode',
            'magnetUrl' => 'magnet:?xt=urn:btih:abc',
            'publishDate' => '2025-09-01T12:00:00Z',
        ]);

        $response->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v3/release/push')
            && $request->method() === 'POST'
            && $this->assertJsonPayload($request->body(), [
                'protocol' => 'torrent',
                'magnetUrl' => 'magnet:?xt=urn:btih:abc',
                'title' => 'Episode',
                'publishDate' => '2025-09-01T12:00:00Z',
            ]) === true);
    }

    public function test_release_push_with_download_url(): void
    {
        $this->configureSonarr();

        Http::fake([
            'http://sonarr.test/api/v3/release/push*' => Http::response(['id' => 2], 201),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson(route('sonarr.release.push'), [
            'title' => 'Episode',
            'downloadUrl' => 'https://example.com/episode.torrent',
            'publishDate' => '2025-09-01T12:00:00Z',
        ]);

        $response->assertOk();
        $this->assertSame(['id' => 2], $response->json());
    }

    public function test_release_push_validates_source(): void
    {
        $this->configureSonarr();

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson(route('sonarr.release.push'), [
            'title' => 'Episode',
        ]);

        $response->assertStatus(422);
        $this->assertSame('Either magnetUrl or downloadUrl must be provided', $response->json('message'));
    }

    public function test_search_episodes_with_ids(): void
    {
        $this->configureSonarr();

        Http::fake([
            'http://sonarr.test/api/v3/command*' => Http::response(['id' => 123], 201),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson(route('sonarr.episodes.search'), [
            'episodeIds' => [101, 102],
        ]);

        $response->assertOk();
        $this->assertSame(['id' => 123], $response->json());
    }

    public function test_search_episodes_posts_command_payload(): void
    {
        $this->configureSonarr();

        Http::fake([
            'http://sonarr.test/api/v3/command*' => Http::response(['id' => 888], 201),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson(route('sonarr.episodes.search'), [
            'episodeIds' => [201],
        ]);

        $response->assertOk()->assertJson(['id' => 888]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v3/command')
            && $request->method() === 'POST'
            && $this->assertJsonPayload($request->body(), [
                'name' => 'EpisodeSearch',
                'episodeIds' => [201],
            ]) === true);
    }

    public function test_search_episodes_for_show(): void
    {
        $this->configureSonarr();

        Http::fake([
            'http://sonarr.test/api/v3/episode*' => Http::response([
                [
                    'id' => 555,
                    'seriesId' => 10,
                ],
            ], 200),
            'http://sonarr.test/api/v3/command*' => Http::response(['id' => 789], 201),
        ]);

        $show = Show::create([
            'sonarr_id' => 10,
            'name' => 'Example Show',
            'slug' => 'example-show',
            'release_year' => 2020,
            'season_count' => 1,
            'episode_count' => 12,
            'type' => 'anime',
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson(route('sonarr.episodes.search'), [
            'showId' => $show->id,
        ]);

        $response->assertOk();
        $this->assertSame(['id' => 789], $response->json());
    }

    public function test_search_episodes_for_show_fetches_missing_before_command(): void
    {
        $this->configureSonarr();

        Http::fake([
            'http://sonarr.test/api/v3/episode*' => Http::response([
                [
                    'id' => 555,
                    'seriesId' => 10,
                ],
            ], 200),
            'http://sonarr.test/api/v3/command*' => Http::response(['id' => 900], 201),
        ]);

        $show = Show::create([
            'sonarr_id' => 10,
            'name' => 'Example Show',
            'slug' => 'example-show',
            'release_year' => 2020,
            'season_count' => 1,
            'episode_count' => 12,
            'type' => 'anime',
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson(route('sonarr.episodes.search'), [
            'showId' => $show->id,
        ]);

        $response->assertOk();
        $this->assertSame(['id' => 900], $response->json());
    }

    public function test_search_episodes_requires_ids_or_show(): void
    {
        $this->configureSonarr();

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson(route('sonarr.episodes.search'));

        $response->assertStatus(422);
        $this->assertSame('No episode IDs provided', $response->json('message'));
    }

    public function test_get_command_status_requires_auth(): void
    {
        $response = $this->getJson(route('sonarr.commands.status', ['commandId' => 1]));

        $response->assertStatus(401);
    }

    public function test_get_command_status_returns_data(): void
    {
        $this->configureSonarr();

        Http::fake([
            'http://sonarr.test/api/v3/command/1*' => Http::response(['id' => 1, 'status' => 'ok'], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson(route('sonarr.commands.status', ['commandId' => 1]));

        $response->assertOk();
        $this->assertSame(['id' => 1, 'status' => 'ok'], $response->json());
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v3/command/1') && $request->method() === 'GET');
    }

    private function assertJsonPayload(string $body, array $expected): bool
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return false;
        }

        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $decoded) || $decoded[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}
