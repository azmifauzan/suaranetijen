    public function chat(array $messages, ?array $jsonSchema = null, ?int $timeoutSeconds = null): array
    {
        if (config('services.llm.provider') === 'codex_subscription') {
            if ($jsonSchema === null) {
                throw new InvalidArgumentException('Codex inference requires a JSON schema.');
            }

            $apiFallbackEnabled = (bool) config('services.llm.codex_api_fallback', false);
            $deferApiFallback = $apiFallbackEnabled && self::$deferApiFallback;
            $codexTimeout = $apiFallbackEnabled && ! $deferApiFallback ? 45 : 120;

it('throws instead of using the paid API while a queue job still has retries left', function () {
    useCodexApiFallback();
    Http::preventStrayRequests();
    $timeouts = [];
    Http::fake(function (Request $request, array $options) use (&$timeouts) {
        $timeouts[] = $options['timeout'];

        return Http::response(['error' => 'inference_timeout'], 504);
    });
    LlmClient::$deferApiFallback = true;