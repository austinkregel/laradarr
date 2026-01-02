<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\MediaTaggingServiceContract;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Show;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class MediaTaggingService implements MediaTaggingServiceContract
{
    /**
     * Analyze a media record and apply dub/sub tags + language metadata.
     *
     * Assumptions (heuristics):
     * - For anime: Japanese audio implies "sub", English audio implies "dub".
     * - Sonarr's `languages` on episodeFile is treated as audio track languages.
     */
    public function tagMediaFile(SpatieMedia $media, Show|Movie|null $model = null): void
    {
        $audioTracks = $this->extractAudioTracks($media);
        $subtitleTracks = $this->extractSubtitleTracks($media);

        $show = $model instanceof Show ? $model : ($this->guessShowFromMedia($media) ?? null);
        $movie = $model instanceof Movie ? $model : ($this->guessMovieFromMedia($media) ?? null);

        $analysis = $this->detectAudioType($show, $movie, $audioTracks, $subtitleTracks);

        // Persist analysis back onto custom_properties.
        $custom = (array) ($media->custom_properties ?? []);
        $custom['audio_tracks'] = $audioTracks;
        $custom['subtitle_tracks'] = $subtitleTracks;
        $custom['primary_audio_language'] = $analysis['primary_audio_language'];
        $custom['has_dub'] = $analysis['has_dub'];
        $custom['has_sub'] = $analysis['has_sub'];
        $media->custom_properties = $custom;
        $media->save();

        // Apply tags (requires Media model to be taggable; we wire that up in the next step).
        if (method_exists($media, 'syncTags')) {
            $tags = [];
            if ($analysis['has_dub']) {
                $tags[] = 'dub';
                foreach ($analysis['dub_languages'] as $lang) {
                    $tags[] = 'dub-'.Str::slug($lang);
                }
            }
            if ($analysis['has_sub']) {
                $tags[] = 'sub';
                foreach ($analysis['sub_languages'] as $lang) {
                    $tags[] = 'sub-'.Str::slug($lang);
                }
            }

            $media->syncTags($tags);
        }
    }

    public function extractAudioTracks(SpatieMedia $media): array
    {
        $langs = data_get($media, 'custom_properties.languages', []);
        $langs = array_values(array_filter(array_map(fn ($l) => is_string($l) ? trim($l) : null, (array) $langs)));
        $langs = array_values(array_unique($langs));
        sort($langs);
        return $langs;
    }

    public function extractSubtitleTracks(SpatieMedia $media): array
    {
        // If we later ingest subtitle track languages from mediainfo/ffprobe, we’ll populate it here.
        $langs = data_get($media, 'custom_properties.subtitle_tracks', []);
        $langs = array_values(array_filter(array_map(fn ($l) => is_string($l) ? trim($l) : null, (array) $langs)));
        $langs = array_values(array_unique($langs));
        sort($langs);
        return $langs;
    }

    public function detectAudioType(?Show $show, ?Movie $movie, array $audioTracks, array $subtitleTracks): array
    {
        // For movies, we don't have a "type" field, so we'll use a simpler heuristic
        $type = $show ? strtolower((string) ($show->type ?? '')) : '';
        $isAnime = $type === 'anime';

        $hasJapaneseAudio = in_array('Japanese', $audioTracks, true);
        $hasEnglishAudio = in_array('English', $audioTracks, true);

        $hasDub = false;
        $hasSub = false;
        $dubLanguages = [];
        $subLanguages = [];

        if ($isAnime) {
            // Heuristic: Japanese audio => subbed version, English audio => dubbed version.
            $hasSub = $hasJapaneseAudio;
            $hasDub = $hasEnglishAudio;
            if ($hasDub) {
                $dubLanguages[] = 'English';
            }
            // Sub language is ideally subtitle track language; fallback to none.
            $subLanguages = $subtitleTracks;
        } else {
            // For non-anime, we treat "dub" as simply "has audio", and "sub" as "has subtitle tracks".
            $hasDub = !empty($audioTracks);
            $hasSub = !empty($subtitleTracks);
            $dubLanguages = $audioTracks;
            $subLanguages = $subtitleTracks;
        }

        return [
            'has_dub' => $hasDub,
            'has_sub' => $hasSub,
            'primary_audio_language' => $audioTracks[0] ?? null,
            'dub_languages' => $dubLanguages,
            'sub_languages' => $subLanguages,
        ];
    }

    protected function guessShowFromMedia(SpatieMedia $media): ?Show
    {
        $model = $media->model;
        if ($model instanceof Episode) {
            $model->loadMissing('season.show');
            return $model->season?->show;
        }

        return null;
    }

    protected function guessMovieFromMedia(SpatieMedia $media): ?Movie
    {
        $model = $media->model;
        if ($model instanceof Movie) {
            return $model;
        }

        return null;
    }
}



