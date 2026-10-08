<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\Exceptions\AiException;

/**
 * A provider turns a prompt into JSON that matches a schema.
 * Prompts and schemas live in CareerAIService; providers only handle transport.
 */
interface AiProvider
{
    /**
     * @param  array<string, mixed>  $schema  JSON Schema the response must satisfy
     * @return array<string, mixed>
     *
     * @throws AiException
     */
    public function generateJson(string $system, string $prompt, array $schema): array;
}
