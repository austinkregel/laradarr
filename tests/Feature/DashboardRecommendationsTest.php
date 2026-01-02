<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_includes_recommendations_section(): void
    {
        $fixtures = $this->createRecommendationFixtures();

        $response = $this->actingAs($fixtures['user'])
            ->get('/dashboard');

        $response->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->has('show_recommendations')
                ->where('show_recommendations.0.show.name', $fixtures['naruto']->name)
            );
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

        return [
            'user' => $user,
            'onePiece' => $onePiece,
            'naruto' => $naruto,
        ];
    }
}

