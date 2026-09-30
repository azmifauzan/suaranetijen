<?php

namespace App\Domains\Search\Commands;

use App\Domains\Entities\Models\Entity;
use App\Domains\Search\Services\EntitySearchDocumentBuilder;
use Illuminate\Console\Command;

class RebuildSearchDocumentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:rebuild-documents {--chunk=100 : Number of entities to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild search documents for all active searchable entities (docs/30)';

    /**
     * Execute the console command.
     */
    public function handle(EntitySearchDocumentBuilder $builder): int
    {
        $chunkSize = (int) $this->option('chunk');
        $this->info("Rebuilding search documents in chunks of {$chunkSize}...");

        $totalProcessed = 0;

        Entity::query()
            ->active()
            ->searchable()
            ->chunkById($chunkSize, function ($entities) use ($builder, &$totalProcessed) {
                foreach ($entities as $entity) {
                    $builder->buildForEntity($entity);
                    $totalProcessed++;
                }
            });

        $this->info("Successfully rebuilt search documents for {$totalProcessed} entities.");

        return self::SUCCESS;
    }
}
