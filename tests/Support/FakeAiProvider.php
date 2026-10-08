<?php

namespace Tests\Support;

use App\Services\AI\Contracts\AiProvider;
use Closure;
use Throwable;

/**
 * Records prompts and returns a canned response instead of calling a real provider.
 * The response may be an array, a Throwable to throw, a Closure(system, prompt, schema) for per-call answers,
 * or (with `$sequence`) a list of arrays/Throwables returned one per call.
 */
class FakeAiProvider implements AiProvider
{
    /** @var list<array{system:string, prompt:string, schema:array}> */
    public array $calls = [];

    public function __construct(private array|Throwable|Closure $response, private bool $sequence = false) {}

    public function generateJson(string $system, string $prompt, array $schema): array
    {
        $this->calls[] = compact('system', 'prompt', 'schema');

        $response = match (true) {
            $this->response instanceof Closure => ($this->response)($system, $prompt, $schema),
            $this->sequence => array_shift($this->response) ?? throw new \LogicException('FakeAiProvider sequence exhausted.'),
            default => $this->response,
        };

        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }
}
