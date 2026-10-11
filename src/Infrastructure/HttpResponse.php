<?php

declare(strict_types=1);

namespace ZohoTime\Infrastructure;

/**
 * An HTTP response's status and body.
 */
final class HttpResponse
{
    private readonly int $status;

    private readonly string $body;

    /**
     * Create a response.
     *
     * @param int    $status HTTP status code.
     * @param string $body   Response body.
     */
    public function __construct(int $status, string $body)
    {
        $this->status = $status;
        $this->body = $body;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * Get the raw body.
     *
     * @return string
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Decode the body as a JSON object.
     *
     * @return array<string, mixed> The decoded object, or an empty array when the body isn't one.
     */
    public function json(): array
    {
        $data = json_decode($this->body, true);

        return is_array($data) ? $data : [];
    }
}
