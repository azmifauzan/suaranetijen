<?php

namespace App\Domains\Admin\Commands;

use App\Domains\Sources\Models\IngestionFailure;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Models\SourcePreflightLog;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

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

        // 0. Redis reachability (session/cache/queue all depend on it — an outage here
        // takes down the public site and silently stalls every scheduled crawl job).
        $redisLatencyMs = null;
        try {
            $start = microtime(true);
            Redis::connection()->ping();
            $redisLatencyMs = round((microtime(true) - $start) * 1000, 1);
            $metrics[] = ['Redis Reachability', "OK ({$redisLatencyMs}ms)"];
        } catch (\Throwable $e) {
            $metrics[] = ['Redis Reachability', 'UNREACHABLE'];
            $alerts[] = "Redis unreachable: {$e->getMessage()}";
        }

        // 0b. Inertia SSR Server status (docs/31 Fase 0 item 0.3)
        $ssrEnabled = (bool) config('inertia.ssr.enabled', true);
        if ($ssrEnabled) {
            $ssrUrl = rtrim((string) config('inertia.ssr.url', 'http://127.0.0.1:13714'), '/');
            $ssrFailures24h = (int) Cache::get('ssr:failures_count_24h', 0);
            $lastSsrFailedAt = Cache::get('ssr:last_failed_at');

            try {
                $response = Http::timeout(1)->get("{$ssrUrl}/health");
                if ($response->successful() || $response->status() === 404 || $response->status() === 200) {
                    $metrics[] = ['Inertia SSR Reachability', 'OK'];
                } else {
                    $metrics[] = ['Inertia SSR Reachability', "ERROR ({$response->status()})"];
                    $alerts[] = "Inertia SSR server returned unexpected status {$response->status()}";
                }
            } catch (\Throwable $e) {
                $metrics[] = ['Inertia SSR Reachability', 'UNREACHABLE'];
                $alerts[] = "Inertia SSR server unreachable at {$ssrUrl}: {$e->getMessage()}";
            }

            $metrics[] = ['SSR Render Failures (24h)', $ssrFailures24h];
            if ($ssrFailures24h > 10) {
                $alerts[] = "High SSR render failures count in last 24h: {$ssrFailures24h} (last: {$lastSsrFailedAt})";
            }
        } else {
            $metrics[] = ['Inertia SSR', 'DISABLED'];
        }

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

        // 5. One entity swallowing a source (last 24h). A whole-page scrape reads boilerplate as an
        // opinion on every page (IndoForum / GitHub, Sep 2026: 96% of a source, 100% positive).
        $concentration = DB::table('sentiment_observations as o')
            ->join('sources as s', 's.id', '=', 'o.source_id')
            ->join('entities as e', 'e.id', '=', 'o.entity_id')
            ->where('o.created_at', '>=', $oneDayAgo)
            ->groupBy('s.id', 's.name', 'e.id', 'e.name')
            ->selectRaw('s.id as source_id, s.name as source, e.name as entity, count(*) as n')
            ->get()
            ->groupBy('source_id');

        foreach ($concentration as $rows) {
            $total = (int) $rows->sum('n');
            $top = $rows->sortByDesc('n')->first();
            $share = $total > 0 ? (int) round($top->n / $total * 100) : 0;

            if ($total >= 50 && $share > 60) {
                $alerts[] = "{$top->source} gave {$share}% of its last-24h opinions ({$top->n} of {$total}) to one entity ({$top->entity}); check for a boilerplate match";
            }
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
