<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Infrastructure;

use ZohoTime\Infrastructure\ZohoApiException;
use ZohoTime\Infrastructure\ZohoOAuth;

/**
 * Tests getting and caching Zoho access tokens.
 */
final class ZohoOAuthTest extends ZohoTestCase
{
    /**
     * Check that an unexpired cached token is used without calling Zoho.
     *
     * @return void
     */
    public function testUsesTheCachedToken(): void
    {
        $this->assertSame('cached', $this->oauth()->accessToken());
        $this->assertSame([], $this->http->requests);
    }

    /**
     * Check that an expired token is refreshed at the data center's Accounts URL and cached.
     *
     * @return void
     */
    public function testRefreshesAnExpiredToken(): void
    {
        $this->credentials->values['access_token'] = (string) json_encode(['token' => 'old', 'expires_at' => 999]);
        $this->http->respond(['access_token' => 'fresh', 'expires_in' => 3600]);

        $this->assertSame('fresh', $this->oauth()->accessToken(1000));

        $request = $this->http->requests[0];
        $this->assertSame('https://accounts.zoho.eu/oauth/v2/token', $request['url']);
        parse_str((string) $request['body'], $sent);
        $this->assertSame(
            ['grant_type' => 'refresh_token', 'refresh_token' => 'refresh', 'client_id' => 'id', 'client_secret' => 'secret'],
            $sent
        );
        $cached = json_decode($this->credentials->values['access_token'], true);
        $this->assertSame(['token' => 'fresh', 'expires_at' => 1000 + 3600 - 120], $cached);
    }

    /**
     * Check that a grant code is swapped for a refresh token, which is stored.
     *
     * @return void
     */
    public function testAuthorizeStoresTheRefreshToken(): void
    {
        $this->http->respond(['access_token' => 'a', 'refresh_token' => 'new-refresh']);

        $this->oauth()->authorize('code');

        $this->assertSame('new-refresh', $this->credentials->values['refresh_token']);
        $this->assertSame('', $this->credentials->values['access_token']);
    }

    /**
     * Check that Zoho's token errors are reported.
     *
     * @return void
     */
    public function testReportsTokenErrors(): void
    {
        $this->http->respond(['error' => 'invalid_code']);

        $this->expectException(ZohoApiException::class);
        $this->expectExceptionMessage('invalid_code');

        $this->oauth()->authorize('bad');
    }

    /**
     * Build the OAuth helper under test.
     *
     * @return ZohoOAuth
     */
    private function oauth(): ZohoOAuth
    {
        return new ZohoOAuth($this->config, $this->credentials, $this->http);
    }
}
