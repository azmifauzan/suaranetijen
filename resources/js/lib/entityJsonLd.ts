type EntityInput = {
    name: string;
    type: string;
    description: string | null;
};

type RatingInput = {
    rating_count: number;
    rating_average: number | null;
};

const schemaTypes: Record<string, string> = {
    brand: 'Organization',
    product: 'Product',
    service: 'Service',
    person: 'Person',
};

/**
 * Schema.org JSON-LD for an entity page.
 *
 * AggregateRating comes ONLY from the first-party Rating Netijen, never from Sentimen Netijen
 * (docs/13, ADR-007), and never on a Person: Google review snippets do not support people.
 */
export function buildEntityJsonLd(
    entity: EntityInput,
    rating: RatingInput,
): Record<string, unknown> {
    const type = schemaTypes[entity.type] ?? 'Thing';
    const data: Record<string, unknown> = {
        '@context': 'https://schema.org',
        '@type': type,
        name: entity.name,
        description: entity.description || `${entity.name} di SuaraNetijen`,
    };

    if (
        type !== 'Person' &&
        rating.rating_count > 0 &&
        rating.rating_average !== null
    ) {
        data.aggregateRating = {
            '@type': 'AggregateRating',
            ratingValue: rating.rating_average,
            reviewCount: rating.rating_count,
            bestRating: 5,
            worstRating: 1,
        };
    }

    return data;
}
