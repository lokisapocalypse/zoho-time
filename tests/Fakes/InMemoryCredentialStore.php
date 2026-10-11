<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Fakes;

use ZohoTime\Infrastructure\CredentialStore;

/**
 * Keeps credentials in memory.
 */
final class InMemoryCredentialStore implements CredentialStore
{
    /** @var array<string, string> */
    public array $values;

    /**
     * Create the store with some credentials.
     *
     * @param array<string, string> $values Credentials by name.
     */
    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    /**
     * Read a credential.
     *
     * @param string $name The name.
     *
     * @return string|null
     */
    public function get(string $name): ?string
    {
        return $this->values[$name] ?? null;
    }

    /**
     * Store a credential.
     *
     * @param string $name  The name.
     * @param string $value The value.
     *
     * @return void
     */
    public function set(string $name, string $value): void
    {
        $this->values[$name] = $value;
    }
}
