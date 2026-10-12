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
        'apple-vs-infinix',
        'apple-vs-oppo',
        'apple-vs-realme',
        'apple-vs-samsung',
        'apple-vs-vivo',
        'apple-vs-xiaomi',
        'byd-vs-hyundai',
        'daihatsu-vs-toyota',
        'infinix-vs-oppo',
        'infinix-vs-realme',
        'infinix-vs-samsung',
        'infinix-vs-tecno',
        'infinix-vs-vivo',
        'infinix-vs-xiaomi',
        'iphone-15-vs-iphone-16',
        'mitsubishi-xpander-vs-toyota-avanza',
        'oppo-vs-realme',
        'oppo-vs-samsung',
        'oppo-vs-tecno',
        'oppo-vs-vivo',
        'oppo-vs-xiaomi',
        'realme-vs-samsung',
        'realme-vs-tecno',
        'realme-vs-vivo',
        'realme-vs-xiaomi',
        'samsung-vs-tecno',
        'samsung-vs-vivo',
        'samsung-vs-xiaomi',
        'suzuki-ertiga-hybrid-vs-toyota-avanza',
        'tecno-vs-vivo',
        'tecno-vs-xiaomi',
        'vivo-vs-xiaomi',
    ],
];
