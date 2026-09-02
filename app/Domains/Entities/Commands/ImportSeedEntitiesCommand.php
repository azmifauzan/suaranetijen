<?php

namespace App\Domains\Entities\Commands;

use App\Domains\Entities\Services\SeedEntityImporter;
use Illuminate\Console\Command;

class ImportSeedEntitiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-seed-entities {--file=database/data/seed_entities.csv : The CSV file to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import seed entities, categories, and aliases from CSV';

    /**
     * Execute the console command.
     */
    public function handle(SeedEntityImporter $importer): int
    {
        $filePath = (string) $this->option('file');

        if (! str_starts_with($filePath, '/')) {
            $filePath = base_path($filePath);
        }

        $this->info("Importing seed entities from: {$filePath}");

        $result = $importer->import($filePath);

        $this->info('Import completed successfully!');
        $this->line("- Categories: {$result['categories']}");
        $this->line("- Entities: {$result['entities']}");
        $this->line("- Aliases: {$result['aliases']}");

        return self::SUCCESS;
    }
}
