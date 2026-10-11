<?php

declare(strict_types=1);

namespace ZohoTime\Domain;

/**
 * A GitHub issue that time is spent on.
 */
final class Issue
{
    private readonly string $owner;

    private readonly string $repository;

    private readonly int $number;

    private readonly string $title;

    private readonly string $url;

    /**
     * Create an issue.
     *
     * @param string $owner      GitHub owner, e.g. "acme-studio".
     * @param string $repository Repository name, e.g. "bluebird-www".
     * @param int    $number     Issue number.
     * @param string $title      Issue title.
     * @param string $url        Issue URL.
     */
    public function __construct(string $owner, string $repository, int $number, string $title, string $url)
    {
        $this->owner = $owner;
        $this->repository = $repository;
        $this->number = $number;
        $this->title = $title;
        $this->url = $url;
    }

    /**
     * Get the GitHub owner.
     *
     * @return string
     */
    public function owner(): string
    {
        return $this->owner;
    }

    /**
     * Get the repository name.
     *
     * @return string
     */
    public function repository(): string
    {
        return $this->repository;
    }

    /**
     * Get the issue number.
     *
     * @return int
     */
    public function number(): int
    {
        return $this->number;
    }

    /**
     * Get the issue title.
     *
     * @return string
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * Refer to the issue the way it's discussed internally, e.g. "bluebird-www #39".
     *
     * @return string
     */
    public function reference(): string
    {
        return "{$this->repository} #{$this->number}";
    }

    /**
     * Get the issue URL.
     *
     * @return string
     */
    public function url(): string
    {
        return $this->url;
    }

    /**
     * Name the Zoho task for this issue: its title, since clients see task names on invoices.
     *
     * @return string
     */
    public function taskName(): string
    {
        return $this->title;
    }
}
