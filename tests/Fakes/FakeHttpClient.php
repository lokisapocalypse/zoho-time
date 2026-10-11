<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Fakes;

use RuntimeException;
use ZohoTime\Infrastructure\HttpClient;
use ZohoTime\Infrastructure\HttpResponse;

/**
 * Answers requests with queued responses and records what was sent.
 */
final class FakeHttpClient implements HttpClient
{
    /** @var list<HttpResponse> */
    private array $responses = [];

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string|null}> */
    public array $requests = [];

    /**
     * Queue a JSON response.
     *
     * @param array<string, mixed> $body   The body.
     * @param int                  $status The HTTP status.
     *
     * @return void
     */
    public function respond(array $body, int $status = 200): void
    {
        $this->responses[] = new HttpResponse($status, (string) json_encode($body));
    }

    /**
     * Record the request and return the next queued response.
     *
     * @param string                $method  HTTP method.
     * @param string                $url     URL.
     * @param array<string, string> $headers Headers.
     * @param string|null           $body    Body.
     *
     * @return HttpResponse
     *
     * @throws RuntimeException When no response is queued.
     */
    public function send(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        return array_shift($this->responses) ?? throw new RuntimeException("Unexpected {$method} {$url}");
    }
}
