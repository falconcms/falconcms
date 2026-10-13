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

    /** @param  list<string|int>  $replies  text to answer with, or an HTTP status to fail with */
    public function __construct(private array $replies) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $reply = array_shift($this->replies) ?? '{}';

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

    /** The JSON body of a request that was sent. */
    public function body(int $i = 0): array
    {
        return json_decode((string) $this->requests[$i]->getBody(), true) ?: [];
    }
}
