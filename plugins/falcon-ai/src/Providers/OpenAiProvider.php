<?php

namespace FalconAi\Providers;

use FalconAi\Contracts\AiProvider;
use FalconAi\Data\AiRequest;
use FalconAi\Data\AiResult;
use FalconAi\Exceptions\AiException;
use FalconAi\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * OpenAI, through its Chat Completions API with the site's own key (Laravel's HTTP client:
 * no extra library to install).
 *
 * JSON answers use structured outputs (`response_format: json_schema`, strict). Long text
 * streams as server-sent events, read off the response body as it arrives.
 *
 * The model is chosen from the account's own list (AI → Settings), so a new model can be used
 * as soon as OpenAI offers it.
 */
final class OpenAiProvider implements AiProvider
{
    public const ID = 'openai';

    private const BASE = 'https://api.openai.com/v1';

    /** Models that cannot write text (images, audio, embeddings…), left out of the list. */
    private const NOT_TEXT = '/(embedding|whisper|tts|dall-e|davinci|babbage|moderation|audio|realtime|transcribe|image|search|computer-use|sora)/i';

    public function id(): string
    {
        return self::ID;
    }

    public function label(): string
    {
        return 'OpenAI';
    }

    public function ready(): bool
    {
        return Settings::key(self::ID) !== '' && $this->model() !== '';
    }

    public function model(): string
    {
        return Settings::model(self::ID);
    }

    public function models(): array
    {
        if (Settings::key(self::ID) === '') {
            return [];
        }

        // One list per key, kept a day: it changes when OpenAI adds a model, not per request.
        $cacheKey = 'falcon_ai_openai_models_'.hash('sha256', Settings::key(self::ID));
        $ids = Cache::get($cacheKey);
        if (!is_array($ids)) {
            try {
                $response = $this->http(20)->get(self::BASE.'/models');
                $ids = $response->successful() ? array_column((array) $response->json('data'), 'id') : [];
            } catch (Throwable $e) {
                $ids = [];
            }
            if ($ids !== []) {
                Cache::put($cacheKey, $ids, now()->addDay());
            }
        }

        $ids = array_values(array_filter(array_map('strval', $ids), fn ($id) => !preg_match(self::NOT_TEXT, $id)));
        rsort($ids);

        return array_combine($ids, $ids) ?: [];
    }

    public function json(AiRequest $request): AiResult
    {
        $response = $this->send(fn () => $this->http(120)->post(self::BASE.'/chat/completions', $this->body($request) + [
            'response_format' => ['type' => 'json_schema', 'json_schema' => [
                'name' => 'falcon_ai_'.preg_replace('/[^a-z0-9_]/', '_', $request->feature),
                'strict' => true,
                'schema' => $request->schema,
            ]],
        ]));

        $choice = (array) $response->json('choices.0');
        if (!empty($choice['message']['refusal'])) {
            throw new AiException('OpenAI declined to write this one. Try rewording it, or write it yourself.');
        }
        $text = (string) ($choice['message']['content'] ?? '');
        $data = json_decode($text, true);
        if (!is_array($data)) {
            throw new AiException(($choice['finish_reason'] ?? '') === 'length' ? 'The answer was cut short. Try again.' : 'OpenAI gave no usable answer. Try again.');
        }

        return new AiResult(self::ID, $this->model(), $text, $data,
            (int) $response->json('usage.prompt_tokens', 0), (int) $response->json('usage.completion_tokens', 0));
    }

