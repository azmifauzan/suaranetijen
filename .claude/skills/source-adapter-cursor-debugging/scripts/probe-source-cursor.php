$key = getenv('PROBE_SOURCE_KEY') ?: 'serayamotor';
$source = App\Domains\Sources\Models\Source::where('key', $key)->firstOrFail();
$state = App\Domains\Sources\Models\CrawlState::where('source_id', $source->id)->firstOrFail();
$metadata = array_merge($source->crawl_policy ?? [], $state->metadata ?? []);
$adapter = app(App\Domains\Sources\Services\SourceRegistry::class)->resolve($source);
try {
    $batch = $adapter->discover(new App\Domains\Sources\Contracts\CrawlCursor(sourceKey: $key, metadata: $metadata));
    $externalIds = array_map(fn ($document) => $document->externalId, $batch->documents);
    $known = App\Domains\Sources\Models\SourceDocument::where('source_id', $source->id)->whereIn('external_id', $externalIds)->count();
    $next = $batch->nextCursor?->cursorValue;
    echo "V {$key} from={$state->cursor_value} docs=".count($externalIds)." new=".(count($externalIds) - $known)." next={$next}".PHP_EOL;
} catch (Throwable $exception) {
    echo "V {$key} ERR ".class_basename($exception).' '.mb_substr($exception->getMessage(), 0, 100).PHP_EOL;
}
