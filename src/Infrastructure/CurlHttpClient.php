<?php

declare(strict_types=1);

namespace ZohoTime\Infrastructure;

use RuntimeException;

/**
 * Sends HTTP requests with the curl extension.
 */
final class CurlHttpClient implements HttpClient
{
    private const TIMEOUT_SECONDS = 30;

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
     * @throws RuntimeException When the request can't be sent.
     */
    public function send(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        $curl = curl_init($url);
        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
        ]);

        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($curl);

        if (!is_string($response)) {
            throw new RuntimeException("{$method} {$url} failed: " . curl_error($curl));
        }

        return new HttpResponse((int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), $response);
    }
}
