<?php

use App\Domains\Sources\Adapters\DiskusiWebHostingAdapter;
use App\Domains\Sources\Adapters\KaskusAdapter;
use App\Domains\Sources\Adapters\SerayaMotorAdapter;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceDocumentRef;
use Carbon\CarbonImmutable;

function piiFixture(string $adapter, string $file): string
{
    return (string) file_get_contents(base_path("tests/Fixtures/Sources/{$adapter}/{$file}"));
}

function piiFetchedDocument(string $sourceKey, string $rawPayload): FetchedDocument
{
    return new FetchedDocument(
        ref: new SourceDocumentRef(sourceKey: $sourceKey, externalId: '1', canonicalUrl: 'https://example.test/1'),
        rawPayload: $rawPayload,
        contentType: 'text/html',
        fetchedAt: CarbonImmutable::now()
    );
}

it('extracts only the post body from a phpBB thread, never the postprofile panel', function () {
    $doc = piiFetchedDocument('serayamotor', piiFixture('serayamotor', 'thread_pii.html'));
    $opinions = iterator_to_array((new SerayaMotorAdapter)->extract($doc));

    expect($opinions)->toHaveCount(1)
        ->and($opinions[0]->text)->toContain('Kaca film bawaan dealer')
        ->and($opinions[0]->text)->not->toContain('tesna')
        ->and($opinions[0]->text)->not->toContain('Jakarta')
        ->and($opinions[0]->text)->not->toContain('Senior Member')
        ->and($opinions[0]->text)->not->toContain('1019');
});

it('extracts only the bbWrapper body from a XenForo thread, never the message-cell--user panel', function () {
    $doc = piiFetchedDocument('diskusiwebhosting', piiFixture('diskusiwebhosting', 'thread_pii.html'));
    $opinions = iterator_to_array((new DiskusiWebHostingAdapter)->extract($doc));

    expect($opinions)->toHaveCount(1)
        ->and($opinions[0]->text)->toContain('Huawei Cloud gangguan masal')
        ->and($opinions[0]->text)->not->toContain('xborgusr')
        ->and($opinions[0]->text)->not->toContain('Beginner 1.0');
});

it('extracts only real post bodies from Kaskus, never bare usernames or reputation notices', function () {
    $doc = piiFetchedDocument('kaskus', piiFixture('kaskus', 'thread_pii.html'));
    $opinions = iterator_to_array((new KaskusAdapter)->extract($doc));

    expect($opinions)->toHaveCount(2)
        ->and($opinions[0]->text)->toContain('Assalamualaikum')
        ->and($opinions[1]->text)->toContain('makasih gan infonya')
        ->and(collect($opinions)->pluck('text')->implode(' '))->not->toContain('diancr7')
        ->and(collect($opinions)->pluck('text')->implode(' '))->not->toContain('rassakhiy')
        ->and(collect($opinions)->pluck('text')->implode(' '))->not->toContain('memberi reputasi')
        ->and(collect($opinions)->pluck('text')->implode(' '))->not->toContain('Bagikan');
});
