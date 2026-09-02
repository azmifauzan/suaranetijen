<?php

use App\Domains\Entities\Services\SeedEntityImporter;
use App\Domains\Search\Services\SearchService;

test('PRD AC 1: search typo samsng a57 finds Samsung Galaxy A57', function () {
    $importer = app(SeedEntityImporter::class);
    $importer->import(database_path('data/seed_entities.csv'));

    $searchService = app(SearchService::class);
    $results = $searchService->search('samsng a57');

    expect($results['data'])->not->toBeEmpty();

    $names = array_column($results['data'], 'name');
    expect($names)->toContain('Samsung Galaxy A57');

    // First result should be Samsung Galaxy A57
    expect($results['data'][0]['name'])->toBe('Samsung Galaxy A57');
});

test('PRD AC 2: search vps biznet finds VPS Biznet Gio and Biznet Gio', function () {
    $importer = app(SeedEntityImporter::class);
    $importer->import(database_path('data/seed_entities.csv'));

    $searchService = app(SearchService::class);
    $results = $searchService->search('vps biznet');

    expect($results['data'])->not->toBeEmpty();

    $names = array_column($results['data'], 'name');
    expect($names)
        ->toContain('VPS Biznet Gio')
        ->toContain('Biznet Gio');

    // VPS Biznet Gio is a prefix match and should rank before Biznet Gio
    $vpsIndex = array_search('VPS Biznet Gio', $names);
    $biznetGioIndex = array_search('Biznet Gio', $names);

    expect($vpsIndex)->toBeLessThan($biznetGioIndex);
});
