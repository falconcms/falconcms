<?php

namespace FalconAi\Providers;

use Anthropic\Beta\Messages\BetaRawContentBlockDeltaEvent;
use Anthropic\Beta\Messages\BetaRawMessageDeltaEvent;
use Anthropic\Beta\Messages\BetaRawMessageStartEvent;
use Anthropic\Beta\Messages\BetaTextDelta;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\NotFoundException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\RequestOptions;
use FalconAi\Contracts\AiProvider;
use FalconAi\Data\AiRequest;
use FalconAi\Data\AiResult;
use FalconAi\Exceptions\AiException;
use FalconAi\Support\Settings;
use Throwable;

/**
 * Claude, through the official Anthropic PHP SDK, with the site's own API key.
 *
 * JSON answers use structured outputs (output_config.format), so they always parse. Long text
 * streams, which keeps a slow article clear of HTTP timeouts. Opus 5.5 and Sonnet 5.5 requests
 * opt into the server-side fallback: a request a model declines for a policy reason is retried
 * on a substitute instead of simply stopping.
 */
final class ClaudeProvider implements AiProvider
{
    public const ID = 'claude';

    /** Offered in the settings, best first. */
    public const MODELS = [
        'claude-opus-5-5' => 'Claude Opus 5.5 (best results)',
        'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (faster, half the cost)',
        'claude-haiku-5-5' => 'Claude Haiku 5.5 (fastest, cheapest)',
    ];

    /** Models that take the server-side fallback ("default" form). */
    private const FALLBACK_MODELS = ['claude-opus-5-5', 'claude-sonnet-5-5'];

    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    public function id(): string
    {
        return self::ID;
    }

    public function label(): string
    {
        return 'Claude (Anthropic)';
    }

    public function ready(): bool
    {
        return class_exists(Client::class) && Settings::key(self::ID) !== '';
    }

    public function model(): string
    {
        return Settings::model(self::ID);
    }

    public function models(): array
    {
        return self::MODELS;
    }

