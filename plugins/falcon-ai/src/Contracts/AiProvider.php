<?php

namespace FalconAi\Contracts;

use FalconAi\Data\AiRequest;
use FalconAi\Data\AiResult;
use FalconAi\Exceptions\AiException;

/**
 * One AI service the plugin can write with (Claude, OpenAI, or one a plugin adds through
 * `falcon_ai_providers`). The site's own key is read from the settings; nothing is sent until a
 * user asks for something.
 *
 * Every method that calls the service throws AiException with a sentence an editor can act on.
 */
interface AiProvider
{
    /** The id stored in the settings ("claude", "openai"). */
    public function id(): string;

    /** The name shown to people ("Claude (Anthropic)"). */
    public function label(): string;

    /** Whether a key (and a model) is saved, so requests can be made. */
    public function ready(): bool;

    /** The model requests use. */
    public function model(): string;

    /**
     * An answer held to `$request->schema`, decoded.
     *
     * @throws AiException
     */
    public function json(AiRequest $request): AiResult;

    /**
     * Free text, handed to `$onText` piece by piece as it is written; the whole text is in the
     * result.
     *
     * @param  callable(string): void  $onText
     *
     * @throws AiException
     */
    public function stream(AiRequest $request, callable $onText): AiResult;

    /**
     * Whether the saved key works, by a call that costs nothing.
     *
     * @return array{ok: bool, message: string}
     */
    public function test(): array;

    /**
     * The models offered in the settings: id => label.
     *
     * @return array<string, string>
     */
    public function models(): array;
}
