<?php

declare(strict_types=1);

namespace ZohoTime\Domain;

use DateTimeImmutable;

/**
 * Reads and writes Zoho time entries.
 */
interface TimeEntryRepository
{
    /**
     * Save a new time entry and mark it with its Zoho ID.
     *
     * @param TimeEntry $entry The unsaved entry.
     *
     * @return void
     */
    public function save(TimeEntry $entry): void;

    /**
     * List the time entries logged on a day, optionally only on one project.
     *
     * @param DateTimeImmutable $date    The day.
     * @param Project|null      $project Only this project's entries, or all when null.
     *
     * @return list<TimeEntry>
     */
    public function onDate(DateTimeImmutable $date, ?Project $project = null): array;

    /**
     * Find a time entry by its ID.
     *
     * @param string $id Zoho time entry ID.
     *
     * @return TimeEntry
     *
     * @throws \RuntimeException When there's no such entry.
     */
    public function find(string $id): TimeEntry;

    /**
     * Delete a time entry.
     *
     * @param string $id Zoho time entry ID.
     *
     * @return void
     */
    public function delete(string $id): void;
}