    public function json(AiRequest $request): AiResult
    {
        $message = $this->call(fn (Client $client) => $client->beta->messages->create(
            ...$this->params($request, ['effort' => $request->effort, 'format' => ['type' => 'json_schema', 'schema' => $request->schema]]),
        ));

        if ($message->stopReason === 'refusal') {
            throw new AiException('Claude declined to write this one. Try rewording it, or write it yourself.');
        }
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                if (is_array($data)) {
                    return new AiResult(self::ID, $this->model(), $block->text, $data,
                        (int) ($message->usage->inputTokens ?? 0), (int) ($message->usage->outputTokens ?? 0));
                }
            }
        }

        throw new AiException($message->stopReason === 'max_tokens' ? 'The answer was cut short. Try again.' : 'Claude gave no usable answer. Try again.');
    }

    public function stream(AiRequest $request, callable $onText): AiResult
    {
        $text = '';
        $input = 0;
        $output = 0;
        $stopReason = null;

        $this->call(function (Client $client) use ($request, $onText, &$text, &$input, &$output, &$stopReason) {
            $stream = $client->beta->messages->createStream(...$this->params($request, ['effort' => $request->effort]));
            foreach ($stream as $event) {
                if ($event instanceof BetaRawMessageStartEvent) {
                    $input = (int) ($event->message->usage->inputTokens ?? 0);
                } elseif ($event instanceof BetaRawContentBlockDeltaEvent && $event->delta instanceof BetaTextDelta) {
                    $text .= $event->delta->text;
                    $onText($event->delta->text);
                } elseif ($event instanceof BetaRawMessageDeltaEvent) {
                    $stopReason = $event->delta->stopReason ?? $stopReason;
                    $output = (int) $event->usage->outputTokens;
                }
            }

            return null;
        });

        if ($stopReason === 'refusal') {
            throw new AiException('Claude declined to write this part. Try rewording the topic, or write it yourself.');
        }
        if (trim($text) === '') {
            throw new AiException('Claude gave no text. Try again.');
        }

        return new AiResult(self::ID, $this->model(), $text, null, $input, $output);
    }

    public function test(): array
    {
        if (!class_exists(Client::class)) {
            return ['ok' => false, 'message' => 'The Anthropic PHP library is missing on this server: run composer update once.'];
        }
        if (Settings::key(self::ID) === '') {
            return ['ok' => false, 'message' => 'No Claude key saved yet. Paste it, Save, then Test.'];
        }
        $model = $this->model();
        try {
            $info = $this->client(20.0, 0)->models->retrieve($model);
        } catch (AuthenticationException|PermissionDeniedException $e) {
            return ['ok' => false, 'message' => 'Claude refused the key. Check that it is active on console.anthropic.com.'];
        } catch (NotFoundException $e) {
            return ['ok' => false, 'message' => "The key works, but {$model} is not available to it: choose another model."];
        } catch (APIConnectionException $e) {
            return ['ok' => false, 'message' => 'This server could not reach api.anthropic.com.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'The test could not run. Try again shortly.'];
        }

        return ['ok' => true, 'message' => 'Works: '.($info->displayName ?? $model).' is ready. (Writing also needs credit on the Anthropic account.)'];
    }

    /**
     * The request's named parameters for messages->create / createStream.
     *
     * @param  array<string, mixed>  $outputConfig
     * @return array<string, mixed>
     */
    private function params(AiRequest $request, array $outputConfig): array
    {
        $params = [
            'model' => $this->model(),
            'maxTokens' => $request->maxTokens,
            'system' => $request->system,
            'messages' => [['role' => 'user', 'content' => $request->prompt]],
            'outputConfig' => $outputConfig,
        ];
        if (in_array($this->model(), self::FALLBACK_MODELS, true)) {
            $params['fallbacks'] = 'default';
            $params['betas'] = [self::FALLBACK_BETA];
        }

        return $params;
    }

    /**
     * Run one call, turning the SDK's errors into a sentence an editor can act on.
     *
     * @template T
     *
     * @param  callable(Client): T  $call
     * @return T
     */
    private function call(callable $call)
    {
        if (!$this->ready()) {
            throw new AiException('Add a Claude API key under AI → Settings first.');
        }

        try {
            return $call($this->client(120.0, 1));
        } catch (AuthenticationException|PermissionDeniedException $e) {
            throw new AiException('Claude did not accept the API key. Check it under AI → Settings.');
        } catch (NotFoundException $e) {
            throw new AiException('The chosen Claude model is not available to this key. Choose another under AI → Settings.');
        } catch (RateLimitException $e) {
            throw new AiException('Claude is busy or the account limit was reached. Try again in a minute.');
        } catch (BadRequestException $e) {
            $reason = self::reason($e);
            throw new AiException(str_contains(strtolower($reason), 'credit balance')
                ? 'The Anthropic account has no credit left. Add some at console.anthropic.com → Plans & Billing, then try again.'
                : 'Claude could not take this request: '.$reason);
        } catch (APIStatusException $e) {
            throw new AiException('Claude did not answer ('.self::reason($e).'). Try again shortly.');
        } catch (APIConnectionException $e) {
            throw new AiException('Claude could not be reached from this server. Check its internet access.');
        }
    }

    private function client(float $timeout, int $retries): Client
    {
        $transporter = app()->bound('falcon-ai.claude.transporter') ? app('falcon-ai.claude.transporter') : null;

        return new Client(apiKey: Settings::key(self::ID),
            requestOptions: RequestOptions::with(timeout: $timeout, maxRetries: $retries, transporter: $transporter));
    }

    /** The API's own sentence from an error ("Your credit balance is too low…"), not the raw response. */
    private static function reason(Throwable $e): string
    {
        $text = $e->getMessage();
        if (preg_match('/"message"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $text, $m)) {
            return mb_substr((string) json_decode('"'.$m[1].'"'), 0, 300);
        }

        return mb_substr(trim(strtok($text, "\n") ?: $text), 0, 300);
    }
}
