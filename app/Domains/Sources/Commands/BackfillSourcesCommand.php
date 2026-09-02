<?php

namespace App\Domains\Sources\Commands;

use App\Domains\Ingestion\Jobs\DiscoverSourceDocumentsJob;
use App\Domains\Sources\Models\Source;
use Illuminate\Console\Command;

class BackfillSourcesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sources:backfill {source? : Optional source key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch incremental discovery for enabled operational sources';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Source::query()->enabled()->operational();
        $sourceKey = $this->argument('source');

        if (is_string($sourceKey) && $sourceKey !== '') {
            $query->where('key', $sourceKey);
        }

        $sources = $query->get();
        foreach ($sources as $source) {
            DiscoverSourceDocumentsJob::dispatch($source);
        }

        $this->info("Dispatched discovery for {$sources->count()} source(s).");

        return self::SUCCESS;
    }
}
