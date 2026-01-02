<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecommendationRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendations_page_exposes_recommendation_payload(): void
    {
        $fixtures = $this->createRecommendationFixtures();

        $this->actingAs($fixtures['user'])
            ->get('/recommendations')
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Recommendations')
                ->has('showRecommendations')
                ->has('movieRecommendations')
            );
    }

    public function test_api_shows_endpoint_returns_show_recommendations(): void
    {
        $fixtures = $this->createRecommendationFixtures();

        Sanctum::actingAs($fixtures['user']);

        $response = $this->getJson('/api/recommendations/shows');

        $response->assertOk();

        $names = collect($response->json())
            ->map(fn (array $entry) => data_get($entry, 'show.name'))
            ->filter()
            ->values();

        $this->assertTrue($names->contains('Naruto'));
    }

    public function test_api_movies_endpoint_returns_movie_recommendations(): void
    {
        $fixtures = $this->createRecommendationFixtures();

        Sanctum::actingAs($fixtures['user']);

        $response = $this->getJson('/api/recommendations/movies');

        $response->assertOk();

        $movieNames = collect($response->json())
            ->map(fn (array $entry) => data_get($entry, 'movie.name'))
            ->filter()
            ->values();

        $this->assertTrue($movieNames->contains('18+ Thriller'));
    }

    public function test_similar_endpoint_returns_related_show(): void
    {
        $fixtures = $this->createRecommendationFixtures();

        Sanctum::actingAs($fixtures['user']);

        $response = $this->getJson("/api/shows/{$fixtures['onePiece']->id}/similar");

        $response->assertOk();

        $names = collect($response->json())
            ->map(fn (array $entry) => data_get($entry, 'show.name'))
            ->filter()
            ->values();

        $this->assertTrue($names->contains('Naruto'));
    }

    private function createRecommendationFixtures(): array
    {
        $category = Category::create([
            'name' => 'Action',
            'slug' => 'action',
            'type' => 'genre',
        ]);

        $onePiece = Show::create([
            'name' => 'One Piece',
            'release_year' => 1999,
            'poster_image' => 'https://example.com/one-piece.jpg',
            'seasons' => 1,
            'episodes' => 100,
            'type' => 'anime',
            'trakt_rating' => 9.7,
            'genres' => ['action', 'adventure'],
        ]);
        $onePiece->categories()->attach($category->id);
        $onePiece->attachTags(['Action']);
        $this->addEpisodeWithEnglishDub($onePiece, 1, 1);

        $naruto = Show::create([
            'name' => 'Naruto',
            'release_year' => 2002,
            'poster_image' => 'https://example.com/naruto.jpg',
            'seasons' => 1,
            'episodes' => 220,
            'type' => 'anime',
            'trakt_rating' => 8.8,
            'genres' => ['action'],
        ]);
        $naruto->categories()->attach($category->id);
        $naruto->attachTags(['Action']);
        $this->addEpisodeWithEnglishDub($naruto, 1, 1);

        $user = User::factory()->create();
        $user->completedShows()->attach($onePiece->id, ['completed_at' => now()]);

        Movie::create([
            'radarr_id' => 1,
            'name' => '18+ Thriller',
            'runtime' => 120,
            'movie_file_id' => 1,
            'is_available' => true,
            'added_at' => now(),
        ]);

        return [
            'user' => $user,
            'onePiece' => $onePiece,
            'naruto' => $naruto,
        ];
    }
}


