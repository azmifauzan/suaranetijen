<?php

namespace App\Domains\Sponsorships\Controllers;

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sponsorships\Enums\SponsoredEntryStatus;
use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Sponsorships\Models\SponsorshipOrder;
use App\Domains\Sponsorships\Services\SponsorLeaderboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SponsorBoardPageController extends Controller
{
    public function index(Request $request, SponsorLeaderboardService $leaderboardService): Response
    {
        $activePeriod = $leaderboardService->getActivePeriod();
        $periods = $leaderboardService->getPeriods();

        $board = $leaderboardService->resolveBoard($request->query('period'));
        $selectedPeriod = $board['period'];
        $leaderboard = $board['leaderboard'];

        $userOrder = null;
        if ($request->has('order_id') && $request->user()) {
            $order = SponsorshipOrder::query()
                ->where('id', $request->query('order_id'))
                ->where('user_id', $request->user()->id)
                ->with('sponsoredEntry.entity')
                ->first();

            if ($order) {
                $userOrder = $this->presentOrder($order);
            }
        }

        $stats = $leaderboardService->getBoardStats(
            $selectedPeriod['key'] === SponsorLeaderboardService::ALL_TIME_KEY ? null : $activePeriod
        );

        return Inertia::render('Sponsor/Index', [
            'periods' => $periods,
            'activePeriod' => [
                'id' => $activePeriod->id,
                'key' => $activePeriod->key,
                'name' => $activePeriod->name,
            ],
            'selectedPeriod' => [
                'id' => $selectedPeriod['id'],
                'key' => $selectedPeriod['key'],
                'name' => $selectedPeriod['name'],
                'is_active' => $selectedPeriod['is_active'],
            ],
            'leaderboard' => $leaderboard,
            'stats' => $stats,
            'minAmount' => (int) config('sponsorship.min_amount', 1000),
            'incrementAmount' => (int) config('sponsorship.increment_amount', 1000),
            'userOrder' => $userOrder,
            'categories' => Category::active()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function paymentStatus(Request $request): Response
    {
        $order = SponsorshipOrder::query()
            ->whereKey($request->integer('order_id'))
            ->where('user_id', $request->user()->id)
            ->with('sponsoredEntry.entity')
            ->firstOrFail();

        return Inertia::render('Sponsor/PaymentStatus', [
            'order' => $this->presentOrder($order),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentOrder(SponsorshipOrder $order): array
    {
        return [
            'id' => $order->id,
            'provider_order_id' => $order->provider_order_id,
            'amount' => $order->amount,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'entity_name' => $order->sponsoredEntry->entity->name,
            'entity_slug' => $order->sponsoredEntry->entity->slug,
            'payment_link_url' => $order->payment_link_url,
        ];
    }

    /**
     * Redirect outbound link with click tracking.
     */
    public function redirect(string $slug): RedirectResponse
    {
        /** @var Entity $entity */
        $entity = Entity::query()->where('slug', $slug)->firstOrFail();

        // Increment clicks on active sponsored entry if exists
        SponsoredEntry::query()
            ->where('entity_id', $entity->id)
            ->where('status', SponsoredEntryStatus::Active)
            ->increment('clicks_count');

        $targetUrl = $entity->website_url;
        if ($targetUrl && ! str_starts_with($targetUrl, 'http://') && ! str_starts_with($targetUrl, 'https://')) {
            $targetUrl = 'https://'.$targetUrl;
        }

        if (! $targetUrl || ! filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            return redirect()->route('entities.show', ['slug' => $entity->slug]);
        }

        $targetUrl = self::buildOutboundUrlWithUtm($targetUrl, $entity->slug, [
            'content' => 'redirect_go',
        ]);

        return redirect()->away($targetUrl, 302, [
            'Referrer-Policy' => 'no-referrer-when-downgrade',
        ]);
    }

    /**
     * Helper to append full GA (Google Analytics) campaign parameters to an outbound URL.
     *
     * @param  array{source?: string, medium?: string, campaign?: string, term?: string, content?: string, id?: string}  $options
     */
    public static function buildOutboundUrlWithUtm(string $url, ?string $slug = null, array $options = []): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        parse_str($parts['query'] ?? '', $queryParams);

        $defaultParams = [
            'ref' => 'suaranetijen.id',
            'utm_source' => $options['source'] ?? 'suaranetijen.id',
            'utm_medium' => $options['medium'] ?? 'sponsor',
            'utm_campaign' => $options['campaign'] ?? 'sponsor_leaderboard',
            'utm_term' => $options['term'] ?? $slug ?? 'suaranetijen',
            'utm_content' => $options['content'] ?? ($slug ? "entry_{$slug}" : 'redirect'),
        ];

        if (! empty($options['id'])) {
            $defaultParams['utm_id'] = $options['id'];
        }

        foreach ($defaultParams as $key => $value) {
            if (! isset($queryParams[$key]) && $value !== null && $value !== '') {
                $queryParams[$key] = $value;
            }
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':'.$parts['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = $parts['path'] ?? '';
        $query = ! empty($queryParams) ? '?'.http_build_query($queryParams) : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return "{$scheme}{$user}{$pass}{$host}{$port}{$path}{$query}{$fragment}";
    }

    /**
     * Record a direct live link click on a sponsored entity (RankUp-style beacon/ping tracking).
     */
    public function trackClick(string $slug): JsonResponse
    {
        /** @var Entity|null $entity */
        $entity = Entity::query()->where('slug', $slug)->first();

        if ($entity) {
            SponsoredEntry::query()
                ->where('entity_id', $entity->id)
                ->where('status', SponsoredEntryStatus::Active)
                ->increment('clicks_count');
        }

        return response()->json([
            'ok' => true,
            'slug' => $slug,
        ]);
    }
}
