<?php

declare(strict_types=1);

namespace ZohoTime\Infrastructure;

use RuntimeException;

/**
 * Gets Zoho access tokens from the stored refresh token, caching each one until shortly before it expires.
 */
final class ZohoOAuth
{
    private const EXPIRY_MARGIN_SECONDS = 120;

    private readonly Config $config;

    private readonly CredentialStore $credentials;

    private readonly HttpClient $http;

    /**
     * Create the OAuth helper.
     *
     * @param Config          $config      Gives the Zoho Accounts URL.
     * @param CredentialStore $credentials Holds the client credentials and tokens.
     * @param HttpClient      $http        Sends the token requests.
     */
    public function __construct(Config $config, CredentialStore $credentials, HttpClient $http)
    {
        $this->config = $config;
        $this->credentials = $credentials;
        $this->http = $http;
    }

    /**
     * Get a valid access token, refreshing it when the cached one has expired.
     *
     * @param int|null $now The current Unix time; defaults to time().
     *
     * @return string
     *
     * @throws ZohoApiException When Zoho refuses the refresh.
     * @throws RuntimeException When credentials are missing.
     */
    public function accessToken(?int $now = null): string
    {
        $now ??= time();
        $cached = json_decode((string) $this->credentials->get('access_token'), true);

        if (is_array($cached) && isset($cached['token'], $cached['expires_at']) && $cached['expires_at'] > $now) {
            return (string) $cached['token'];
        }

        $data = $this->requestToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->required('refresh_token'),
        ]);

        if (!isset($data['access_token'])) {
            throw new ZohoApiException('Zoho returned no access token.');
        }

        $expiresIn = (int) ($data['expires_in'] ?? 3600);
        $this->credentials->set('access_token', (string) json_encode([
            'token' => $data['access_token'],
            'expires_at' => $now + $expiresIn - self::EXPIRY_MARGIN_SECONDS,
        ]));

        return (string) $data['access_token'];
    }

    /**
     * Swap a Self Client grant code for a refresh token and store it.
     *
     * @param string $code The grant code from the Zoho API Console.
     *
     * @return void
     *
     * @throws ZohoApiException When Zoho refuses the code.
     * @throws RuntimeException When the client credentials are missing.
     */
    public function authorize(string $code): void
    {
        $data = $this->requestToken(['grant_type' => 'authorization_code', 'code' => $code]);

        if (!isset($data['refresh_token'])) {
            throw new ZohoApiException(
                'Zoho returned no refresh token; generate a new grant code and try again.'
            );
        }

        $this->credentials->set('refresh_token', (string) $data['refresh_token']);
        $this->credentials->set('access_token', '');
    }

    /**
     * Post to Zoho's token endpoint with the client credentials.
     *
     * @param array<string, string> $params Grant parameters.
     *
     * @return array<string, mixed>
     *
     * @throws ZohoApiException When Zoho returns an error.
     * @throws RuntimeException When the client credentials are missing.
     */
    private function requestToken(array $params): array
    {
        $params['client_id'] = $this->required('client_id');
        $params['client_secret'] = $this->required('client_secret');

        $response = $this->http->send(
            'POST',
            $this->config->accountsUrl() . '/oauth/v2/token',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query($params)
        );
        $data = $response->json();

        if ($response->status() >= 400 || isset($data['error'])) {
            throw new ZohoApiException(
                'Zoho refused the token request: ' . ($data['error'] ?? "HTTP {$response->status()}")
            );
        }

        return $data;
    }

    /**
     * Read a credential that must be present.
     *
     * @param string $name The credential's name.
     *
     * @return string
     *
     * @throws RuntimeException When it isn't stored.
     */
    private function required(string $name): string
    {
        $value = $this->credentials->get($name);

        if ($value === null || $value === '') {
            throw new RuntimeException("No Zoho {$name} is stored; run `zoho-time auth` first.");
        }

        return $value;
    }
}
