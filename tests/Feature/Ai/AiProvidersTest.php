<?php

namespace FalconCms\Core\Tests\Feature\Ai;

use App\Models\User;
use FalconAi\Data\AiRequest;
use FalconAi\Exceptions\AiException;
use FalconAi\Support\AiManager;
use FalconAi\Support\Settings;
use FalconAi\Support\Usage;
use FalconCms\Core\Tests\Doubles\FakeClaudeTransport;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Falcon AI's two providers, against fake Claude and OpenAI servers: the request each sends
 * (model, structured output, streaming), what each answer becomes, what a failure says, and the
 * tokens recorded for the month.
 */
class AiProvidersTest extends TestCase
{
    private const SCHEMA = ['type' => 'object', 'properties' => ['title' => ['type' => 'string']], 'required' => ['title'], 'additionalProperties' => false];

    private function claude(array $replies, string $model = 'claude-opus-5-5'): FakeClaudeTransport
    {
        $fake = new FakeClaudeTransport($replies);
        $this->app->instance('falcon-ai.claude.transporter', $fake);
        $this->setCmsOptions(['ai_provider' => 'claude', 'ai_claude_key' => Crypt::encryptString('sk-ant-api03-testtesttesttesttesttest'), 'ai_claude_model' => $model]);

        return $fake;
    }

    private function openai(string $model = 'gpt-test-1'): void
    {
        $this->setCmsOptions(['ai_provider' => 'openai', 'ai_openai_key' => Crypt::encryptString('sk-proj-testtesttesttesttesttest'), 'ai_openai_model' => $model]);
    }

    private function ai(): AiManager
    {
        // Scoped: a fresh one per test, so the provider list follows the options just set.
        return $this->app->make(AiManager::class);
    }

    private function request(): AiRequest
    {
        return new AiRequest('outline', 'You plan articles.', '<topic>Green tea</topic>', self::SCHEMA, 500);
    }

    // ── Claude ───────────────────────────────────────────────────────────────

    public function test_claude_answers_json_with_structured_output_and_the_fallback(): void
    {
        $fake = $this->claude([json_encode(['title' => 'Green Tea, Brewed Right'])]);

        $result = $this->ai()->json($this->request());

        $this->assertSame(['title' => 'Green Tea, Brewed Right'], $result->data);
        $body = $fake->body();
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertSame(self::SCHEMA, $body['output_config']['format']['schema']);
        $this->assertSame('low', $body['output_config']['effort']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertSame('You plan articles.', $body['system']);
        $this->assertSame('<topic>Green tea</topic>', $body['messages'][0]['content']);
        $this->assertSame('sk-ant-api03-testtesttesttesttesttest', $fake->requests[0]->getHeaderLine('x-api-key'));
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $fake->requests[0]->getHeaderLine('anthropic-beta'));

        // Recorded: counts only.
        $row = DB::table(Usage::TABLE)->first();
        $this->assertSame(['claude', 'claude-opus-5-5', 'outline', 10, 10], [$row->provider, $row->model, $row->feature, (int) $row->input_tokens, (int) $row->output_tokens]);
    }

    /** Haiku has no server-side fallback: the request must not ask for one. */
    public function test_haiku_is_asked_without_the_fallback(): void
    {
        $fake = $this->claude([json_encode(['title' => 'x'])], 'claude-haiku-5-5');

        $this->ai()->json($this->request());

        $this->assertArrayNotHasKey('fallbacks', $fake->body());
        $this->assertStringNotContainsString('server-side-fallback', $fake->requests[0]->getHeaderLine('anthropic-beta'));
    }

    public function test_claude_streams_text_piece_by_piece(): void
    {
        $fake = $this->claude([['__stream' => ['<h2>Why ', 'green tea</h2>', '<p>It is calming.</p>']]]);
        $pieces = [];

        $result = $this->ai()->stream(new AiRequest('section', 'Write a section.', 'Green tea', null, 4000, 'medium'), function ($text) use (&$pieces) {
            $pieces[] = $text;
        });

        $this->assertSame(['<h2>Why ', 'green tea</h2>', '<p>It is calming.</p>'], $pieces);
        $this->assertSame('<h2>Why green tea</h2><p>It is calming.</p>', $result->text);
        $this->assertSame([12, 40], [$result->inputTokens, $result->outputTokens]);
        $this->assertTrue($fake->body()['stream']);
        $this->assertSame('medium', $fake->body()['output_config']['effort']);
        $this->assertArrayNotHasKey('format', $fake->body()['output_config'], 'free text is not held to a schema');
    }

