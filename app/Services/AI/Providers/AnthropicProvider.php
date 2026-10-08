<?php

namespace App\Services\AI\Providers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Services\AI\Contracts\AiProvider;
use App\Services\AI\Exceptions\AiException;
use App\Services\AI\Exceptions\AiNotConfiguredException;
use Illuminate\Support\Facades\Log;
use JsonException;
use Psr\Http\Client\ClientInterface;

/** Claude via the official Anthropic PHP SDK, using structured outputs for schema-valid JSON. */
class AnthropicProvider implements AiProvider
{
    private Client $client;

    /**
     * @param  array<string, mixed>  $config  the `ai` config array
     * @param  ClientInterface|null  $transporter  PSR-18 client override (tests only)
     */
    public function __construct(private array $config, ?ClientInterface $transporter = null)
    {
        $this->client = new Client(
            apiKey: $config['api_key'],
            requestOptions: array_filter([
                'timeout' => $config['timeout'],
                'maxRetries' => $config['max_retries'],
                'transporter' => $transporter,
            ], fn ($v) => $v !== null),
        );
    }

    public function generateJson(string $system, string $prompt, array $schema): array
    {
        $fallbacks = $this->config['fallbacks'];

        try {
            $message = $this->client->beta->messages->create(
                model: $this->config['model'],
                maxTokens: $this->config['max_output_tokens'],
                system: $system,
                messages: [['role' => 'user', 'content' => $prompt]],
                outputConfig: [
                    'effort' => $this->config['effort'],
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
                // On a safety-policy decline, let the API retry on its recommended fallback model.
                fallbacks: $fallbacks ? 'default' : null,
                betas: $fallbacks ? ['server-side-fallback-2026-07-01'] : null,
            );
        } catch (AuthenticationException|PermissionDeniedException $e) {
            $this->log('credentials rejected', $e);
            throw new AiNotConfiguredException('AI analysis is not available: the AI provider rejected the server credentials.');
        } catch (RateLimitException $e) {
            $this->log('rate limited', $e);
            throw new AiException('The AI service is busy right now. Please try again in a minute.', 503);
        } catch (BadRequestException $e) {
            $this->log('bad request', $e);
            throw new AiException;
        } catch (APIStatusException $e) {
            $this->log('API error', $e);
            throw new AiException;
        } catch (APIConnectionException $e) {
            Log::warning('AI provider unreachable', ['provider' => 'anthropic', 'error' => class_basename($e)]);
            throw new AiException('The AI service could not be reached. Please try again in a moment.', 504);
        }

        if ($message->stopReason === 'refusal') {
            throw new AiException('The AI service declined to process this document.', 422);
        }

        if ($message->stopReason === 'max_tokens') {
            Log::warning('AI response truncated', ['provider' => 'anthropic', 'model' => $message->model]);
            throw new AiException('The AI response was incomplete. Please try again.');
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                try {
                    $data = json_decode($block->text, true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    break;
                }

                return is_array($data) ? $data : throw new AiException;
            }
        }

        Log::warning('AI response had no JSON output', ['provider' => 'anthropic', 'stop_reason' => $message->stopReason]);
        throw new AiException;
    }

    private function log(string $what, APIStatusException $e): void
    {
        // Status and error type only: never the request body (CV text) or credentials.
        Log::warning("AI provider {$what}", [
            'provider' => 'anthropic',
            'status' => $e->status,
            'type' => $e->type?->value,
        ]);
    }
}
