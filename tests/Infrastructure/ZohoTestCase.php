<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Infrastructure;

use PHPUnit\Framework\TestCase;
use ZohoTime\Infrastructure\Config;
use ZohoTime\Infrastructure\ZohoClient;
use ZohoTime\Infrastructure\ZohoOAuth;
use ZohoTime\Tests\Fakes\FakeHttpClient;
use ZohoTime\Tests\Fakes\InMemoryCredentialStore;

/**
 * Sets up a Zoho client against a fake HTTP client with a valid cached token.
 */
abstract class ZohoTestCase extends TestCase
{
    protected FakeHttpClient $http;

    protected InMemoryCredentialStore $credentials;

    protected Config $config;

    protected ZohoClient $client;

    /**
     * Build the client with credentials and an unexpired access token.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->credentials = new InMemoryCredentialStore([
            'client_id' => 'id',
            'client_secret' => 'secret',
            'refresh_token' => 'refresh',
            'access_token' => (string) json_encode(['token' => 'cached', 'expires_at' => PHP_INT_MAX]),
        ]);
        $this->config = new Config('eu', 'org1', 'acme-studio', [], ['client-bluebird'], ['harbor' => 'Harbor Family Clinic']);
        $this->client = new ZohoClient(
            $this->config,
            new ZohoOAuth($this->config, $this->credentials, $this->http),
            $this->http
        );
    }
}
