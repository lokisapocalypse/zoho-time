<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Fakes;

use RuntimeException;
use ZohoTime\Domain\Issue;
use ZohoTime\Domain\IssueRepository;

/**
 * Serves issues and topics from memory.
 */
final class FakeIssueRepository implements IssueRepository
{
    /** @var array<string, Issue> */
    private array $issues = [];

    /** @var array<string, list<string>> */
    private array $topics = [];

    /**
     * Add an issue, findable as "repo#number", and its repository's topics.
     *
     * @param Issue        $issue  The issue.
     * @param list<string> $topics The repository's topics.
     *
     * @return void
     */
    public function add(Issue $issue, array $topics): void
    {
        $this->issues["{$issue->repository()}#{$issue->number()}"] = $issue;
        $this->topics[$issue->repository()] = $topics;
    }

    /**
     * Find an added issue.
     *
     * @param string $reference "repo#number".
     *
     * @return Issue
     *
     * @throws RuntimeException When it wasn't added.
     */
    public function find(string $reference): Issue
    {
        return $this->issues[$reference] ?? throw new RuntimeException("No issue {$reference}.");
    }

    /**
     * List the topics added with the issue's repository.
     *
     * @param Issue $issue The issue.
     *
     * @return list<string>
     */
    public function topicsOf(Issue $issue): array
    {
        return $this->topics[$issue->repository()] ?? [];
    }
}
