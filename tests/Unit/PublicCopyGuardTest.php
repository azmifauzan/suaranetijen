<?php

use App\Domains\Themes\Services\PublicCopyGuard;

test('it allows clean Indonesian copy within char limit', function () {
    $text = 'Banyak pengguna menyukai performa dan ketahanan produk ini.';
    expect(PublicCopyGuard::isAllowed($text, 100))->toBeTrue();
});

test('it rejects empty text', function () {
    expect(PublicCopyGuard::isAllowed('', 100))->toBeFalse();
});

test('it rejects text exceeding max chars', function () {
    $text = str_repeat('a', 51);
    expect(PublicCopyGuard::isAllowed($text, 50))->toBeFalse();
});

test('it rejects superlatives like terbaik and terburuk', function () {
    expect(PublicCopyGuard::isAllowed('Layanan ini terbaik di Indonesia', 100))->toBeFalse();
    expect(PublicCopyGuard::isAllowed('Produk ini terburuk tahun ini', 100))->toBeFalse();
    expect(PublicCopyGuard::isAllowed('TERBAIK BANGET', 100))->toBeFalse();
});

test('it rejects percentages and word percent', function () {
    expect(PublicCopyGuard::isAllowed('Diskon 50% untuk pelanggan', 100))->toBeFalse();
    expect(PublicCopyGuard::isAllowed('Sebanyak 80 persen menyukai ini', 100))->toBeFalse();
    expect(PublicCopyGuard::isAllowed('Over 90 percent agreed', 100))->toBeFalse();
});

test('it rejects handles and urls', function () {
    expect(PublicCopyGuard::isAllowed('Hubungi kami di @suaranetijen', 100))->toBeFalse();
    expect(PublicCopyGuard::isAllowed('Kunjungi https://example.com/info', 100))->toBeFalse();
    expect(PublicCopyGuard::isAllowed('Buka http://test.com', 100))->toBeFalse();
});
