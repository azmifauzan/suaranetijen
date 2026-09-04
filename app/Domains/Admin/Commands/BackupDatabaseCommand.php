<?php

namespace App\Domains\Admin\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Throwable;

class BackupDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:database {--verify : Verify the integrity of the most recent backup} {--prune-only : Only run retention pruning without creating a new backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create encrypted daily PostgreSQL backup with 7 daily + 4 weekly retention (docs/16)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $backupDir = storage_path('app/backups');
        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        if ($this->option('verify')) {
            return $this->verifyBackup($backupDir);
        }

        if (! $this->option('prune-only')) {
            $this->createBackup($backupDir);
        }

        $this->pruneOldBackups($backupDir);

        return self::SUCCESS;
    }

    /**
     * Create an encrypted database dump.
     */
    protected function createBackup(string $backupDir): void
    {
        $this->info('Starting database backup...');
        $sqlDump = $this->dumpDatabase();

        if (empty($sqlDump)) {
            $this->error('Failed to generate database dump.');

            return;
        }

        $encrypted = Crypt::encryptString($sqlDump);
        $timestamp = CarbonImmutable::now()->format('Y-m-d_His');
        $filename = "db_backup_{$timestamp}.enc";
        $filepath = "{$backupDir}/{$filename}";

        File::put($filepath, $encrypted);

        $sizeKb = round(strlen($encrypted) / 1024, 2);
        $this->info("Encrypted backup saved: {$filename} ({$sizeKb} KB)");
    }

    /**
     * Dump the database to a raw SQL string.
     */
    protected function dumpDatabase(): string
    {
        $connection = config('database.default');

        if ($connection === 'pgsql') {
            $host = config('database.connections.pgsql.host', '127.0.0.1');
            $port = config('database.connections.pgsql.port', '5432');
            $database = config('database.connections.pgsql.database');
            $username = config('database.connections.pgsql.username');
            $password = config('database.connections.pgsql.password');

            // Try running pg_dump
            $process = Process::env([
                'PGPASSWORD' => (string) $password,
            ])->run([
                'pg_dump',
                '-h', (string) $host,
                '-p', (string) $port,
                '-U', (string) $username,
                '--clean',
                '--if-exists',
                (string) $database,
            ]);

            if ($process->successful() && ! empty($process->output())) {
                return $process->output();
            }
        }

        // Fallback dump for SQLite or systems without pg_dump binary
        return $this->exportDatabaseViaEloquent();
    }

    /**
     * Fallback database exporter for testing or environments lacking pg_dump.
     */
    protected function exportDatabaseViaEloquent(): string
    {
        $tables = [
            'users',
            'categories',
            'entities',
            'entity_aliases',
            'sources',
            'source_documents',
            'source_items',
            'sentiment_observations',
            'sentiment_daily',
            'sentiment_snapshots',
            'themes',
            'theme_aliases',
            'theme_observations',
            'entity_theme_daily',
            'entity_theme_snapshots',
            'user_ratings',
            'rating_snapshots',
            'crawl_states',
            'source_preflight_logs',
            'unmatched_mentions',
            'ingestion_failures',
        ];

        $output = "-- SuaraNetijen Database Backup Export\n";
        $output .= '-- Generated at: '.CarbonImmutable::now()->toIso8601String()."\n\n";

        foreach ($tables as $table) {
            try {
                if (! DB::getSchemaBuilder()->hasTable($table)) {
                    continue;
                }

                $rows = DB::table($table)->get();
                $output .= "-- Table: {$table} (".count($rows)." rows)\n";

                foreach ($rows as $row) {
                    $rowArray = (array) $row;
                    $columns = implode(', ', array_keys($rowArray));
                    $values = implode(', ', array_map(function ($val) {
                        if ($val === null) {
                            return 'NULL';
                        }
                        if (is_numeric($val)) {
                            return $val;
                        }

                        return "'".addslashes((string) $val)."'";
                    }, array_values($rowArray)));

                    $output .= "INSERT INTO {$table} ({$columns}) VALUES ({$values});\n";
                }
                $output .= "\n";
            } catch (Throwable) {
                // Ignore missing table during fallback
            }
        }

        return $output;
    }

    /**
     * Verify the most recent encrypted backup can be decrypted and is valid.
     */
    protected function verifyBackup(string $backupDir): int
    {
        $files = File::glob("{$backupDir}/db_backup_*.enc");
        if (empty($files)) {
            $this->error('No backup files found to verify.');

            return self::FAILURE;
        }

        rsort($files);
        $latest = $files[0];
        $this->info('Verifying latest backup: '.basename($latest));

        try {
            $encrypted = File::get($latest);
            $decrypted = Crypt::decryptString($encrypted);

            if (empty($decrypted)) {
                $this->error('Backup file decrypted to empty content.');

                return self::FAILURE;
            }

            $hasValidSql = str_contains($decrypted, 'INSERT INTO')
                || str_contains($decrypted, 'CREATE TABLE')
                || str_contains($decrypted, 'PostgreSQL database dump')
                || str_contains($decrypted, 'SuaraNetijen Database Backup Export');

            if (! $hasValidSql) {
                $this->error('Decrypted content does not appear to contain valid SQL dump.');

                return self::FAILURE;
            }

            $sizeKb = round(strlen($decrypted) / 1024, 2);
            $this->info("Backup verified successfully! Decrypted size: {$sizeKb} KB.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Backup verification failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Apply retention policy: 7 daily + 4 weekly backups (docs/16).
     */
    protected function pruneOldBackups(string $backupDir): void
    {
        $files = File::glob("{$backupDir}/db_backup_*.enc");
        if (count($files) <= 7) {
            return;
        }

        // Sort oldest to newest
        sort($files);

        $now = CarbonImmutable::now();
        $dailyCutoff = $now->subDays(7);
        $weeklyCutoff = $now->subWeeks(5); // 7 daily + 4 weekly = ~5 weeks

        $keptDailyDates = [];
        $keptWeeklyDates = [];

        // Traverse in reverse (newest first) to select what to keep
        rsort($files);

        foreach ($files as $file) {
            $basename = basename($file);
            // Expected filename: db_backup_YYYY-MM-DD_HHmmss.enc
            if (! preg_match('/db_backup_(\d{4}-\d{2}-\d{2})_(\d{6})\.enc/', $basename, $matches)) {
                continue;
            }

            $dateStr = $matches[1];
            $fileDate = CarbonImmutable::parse($dateStr);

            if ($fileDate->greaterThanOrEqualTo($dailyCutoff)) {
                // In the daily window (last 7 days): keep the newest backup for each calendar day
                if (! isset($keptDailyDates[$dateStr])) {
                    $keptDailyDates[$dateStr] = $file;
                } else {
                    File::delete($file);
                    $this->line("Pruned redundant daily backup: {$basename}");
                }
            } elseif ($fileDate->greaterThanOrEqualTo($weeklyCutoff)) {
                // In weekly window (preceding 4 weeks): keep 1 backup per week (by year-week)
                $weekKey = $fileDate->format('Y-W');
                if (! isset($keptWeeklyDates[$weekKey])) {
                    $keptWeeklyDates[$weekKey] = $file;
                } else {
                    File::delete($file);
                    $this->line("Pruned redundant weekly backup: {$basename}");
                }
            } else {
                // Older than weekly cutoff: prune
                File::delete($file);
                $this->line("Pruned expired backup: {$basename}");
            }
        }
    }
}