    public function test_a_declined_stream_says_so(): void
    {
        $this->claude([['__stream' => [], '__stop' => 'refusal']]);

        $this->expectExceptionMessage('Claude declined to write this part');
        $this->ai()->stream(new AiRequest('section', 's', 'p'), fn () => null);
    }

    public function test_claude_failures_say_what_to_do(): void
    {
        $this->claude([401]);
        try {
            $this->ai()->json($this->request());
            $this->fail('no exception');
        } catch (AiException $e) {
            $this->assertSame('Claude did not accept the API key. Check it under AI → Settings.', $e->getMessage());
        }

        $this->claude([['__status' => 400, 'type' => 'error', 'error' => ['type' => 'invalid_request_error',
            'message' => 'Your credit balance is too low to access the Anthropic API. Please go to Plans & Billing to upgrade or purchase credits.']]]);
        $this->expectExceptionMessage('The Anthropic account has no credit left.');
        $this->ai()->json($this->request());
    }

    public function test_the_key_test_costs_nothing(): void
    {
        $fake = $this->claude([['type' => 'model', 'id' => 'claude-opus-5-5', 'display_name' => 'Claude Opus 5.5', 'created_at' => '2026-09-01T00:00:00Z'], 401]);
        $claude = $this->ai()->provider('claude');

        $this->assertSame(['ok' => true, 'message' => 'Works: Claude Opus 5.5 is ready. (Writing also needs credit on the Anthropic account.)'], $claude->test());
        $this->assertStringEndsWith('/v1/models/claude-opus-5-5', (string) $fake->requests[0]->getUri());
        $this->assertFalse($claude->test()['ok']);
    }

    // ── OpenAI ───────────────────────────────────────────────────────────────

