<?php

namespace Tests\Unit;

use App\Services\AI\Exceptions\AiException;
use App\Services\AI\Exceptions\AiNotConfiguredException;
use App\Services\AI\Providers\AnthropicProvider;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

/** Exercises the real SDK request/response cycle against a canned transport (no network). */
class AnthropicProviderTest extends TestCase
{
    private const CONFIG = [
        'api_key' => 'test-key-not-real',
        'model' => 'claude-opus-5-5',
        'effort' => 'medium',
        'timeout' => 5.0,
        'max_retries' => 0,
        'fallbacks' => true,
        'max_output_tokens' => 16000,
    ];

    private function transport(int $status, array $body): ClientInterface
    {
        return new class($status, $body) implements ClientInterface
        {
            public ?RequestInterface $request = null;

            public function __construct(private int $status, private array $body) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response($this->status, ['content-type' => 'application/json'], json_encode($this->body));
            }
        };
    }

    private function message(array $content, string $stopReason = 'end_turn'): array
    {
        return [
            'id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
            'content' => $content, 'stop_reason' => $stopReason, 'stop_sequence' => null,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ];
    }

    public function test_sends_structured_output_request_and_parses_json(): void
    {
        $transport = $this->transport(200, $this->message([
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
            ['type' => 'text', 'text' => '{"score": 81, "summary": "ok"}'],
        ]));
        $schema = ['type' => 'object', 'properties' => ['score' => ['type' => 'integer']], 'required' => ['score'], 'additionalProperties' => false];

        $result = (new AnthropicProvider(self::CONFIG, $transport))->generateJson('system prompt', 'user prompt', $schema);

        $this->assertSame(['score' => 81, 'summary' => 'ok'], $result);

        $request = $transport->request;
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/v1/messages', $request->getUri()->getPath());
        $this->assertSame('test-key-not-real', $request->getHeaderLine('x-api-key'));
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $request->getHeaderLine('anthropic-beta'));

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame('system prompt', $body['system']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertSame('medium', $body['output_config']['effort']);
        $this->assertSame(['type' => 'json_schema', 'schema' => $schema], $body['output_config']['format']);
        $this->assertArrayNotHasKey('thinking', $body);
    }

    public function test_refusal_is_reported_without_reading_content(): void
    {
        $transport = $this->transport(200, $this->message([], 'refusal'));

        try {
            (new AnthropicProvider(self::CONFIG, $transport))->generateJson('s', 'p', ['type' => 'object']);
            $this->fail('Expected AiException');
        } catch (AiException $e) {
            $this->assertSame(422, $e->status());
        }
    }

    public function test_invalid_credentials_map_to_not_configured_without_leaking_key(): void
    {
        $transport = $this->transport(401, ['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']]);

        try {
            (new AnthropicProvider(self::CONFIG, $transport))->generateJson('s', 'p', ['type' => 'object']);
            $this->fail('Expected AiNotConfiguredException');
        } catch (AiNotConfiguredException $e) {
            $this->assertSame(503, $e->status());
            $this->assertStringNotContainsString('test-key-not-real', $e->getMessage());
        }
    }

    public function test_rate_limit_maps_to_busy_error(): void
    {
        $transport = $this->transport(429, ['type' => 'error', 'error' => ['type' => 'rate_limit_error', 'message' => 'slow down']]);

        try {
            (new AnthropicProvider(self::CONFIG, $transport))->generateJson('s', 'p', ['type' => 'object']);
            $this->fail('Expected AiException');
        } catch (AiException $e) {
            $this->assertSame(503, $e->status());
            $this->assertStringContainsString('busy', $e->getMessage());
        }
    }

    public function test_fallbacks_can_be_disabled(): void
    {
        $transport = $this->transport(200, $this->message([['type' => 'text', 'text' => '{}']]));

        (new AnthropicProvider([...self::CONFIG, 'fallbacks' => false], $transport))->generateJson('s', 'p', ['type' => 'object']);

        $body = json_decode((string) $transport->request->getBody(), true);
        $this->assertArrayNotHasKey('fallbacks', $body);
        $this->assertStringNotContainsString('server-side-fallback', $transport->request->getHeaderLine('anthropic-beta'));
    }
}
