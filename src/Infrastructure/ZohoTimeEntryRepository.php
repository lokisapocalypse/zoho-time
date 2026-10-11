<?php

declare(strict_types=1);

namespace ZohoTime\Infrastructure;

use DateTimeImmutable;
use ZohoTime\Domain\Duration;
use ZohoTime\Domain\Project;
use ZohoTime\Domain\Task;
use ZohoTime\Domain\TimeEntry;
use ZohoTime\Domain\TimeEntryRepository;

/**
 * Reads and writes Zoho Invoice time entries.
 */
final class ZohoTimeEntryRepository implements TimeEntryRepository
{
    private readonly ZohoClient $client;

    /**
     * Create the repository.
     *
     * @param ZohoClient $client Calls Zoho.
     */
    public function __construct(ZohoClient $client)
    {
        $this->client = $client;
    }

    /**
     * Save a new, billable time entry and mark it with its Zoho ID.
     *
     * @param TimeEntry $entry The unsaved entry; its task must be saved.
     *
     * @return void
     *
     * @throws ZohoApiException When Zoho refuses the entry.
     */
    public function save(TimeEntry $entry): void
    {
        $data = $this->client->post('/projects/timeentries', [
            'project_id' => $entry->task()->project()->id(),
            'task_id' => $entry->task()->id(),
            'user_id' => $entry->userId(),
            'log_date' => $entry->date()->format('Y-m-d'),
            'log_time' => $entry->duration()->toZohoLogTime(),
            'is_billable' => true,
            'notes' => $entry->notes(),
        ]);

        if (!isset($data['time_entry']['time_entry_id'])) {
            throw new ZohoApiException('Zoho saved the time entry but returned no ID.');
        }

        $entry->markSaved((string) $data['time_entry']['time_entry_id']);
    }

    /**
     * List the time entries logged on a day, optionally only on one project.
     *
     * @param DateTimeImmutable $date    The day.
     * @param Project|null      $project Only this project's entries, or all when null.
     *
     * @return list<TimeEntry>
     */
    public function onDate(DateTimeImmutable $date, ?Project $project = null): array
    {
        $query = [
            'filter_by' => 'Date.CustomDate',
            'from_date' => $date->format('Y-m-d'),
            'to_date' => $date->format('Y-m-d'),
        ];

        if ($project !== null) {
            $query['project_id'] = $project->id();
        }

        return array_map(
            fn (array $row): TimeEntry => $this->hydrate($row),
            $this->client->all('/projects/timeentries', 'time_entries', $query)
        );
    }

    /**
     * Find a time entry by its ID.
     *
     * @param string $id Zoho time entry ID.
     *
     * @return TimeEntry
     *
     * @throws ZohoApiException When there's no such entry.
     */
    public function find(string $id): TimeEntry
    {
        $data = $this->client->get('/projects/timeentries/' . rawurlencode($id));

        if (!isset($data['time_entry']) || !is_array($data['time_entry'])) {
            throw new ZohoApiException("Zoho has no time entry {$id}.");
        }

        return $this->hydrate($data['time_entry']);
    }

    /**
     * Delete a time entry.
     *
     * @param string $id Zoho time entry ID.
     *
     * @return void
     *
     * @throws ZohoApiException When Zoho refuses the deletion.
     */
    public function delete(string $id): void
    {
        $this->client->delete('/projects/timeentries/' . rawurlencode($id));
    }

    /**
     * Build a time entry from a row of Zoho's time entry list.
     *
     * @param array<string, mixed> $row The row.
     *
     * @return TimeEntry
     */
    private function hydrate(array $row): TimeEntry
    {
        $project = new Project((string) $row['project_id'], (string) ($row['project_name'] ?? ''));

        return new TimeEntry(
            (string) $row['time_entry_id'],
            new Task((string) $row['task_id'], $project, (string) ($row['task_name'] ?? '')),
            (string) ($row['user_id'] ?? ''),
            new DateTimeImmutable((string) $row['log_date']),
            Duration::fromZohoLogTime((string) $row['log_time']),
            (string) ($row['notes'] ?? '')
        );
    }
}
