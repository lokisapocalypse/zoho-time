<?php

declare(strict_types=1);

namespace ZohoTime\Infrastructure;

/**
 * Calls the Zoho Invoice API for the configured organization.
 */
final class ZohoClient
{
    private readonly Config $config;

    private readonly ZohoOAuth $oauth;

    private readonly HttpClient $http;

    /**
     * Create the client.
     *
     * @param Config     $config Gives the API URL and organization ID.
     * @param ZohoOAuth  $oauth  Supplies access tokens.
     * @param HttpClient $http   Sends the requests.
     */
    public function __construct(Config $config, ZohoOAuth $oauth, HttpClient $http)
    {
        $this->config = $config;
        $this->oauth = $oauth;
        $this->http = $http;
    }

    /**
     * Send a GET request.
     *
     * @param string               $path  API path, e.g. "/projects".
     * @param array<string, mixed> $query Query parameters.
     *
     * @return array<string, mixed> The decoded response.
     *
     * @throws ZohoApiException When Zoho returns an error.
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    /**
     * Send a POST request with a JSON body.
     *
     * @param string               $path API path.
     * @param array<string, mixed> $body The body.
     *
     * @return array<string, mixed> The decoded response.
     *
     * @throws ZohoApiException When Zoho returns an error.
     */
    public function post(string $path, array $body): array
    {
        return $this->request('POST', $path, [], $body);
    }

    /**
     * Send a DELETE request.
     *
     * @param string $path API path.
     *
     * @return array<string, mixed> The decoded response.
     *
     * @throws ZohoApiException When Zoho returns an error.
     */
    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    /**
     * Fetch every page of a list endpoint.
     *
     * @param string               $path  API path.
     * @param string|list<string>  $key   The response key holding the list, e.g. "projects"; the first present is used.
     * @param array<string, mixed> $query Query parameters.
     *
     * @return list<array<string, mixed>>
     *
     * @throws ZohoApiException When Zoho returns an error.
     */
    public function all(string $path, string|array $key, array $query = []): array
    {
        $rows = [];
        $page = 1;

        do {
            $data = $this->get($path, $query + ['page' => $page, 'per_page' => 200]);
            $rows = array_merge($rows, array_values($this->firstList($data, (array) $key)));
            $page++;
        } while (($data['page_context']['has_more_page'] ?? false) === true);

        return $rows;
    }

    /**
     * Send an authorized request and check Zoho's response code.
     *
     * @param string                    $method HTTP method.
     * @param string                    $path   API path.
     * @param array<string, mixed>      $query  Query parameters.
     * @param array<string, mixed>|null $body   JSON body, if any.
     *
     * @return array<string, mixed>
     *
     * @throws ZohoApiException When Zoho returns an error.
     */
    private function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $url = $this->config->invoiceApiUrl() . $path;

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            'Authorization' => 'Zoho-oauthtoken ' . $this->oauth->accessToken(),
            'X-com-zoho-invoice-organizationid' => $this->config->organizationId(),
        ];

        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        $response = $this->http->send(
            $method,
            $url,
            $headers,
            $body === null ? null : (string) json_encode($body)
        );
        $data = $response->json();

        if ($response->status() >= 400 || ($data['code'] ?? 0) !== 0) {
            throw new ZohoApiException(
                "Zoho refused {$method} {$path}: " . ($data['message'] ?? "HTTP {$response->status()}")
            );
        }

        return $data;
    }

    /**
     * Pick the first list found under one of the keys.
     *
     * @param array<string, mixed> $data The decoded response.
     * @param list<string>         $keys Keys to try, in order.
     *
     * @return array<int, array<string, mixed>>
     */
    private function firstList(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }

        return [];
    }
}
