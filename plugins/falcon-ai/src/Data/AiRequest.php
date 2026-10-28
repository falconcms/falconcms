<?php

namespace FalconAi\Data;

/** What is asked of a provider: the instructions, the material, and the shape of the answer. */
final class AiRequest
{
    /**
     * @param  string  $feature  What it is for, recorded with the tokens ("outline", "section", "seo_title"…)
     * @param  array<string, mixed>|null  $schema  JSON Schema the answer must match (json() only)
     * @param  string  $effort  How hard to think: "low" for short, well-defined writing, "medium" for articles
     */
    public function __construct(
        public readonly string $feature,
        public readonly string $system,
        public readonly string $prompt,
        public readonly ?array $schema = null,
        public readonly int $maxTokens = 4000,
        public readonly string $effort = 'low',
    ) {}
}
