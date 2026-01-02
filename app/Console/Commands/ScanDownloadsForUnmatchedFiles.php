<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ScanDownloadsForUnmatchedFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scan:downloads-for-unmatched-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $service = new \Illuminate\Filesystem\Filesystem();
        $patternForSeasonAndEpisode = '/(?:S?(\d{1,2}))?E(\d{1,3})/i';
        $patternForEpisode = '/(E?(\d{1,2}))/i';
        $sonarrService = app(\App\Contracts\SonarrServiceContract::class);
        $downloads = $service->allFiles('/data/Downloads/tv-sonarr/');

        $extensions = [];
        $filesWeCanAttach = [];
        $shows = \App\Models\Show::query()
            ->where('name', '!=', 'K')
            ->get()
            ->map(function ($show) {
                $carry = [];
                $names = array_merge([
                    $show->name,
                ], $show->aliases ?? []);

                foreach ($names as $name) {
                    $carry[] = strtolower($name);
                    $carry[] = strtolower($name);
                    $carry[] = strtolower(\Illuminate\Support\Str::slug($name));
                    $carry[] = strtolower(str_replace(' ', '.', $name));
                    // Preg_replace all non-alphanumeric characters, except the comma and hyphen;
                    $carry[] = strtolower(preg_replace('/[^a-zA-Z0-9,\.\-\ ]/', '', $name));
                }

                $show->aliases = array_filter($carry);
                return $show;
            }, []);

        $filesWeCanPotentiallyDelete = [];
        foreach ($downloads as $file) {
            $extension = $file->getExtension();
            if (in_array($extension, [
                'mobileconfig',
                'sci',
                'svg',
                'sqlite',
                'xml',
                'mp3',
                'm4a',
                'flac',
                'rar',
                'wav',
                'log',
                'accurip',
                'cue',
                'txt',
                'jpg',
                'png',
                '!qB',
                'ogg',
                'afpk',
                'sfv',
                'torrent',
                'exe',
                'website',
                'lnk',
                'mta',
                'sub',
                'sup',
                'tif',
                'bat',
                'ts',
                'zip',
                'xci',
                'm3u',
                'nfo',
                'm3u8',
                'pdf',
                'zip',
                'srt',
                'idx',

            ])) {
                continue;
            }
            // We want to take the file name and try to find potential string matches to shows in our database.
            // If we can match a show, we want to extract the season and episode number from the file name.
            $fileName = $file->getFilename();
            $path = $file->getRealPath();
            $this->info("Trying to find show match for $fileName");
            /** @var \App\Models\Show|null $show */
            $show = $shows->first(function ($show) use ($fileName) {
                return collect($show->aliases)->filter(function ($alias) use ($fileName) {
                        // If the alias is shorter than 3 characters, we want to ignore it.
                        if (strlen($alias) < 3) {
                            return false;
                        }

                        return str_contains(strtolower($fileName), $alias);
                    })->count() > 0;
            });

            if (!$show) {
                $this->warn("Could not find show for $path.");
                continue;
            }

            $season = 1;
            $episode = 1;

            if (preg_match($patternForSeasonAndEpisode, $filename = $file->getFilename(), $matches)) {
                $season = max(1, (int)str_replace('s', '', strtolower($matches[1])));
                $episode = (int)str_replace('e', '', strtolower($matches[2]));
            } elseif (preg_match($patternForEpisode, $filename = $file->getFilename(), $matches)) {
                $episode = (int)str_replace('e', '', strtolower($matches[0]));
            }

            $episodes = $show->episodes()
                ->whereHas('season', function ($query) use ($season) {
                    $query->where('season', $season);
                })
                ->where('episode_number', $episode)
                ->get();

            if ($episodes->count() === 0) {
                $this->warn("Could not find episode for $path. With matched $season, and $episode");
                continue;
            }

            if ($episodes->count() > 1) {
                $this->warn("Found multiple episodes for $path. With matched $season, and $episode");
                continue;
            }

            $episode = $episodes->first();
            $this->info("Found show: {$show->name} for file: $path");

            $sonarrShow = $sonarrService->getShow($show->sonarr_id) ?? [];

            if (!isset($sonarrShow['path'])) {
                if ($sonarrShow['status'] === 404) {
                    $this->warn("Show was deleted in sonarr $path. With matched $season, and $episode");
                    continue;
                }
            }

            if ($episode->media()->count() > 0) {
                $this->warn("Episode already has media attached. Skipping.");
                $filesWeCanPotentiallyDelete[] = "rm -rv " . escapeshellarg($path);
                continue;
            }

            if ($episode->media()->count() === 0) {
                $name = $episode->name . ' - S' . str_pad($season, 2, '0', STR_PAD_LEFT) . 'E' . str_pad($episode->episode_number, 2, '0', STR_PAD_LEFT) . '.' . $file->getExtension();


                $filesWeCanAttach[] = "mkdir -p " . escapeshellarg($sonarrShow['path'] . "/Season $season");
                $filesWeCanAttach[] = "mv " . escapeshellag($path) . " " . escapeshellarg($sonarrShow['path'] . "/Season $season/$name");
            }
        }
    }
}
