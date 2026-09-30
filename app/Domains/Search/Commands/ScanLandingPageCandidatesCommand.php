<?php

namespace App\Domains\Search\Commands;

use App\Domains\Search\Enums\SearchLandingPageStatus;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Search\Services\CandidateScannerService;
use App\Domains\Search\Services\TopicDraftWriter;
use Illuminate\Console\Command;

class ScanLandingPageCandidatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'landing-pages:scan-candidates {--skip-llm : Skip LLM drafting step}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan candidate topic landing pages from search queries and category-theme pairs';

    /**
     * Execute the console command.
     */
    public function handle(CandidateScannerService $scanner, TopicDraftWriter $draftWriter): int
    {
        $this->info('Scanning topic landing page candidates...');

        $result = $scanner->scan();

        $this->info("Created {$result['search_queries']} candidates from search queries.");
        $this->info("Created {$result['category_themes']} candidates from category x theme.");

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        if (! $this->option('skip-llm')) {
            $maxCalls = (int) config('landing_pages.scan_max_llm_calls', 20);
            $candidates = SearchLandingPage::query()
                ->where('status', SearchLandingPageStatus::Candidate)
                ->whereNull('llm_drafted_at')
                ->orderByDesc('candidate_signal')
                ->limit($maxCalls)
                ->get();

            $this->info("Drafting up to {$candidates->count()} candidates with LLM...");

            $draftedCount = 0;
            foreach ($candidates as $candidate) {
                $status = $draftWriter->draft($candidate);
                $this->line("Candidate #{$candidate->id} '{$candidate->keyword}' -> {$status->value}");
                if ($status === SearchLandingPageStatus::Draft) {
                    $draftedCount++;
                }
            }

            $this->info("LLM drafting finished: {$draftedCount} candidates converted to draft.");
        }

        return self::SUCCESS;
    }
}
