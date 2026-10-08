<?php

namespace Tests\Support;

use App\Services\AI\AiProviderFactory;
use App\Services\AI\Contracts\AiProvider;
use Closure;
use Throwable;

trait FakesAi
{
    /**
     * Swap the AI provider for a fake. `$response` is a canned array, a Throwable to throw,
     * a Closure(system, prompt, schema) for per-call answers, or (with `$sequence`) a list of responses returned in order.
     */
    protected function fakeAi(array|Throwable|Closure $response, bool $sequence = false): FakeAiProvider
    {
        $provider = new FakeAiProvider($response, $sequence);

        $this->app->instance(AiProviderFactory::class, new class($provider) extends AiProviderFactory
        {
            public function __construct(private AiProvider $provider) {}

            public function make(): AiProvider
            {
                return $this->provider;
            }
        });

        return $provider;
    }
}
