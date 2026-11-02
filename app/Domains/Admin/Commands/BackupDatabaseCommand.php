<?php

namespace App\Domains\Admin\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class BackupDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:database {--verify : Verify the integrity of the most recent backup} {--prune-only : Only run retention pruning without creating a new backup} {--decrypt-to= : Write the latest backup decrypted to this path for pg_restore}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create encrypted daily PostgreSQL backup with 7 daily + 4 weekly retention (docs/16)';

    /**
     * Raw bytes encrypted per line. Each line is one Crypt envelope, so neither writing nor
     * verifying a backup ever holds more than one chunk in memory (the scheduler container has
     * 256MB; encrypting a whole 5GB dump as one string was OOM-killed every night).
     */
    private const CHUNK_BYTES = 4 * 1024 * 1024;

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

        if (is_string($this->option('decrypt-to'))) {
            return $this->decryptLatest($backupDir, $this->option('decrypt-to'));
        }

        if (! $this->option('prune-only') && ! $this->createBackup($backupDir)) {
            return self::FAILURE;
        }

        $this->pruneOldBackups($backupDir);

        return self::SUCCESS;
    }

    /**
     * Create an encrypted database dump.
     */
    protected function createBackup(string $backupDir): bool
    {
        $this->info('Starting database backup...');
        $dumpPath = tempnam($backupDir, 'dump_');

        try {
            if (! $this->dumpDatabase($dumpPath) || filesize($dumpPath) === 0) {
                $this->error('Failed to generate database dump.');

                return false;
            }

            $filename = 'db_backup_'.CarbonImmutable::now()->format('Y-m-d_His').'.enc';
            $filepath = "{$backupDir}/{$filename}";
            $in = $this->openStream($dumpPath, 'rb');
            $out = $this->openStream($filepath, 'wb');

            while (($chunk = fread($in, self::CHUNK_BYTES)) !== false && $chunk !== '') {
                fwrite($out, Crypt::encryptString($chunk)."\n");
            }

            fclose($in);
            fclose($out);

            $sizeKb = round(filesize($filepath) / 1024, 2);
            $this->info("Encrypted backup saved: {$filename} ({$sizeKb} KB)");

            return true;
        } finally {
            File::delete($dumpPath);
        }
    }

    /**
     * Dump the database into a file: pg_dump custom format on PostgreSQL, SQL text elsewhere.
     *
     * A missing or failing pg_dump is a failed backup, never a silent fallback: the row-by-row
     * export below loads whole tables into memory and cannot restore a production database.
     */
    protected function dumpDatabase(string $path): bool
    {
        if (config('database.default') !== 'pgsql') {
            return File::put($path, $this->exportDatabaseViaEloquent()) !== false;
        }

        $process = Process::timeout(3600)
            ->env(['PGPASSWORD' => (string) config('database.connections.pgsql.password')])
            ->run([
                'pg_dump',
                '-h', (string) config('database.connections.pgsql.host', '127.0.0.1'),
                '-p', (string) config('database.connections.pgsql.port', '5432'),
                '-U', (string) config('database.connections.pgsql.username'),
                '--format=custom',
                '--file='.$path,
                (string) config('database.connections.pgsql.database'),
            ]);

        if (! $process->successful()) {
            $this->error('pg_dump failed: '.trim($process->errorOutput()));

            return false;
        }

        return true;
    }

    /**
     * SQL text export for non-PostgreSQL connections (the SQLite test suite).
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
     * Verify the most recent encrypted backup decrypts completely and starts like a dump.
     */
    protected function verifyBackup(string $backupDir): int
    {
        $latest = $this->latestBackup($backupDir);
        if ($latest === null) {
            $this->error('No backup files found to verify.');

            return self::FAILURE;
        }

        $this->info('Verifying latest backup: '.basename($latest));

        try {
            $bytes = $this->decryptChunks($latest, function (string $chunk, int $index): void {
                if ($index === 0 && ! str_starts_with($chunk, 'PGDMP') && ! str_starts_with($chunk, '-- SuaraNetijen Database Backup Export')) {
                    throw new RuntimeException('Decrypted content does not start like a database dump.');
                }
            });
        } catch (Throwable $e) {
            $this->error('Backup verification failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($bytes === 0) {
            $this->error('Backup file decrypted to empty content.');

            return self::FAILURE;
        }

        $sizeKb = round($bytes / 1024, 2);
        $this->info("Backup verified successfully! Decrypted size: {$sizeKb} KB.");

        return self::SUCCESS;
    }

    /**
     * Write the latest backup decrypted, ready for `pg_restore --clean --if-exists -d <db> <path>`.
     */
    protected function decryptLatest(string $backupDir, string $target): int
    {
        $latest = $this->latestBackup($backupDir);
        if ($latest === null) {
            $this->error('No backup files found to decrypt.');

            return self::FAILURE;
        }

        $out = $this->openStream($target, 'wb');

        try {
            $bytes = $this->decryptChunks($latest, fn (string $chunk) => fwrite($out, $chunk));
        } catch (Throwable $e) {
            $this->error('Backup decryption failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            fclose($out);
        }

        $this->info('Decrypted '.basename($latest)." to {$target} ({$bytes} bytes).");

        return self::SUCCESS;
    }

    /**
     * @return resource
     */
    private function openStream(string $path, string $mode)
    {
        $stream = fopen($path, $mode);
        if ($stream === false) {
            throw new RuntimeException("Cannot open {$path}.");
        }

        return $stream;
    }

    private function latestBackup(string $backupDir): ?string
    {
        $files = File::glob("{$backupDir}/db_backup_*.enc");
        rsort($files);

        return $files[0] ?? null;
    }

    /**
     * Decrypt a backup line by line, handing each chunk to $consume; returns the byte count.
     *
     * @param  \Closure(string, int): mixed  $consume
     */
    private function decryptChunks(string $path, \Closure $consume): int
    {
        $in = $this->openStream($path, 'rb');
        $bytes = 0;
        $index = 0;

        try {
            while (($line = fgets($in)) !== false) {
                $line = rtrim($line, "\n");
                if ($line === '') {
                    continue;
                }

                $chunk = Crypt::decryptString($line);
                $consume($chunk, $index++);
                $bytes += strlen($chunk);
            }
        } finally {
            fclose($in);
        }

        return $bytes;
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
