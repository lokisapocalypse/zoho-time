<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Fakes;

use DateTimeImmutable;
use RuntimeException;
use ZohoTime\Domain\Project;
use ZohoTime\Domain\TimeEntry;
use ZohoTime\Domain\TimeEntryRepository;

/**
 * Keeps time entries in memory.
 */
final class FakeTimeEntryRepository implements TimeEntryRepository
{
    /** @var array<string, TimeEntry> */
    public array $saved = [];

    /**
     * Save an entry with the next ID.
     *
     * @param TimeEntry $entry The entry.
     *
     * @return void
     */
    public function save(TimeEntry $entry): void
    {
        $entry->markSaved('e' . (count($this->saved) + 1));
        $this->saved[(string) $entry->id()] = $entry;
    }

    /**
     * List saved entries on a day, optionally on one project.
     *
     * @param DateTimeImmutable $date    The day.
     * @param Project|null      $project The project, or null for all.
     *
     * @return list<TimeEntry>
     */
    public function onDate(DateTimeImmutable $date, ?Project $project = null): array
    {
        return array_values(array_filter(
            $this->saved,
            static fn (TimeEntry $entry): bool => $entry->date() == $date->setTime(0, 0)
                && ($project === null || $entry->task()->project()->id() === $project->id())
        ));
    }

    /**
     * Find a saved entry.
     *
     * @param string $id The entry ID.
     *
     * @return TimeEntry
     *
     * @throws RuntimeException When there's none.
     */
    public function find(string $id): TimeEntry
    {
        return $this->saved[$id] ?? throw new RuntimeException("No entry {$id}.");
    }

    /**
     * Delete a saved entry.
     *
     * @param string $id The entry ID.
     *
     * @return void
     */
    public function delete(string $id): void
    {
        unset($this->saved[$id]);
    }
}
