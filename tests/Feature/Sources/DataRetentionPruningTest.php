<?php

use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Models\UnmatchedMention;
use Illuminate\Console\Scheduling\Schedule;

function unmatchedMentionCreatedDaysAgo(int $days): UnmatchedMention
{
    $source = Source::factory()->create();
    $item = SourceItem::factory()->create(['source_id' => $source->id]);
    $mention = UnmatchedMention::create([
        'source_id' => $source->id,
        'source_item_id' => $item->id,
        'content_hash' => $item->content_hash,
        'reason' => 'entity_not_resolved',
    ]);
    $mention->forceFill(['created_at' => now()->subDays($days)])->saveQuietly();

    return $mention;
}

it('prunes unmatched mentions older than the retention window and keeps recent ones', function () {
    config(['sources.unmatched_mentions_retention_days' => 30]);
    $old = unmatchedMentionCreatedDaysAgo(31);
    $recent = unmatchedMentionCreatedDaysAgo(29);

    $this->artisan('model:prune', ['--model' => [UnmatchedMention::class]])->assertSuccessful();

    expect(UnmatchedMention::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(UnmatchedMention::query()->whereKey($recent->id)->exists())->toBeTrue();
});

it('keeps the source item itself when its unmatched mention is pruned', function () {
    $old = unmatchedMentionCreatedDaysAgo(90);

    $this->artisan('model:prune', ['--model' => [UnmatchedMention::class]])->assertSuccessful();

    expect(SourceItem::query()->whereKey($old->source_item_id)->exists())->toBeTrue();
});

it('schedules daily pruning of unmatched mentions and failed jobs', function () {
    $commands = collect(app(Schedule::class)->events())->map(fn ($event) => (string) $event->command);

    expect($commands->contains(fn (string $command) => str_contains($command, 'model:prune') && str_contains($command, 'UnmatchedMention')))->toBeTrue()
        ->and($commands->contains(fn (string $command) => str_contains($command, 'queue:prune-failed') && str_contains($command, '--hours=168')))->toBeTrue();
});
