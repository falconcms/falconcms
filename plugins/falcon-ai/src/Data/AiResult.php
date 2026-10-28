<?php

namespace FalconAi\Data;

/** What a provider answered, with the tokens it took (recorded for the monthly usage). */
final class AiResult
{
    /** @param  array<string, mixed>|null  $data  The decoded answer of a json() request */
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly string $text,
        public readonly ?array $data = null,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
    ) {}
}
