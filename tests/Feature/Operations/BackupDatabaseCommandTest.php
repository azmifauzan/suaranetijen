<?php

use App\Domains\Entities\Models\Entity;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->storageDir = sys_get_temp_dir().'/backup-test-'.uniqid();
    $this->app->useStoragePath($this->storageDir);
    $this->defaultConnection = config('database.default');
});

afterEach(function () {
    config(['database.default' => $this->defaultConnection]);
    File::deleteDirectory($this->storageDir);
});

function backupFiles(): array
{
    return File::glob(storage_path('app/backups/db_backup_*.enc'));
}

test('a dump larger than one chunk is encrypted per line, verifies, and decrypts back intact', function () {
    $marker = 'penanda-'.str_repeat('x', 5 * 1024 * 1024);
    Entity::factory()->create(['description' => $marker]);

    $this->artisan('backup:database')->assertSuccessful();

    $lines = array_filter(explode("\n", File::get(backupFiles()[0])));
    expect($lines)->toHaveCount(2);
    $this->artisan('backup:database', ['--verify' => true])->assertSuccessful();

    $target = storage_path('restore.sql');
    $this->artisan('backup:database', ['--decrypt-to' => $target])->assertSuccessful();
    expect(File::get($target))
        ->toStartWith('-- SuaraNetijen Database Backup Export')
        ->toContain($marker);
});

test('verify fails when a backup line was tampered with', function () {
    $this->artisan('backup:database')->assertSuccessful();
    File::put(backupFiles()[0], "bukan-envelope\n");

    $this->artisan('backup:database', ['--verify' => true])
        ->expectsOutputToContain('Backup verification failed')
        ->assertFailed();
});

test('on postgres a pg_dump custom-format dump is written straight to a file and encrypted', function () {
    config(['database.default' => 'pgsql']);
    Process::fake(function (PendingProcess $process) {
        $file = collect($process->command)->first(fn ($arg) => str_starts_with($arg, '--file='));
        File::put(substr($file, strlen('--file=')), "PGDMP\x01custom-format-body");

        return Process::result();
    });

    $this->artisan('backup:database')->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process) => $process->command[0] === 'pg_dump'
        && in_array('--format=custom', $process->command, true)
        && $process->timeout === 3600);
    expect(backupFiles())->toHaveCount(1)
        ->and(File::glob(storage_path('app/backups/dump_*')))->toBeEmpty();
    $this->artisan('backup:database', ['--verify' => true])->assertSuccessful();
});

test('on postgres a failing pg_dump fails the command and writes no backup', function () {
    config(['database.default' => 'pgsql']);
    Process::fake(['*' => Process::result(errorOutput: 'pg_dump: error: server version mismatch', exitCode: 1)]);

    $this->artisan('backup:database')
        ->expectsOutputToContain('pg_dump failed: pg_dump: error: server version mismatch')
        ->assertFailed();

    expect(backupFiles())->toBeEmpty()
        ->and(File::glob(storage_path('app/backups/dump_*')))->toBeEmpty();
});
