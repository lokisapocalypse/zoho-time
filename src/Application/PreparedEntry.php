<?php

declare(strict_types=1);

namespace ZohoTime\Application;

use ZohoTime\Domain\Duration;
use ZohoTime\Domain\Issue;
use ZohoTime\Domain\TimeEntry;

/**
 * A time entry ready to show for confirmation, with what's needed to explain it.
 */
final class PreparedEntry
{
    private readonly Issue $issue;

    private readonly TimeEntry $entry;

    private readonly Duration $worked;

    private readonly bool $createsTask;

    /** @var list<TimeEntry> */
    private readonly array $sameDayEntries;

    /**
     * Create a prepared entry.
     *
     * @param Issue           $issue          The issue being billed.
     * @param TimeEntry       $entry          The entry to save.
     * @param Duration        $worked         Time worked before rounding.
     * @param bool            $createsTask    Whether saving also creates the issue's Zoho task.
     * @param list<TimeEntry> $sameDayEntries Entries already on the task that day.
     */
    public function __construct(
        Issue $issue,
        TimeEntry $entry,
        Duration $worked,
        bool $createsTask,
        array $sameDayEntries
    ) {
        $this->issue = $issue;
        $this->entry = $entry;
        $this->worked = $worked;
        $this->createsTask = $createsTask;
        $this->sameDayEntries = $sameDayEntries;
    }

    /**
     * Get the issue being billed.
     *
     * @return Issue
     */
    public function issue(): Issue
    {
        return $this->issue;
    }

    /**
     * Get the entry to save.
     *
     * @return TimeEntry
     */
    public function entry(): TimeEntry
    {
        return $this->entry;
    }

    /**
     * Get the time worked before rounding.
     *
     * @return Duration
     */
    public function worked(): Duration
    {
        return $this->worked;
    }

    /**
     * Tell whether saving also creates the issue's Zoho task.
     *
     * @return bool
     */
    public function createsTask(): bool
    {
        return $this->createsTask;
    }

    /**
     * Get the entries already logged on the task that day.
     *
     * @return list<TimeEntry>
     */
    public function sameDayEntries(): array
    {
        return $this->sameDayEntries;
    }
}
