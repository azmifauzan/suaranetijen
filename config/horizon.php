<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Name
    |--------------------------------------------------------------------------
    |
    | This name appears in notifications and in the Horizon UI. Unique names
    | can be useful while running multiple instances of Horizon within an
    | application, allowing you to identify the Horizon you're viewing.
    |
    */

    'name' => env('HORIZON_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug((string) env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    'waits' => [
        'redis:default' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => env('HORIZON_TRIM_RECENT', 60),
        'pending' => env('HORIZON_TRIM_PENDING', 60),
        'completed' => env('HORIZON_TRIM_COMPLETED', 60),
        'recent_failed' => env('HORIZON_TRIM_RECENT_FAILED', 1440),
        'failed' => env('HORIZON_TRIM_FAILED', 1440),
        'monitored' => env('HORIZON_TRIM_MONITORED', 1440),
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    'silenced_tags' => [
        // 'notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

    'defaults' => [
        'supervisor-critical' => [
            'connection' => 'redis',
            'queue' => ['critical', 'aggregate'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 2,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
        ],
        'supervisor-crawl' => [
            'connection' => 'redis',
            'queue' => ['discovery', 'crawl'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 2,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
        ],
        'supervisor-analysis' => [
            'connection' => 'redis',
            'queue' => ['analysis'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
        ],
        // Isolates YouTube's high-volume comment-page fetch fan-out from the
        // shared 'crawl' queue (see FetchSourceDocumentJob and SourceSeeder's
        // youtube 'queue' crawl_policy) so a low-rate-limit source like
        // Kaskus never again waits behind hundreds of YouTube fetches for a
        // FIFO turn — confirmed live 5-6 Sep 2026 as queue-position
        // starvation, not an actual rate limit.
        'supervisor-crawl-youtube' => [
            'connection' => 'redis',
            'queue' => ['crawl-youtube'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 2,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
        ],
        'supervisor-maintenance' => [
            'connection' => 'redis',
            'queue' => ['maintenance'],
            'balance' => false,
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-critical' => [
                'maxProcesses' => 4,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-crawl' => [
                'maxProcesses' => 6,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-analysis' => [
                'maxProcesses' => 4,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-maintenance' => [],
        ],

        'staging' => [
            'supervisor-critical' => [],
            // Raised from the default 2 (5 Sep 2026): FlareSolverr-routed fetches
            // (SerayaMotor/Kaskus/IndoForum) take far longer per job than a plain
            // HTTP fetch and share this same queue with fast sources
            // (DiskusiWebHosting, YouTube's comment fetches) — 2 workers let the
            // crawl queue back up to 900+ pending, starving the fast sources
            // behind the slow ones.
            'supervisor-crawl' => [
                'maxProcesses' => 6,
            ],
            'supervisor-analysis' => [
                'maxProcesses' => 3,
            ],
            'supervisor-maintenance' => [],
        ],

        // Distributed crawl workers (5 Sep 2026), split by host headroom
        // (8 Sep 2026): they don't serve web traffic or run the scheduler,
        // and each container is docker-capped to a `cpus` budget sized to
        // what its shared host can actually spare (see docker-compose.yml
        // on each worker) — the maxProcesses below are sized to not
        // massively oversubscribe that cpus cap. Horizon requires
        // minProcesses >= 1 for every supervisor in every environment (0 is
        // rejected at boot — confirmed live, it crash-loops Horizon on
        // *every* host reading this file, not just the one selecting this
        // environment), so a queue a host shouldn't really be doing can only
        // be minimized to a single idle worker, not fully disabled.
        //
        // worker1 (20 cores, most headroom of the two workers, cpus:3
        // container cap): does the heavy lifting — FlareSolverr-bound crawl
        // (Kaskus/SerayaMotor/IndoForum) and YouTube's comment fan-out.
        'worker-heavy' => [
            'supervisor-critical' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
            'supervisor-crawl' => [
                'maxProcesses' => 6,
            ],
            'supervisor-crawl-youtube' => [
                'maxProcesses' => 3,
            ],
            'supervisor-analysis' => [
                'maxProcesses' => 3,
            ],
            'supervisor-maintenance' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
        ],

        // worker2 (least headroom of the two workers, the host that fell
        // over from FlareSolverr+crawl load on 8 Sep 2026, cpus:1.5
        // container cap): analysis only —
        // no FlareSolverr, no YouTube fan-out. supervisor-crawl and
        // supervisor-crawl-youtube stay pinned at 1 process (Horizon's
        // floor) purely so the environment definition is valid; they should
        // sit idle almost all the time.
        'worker-light' => [
            'supervisor-critical' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
            'supervisor-crawl' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
            'supervisor-crawl-youtube' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
            'supervisor-analysis' => [
                'maxProcesses' => 2,
            ],
            'supervisor-maintenance' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
        ],

        'local' => [
            'supervisor-critical' => [],
            'supervisor-crawl' => [],
            'supervisor-analysis' => [],
            'supervisor-maintenance' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Watcher Configuration
    |--------------------------------------------------------------------------
    |
    | The following list of directories and files will be watched when using
    | the `horizon:listen` command. Whenever any directories or files are
    | changed, Horizon will automatically restart to apply all changes.
    |
    */

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];