    public function test_openai_answers_json_with_a_strict_schema(): void
    {
        $this->openai();
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['title' => 'Green Tea, Brewed Right'])], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 21, 'completion_tokens' => 9],
        ])]);

        $result = $this->ai()->json($this->request());

        $this->assertSame(['title' => 'Green Tea, Brewed Right'], $result->data);
        $this->assertSame([21, 9], [$result->inputTokens, $result->outputTokens]);
        Http::assertSent(function (Request $request) {
            $format = $request->data()['response_format'];

            return $request->hasHeader('Authorization', 'Bearer sk-proj-testtesttesttesttesttest')
                && $request->data()['model'] === 'gpt-test-1'
                && $request->data()['messages'][0] === ['role' => 'system', 'content' => 'You plan articles.']
                && $format['type'] === 'json_schema' && $format['json_schema']['strict'] === true
                && $format['json_schema']['schema'] === self::SCHEMA;
        });
    }

    public function test_openai_streams_server_sent_events(): void
    {
        $this->openai();
        $sse = implode('', array_map(fn ($e) => 'data: '.json_encode($e)."\n\n", [
            ['choices' => [['delta' => ['content' => '<h2>Why ']]]],
            ['choices' => [['delta' => ['content' => 'green tea</h2>']]]],
            ['choices' => [['delta' => [], 'finish_reason' => 'stop']]],
            ['choices' => [], 'usage' => ['prompt_tokens' => 30, 'completion_tokens' => 8]],
        ]))."data: [DONE]\n\n";
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);
        $pieces = [];

        $result = $this->ai()->stream(new AiRequest('section', 's', 'p'), function ($text) use (&$pieces) {
            $pieces[] = $text;
        });

        $this->assertSame(['<h2>Why ', 'green tea</h2>'], $pieces);
        $this->assertSame('<h2>Why green tea</h2>', $result->text);
        $this->assertSame([30, 8], [$result->inputTokens, $result->outputTokens]);
        Http::assertSent(fn (Request $request) => $request->data()['stream'] === true && $request->data()['stream_options'] === ['include_usage' => true]);
    }

    /** An empty account, in the shapes OpenAI has used, says so; a real rate limit says what OpenAI said. */
    public function test_openai_failures_say_what_to_do(): void
    {
        $this->openai();
        $cases = [
            [['message' => 'You exceeded your current quota.', 'code' => 'insufficient_quota'], 'The OpenAI account has no credit left.'],
            // What an empty account answers now.
            [['message' => 'You have no credits remaining. Add credits to continue using the API.', 'type' => 'insufficient_quota', 'code' => 'credit_balance_exhausted'],
                'The OpenAI account has no credit left.'],
            [['message' => 'Rate limit reached for gpt-test-1 on tokens per min (TPM): Limit 30000.', 'type' => 'tokens', 'code' => 'rate_limit_exceeded'],
                'OpenAI is busy or this account\'s rate limit was reached. Try again in a minute. (OpenAI: Rate limit reached for gpt-test-1 on tokens per min (TPM): Limit 30000.)'],
        ];
        $replies = Http::sequence();
        foreach ($cases as [$error]) {
            $replies->push(['error' => $error], 429);
        }
        Http::fake(['api.openai.com/*' => $replies]);

        foreach ($cases as [, $message]) {
            try {
                $this->ai()->json($this->request());
                $this->fail('no exception');
            } catch (AiException $e) {
                $this->assertStringStartsWith($message, $e->getMessage());
            }
        }
    }

    public function test_openai_needs_a_model_chosen(): void
    {
        $this->openai('');

        $this->assertFalse($this->ai()->available());
        $this->expectExceptionMessage('choose which OpenAI model to use under AI → Settings');
        $this->ai()->json($this->request());
    }

    /** The model list is the account's own, without the models that cannot write text. */
    public function test_openai_offers_the_accounts_text_models(): void
    {
        $this->openai();
        Http::fake(['api.openai.com/v1/models' => Http::response(['data' => [
            ['id' => 'gpt-test-1'], ['id' => 'text-embedding-3-large'], ['id' => 'whisper-1'], ['id' => 'gpt-test-2'], ['id' => 'dall-e-3'],
        ]])]);

        $this->assertSame(['gpt-test-2' => 'gpt-test-2', 'gpt-test-1' => 'gpt-test-1'], $this->ai()->provider('openai')->models());
    }

    // ── Gemini ───────────────────────────────────────────────────────────────

    private const GEMINI_KEY = 'AIzaTESTtestTESTtestTESTtestTESTtest123';

    private function gemini(string $model = 'gemini-test-flash'): void
    {
        $this->setCmsOptions(['ai_provider' => 'gemini', 'ai_gemini_key' => Crypt::encryptString(self::GEMINI_KEY), 'ai_gemini_model' => $model]);
    }

    /** The account's model list, with each model's output limit. */
    private function geminiModels(): array
    {
        return ['generativelanguage.googleapis.com/v1beta/models?*' => Http::response(['models' => [
            ['name' => 'models/gemini-test-flash', 'displayName' => 'Gemini Test Flash', 'outputTokenLimit' => 9000, 'supportedGenerationMethods' => ['generateContent', 'countTokens']],
            ['name' => 'models/gemini-test-pro', 'displayName' => 'Gemini Test Pro', 'outputTokenLimit' => 65536, 'supportedGenerationMethods' => ['generateContent']],
            ['name' => 'models/text-embedding-004', 'supportedGenerationMethods' => ['embedContent']],
            ['name' => 'models/gemini-test-flash-tts', 'supportedGenerationMethods' => ['generateContent']],
        ]])];
    }

    public function test_gemini_answers_json_with_a_json_schema_and_the_key_in_a_header(): void
    {
        $this->gemini();
        Http::fake($this->geminiModels() + [
            'generativelanguage.googleapis.com/v1beta/models/gemini-test-flash:generateContent' => Http::response([
                'candidates' => [['content' => ['parts' => [
                    ['text' => 'thinking out loud', 'thought' => true],
                    ['text' => json_encode(['title' => 'Green Tea, Brewed Right'])],
                ]], 'finishReason' => 'STOP']],
                'usageMetadata' => ['promptTokenCount' => 33, 'candidatesTokenCount' => 12, 'thoughtsTokenCount' => 50],
            ]),
        ]);

        $result = $this->ai()->json($this->request());

        $this->assertSame(['title' => 'Green Tea, Brewed Right'], $result->data, 'thought summaries are not part of the answer');
        $this->assertSame([33, 62], [$result->inputTokens, $result->outputTokens], 'thinking is billed as output');
        Http::assertSent(function (Request $request) {
            if (!str_contains($request->url(), ':generateContent')) {
                return false;
            }
            $body = $request->data();

            return $request->hasHeader('x-goog-api-key', self::GEMINI_KEY) && !str_contains($request->url(), self::GEMINI_KEY)
                && $body['systemInstruction']['parts'][0]['text'] === 'You plan articles.'
                && $body['contents'][0]['parts'][0]['text'] === '<topic>Green tea</topic>'
                && $body['generationConfig']['responseMimeType'] === 'application/json'
                && $body['generationConfig']['responseJsonSchema'] === self::SCHEMA
                // Room to think (3 x 500 is too little), but never above the model's own limit.
                && $body['generationConfig']['maxOutputTokens'] === 8192;
        });
    }

    public function test_gemini_never_asks_for_more_than_the_model_can_give(): void
    {
        $this->gemini();
        Http::fake($this->geminiModels() + ['*:generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"title":"x"}']]]]]])]);

        $this->ai()->json(new AiRequest('section', 's', 'p', self::SCHEMA, 6000));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), ':generateContent') && $r->data()['generationConfig']['maxOutputTokens'] === 9000);
    }

    public function test_gemini_streams_server_sent_events(): void
    {
        $this->gemini();
        $chunk = fn (string $text, array $usage, ?string $finish = null) => 'data: '.json_encode([
            'candidates' => [array_filter(['content' => ['parts' => [['text' => $text]]], 'finishReason' => $finish])],
            'usageMetadata' => $usage,
        ])."\r\n\r\n";
        Http::fake($this->geminiModels() + [
            '*:streamGenerateContent*' => Http::response(
                $chunk('<h2>Why ', ['promptTokenCount' => 40, 'candidatesTokenCount' => 3])
                .$chunk('green tea</h2>', ['promptTokenCount' => 40, 'candidatesTokenCount' => 9], 'STOP'),
                200, ['Content-Type' => 'text/event-stream']),
        ]);
        $pieces = [];

        $result = $this->ai()->stream(new AiRequest('section', 's', 'p'), function ($text) use (&$pieces) {
            $pieces[] = $text;
        });

        $this->assertSame(['<h2>Why ', 'green tea</h2>'], $pieces);
        $this->assertSame([40, 9], [$result->inputTokens, $result->outputTokens], 'running totals: the last chunk counts');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), ':streamGenerateContent?alt=sse'));
    }

    public function test_gemini_offers_the_accounts_text_models(): void
    {
        $this->gemini('');
        Http::fake($this->geminiModels());

        $this->assertSame([
            'gemini-test-pro' => 'Gemini Test Pro (gemini-test-pro)',
            'gemini-test-flash' => 'Gemini Test Flash (gemini-test-flash)',
        ], $this->ai()->provider('gemini')->models());
        $this->assertFalse($this->ai()->available(), 'no model chosen yet');
    }

    public function test_gemini_says_why_it_did_not_write(): void
    {
        $this->gemini();
        Http::fake($this->geminiModels() + ['*:generateContent' => Http::sequence()
            ->push(['candidates' => [['finishReason' => 'SAFETY']]])
            ->push(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded for requests per minute.']], 429)
            ->push(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'API key not valid.', 'details' => [['reason' => 'API_KEY_INVALID']]]], 400)
            ->push(['error' => ['code' => 401, 'status' => 'UNAUTHENTICATED', 'message' => 'Key AQ.Ab8RN6KQsecretsecret is not supported (ACCESS_TOKEN_TYPE_UNSUPPORTED).']], 401),
        ]);

        foreach ([
            'Gemini declined to write this one.',
            'Gemini\'s limit for now was reached (on the free tier, per minute or per day). Wait a minute and try again, or choose another model. (Google: Quota exceeded for requests per minute.)',
            'Google did not accept the Gemini API key. Copy it again in full from Google AI Studio (or create a new one) and save it under AI → Settings. (Google: API key not valid.)',
            'Google did not accept the Gemini API key. Copy it again in full from Google AI Studio (or create a new one) and save it under AI → Settings. (Google: Key AQ.… is not supported (ACCESS_TOKEN_TYPE_UNSUPPORTED).)',
        ] as $message) {
            try {
                $this->ai()->json($this->request());
                $this->fail('no exception');
            } catch (AiException $e) {
                $this->assertStringStartsWith($message, $e->getMessage());
                $this->assertStringNotContainsString('secret', $e->getMessage(), 'a key is never echoed back');
            }
        }
    }

    /** 503 "the model is overloaded" passes in seconds: two more tries, then a clear message. */
    public function test_gemini_tries_again_when_googles_servers_are_busy(): void
    {
        Sleep::fake();
        $this->gemini();
        $busy = ['error' => ['code' => 503, 'status' => 'UNAVAILABLE', 'message' => 'The model is overloaded. Please try again later.']];
        Http::fake($this->geminiModels() + ['*:generateContent' => Http::sequence()
            ->push($busy, 503)
            ->push(['candidates' => [['content' => ['parts' => [['text' => '{"title":"x"}']]]]]])
            ->push($busy, 503)->push($busy, 503)->push($busy, 503),
        ]);

        $this->assertSame(['title' => 'x'], $this->ai()->json($this->request())->data);

        try {
            $this->ai()->json($this->request());
            $this->fail('no exception');
        } catch (AiException $e) {
            $this->assertStringStartsWith('Google\'s servers are busy with this model right now (503).', $e->getMessage());
        }
        Sleep::assertSequence([Sleep::for(2)->seconds(), Sleep::for(2)->seconds(), Sleep::for(5)->seconds()]);
    }

    public function test_a_gemini_key_is_checked_for_its_shape(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->post('/admin/ai/settings', ['provider' => 'gemini', 'language' => 'en', 'tone' => 'friendly', 'gemini_key' => 'sk-not-a-google-key-at-all-123456'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'The Gemini key was not saved'));

        $this->post('/admin/ai/settings', ['provider' => 'gemini', 'language' => 'en', 'tone' => 'friendly', 'gemini_key' => self::GEMINI_KEY, 'gemini_model' => 'gemini-test-flash'])
            ->assertSessionHas('success');
        $this->assertSame(self::GEMINI_KEY, Settings::key('gemini'));
        $this->assertSame('gemini-test-flash', Settings::model('gemini'));

        // AI Studio's newer keys start with "AQ." and are longer.
        $aq = 'AQ.Ab8RN6KQ'.str_repeat('xY_9-z', 12);
        $this->post('/admin/ai/settings', ['provider' => 'gemini', 'language' => 'en', 'tone' => 'friendly', 'gemini_key' => ' '.$aq.' '])
            ->assertSessionHas('success');
        $this->assertSame($aq, Settings::key('gemini'));

        $this->post('/admin/ai/settings', ['provider' => 'gemini', 'language' => 'en', 'tone' => 'friendly', 'gemini_key' => 'AQ.short'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'The Gemini key was not saved'));
        $this->assertSame($aq, Settings::key('gemini'), 'the good key stays');
    }

    private function makeAdmin()
    {
        return User::forceCreate(['name' => 'Admin', 'email' => 'admin-ai@example.test', 'password' => bcrypt('secret-password'),
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
    }

    // ── the manager ──────────────────────────────────────────────────────────

    public function test_the_provider_chosen_is_the_one_used(): void
    {
        $this->claude([json_encode(['title' => 'from claude'])]);
        $this->setCmsOptions(['ai_openai_key' => Crypt::encryptString('sk-proj-testtesttesttesttesttest'), 'ai_openai_model' => 'gpt-test-1']);
        Http::fake();

        $this->assertSame('from claude', $this->ai()->json($this->request())->data['title']);
        Http::assertNothingSent();
    }

    public function test_the_monthly_limit_stops_ai_writing(): void
    {
        $this->claude([json_encode(['title' => 'x'])]);
        $this->setCmsOptions(['ai_monthly_token_limit' => '1000']);
        DB::table(Usage::TABLE)->insert(['provider' => 'claude', 'model' => 'm', 'feature' => 'section', 'input_tokens' => 600, 'output_tokens' => 400, 'created_at' => now()]);
        // Last month's use does not count.
        DB::table(Usage::TABLE)->insert(['provider' => 'claude', 'model' => 'm', 'feature' => 'section', 'input_tokens' => 9000, 'output_tokens' => 0, 'created_at' => now()->subMonthNoOverflow()->startOfMonth()]);

        $this->assertSame(1000, Usage::thisMonth()['tokens']);
        $this->expectExceptionMessage('This month\'s AI limit (1,000 tokens) has been reached.');
        $this->ai()->json($this->request());
    }

    public function test_nothing_is_set_up_until_a_key_is_saved(): void
    {
        $this->assertFalse($this->ai()->available());
        $this->expectExceptionMessage('add the Claude (Anthropic) API key under AI → Settings');
        $this->ai()->json($this->request());
    }
}
