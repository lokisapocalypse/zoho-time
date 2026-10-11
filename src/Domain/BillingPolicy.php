<?php

declare(strict_types=1);

namespace ZohoTime\Domain;

/**
 * Decides which repositories' issues are billed.
 */
final class BillingPolicy
{
    /** @var list<string> */
    private readonly array $billableRepositories;

    /** @var list<string> */
    private readonly array $billableTopics;

    /**
     * Create the policy.
     *
     * @param list<string> $billableRepositories Repositories that are always billed, e.g. ["harbor-clinic"].
     * @param list<string> $billableTopics       Repository topics that mark a client repo, e.g. ["client-bluebird"].
     */
    public function __construct(array $billableRepositories, array $billableTopics)
    {
        $this->billableRepositories = array_map('strtolower', $billableRepositories);
        $this->billableTopics = array_map('strtolower', $billableTopics);
    }

    /**
     * Tell whether work in a repository is billed.
     *
     * @param string       $repository The repository name.
     * @param list<string> $topics     The repository's GitHub topics.
     *
     * @return bool
     */
    public function isBillable(string $repository, array $topics): bool
    {
        if (in_array(strtolower($repository), $this->billableRepositories, true)) {
            return true;
        }

        return array_intersect(array_map('strtolower', $topics), $this->billableTopics) !== [];
    }

    /**
     * Refuse to bill an issue whose repository isn't billable.
     *
     * @param Issue        $issue  The issue to bill.
     * @param list<string> $topics The repository's GitHub topics.
     *
     * @return void
     *
     * @throws NotBillableException When the repository isn't billable.
     */
    public function assertBillable(Issue $issue, array $topics): void
    {
        if (!$this->isBillable($issue->repository(), $topics)) {
            throw new NotBillableException(
                "{$issue->repository()} isn't billable: only "
                . implode(', ', $this->billableRepositories)
                . ' and repos with the topic ' . implode(' or ', $this->billableTopics) . ' are billed.'
            );
        }
    }
}
