<?php

namespace App\Domains\Sources\Commands;

use App\Domains\Ingestion\Jobs\PreflightSourceJob;
use App\Domains\Sources\Models\Source;
use Illuminate\Console\Command;

class PreflightSourcesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sources:preflight {source? : Optional source key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch source preflight checks';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Source::query()->where('enabled', true);
        $sourceKey = $this->argument('source');

        if (is_string($sourceKey) && $sourceKey !== '') {
            $query->where('key', $sourceKey);
        }

        $sources = $query->get();
        foreach ($sources as $source) {
            PreflightSourceJob::dispatch($source);
        }

        $this->info("Dispatched preflight for {$sources->count()} source(s).");

        return self::SUCCESS;
    }
}
