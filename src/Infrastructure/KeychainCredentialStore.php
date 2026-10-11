<?php

declare(strict_types=1);

namespace ZohoTime\Infrastructure;

use RuntimeException;

/**
 * Keeps credentials in the macOS login Keychain under the service "zoho-time".
 *
 * An environment variable such as ZOHO_TIME_CLIENT_ID takes precedence over the Keychain.
 */
final class KeychainCredentialStore implements CredentialStore
{
    public const SERVICE = 'zoho-time';

    private const ITEM_NOT_FOUND = 44;

    private readonly ProcessRunner $runner;

    /**
     * Create the store.
     *
     * @param ProcessRunner $runner Runs the `security` command.
     */
    public function __construct(ProcessRunner $runner)
    {
        $this->runner = $runner;
    }

    /**
     * Read a credential from the environment or the Keychain.
     *
     * @param string $name The credential's name.
     *
     * @return string|null
     *
     * @throws RuntimeException When the Keychain can't be read.
     */
    public function get(string $name): ?string
    {
        $fromEnvironment = getenv('ZOHO_TIME_' . strtoupper($name));

        if ($fromEnvironment !== false && $fromEnvironment !== '') {
            return $fromEnvironment;
        }

        $result = $this->runner->run(
            ['security', 'find-generic-password', '-s', self::SERVICE, '-a', $name, '-w']
        );

        if ($result->exitCode() === self::ITEM_NOT_FOUND) {
            return null;
        }

        if (!$result->succeeded()) {
            throw new RuntimeException("Can't read {$name} from the Keychain: {$result->error()}");
        }

        return rtrim($result->output(), "\n");
    }

    /**
     * Store a credential in the Keychain.
     *
     * @param string $name  The credential's name.
     * @param string $value The value.
     *
     * @return void
     *
     * @throws RuntimeException When the Keychain can't be written.
     */
    public function set(string $name, string $value): void
    {
        $result = $this->runner->run(
            ['security', 'add-generic-password', '-U', '-s', self::SERVICE, '-a', $name, '-w', $value]
        );

        if (!$result->succeeded()) {
            throw new RuntimeException("Can't save {$name} to the Keychain: {$result->error()}");
        }
    }
}
