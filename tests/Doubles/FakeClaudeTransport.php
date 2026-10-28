<?php

namespace FalconCms\Core\Tests\Doubles;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Stands in for the Anthropic API in tests: answers each Messages request with the next queued
 * reply (the text Claude would write, or an error status) and keeps every request for checks.
 */
class FakeClaudeTransport implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /**
     * @param  list<string|int|array>  $replies  text to answer with, an HTTP status to fail with, a whole JSON
     *                                           body, or ['__stream' => list<string> chunks, '__stop' => stop reason]
     *                                           for a streamed answer
     */
    public function __construct(private array $replies) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $reply = array_shift($this->replies) ?? '{}';

        // A streamed Messages answer: the server-sent events the API sends, text in the chunks given.
        if (is_array($reply) && isset($reply['__stream'])) {
            return new Response(200, ['Content-Type' => 'text/event-stream'], $this->events((array) $reply['__stream'], (string) ($reply['__stop'] ?? 'end_turn')));
        }

        // A whole response body (other endpoints than Messages, e.g. a model's details).
        if (is_array($reply)) {
            $status = (int) ($reply['__status'] ?? 200);
            unset($reply['__status']);

            return new Response($status, ['Content-Type' => 'application/json'], json_encode($reply));
        }
        if (is_int($reply)) {
            return new Response($reply, ['Content-Type' => 'application/json'],
                json_encode(['type' => 'error', 'error' => ['type' => $reply === 401 ? 'authentication_error' : 'api_error', 'message' => 'fake failure']]));
        }

        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
            'content' => [['type' => 'text', 'text' => $reply]],
            'stop_reason' => 'end_turn', 'stop_sequence' => null,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ]));
    }

    /** @param  list<string>  $chunks */
    private function events(array $chunks, string $stop): string
    {
        $events = [
            ['message_start', ['type' => 'message_start', 'message' => ['id' => 'msg_test', 'type' => 'message', 'role' => 'assistant',
                'model' => 'claude-opus-5-5', 'content' => [], 'stop_reason' => null, 'stop_sequence' => null,
                'usage' => ['input_tokens' => 12, 'output_tokens' => 1]]]],
            ['content_block_start', ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '']]],
        ];
        foreach ($chunks as $chunk) {
            $events[] = ['content_block_delta', ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => $chunk]]];
        }
        $events[] = ['content_block_stop', ['type' => 'content_block_stop', 'index' => 0]];
        $events[] = ['message_delta', ['type' => 'message_delta', 'delta' => ['stop_reason' => $stop, 'stop_sequence' => null], 'usage' => ['output_tokens' => 40]]];
        $events[] = ['message_stop', ['type' => 'message_stop']];

        return implode('', array_map(fn ($e) => 'event: '.$e[0]."\ndata: ".json_encode($e[1])."\n\n", $events));
    }

    /** The JSON body of a request that was sent. */
    public function body(int $i = 0): array
    {
        return json_decode((string) $this->requests[$i]->getBody(), true) ?: [];
    }
}
