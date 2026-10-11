<?php

declare(strict_types=1);

namespace ZohoTime\Infrastructure;

use InvalidArgumentException;
use RuntimeException;
use ZohoTime\Domain\Issue;
use ZohoTime\Domain\IssueRepository;

/**
 * Reads issues and repository topics from GitHub through the gh CLI.
 */
final class GitHubIssueRepository implements IssueRepository
{
    private readonly ProcessRunner $runner;

    private readonly string $defaultOwner;

    /**
     * Create the repository.
     *
     * @param ProcessRunner $runner       Runs gh.
     * @param string        $defaultOwner Owner assumed when a reference has none.
     */
    public function __construct(ProcessRunner $runner, string $defaultOwner)
    {
        $this->runner = $runner;
        $this->defaultOwner = $defaultOwner;
    }

    /**
     * Find an issue from a reference such as "bluebird-www#39", "bluebird-www 39" or "Owner/bluebird-www#39".
     *
     * @param string $reference The issue reference.
     *
     * @return Issue
     *
     * @throws InvalidArgumentException When the reference can't be read.
     * @throws RuntimeException When gh can't find the issue.
     */
    public function find(string $reference): Issue
    {
        if (!preg_match('~^(?:([\w.-]+)/)?([\w.-]+)\s*(?:#|\s)(\d+)$~', trim($reference), $match)) {
            throw new InvalidArgumentException(
                "Can't read \"{$reference}\" as an issue; use e.g. bluebird-www#39."
            );
        }

        $owner = $match[1] !== '' ? $match[1] : $this->defaultOwner;
        $data = $this->json(['gh', 'issue', 'view', $match[3], '-R', "{$owner}/{$match[2]}", '--json', 'title,url']);

        return new Issue($owner, $match[2], (int) $match[3], (string) $data['title'], (string) $data['url']);
    }

    /**
     * List the topics of the issue's repository.
     *
     * @param Issue $issue The issue.
     *
     * @return list<string>
     *
     * @throws RuntimeException When gh can't read the repository.
     */
    public function topicsOf(Issue $issue): array
    {
        $data = $this->json(['gh', 'api', "repos/{$issue->owner()}/{$issue->repository()}/topics"]);

        return array_values(array_map('strval', $data['names'] ?? []));
    }

    /**
     * Run a gh command and decode its JSON output.
     *
     * @param list<string> $command The command.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException When the command fails or its output isn't JSON.
     */
    private function json(array $command): array
    {
        $result = $this->runner->run($command);
        $data = json_decode($result->output(), true);

        if (!$result->succeeded() || !is_array($data)) {
            throw new RuntimeException(implode(' ', $command) . ' failed: ' . $result->error());
        }

        return $data;
    }
}
