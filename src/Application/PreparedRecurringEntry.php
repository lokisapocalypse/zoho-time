<?php

declare(strict_types=1);

namespace ZohoTime\Application;

use LogicException;
use ZohoTime\Domain\RecurringEntry;
use ZohoTime\Domain\TimeEntry;

/**
 * A month's recurring entry ready to show for confirmation, with whether it's due, already logged or can't be logged.
 */
final class PreparedRecurringEntry
{
    private readonly int $number;

    private readonly RecurringEntry $recurring;

    private RecurringStatus $status;

    private readonly ?TimeEntry $entry;

    private readonly ?string $problem;

    private readonly bool $createsTask;

    /**
     * Create a prepared recurring entry.
     *
     * @param int             $number      Its position in the config, from 1, used to skip it.
     * @param RecurringEntry  $recurring   The configured entry.
     * @param RecurringStatus $status      Where it stands.
     * @param TimeEntry|null  $entry       The entry to save, or the one already logged; null when unavailable.
     * @param string|null     $problem     Why it can't be logged, when unavailable.
     * @param bool            $createsTask Whether saving also creates the entry's Zoho task.
     */
    public function __construct(
        int $number,
        RecurringEntry $recurring,
        RecurringStatus $status,
        ?TimeEntry $entry,
        ?string $problem = null,
        bool $createsTask = false
    ) {
        $this->number = $number;
        $this->recurring = $recurring;
        $this->status = $status;
        $this->entry = $entry;
        $this->problem = $problem;
        $this->createsTask = $createsTask;
    }

    /**
     * Record that the due entry was saved.
     *
     * @return void
     *
     * @throws LogicException When the entry wasn't due or hasn't been given a Zoho ID.
     */
    public function markSaved(): void
    {
        if ($this->status !== RecurringStatus::Due || $this->entry?->id() === null) {
            throw new LogicException('Only a due entry that Zoho has saved can be marked saved.');
        }

        $this->status = RecurringStatus::Saved;
    }

    /**
     * Get its position in the config, from 1.
     *
     * @return int
     */
    public function number(): int
    {
        return $this->number;
    }

    /**
     * Get the configured entry.
     *
     * @return RecurringEntry
     */
    public function recurring(): RecurringEntry
    {
        return $this->recurring;
    }

    /**
     * Get where it stands.
     *
     * @return RecurringStatus
     */
    public function status(): RecurringStatus
    {
        return $this->status;
    }

    /**
     * Get the entry to save, or the one already logged; null when unavailable.
     *
     * @return TimeEntry|null
     */
    public function entry(): ?TimeEntry
    {
        return $this->entry;
    }

    /**
     * Tell whether saving also creates the entry's Zoho task.
     *
     * @return bool
     */
    public function createsTask(): bool
    {
        return $this->createsTask;
    }

    /**
     * Get why it can't be logged, when unavailable.
     *
     * @return string|null
     */
    public function problem(): ?string
    {
        return $this->problem;
    }
}
