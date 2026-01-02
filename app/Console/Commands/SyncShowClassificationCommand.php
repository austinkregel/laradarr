<?php

namespace App\Console\Commands;

use App\Contracts\ShowClassificationServiceContract;
use App\Models\Show;
use Illuminate\Console\Command;

class SyncShowClassificationCommand extends Command
{
    protected $signature = 'sync:classify {--show_id=}';

    protected $description = 'Infer and attach categories/content warnings for shows using keyword rules and metadata';

    public function handle(ShowClassificationServiceContract $service): int
    {
        $showId = $this->option('show_id');

        if ($showId !== null) {
            $show = Show::query()->find((int) $showId);
            if (!$show) {
                $this->error('Show not found for show_id='.(int) $showId);
                return self::FAILURE;
            }
            $service->classifyAndAttach($show);
            $this->info('Classified show_id='.(int) $showId);
            return self::SUCCESS;
        }

        $count = 0;
        Show::query()
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(250, function ($shows) use (&$count, $service) {
                foreach ($shows as $show) {
                    $full = Show::query()->find((int) $show->id);
                    if (!$full) {
                        continue;
                    }
                    $service->classifyAndAttach($full);
                    $count++;
                }
            });

        $this->info("Classified {$count} shows");
        return self::SUCCESS;
    }
}



