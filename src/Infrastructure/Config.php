<?php

declare(strict_types=1);

namespace ZohoTime\Infrastructure;

use InvalidArgumentException;
use RuntimeException;
use ZohoTime\Domain\Duration;
use ZohoTime\Domain\RecurringEntry;

/**
 * Settings read from ~/.config/zoho-time/config.json; nothing secret lives here.
 */
final class Config
{
    /** @var array<string, array{accounts: string, api: string}> */
    private const DATA_CENTERS = [
        'com' => ['accounts' => 'https://accounts.zoho.com', 'api' => 'https://www.zohoapis.com'],
        'eu' => ['accounts' => 'https://accounts.zoho.eu', 'api' => 'https://www.zohoapis.eu'],
        'in' => ['accounts' => 'https://accounts.zoho.in', 'api' => 'https://www.zohoapis.in'],
        'com.au' => ['accounts' => 'https://accounts.zoho.com.au', 'api' => 'https://www.zohoapis.com.au'],
        'jp' => ['accounts' => 'https://accounts.zoho.jp', 'api' => 'https://www.zohoapis.jp'],
        'ca' => ['accounts' => 'https://accounts.zohocloud.ca', 'api' => 'https://www.zohoapis.ca'],
    ];

    private readonly string $dataCenter;

    private readonly string $organizationId;

    private readonly string $githubOwner;

    /** @var list<string> */
    private readonly array $billableRepositories;

    /** @var list<string> */
    private readonly array $billableTopics;

    /** @var array<string, string> */
    private readonly array $projectNames;

    /** @var list<RecurringEntry> */
    private readonly array $recurringEntries;

    /**
     * Create the config.
     *
     * @param string                $dataCenter           Zoho data center domain, e.g. "com" or "eu".
     * @param string                $organizationId       Zoho Invoice organization ID.
     * @param string                $githubOwner          Owner assumed when an issue reference has none.
     * @param list<string>          $billableRepositories Repositories that are always billed.
     * @param list<string>          $billableTopics       Repository topics that mark a billed client.
     * @param array<string, string> $projectNames         Zoho project name per repository, where it differs from the repository name.
     * @param list<RecurringEntry>  $recurringEntries     Entries logged every month.
     *
     * @throws RuntimeException When the data center isn't known.
     */
    public function __construct(
        string $dataCenter,
        string $organizationId,
        string $githubOwner,
        array $billableRepositories,
        array $billableTopics,
        array $projectNames,
        array $recurringEntries = []
    ) {
        if (!isset(self::DATA_CENTERS[$dataCenter])) {
            throw new RuntimeException(
                "Unknown Zoho data center \"{$dataCenter}\"; use one of "
                . implode(', ', array_keys(self::DATA_CENTERS)) . '.'
            );
        }

        $this->dataCenter = $dataCenter;
        $this->organizationId = $organizationId;
        $this->githubOwner = $githubOwner;
        $this->billableRepositories = $billableRepositories;
        $this->billableTopics = $billableTopics;
        $this->projectNames = $projectNames;
        $this->recurringEntries = $recurringEntries;
    }

    /**
     * Get the config file's path: $ZOHO_TIME_CONFIG, or ~/.config/zoho-time/config.json.
     *
     * @return string
     */
    public static function defaultPath(): string
    {
        $path = getenv('ZOHO_TIME_CONFIG');

        return $path !== false && $path !== ''
            ? $path
            : rtrim((string) getenv('HOME'), '/') . '/.config/zoho-time/config.json';
    }

    /**
     * Load the config from a JSON file.
     *
     * @param string $path The file's path.
     *
     * @return self
     *
     * @throws RuntimeException When the file is missing, unreadable or incomplete.
     */
    public static function load(string $path): self
    {
        if (!is_readable($path)) {
            throw new RuntimeException(
                "No config at {$path}; copy config.example.json there and fill it in."
            );
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (!is_array($data)) {
            throw new RuntimeException("{$path} isn't valid JSON.");
        }

        foreach (['data_center', 'organization_id', 'github_owner'] as $key) {
            if (!isset($data[$key]) || !is_string($data[$key]) || $data[$key] === '') {
                throw new RuntimeException("{$path} needs a \"{$key}\".");
            }
        }

        return new self(
            $data['data_center'],
            $data['organization_id'],
            $data['github_owner'],
            array_values(array_map('strval', $data['billable_repositories'] ?? [])),
            array_values(array_map('strval', $data['billable_topics'] ?? [])),
            array_map('strval', $data['projects'] ?? []),
            self::recurringFrom($path, $data['recurring'] ?? [])
        );
    }

    /**
     * Read the "recurring" list: objects with a project, task, time (e.g. "0:30") and notes.
     *
     * @param string $path The config file's path, for error messages.
     * @param mixed  $rows The list as decoded from JSON.
     *
     * @return list<RecurringEntry>
     *
     * @throws RuntimeException When the list or one of its entries is malformed.
     */
    private static function recurringFrom(string $path, mixed $rows): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new RuntimeException("{$path}: \"recurring\" must be a list.");
        }

        $entries = [];

        foreach ($rows as $index => $row) {
            $number = $index + 1;

            try {
                if (!is_array($row)) {
                    throw new InvalidArgumentException('it must be an object.');
                }

                $entries[] = new RecurringEntry(
                    (string) ($row['project'] ?? ''),
                    (string) ($row['task'] ?? ''),
                    Duration::parse((string) ($row['time'] ?? '')),
                    (string) ($row['notes'] ?? '')
                );
            } catch (InvalidArgumentException $exception) {
                throw new RuntimeException("{$path}: recurring entry {$number}: {$exception->getMessage()}");
            }
        }

        return $entries;
    }

    /**
     * Get the Zoho Accounts URL for the data center, e.g. "https://accounts.zoho.com".
     *
     * @return string
     */
    public function accountsUrl(): string
    {
        return self::DATA_CENTERS[$this->dataCenter]['accounts'];
    }

    /**
     * Get the Zoho Invoice API base URL for the data center.
     *
     * @return string
     */
    public function invoiceApiUrl(): string
    {
        return self::DATA_CENTERS[$this->dataCenter]['api'] . '/invoice/v3';
    }

    /**
     * Get the Zoho Invoice organization ID.
     *
     * @return string
     */
    public function organizationId(): string
    {
        return $this->organizationId;
    }

    /**
     * Get the GitHub owner assumed when an issue reference has none.
     *
     * @return string
     */
    public function githubOwner(): string
    {
        return $this->githubOwner;
    }

    /**
     * Get the repositories that are always billed.
     *
     * @return list<string>
     */
    public function billableRepositories(): array
    {
        return $this->billableRepositories;
    }

    /**
     * Get the repository topics that mark a billed client.
     *
     * @return list<string>
     */
    public function billableTopics(): array
    {
        return $this->billableTopics;
    }

    /**
     * Get the Zoho project name for a repository; the repository's own name unless mapped.
     *
     * @param string $repository The repository name.
     *
     * @return string
     */
    public function projectNameFor(string $repository): string
    {
        return $this->projectNames[$repository] ?? $repository;
    }

    /**
     * Get the entries logged every month.
     *
     * @return list<RecurringEntry>
     */
    public function recurringEntries(): array
    {
        return $this->recurringEntries;
    }
}