    public function stream(AiRequest $request, callable $onText): AiResult
    {
        $response = $this->send(fn () => $this->http(300)->withOptions(['stream' => true])
            ->post(self::BASE.'/chat/completions', $this->body($request) + [
                'stream' => true,
                'stream_options' => ['include_usage' => true],
            ]));

        $text = '';
        $input = 0;
        $output = 0;
        $finish = null;
        $buffer = '';
        $body = $response->toPsrResponse()->getBody();

        // Server-sent events: "data: {json}" lines, ending with "data: [DONE]".
        while (true) {
            $chunk = $body->eof() ? '' : $body->read(8192);
            $buffer .= $chunk;
            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newline));
                $buffer = substr($buffer, $newline + 1);
                if (!str_starts_with($line, 'data:')) {
                    continue;
                }
                $payload = trim(substr($line, 5));
                if ($payload === '[DONE]') {
                    break 2;
                }
                $event = json_decode($payload, true);
                if (!is_array($event)) {
                    continue;
                }
                if (isset($event['error'])) {
                    throw new AiException('OpenAI stopped with an error: '.mb_substr((string) ($event['error']['message'] ?? 'unknown'), 0, 300));
                }
                $delta = (string) ($event['choices'][0]['delta']['content'] ?? '');
                if ($delta !== '') {
                    $text .= $delta;
                    $onText($delta);
                }
                if (!empty($event['choices'][0]['delta']['refusal'])) {
                    $finish = 'refusal';
                }
                $finish = $event['choices'][0]['finish_reason'] ?? $finish;
                if (isset($event['usage'])) {
                    $input = (int) ($event['usage']['prompt_tokens'] ?? 0);
                    $output = (int) ($event['usage']['completion_tokens'] ?? 0);
                }
            }
            if ($chunk === '' && $body->eof()) {
                break;
            }
        }

        if ($finish === 'refusal' || $finish === 'content_filter') {
            throw new AiException('OpenAI declined to write this part. Try rewording the topic, or write it yourself.');
        }
        if (trim($text) === '') {
            throw new AiException('OpenAI gave no text. Try again.');
        }

        return new AiResult(self::ID, $this->model(), $text, null, $input, $output);
    }

    public function test(): array
    {
        if (Settings::key(self::ID) === '') {
            return ['ok' => false, 'message' => 'No OpenAI key saved yet. Paste it, Save, then Test.'];
        }
        try {
            $model = $this->model();
            $response = $this->http(20)->get(self::BASE.'/models'.($model !== '' ? '/'.rawurlencode($model) : ''));
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'This server could not reach api.openai.com.'];
        }
        if (in_array($response->status(), [401, 403], true)) {
            return ['ok' => false, 'message' => 'OpenAI refused the key. Check that it is active on platform.openai.com.'];
        }
        if ($response->status() === 404 && $model !== '') {
            return ['ok' => false, 'message' => "The key works, but {$model} is not available to it: choose another model."];
        }
        if (!$response->successful()) {
            return ['ok' => false, 'message' => 'OpenAI did not answer ('.$response->status().').'];
        }

        return ['ok' => true, 'message' => $model !== ''
            ? "Works: {$model} is ready. (Writing also needs credit on the OpenAI account.)"
            : 'The key works. Now choose a model and Save.'];
    }

    /** @return array<string, mixed> */
    private function body(AiRequest $request): array
    {
        return [
            'model' => $this->model(),
            'messages' => [
                ['role' => 'system', 'content' => $request->system],
                ['role' => 'user', 'content' => $request->prompt],
            ],
            'max_completion_tokens' => $request->maxTokens,
        ];
    }

    private function http(int $timeout): PendingRequest
    {
        return Http::withToken(Settings::key(self::ID))->acceptJson()->asJson()->timeout($timeout)->connectTimeout(15);
    }

    /**
     * Send, and turn a failure into a sentence an editor can act on.
     *
     * @param  callable(): Response  $send
     */
    private function send(callable $send): Response
    {
        if (Settings::key(self::ID) === '') {
            throw new AiException('Add an OpenAI API key under AI → Settings first.');
        }
        if ($this->model() === '') {
            throw new AiException('Choose an OpenAI model under AI → Settings first.');
        }

        try {
            $response = $send();
        } catch (ConnectionException $e) {
            throw new AiException('OpenAI could not be reached from this server. Check its internet access.');
        }
        if ($response->successful()) {
            return $response;
        }

        // A streamed error still arrives as a JSON body.
        $error = json_decode((string) $response->body(), true)['error'] ?? [];
        $error = is_array($error) ? $error : [];
        $reason = mb_substr(preg_replace('/sk-[A-Za-z0-9_\-]+/', 'sk-…', (string) ($error['message'] ?? '')) ?? '', 0, 300);
        // An empty account: OpenAI has said so with code "insufficient_quota" and, newer, type
        // "insufficient_quota" with code "credit_balance_exhausted".
        $noCredit = $response->status() === 429 && (
            in_array($error['code'] ?? '', ['insufficient_quota', 'credit_balance_exhausted'], true)
            || ($error['type'] ?? '') === 'insufficient_quota'
        );

        throw new AiException(match (true) {
            in_array($response->status(), [401, 403], true) => 'OpenAI did not accept the API key. Check it under AI → Settings.',
            $response->status() === 404 => 'The chosen OpenAI model is not available to this key. Choose another under AI → Settings.',
            $noCredit => 'The OpenAI account has no credit left. Add some at platform.openai.com → Settings → Billing, then try again.',
            // Any other 429 is a rate limit; OpenAI's own sentence says which one.
            $response->status() === 429 => 'OpenAI is busy or this account\'s rate limit was reached. Try again in a minute.'.($reason !== '' ? ' (OpenAI: '.$reason.')' : ''),
            $response->status() === 400 => 'OpenAI could not take this request'.($reason !== '' ? ': '.$reason : '.'),
            default => 'OpenAI did not answer ('.$response->status().'). Try again shortly.',
        });
    }
}
