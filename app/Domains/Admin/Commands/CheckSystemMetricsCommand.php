<?php

namespace App\Domains\Admin\Commands;

use App\Domains\Sources\Models\IngestionFailure;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Models\SourcePreflightLog;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckSystemMetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:metrics {--fail-on-breach : Return non-zero exit code if any alert threshold is breached}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check operational metrics (queue depth, failure rates, crawl rates) per docs/16 and alert on breaches';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $alerts = [];
        $metrics = [];
        $now = CarbonImmutable::now();
        $oneDayAgo = $now->subDay();

        // 1. Queue depth and age
        $queueDepth = 0;
        $oldestJobAgeSeconds = 0;
        try {
            $queueDepth = DB::table('jobs')->count();
            $oldestJob = DB::table('jobs')->orderBy('created_at')->first();
            if ($oldestJob) {
                $oldestJobAgeSeconds = $now->timestamp - $oldestJob->created_at;
            }
        } catch (\Throwable) {
            // table might not exist in some environments
        }

        $metrics[] = ['Queue Depth (pending jobs)', $queueDepth];
        $metrics[] = ['Oldest Job Age', "{$oldestJobAgeSeconds}s"];

        if ($queueDepth > 1000) {
            $alerts[] = "Queue depth high: {$queueDepth} pending jobs";
        }
        if ($oldestJobAgeSeconds > 3600) {
            $alerts[] = "Oldest job age critical: {$oldestJobAgeSeconds}s in queue";
        }

        // 2. Failed jobs rate (24h)
        $failedJobs24h = 0;
        try {
            $failedJobs24h = DB::table('failed_jobs')
                ->where('failed_at', '>=', $oneDayAgo)
                ->count();
        } catch (\Throwable) {
            // ignore
        }

        $metrics[] = ['Failed Jobs (last 24h)', $failedJobs24h];
        if ($failedJobs24h > 20) {
            $alerts[] = "High failed jobs count in last 24h: {$failedJobs24h}";
        }

        // 3. Crawl success rate per source (last 24h)
        $sources = Source::query()->enabled()->get();
        foreach ($sources as $source) {
            $logs = SourcePreflightLog::query()
                ->where('source_id', $source->id)
                ->where('created_at', '>=', $oneDayAgo)
                ->get();

            $totalLogs = $logs->count();
            if ($totalLogs > 0) {
                $healthyLogs = $logs->where('status', 'healthy')->count();
                $rate = round(($healthyLogs / $totalLogs) * 100, 1);
                $metrics[] = ["Crawl Preflight Success: {$source->name}", "{$rate}% ({$healthyLogs}/{$totalLogs})"];

                if ($rate < 75.0) {
                    $alerts[] = "Crawl preflight success low for {$source->name}: {$rate}%";
                }
            } else {
                $metrics[] = ["Crawl Preflight Status: {$source->name}", $source->health_state->value];
                if (! $source->health_state->isOperational()) {
                    $alerts[] = "Source {$source->name} is not operational: {$source->health_state->value}";
                }
            }
        }

        // 4. Parser/extraction failure rate (last 24h)
        $extractFailures24h = IngestionFailure::query()
            ->where('stage', 'extract')
            ->where('created_at', '>=', $oneDayAgo)
            ->count();

        $itemsCreated24h = SourceItem::query()
            ->where('created_at', '>=', $oneDayAgo)
            ->count();

        $metrics[] = ['Parser/Extract Failures (last 24h)', $extractFailures24h];
        $metrics[] = ['Source Items Extracted (last 24h)', $itemsCreated24h];

        if ($extractFailures24h > 10) {
            $alerts[] = "High parser extraction failure count in last 24h: {$extractFailures24h}";
        }

        // Display results
        $this->table(['Metric', 'Value'], $metrics);

        if (! empty($alerts)) {
            $this->warn("\n⚠️ Operational Alert Breaches Detected:");
            foreach ($alerts as $alert) {
                $this->error("- {$alert}");
                Log::warning("[SystemMetricsAlert] {$alert}");
            }

            if ($this->option('fail-on-breach')) {
                return self::FAILURE;
            }
        } else {
            $this->info("\n✓ All system metrics are within normal operational thresholds.");
        }

        return self::SUCCESS;
    }
}
