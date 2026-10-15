import { expect, it } from 'vite-plus/test';
import { buildEntityJsonLd } from '@/lib/entityJsonLd';

const rated = { rating_count: 4, rating_average: 4.5 };

it('marks a public figure as Person without an aggregate rating', () => {
    const data = buildEntityJsonLd(
        { name: 'Dedi Mulyadi', type: 'person', description: null },
        rated,
    );

    expect(data['@type']).toBe('Person');
    expect(data).not.toHaveProperty('aggregateRating');
});

it('maps brand, product, and service to their schema types', () => {
    const typeOf = (type: string) =>
        buildEntityJsonLd({ name: 'X', type, description: null }, rated)[
            '@type'
        ];

    expect(typeOf('brand')).toBe('Organization');
    expect(typeOf('product')).toBe('Product');
    expect(typeOf('service')).toBe('Service');
});

it('adds AggregateRating only when first-party ratings exist', () => {
    const entity = { name: 'Xiaomi', type: 'brand', description: 'Brand' };

    expect(buildEntityJsonLd(entity, rated).aggregateRating).toMatchObject({
        ratingValue: 4.5,
        reviewCount: 4,
    });
    expect(
        buildEntityJsonLd(entity, { rating_count: 0, rating_average: null }),
    ).not.toHaveProperty('aggregateRating');
});
