<?php

declare(strict_types=1);

namespace ZohoTime\Infrastructure;

/**
 * Holds Zoho OAuth credentials outside the repository.
 */
interface CredentialStore
{
    /**
     * Read a credential.
     *
     * @param string $name e.g. "client_id", "client_secret", "refresh_token", "access_token".
     *
     * @return string|null The value, or null when it isn't stored.
     */
    public function get(string $name): ?string;

    /**
     * Store a credential, replacing any earlier value.
     *
     * @param string $name  The credential's name.
     * @param string $value The value.
     *
     * @return void
     */
    public function set(string $name, string $value): void;
}
