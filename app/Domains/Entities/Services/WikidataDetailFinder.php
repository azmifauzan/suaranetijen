<?php

namespace App\Domains\Entities\Services;

use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Entity;
use Illuminate\Support\Facades\Http;

/**
 * Reads reference details and a Commons image for an entity from Wikidata. The item is only accepted when its
 * label equals the entity's name or alias, so a homonym never lends its photo or birth date (precision over recall).
 */
class WikidataDetailFinder
{
    private const ENDPOINT = 'https://www.wikidata.org/w/api.php';

    private const COMMONS = 'https://commons.wikimedia.org/w/api.php';

    /**
     * What a Wikidata description must mention for a product in these categories: "Honda Beat" is both a 1991 kei car
     * and a scooter, and the label alone cannot tell them apart.
     */
    private const CATEGORY_KEYWORDS = [
        'motor' => ['scooter', 'skuter', 'motorcycle', 'sepeda motor', 'motor ', 'moped', 'underbone', 'cub', 'bike'],
        'mobil' => ['car', 'mobil', 'mpv', 'suv', 'sedan', 'hatchback', 'crossover', 'pickup', 'van', 'truck', 'minivan', 'wagon', 'coupe'],
        'smartphone' => ['phone', 'ponsel', 'smartphone', 'tablet'],
    ];

    private const USER_AGENT = 'SuaraNetijen/1.0 (+https://suaranetijen.id/sources)';

    public function __construct(private OfficialWebsiteFinder $finder) {}

