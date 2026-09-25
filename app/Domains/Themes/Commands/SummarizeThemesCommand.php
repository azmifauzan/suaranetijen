<?php

namespace App\Domains\Themes\Commands;

use App\Domains\Themes\Jobs\SummarizeEntityThemesJob;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use Illuminate\Console\Command;

class SummarizeThemesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'themes:summarize';

    /**
     * @var string
     */
    protected $description = 'Queue Ringkasan Suara Netijen regeneration for every entity with theme snapshots (skips fresh ones)';

    public function handle(): int
    {
        if (config('themes.extractor') !== 'llm') {
            $this->warn('THEMES_EXTRACTOR is not llm; summaries need LLM-extracted contexts. Skipping.');

            return self::SUCCESS;
        }

        $entityIds = EntityThemeSnapshot::query()->distinct()->pluck('entity_id');
        $entityIds->each(fn (int $id) => SummarizeEntityThemesJob::dispatch($id));

        $this->info("Queued {$entityIds->count()} summary job(s).");

        return self::SUCCESS;
    }
}
