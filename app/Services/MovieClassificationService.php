<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\MovieClassificationServiceContract;
use App\Models\Category;
use App\Models\ContentWarning;
use App\Models\Movie;
use Illuminate\Support\Str;

class MovieClassificationService implements MovieClassificationServiceContract
{
    public function classifyAndAttach(Movie $movie): void
    {
        $text = $this->buildSearchText($movie);

        $this->applyCategories($movie, $text);
        $this->applyContentWarnings($movie, $text);
    }

    protected function buildSearchText(Movie $movie): string
    {
        $parts = [
            $movie->name,
            $movie->slug,
            $movie->description,
            implode(' ', (array) ($movie->aliases ?? [])),
            implode(' ', (array) ($movie->genres ?? [])),
        ];

        $joined = strtolower(implode(' ', array_values(array_filter(array_map(
            fn ($v) => is_string($v) ? $v : null,
            $parts
        )))));

        // Normalize spacing
        return preg_replace('/\s+/', ' ', $joined) ?? $joined;
    }

    protected function applyCategories(Movie $movie, string $text): void
    {
        $rules = (array) config('classification.categories', []);
        $slugsToAttach = [];

        foreach ($rules as $slug => $rule) {
            $keywords = (array) data_get($rule, 'keywords', []);
            if ($this->matchesAny($text, $keywords)) {
                $slugsToAttach[] = (string) $slug;
            }
        }

        // Also map genres directly to categories, and auto-create them if missing.
        foreach ((array) ($movie->genres ?? []) as $genre) {
            if (!is_string($genre) || $genre === '') {
                continue;
            }
            $slugsToAttach[] = Str::slug($genre);
        }

        $slugsToAttach = array_values(array_unique(array_filter($slugsToAttach)));
        if (empty($slugsToAttach)) {
            return;
        }

        foreach ($slugsToAttach as $slug) {
            $name = Str::of($slug)->replace('-', ' ')->title()->toString();
            $category = Category::query()->firstWhere('slug', $slug);
            if (!$category) {
                $category = Category::query()->create([
                    'name' => $name,
                    'slug' => $slug,
                    'type' => 'system',
                ]);
            }

            // Don't detach anything; just add missing.
            $movie->categories()->syncWithoutDetaching([$category->id]);
        }
    }

    protected function applyContentWarnings(Movie $movie, string $text): void
    {
        $rules = (array) config('classification.content_warnings', []);

        foreach ($rules as $slug => $rule) {
            $keywords = (array) data_get($rule, 'keywords', []);
            if (!$this->matchesAny($text, $keywords)) {
                continue;
            }

            $name = (string) (data_get($rule, 'name') ?: Str::of((string) $slug)->replace('-', ' ')->title());
            $severity = (string) (data_get($rule, 'severity') ?: 'mild');

            $warning = ContentWarning::query()->firstWhere('slug', $slug);
            if (!$warning) {
                $warning = ContentWarning::query()->create([
                    'name' => $name,
                    'slug' => (string) $slug,
                    'description' => null,
                    'icon' => null,
                ]);
            }

            // Ensure pivot exists with a severity (idempotent-ish).
            $existing = $movie->contentWarnings()
                ->where('content_warnings.id', $warning->id)
                ->first();

            if ($existing) {
                // Update severity if needed
                $current = (string) data_get($existing, 'pivot.severity', 'mild');
                if ($current !== $severity) {
                    $movie->contentWarnings()->updateExistingPivot($warning->id, ['severity' => $severity]);
                }
                continue;
            }

            $movie->contentWarnings()->attach($warning->id, ['severity' => $severity]);
        }
    }

    protected function matchesAny(string $text, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (!is_string($kw) || $kw === '') {
                continue;
            }
            if (str_contains($text, strtolower($kw))) {
                return true;
            }
        }
        return false;
    }
}

