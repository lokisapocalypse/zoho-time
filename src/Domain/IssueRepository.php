<?php

declare(strict_types=1);

namespace ZohoTime\Domain;

/**
 * Looks up GitHub issues and their repositories' topics.
 */
interface IssueRepository
{
    /**
     * Find an issue from a reference such as "bluebird-www#39" or "acme-studio/bluebird-www#39".
     *
     * @param string $reference The issue reference.
     *
     * @return Issue
     *
     * @throws \InvalidArgumentException When the reference can't be read.
     * @throws \RuntimeException When the issue can't be found.
     */
    public function find(string $reference): Issue;

    /**
     * List the topics of the issue's repository.
     *
     * @param Issue $issue The issue.
     *
     * @return list<string>
     *
     * @throws \RuntimeException When the topics can't be read.
     */
    public function topicsOf(Issue $issue): array;
}
