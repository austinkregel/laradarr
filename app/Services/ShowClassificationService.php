<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\ShowClassificationServiceContract;
use App\Models\Category;
use App\Models\ContentWarning;
use App\Models\Show;
use Illuminate\Support\Str;

class ShowClassificationService implements ShowClassificationServiceContract
{
    public function classifyAndAttach(Show $show): void
    {
        $text = $this->buildSearchText($show);

        $this->applyCategories($show, $text);
        $this->applyContentWarnings($show, $text);
    }

    protected function buildSearchText(Show $show): string
    {
        $parts = [
            $show->name,
            $show->slug,
            $show->description,
            implode(' ', (array) ($show->aliases ?? [])),
            implode(' ', (array) ($show->genres ?? [])),
            $show->type,
        ];

        $joined = strtolower(implode(' ', array_values(array_filter(array_map(
            fn ($v) => is_string($v) ? $v : null,
            $parts
        )))));

        // Normalize spacing
        return preg_replace('/\s+/', ' ', $joined) ?? $joined;
    }

    protected function applyCategories(Show $show, string $text): void
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
        foreach ((array) ($show->genres ?? []) as $genre) {
            if (!is_string($genre) || $genre === '') {
                continue;
            }
            $slugsToAttach[] = Str::slug($genre);
        }

        // Ensure "Anime" / "Live Action" style categories from type.
        if ($show->type) {
            $type = strtolower((string) $show->type);
            if ($type === 'anime') {
                $slugsToAttach[] = Str::slug('Anime');
            } else {
                $slugsToAttach[] = Str::slug('Live Action');
            }
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
            $show->categories()->syncWithoutDetaching([$category->id]);
        }
    }

    protected function applyContentWarnings(Show $show, string $text): void
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
            $existing = $show->contentWarnings()
                ->where('content_warnings.id', $warning->id)
                ->first();

            if ($existing) {
                // Update severity if needed
                $current = (string) data_get($existing, 'pivot.severity', 'mild');
                if ($current !== $severity) {
                    $show->contentWarnings()->updateExistingPivot($warning->id, ['severity' => $severity]);
                }
                continue;
            }

            $show->contentWarnings()->attach($warning->id, ['severity' => $severity]);
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



