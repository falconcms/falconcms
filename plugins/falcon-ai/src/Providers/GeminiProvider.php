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
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Google Gemini, through its generateContent REST API with the site's own key (Laravel's HTTP
 * client: no extra library). Gemini's Flash models have a free tier, so a site can write with AI
 * without paying (within Google's free limits).
 *
 * JSON answers use structured output (`responseMimeType: application/json` + `responseJsonSchema`).
 * Long text streams as server-sent events (`:streamGenerateContent?alt=sse`). The key travels in
 * the `x-goog-api-key` header, never in the address.
 *
 * The model is chosen from the account's own list (AI → Settings), with each model's output limit,
 * so a request never asks for more than the model can give.
 */
final class GeminiProvider implements AiProvider
{
    public const ID = 'gemini';

    private const BASE = 'https://generativelanguage.googleapis.com/v1beta';

    /** Models that cannot write text (embeddings, speech, images…), left out of the list. */
    private const NOT_TEXT = '/(embedding|aqa|tts|audio|live|image|imagen|veo|robotics|computer-use|learnlm)/i';

    /** Reasons Gemini stops without (all of) the answer because of its safety rules. */
    private const BLOCKED = ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII', 'IMAGE_SAFETY'];

    public function id(): string
    {
        return self::ID;
    }

    public function label(): string
    {
        return 'Google Gemini';
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
        $labels = [];
        foreach ($this->catalogue() as $id => $model) {
            $labels[$id] = $model['label'];
        }

        return $labels;
    }

    public function json(AiRequest $request): AiResult
    {
        $response = $this->send(fn () => $this->http(120)->post($this->url(':generateContent'), $this->body($request, [
            'responseMimeType' => 'application/json',
            'responseJsonSchema' => $request->schema,
        ])));

        $this->guard((array) $response->json());
        $text = $this->text((array) $response->json());
        $data = json_decode($text, true);
        if (!is_array($data)) {
            throw new AiException($this->finish((array) $response->json()) === 'MAX_TOKENS' ? 'The answer was cut short. Try again.' : 'Gemini gave no usable answer. Try again.');
        }

        return new AiResult(self::ID, $this->model(), $text, $data,
            (int) $response->json('usageMetadata.promptTokenCount', 0), $this->outputTokens((array) $response->json('usageMetadata', [])));
    }

    public function stream(AiRequest $request, callable $onText): AiResult
    {
        $response = $this->send(fn () => $this->http(300)->withOptions(['stream' => true])
            ->post($this->url(':streamGenerateContent').'?alt=sse', $this->body($request)));

        $text = '';
        $usage = [];
        $buffer = '';
        $body = $response->toPsrResponse()->getBody();

        // Server-sent events: one "data: {json}" chunk per piece of the answer.
        while (true) {
            $chunk = $body->eof() ? '' : $body->read(8192);
            $buffer .= $chunk;
            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newline));
                $buffer = substr($buffer, $newline + 1);
                if (!str_starts_with($line, 'data:')) {
                    continue;
                }
                $event = json_decode(trim(substr($line, 5)), true);
                if (!is_array($event)) {
                    continue;
                }
                if (isset($event['error'])) {
                    throw new AiException('Gemini stopped with an error: '.mb_substr((string) ($event['error']['message'] ?? 'unknown'), 0, 300));
                }
                $this->guard($event, 'this part');
                $piece = $this->text($event);
                if ($piece !== '') {
                    $text .= $piece;
                    $onText($piece);
                }
                // The counts are running totals: the last chunk holds the whole request's.
                $usage = (array) ($event['usageMetadata'] ?? $usage);
            }
            if ($chunk === '' && $body->eof()) {
                break;
            }
        }

        if (trim($text) === '') {
            throw new AiException('Gemini gave no text. Try again.');
        }

        return new AiResult(self::ID, $this->model(), $text, null, (int) ($usage['promptTokenCount'] ?? 0), $this->outputTokens($usage));
    }

    public function test(): array
    {
        if (Settings::key(self::ID) === '') {
            return ['ok' => false, 'message' => 'No Gemini key saved yet. Paste it, Save, then Test.'];
        }
        $model = $this->model();
        try {
            $response = $this->http(20)->get($model !== '' ? $this->url('') : self::BASE.'/models', $model !== '' ? [] : ['pageSize' => 1]);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'This server could not reach generativelanguage.googleapis.com.'];
        }
        if ($this->keyRefused($response)) {
            return ['ok' => false, 'message' => 'Google refused the key. Copy it again in full from Google AI Studio → API keys, or create a new one.'];
        }
        if ($response->status() === 404 && $model !== '') {
            return ['ok' => false, 'message' => "The key works, but {$model} is not available to it: choose another model."];
        }
        if (!$response->successful()) {
            return ['ok' => false, 'message' => 'Gemini did not answer ('.$response->status().').'];
        }

        return ['ok' => true, 'message' => $model !== ''
            ? 'Works: '.($response->json('displayName') ?: $model).' is ready.'
            : 'The key works. Now choose a model and Save.'];
    }

    /**
     * The account's text models, newest first: id => [label, output limit]. One list per key,
     * kept a day.
     *
     * @return array<string, array{label: string, limit: int}>
     */
    private function catalogue(): array
    {
        if (Settings::key(self::ID) === '') {
            return [];
        }
        $cacheKey = 'falcon_ai_gemini_models_'.hash('sha256', Settings::key(self::ID));
        $models = Cache::get($cacheKey);
        if (!is_array($models)) {
            $models = [];
            try {
                $response = $this->http(20)->get(self::BASE.'/models', ['pageSize' => 1000]);
                foreach ((array) $response->json('models', []) as $model) {
                    $id = preg_replace('#^models/#', '', (string) ($model['name'] ?? ''));
                    if ($id === '' || !str_starts_with($id, 'gemini') || preg_match(self::NOT_TEXT, $id)
                        || !in_array('generateContent', (array) ($model['supportedGenerationMethods'] ?? []), true)) {
                        continue;
                    }
                    $label = (string) ($model['displayName'] ?? $id);
                    $models[$id] = ['label' => $label !== $id ? $label.' ('.$id.')' : $id, 'limit' => (int) ($model['outputTokenLimit'] ?? 0)];
                }
            } catch (Throwable $e) {
                $models = [];
            }
            if ($models !== []) {
                krsort($models);
                Cache::put($cacheKey, $models, now()->addDay());
            }
        }

        return $models;
    }

    /**
     * The request body. Gemini's thinking models spend part of the output budget thinking, so
     * the budget is generous, but never above what the model can give.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function body(AiRequest $request, array $config = []): array
    {
        $limit = $this->catalogue()[$this->model()]['limit'] ?? 0;
        $wanted = max($request->maxTokens * 3, 8192);

        return [
            'systemInstruction' => ['parts' => [['text' => $request->system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $request->prompt]]]],
            'generationConfig' => ['maxOutputTokens' => $limit > 0 ? min($wanted, $limit) : 8192] + $config,
        ];
    }

    /** Everything Gemini wrote in this answer (or chunk), without any thought summaries. */
    private function text(array $answer): string
    {
        $text = '';
        foreach ((array) ($answer['candidates'][0]['content']['parts'] ?? []) as $part) {
            if (empty($part['thought']) && isset($part['text'])) {
                $text .= (string) $part['text'];
            }
        }

        return $text;
    }

    private function finish(array $answer): string
    {
        return (string) ($answer['candidates'][0]['finishReason'] ?? '');
    }

    /** A request or answer Gemini's safety rules stopped. */
    private function guard(array $answer, string $what = 'this one'): void
    {
        if (!empty($answer['promptFeedback']['blockReason']) || in_array($this->finish($answer), self::BLOCKED, true)) {
            throw new AiException("Gemini declined to write {$what}. Try rewording the topic, or write it yourself.");
        }
    }

    /** What the model wrote, counting its thinking (billed as output). */
    private function outputTokens(array $usage): int
    {
        return (int) ($usage['candidatesTokenCount'] ?? 0) + (int) ($usage['thoughtsTokenCount'] ?? 0);
    }

    private function url(string $method): string
    {
        return self::BASE.'/models/'.rawurlencode($this->model()).$method;
    }

    private function http(int $timeout): PendingRequest
    {
        return Http::withHeaders(['x-goog-api-key' => Settings::key(self::ID)])->acceptJson()->asJson()->timeout($timeout)->connectTimeout(15);
    }

    /** Google answers a wrong key with 400 (API_KEY_INVALID) as well as 401/403. */
    private function keyRefused(Response $response): bool
    {
        if (in_array($response->status(), [401, 403], true)) {
            return true;
        }

        return $response->status() === 400 && str_contains((string) $response->body(), 'API_KEY_INVALID');
    }

    /**
     * Send, and turn a failure into a sentence an editor can act on.
     *
     * @param  callable(): Response  $send
     */
    private function send(callable $send): Response
    {
        if (Settings::key(self::ID) === '') {
            throw new AiException('Add a Gemini API key under AI → Settings first.');
        }
        if ($this->model() === '') {
            throw new AiException('Choose a Gemini model under AI → Settings first.');
        }

        // 500/503 (UNAVAILABLE, "the model is overloaded") pass in seconds: wait and try again, twice.
        foreach ([2, 5, null] as $wait) {
            try {
                $response = $send();
            } catch (ConnectionException $e) {
                throw new AiException('Gemini could not be reached from this server. Check its internet access.');
            }
            if ($response->successful()) {
                return $response;
            }
            if ($wait === null || !in_array($response->status(), [500, 503], true)) {
                break;
            }
            Sleep::for($wait)->seconds();
        }

        $error = json_decode((string) $response->body(), true)['error'] ?? [];
        $error = is_array($error) ? $error : [];
        // Never echo a key back, in either of Google's formats.
        $reason = mb_substr(preg_replace('/(AIza|AQ\.)[0-9A-Za-z_\-.]+/', '$1…', (string) ($error['message'] ?? '')) ?? '', 0, 300);

        throw new AiException(match (true) {
            // A long "AQ." key copied short is the usual cause of a 401 (ACCESS_TOKEN_TYPE_UNSUPPORTED).
            $this->keyRefused($response) => 'Google did not accept the Gemini API key. Copy it again in full from Google AI Studio (or create a new one) and save it under AI → Settings.'
                .($reason !== '' ? ' (Google: '.$reason.')' : ''),
            $response->status() === 404 => 'The chosen Gemini model is not available to this key. Choose another under AI → Settings.',
            // RESOURCE_EXHAUSTED: on the free tier, the per-minute or per-day limit.
            $response->status() === 429 => 'Gemini\'s limit for now was reached (on the free tier, per minute or per day). Wait a minute and try again, or choose another model.'.($reason !== '' ? ' (Google: '.$reason.')' : ''),
            $response->status() === 400 => 'Gemini could not take this request'.($reason !== '' ? ': '.$reason : '.'),
            in_array($response->status(), [500, 503], true) => 'Google\'s servers are busy with this model right now ('.$response->status().'). Try again in a few minutes, or choose another Gemini model under AI → Settings.',
            default => 'Gemini did not answer ('.$response->status().'). Try again shortly.',
        });
    }
}
