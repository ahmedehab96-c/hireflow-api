<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AiProvider;
use App\Services\AI\Exceptions\AiNotConfiguredException;
use App\Services\AI\Providers\AnthropicProvider;

/** Resolves the configured provider lazily, so missing config only affects AI endpoints. */
class AiProviderFactory
{
    public function make(): AiProvider
    {
        $config = config('ai');

        if (blank($config['provider']) || blank($config['api_key'])) {
            throw new AiNotConfiguredException;
        }

        return match ($config['provider']) {
            'anthropic' => new AnthropicProvider($config),
            default => throw new AiNotConfiguredException('AI analysis is not available: the configured AI provider is not supported.'),
        };
    }
}
