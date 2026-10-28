<?php

namespace FalconAi\Support;

use FalconAi\Contracts\AiProvider;
use FalconAi\Data\AiRequest;
use FalconAi\Data\AiResult;
use FalconAi\Exceptions\AiException;
use FalconAi\Providers\ClaudeProvider;
use FalconAi\Providers\GeminiProvider;
use FalconAi\Providers\OpenAiProvider;

/**
 * The one door every AI request goes through: picks the provider chosen in the settings, holds
 * the monthly token limit, and records what each request used.
 *
 * Filter: `falcon_ai_providers` (array<string, class-string<AiProvider>>) — add a provider.
 * Action: `falcon_ai_generated` (string $feature, AiResult $result) — after every answer.
 */
final class AiManager
{
    /** @var array<string, AiProvider>|null */
    private ?array $providers = null;

    /** @return array<string, AiProvider> id => provider */
    public function providers(): array
    {
        if ($this->providers === null) {
            $classes = (array) apply_falcon_filters('falcon_ai_providers', [
                ClaudeProvider::ID => ClaudeProvider::class,
                OpenAiProvider::ID => OpenAiProvider::class,
                GeminiProvider::ID => GeminiProvider::class,
            ]);
            $this->providers = [];
            foreach ($classes as $id => $class) {
                if (is_string($class) && is_subclass_of($class, AiProvider::class)) {
                    $provider = app($class);
                    $this->providers[$provider->id()] = $provider;
                }
            }
        }

        return $this->providers;
    }

    public function provider(string $id): ?AiProvider
    {
        return $this->providers()[$id] ?? null;
    }

    /** The provider chosen in the settings, when it has what it needs (a key, a model). */
    public function active(): ?AiProvider
    {
        $provider = $this->provider(Settings::provider());

        return $provider && $provider->ready() ? $provider : null;
    }

    public function available(): bool
    {
        return $this->active() !== null;
    }

    /** @throws AiException */
    public function json(AiRequest $request): AiResult
    {
        return $this->record($request, $this->require()->json($request));
    }

    /**
     * @param  callable(string): void  $onText
     *
     * @throws AiException
     */
    public function stream(AiRequest $request, callable $onText): AiResult
    {
        return $this->record($request, $this->require()->stream($request, $onText));
    }

    private function require(): AiProvider
    {
        $provider = $this->active();
        if (!$provider) {
            $chosen = $this->provider(Settings::provider());
            throw new AiException(match (true) {
                !$chosen => 'AI writing is not set up yet: choose a provider under AI → Settings.',
                Settings::key($chosen->id()) === '' => 'AI writing is not set up yet: add the '.$chosen->label().' API key under AI → Settings.',
                default => 'AI writing is not set up yet: choose which '.$chosen->label().' model to use under AI → Settings.',
            });
        }

        $limit = Settings::monthlyLimit();
        if ($limit > 0 && Usage::thisMonth()['tokens'] >= $limit) {
            throw new AiException('This month\'s AI limit ('.number_format($limit).' tokens) has been reached. An administrator can raise it under AI → Settings.');
        }

        return $provider;
    }

    private function record(AiRequest $request, AiResult $result): AiResult
    {
        Usage::record($request->feature, $result);
        do_falcon_action('falcon_ai_generated', $request->feature, $result);

        return $result;
    }
}
