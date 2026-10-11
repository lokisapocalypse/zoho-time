<?php

declare(strict_types=1);

namespace ZohoTime\Infrastructure;

/**
 * Sends HTTP requests.
 */
interface HttpClient
{
    /**
     * Send a request.
     *
     * @param string                $method  HTTP method.
     * @param string                $url     Full URL, including any query string.
     * @param array<string, string> $headers Request headers.
     * @param string|null           $body    Request body, if any.
     *
     * @return HttpResponse
     *
     * @throws \RuntimeException When the request can't be sent.
     */
    public function send(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse;
}
