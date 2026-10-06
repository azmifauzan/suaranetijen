<?php

/**
 * Entity pairs that get an indexable /banding/{a}-vs-{b} page (docs/31 Fase 3).
 *
 * Each pair is two entity slugs in alphabetical order, joined by "-vs-", and picked because people search
 * for it ("oppo vs vivo bagus mana"). Any other pair of eligible entities in the same category still
 * renders, but is noindex and absent from the sitemap, so the set of indexed pages stays curated.
 */
return [
    'pairs' => [
        'apple-vs-samsung',
        'byd-vs-hyundai',
        'daihatsu-vs-toyota',
        'infinix-vs-oppo',
        'infinix-vs-vivo',
        'iphone-15-vs-iphone-16',
        'mitsubishi-xpander-vs-toyota-avanza',
        'oppo-vs-realme',
        'oppo-vs-samsung',
        'oppo-vs-vivo',
        'oppo-vs-xiaomi',
        'realme-vs-vivo',
        'samsung-vs-vivo',
        'samsung-vs-xiaomi',
        'suzuki-ertiga-hybrid-vs-toyota-avanza',
    ],
];