    /**
     * @return array{image_url: ?string, image_credit: ?string, details: array<string, mixed>}|null
     */
    public function find(Entity $entity): ?array
    {
        $claims = $this->matchingClaims($entity);
        if ($claims === null) {
            return null;
        }

        $labels = $this->labels(array_values(array_filter([
            $this->itemId($claims, 'P19'),
            $this->itemId($claims, 'P106'),
            $this->itemId($claims, 'P102') ?? $this->itemId($claims, 'P108'),
            $this->itemId($claims, 'P306'),
            $this->itemId($claims, 'P880'),
        ])));

        $details = $entity->type === EntityType::Person
            ? [
                'birth_date' => $this->birthDate($claims),
                'birth_place' => $labels[$this->itemId($claims, 'P19') ?? ''] ?? null,
                'occupation' => $labels[$this->itemId($claims, 'P106') ?? ''] ?? null,
                'affiliation' => $labels[$this->itemId($claims, 'P102') ?? $this->itemId($claims, 'P108') ?? ''] ?? null,
            ]
            : [
                'release_year' => $this->year($claims, ['P6949', 'P577', 'P571']),
                'os' => $labels[$this->itemId($claims, 'P306') ?? ''] ?? null,
                'chipset' => $labels[$this->itemId($claims, 'P880') ?? ''] ?? null,
            ];

        // A brand's P18 is usually its headquarters or a store (Apple -> Apple Park); its logo (P154) identifies it.
        $imageProperties = in_array($entity->type, [EntityType::Brand, EntityType::Service], true) ? ['P154', 'P18'] : ['P18', 'P154'];
        $image = $this->image($claims[$imageProperties[0]][0]['mainsnak']['datavalue']['value'] ?? $claims[$imageProperties[1]][0]['mainsnak']['datavalue']['value'] ?? null);

        return [
            'image_url' => $image['url'] ?? null,
            'image_credit' => $image['credit'] ?? null,
            'details' => array_filter($details, fn ($value) => $value !== null),
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>|null
     */
    private function matchingClaims(Entity $entity): ?array
    {
        $names = array_filter(array_map(
            fn (string $name): string => $this->finder->normalizeToken((string) preg_replace('/\s(5g|4g|lte)$/i', '', $name)),
            [$entity->name, ...$entity->aliases->pluck('alias')->all()],
        ));

        foreach ([$entity->name, ...$entity->aliases->pluck('alias')->take(1)->all()] as $query) {
            foreach (['en', 'id'] as $language) {
                foreach ($this->finder->searchCandidates($query, $language) as $candidate) {
                    $label = $this->finder->normalizeToken((string) preg_replace('/\s(5g|4g|lte)$/i', '', (string) ($candidate['label'] ?? '')));
                    if ($this->finder->isIrrelevantCandidate($entity, $candidate) || ! in_array($label, $names, true) || ! $this->fitsCategory($entity, $candidate)) {
                        continue;
                    }

                    $claims = $this->claims((string) $candidate['id']);
                    $isHuman = $this->itemId($claims ?? [], 'P31') === 'Q5';
                    if ($claims !== null && $isHuman === ($entity->type === EntityType::Person)) {
                        return $claims;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function fitsCategory(Entity $entity, array $candidate): bool
    {
        $keywords = self::CATEGORY_KEYWORDS[$entity->category->slug] ?? null;
        if ($keywords === null || $entity->type !== EntityType::Product) {
            return true;
        }

        $description = mb_strtolower((string) ($candidate['description'] ?? '')).' ';

        return array_any($keywords, fn (string $keyword): bool => str_contains($description, $keyword));
    }

    /**
     * @return array<string, list<array<string, mixed>>>|null
     */
    private function claims(string $id): ?array
    {
        $claims = $this->get(self::ENDPOINT, ['action' => 'wbgetentities', 'ids' => $id, 'props' => 'claims'])['entities'][$id]['claims'] ?? null;

        return is_array($claims) ? $claims : null;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    private function labels(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $entities = $this->get(self::ENDPOINT, [
            'action' => 'wbgetentities', 'ids' => implode('|', array_unique($ids)), 'props' => 'labels', 'languages' => 'id|en',
        ])['entities'] ?? [];

        $labels = [];
        foreach ($entities as $id => $item) {
            $label = $item['labels']['id']['value'] ?? $item['labels']['en']['value'] ?? null;
            if (is_string($label)) {
                $labels[$id] = $label;
            }
        }

        return $labels;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $claims
     */
    private function itemId(array $claims, string $property): ?string
    {
        $id = $claims[$property][0]['mainsnak']['datavalue']['value']['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $claims
     */
    private function birthDate(array $claims): ?string
    {
        $value = $claims['P569'][0]['mainsnak']['datavalue']['value'] ?? null;

        // Precision 11 = day; a year-only or month-only date would be stored as a false Jan 1st.
        if (! is_array($value) || ($value['precision'] ?? 0) < 11 || preg_match('/^\+(\d{4}-\d{2}-\d{2})/', (string) ($value['time'] ?? ''), $m) !== 1) {
            return null;
        }

        return $m[1];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $claims
     * @param  list<string>  $properties
     */
    private function year(array $claims, array $properties): ?int
    {
        foreach ($properties as $property) {
            if (preg_match('/^\+(\d{4})/', (string) ($claims[$property][0]['mainsnak']['datavalue']['value']['time'] ?? ''), $m) === 1) {
                return (int) $m[1];
            }
        }

        return null;
    }

    /**
     * @return array{url: string, credit: string}|null
     */
    private function image(mixed $file): ?array
    {
        if (! is_string($file) || $file === '') {
            return null;
        }

        $pages = $this->get(self::COMMONS, [
            'action' => 'query', 'titles' => 'File:'.$file, 'prop' => 'imageinfo', 'iiprop' => 'url|extmetadata', 'iiurlwidth' => 800,
        ])['query']['pages'] ?? [];
        $info = reset($pages)['imageinfo'][0] ?? null;
        $url = is_string($info['thumburl'] ?? null) ? strtok($info['thumburl'], '?') : null;

        if (! is_string($url) || ! preg_match('~^https://(upload|thumb)\.wikimedia\.org/~', $url)) {
            return null;
        }

        $meta = $info['extmetadata'] ?? [];
        $artist = trim(strip_tags((string) ($meta['Artist']['value'] ?? '')));
        $license = trim((string) ($meta['LicenseShortName']['value'] ?? ''));

        return [
            'url' => $url,
            'credit' => mb_substr(trim(implode(' · ', array_filter([$artist, $license, 'Wikimedia Commons']))), 0, 255),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $endpoint, array $query): array
    {
        try {
            $response = Http::timeout(10)->withHeaders(['User-Agent' => self::USER_AGENT])->get($endpoint, $query + ['format' => 'json']);

            return $response->successful() ? (array) $response->json() : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
