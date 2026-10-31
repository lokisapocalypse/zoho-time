<?php

declare(strict_types=1);

namespace ZohoTime\Application;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use ZohoTime\Domain\ProjectRepository;
use ZohoTime\Domain\RecurringEntry;
use ZohoTime\Domain\TaskRepository;
use ZohoTime\Domain\TimeEntry;
use ZohoTime\Domain\TimeEntryRepository;

/**
 * Prepares and saves a month's recurring entries, skipping any already in Zoho and creating missing tasks.
 */
final class RecurringEntryService
{
    private readonly ProjectRepository $projects;

    private readonly TaskRepository $tasks;

    private readonly TimeEntryRepository $entries;

    /** @var list<RecurringEntry> */
    private readonly array $recurring;

    /**
     * Create the service.
     *
     * @param ProjectRepository    $projects  Zoho projects.
     * @param TaskRepository       $tasks     Zoho tasks.
     * @param TimeEntryRepository  $entries   Zoho time entries.
     * @param list<RecurringEntry> $recurring The configured recurring entries.
     */
    public function __construct(
        ProjectRepository $projects,
        TaskRepository $tasks,
        TimeEntryRepository $entries,
        array $recurring
    ) {
        $this->projects = $projects;
        $this->tasks = $tasks;
        $this->entries = $entries;
        $this->recurring = $recurring;
    }

    /**
     * Work out where each of the month's recurring entries stands, without saving anything.
     *
     * @param DateTimeImmutable $month Any day in the month being billed.
     * @param DateTimeImmutable $today Today's date.
     * @param list<int>         $skip  Numbers of the entries to leave out this time.
     *
     * @return list<PreparedRecurringEntry>
     *
     * @throws DomainException When the month hasn't started yet.
     * @throws InvalidArgumentException When a number to skip isn't one of the entries.
     */
    public function prepare(DateTimeImmutable $month, DateTimeImmutable $today, array $skip = []): array
    {
        RecurringEntry::assertDue($month, $today);
        $date = RecurringEntry::dateFor($month);

        foreach ($skip as $number) {
            if ($number < 1 || $number > count($this->recurring)) {
                throw new InvalidArgumentException(
                    "There's no recurring entry {$number}; they're numbered 1 to " . count($this->recurring) . '.'
                );
            }
        }

        $logged = [];
        $users = [];
        $newTasks = [];
        $prepared = [];

        foreach ($this->recurring as $index => $recurring) {
            $number = $index + 1;
            $project = $this->projects->findByName($recurring->projectName());

            if ($project === null) {
                $prepared[] = $this->unavailable(
                    $number,
                    $recurring,
                    "Zoho has no active project named \"{$recurring->projectName()}\"."
                );

                continue;
            }

            // Two entries on the same missing task share one new task, so it's only created once.
            $newTaskKey = $project->id() . "\n" . mb_strtolower($recurring->taskName());
            $task = $this->tasks->findByNameOrId($project, $recurring->taskName())
                ?? ($newTasks[$newTaskKey] ??= $recurring->newTask($project));

            $logged[$project->id()] ??= $this->entries->onDate($date, $project);
            $existing = $recurring->loggedAmong($task, $logged[$project->id()]);

            if ($existing !== null) {
                $prepared[] = new PreparedRecurringEntry($number, $recurring, RecurringStatus::Logged, $existing);

                continue;
            }

            $users[$project->id()] ??= $this->projects->currentUserIdOn($project);
            $prepared[] = new PreparedRecurringEntry(
                $number,
                $recurring,
                in_array($number, $skip, true) ? RecurringStatus::Skipped : RecurringStatus::Due,
                $recurring->entryFor($task, $users[$project->id()], $month, $today),
                null,
                !$task->isSaved()
            );
        }

        return $prepared;
    }

    /**
     * Save the month's due recurring entries, creating any of their tasks the project doesn't have yet.
     *
     * @param DateTimeImmutable $month Any day in the month being billed.
     * @param DateTimeImmutable $today Today's date.
     * @param list<int>         $skip  Numbers of the entries to leave out this time.
     *
     * @return list<PreparedRecurringEntry> Every entry, with the ones saved now marked Saved.
     *
     * @throws DomainException When the month hasn't started yet.
     * @throws InvalidArgumentException When a number to skip isn't one of the entries.
     * @throws \RuntimeException When Zoho refuses an entry; the ones before it stay saved.
     */
    public function log(DateTimeImmutable $month, DateTimeImmutable $today, array $skip = []): array
    {
        $prepared = $this->prepare($month, $today, $skip);

        foreach ($prepared as $item) {
            if ($item->status() === RecurringStatus::Due && $item->entry() instanceof TimeEntry) {
                if (!$item->entry()->task()->isSaved()) {
                    $this->tasks->save($item->entry()->task());
                }

                $this->entries->save($item->entry());
                $item->markSaved();
            }
        }

        return $prepared;
    }

    /**
     * Prepare an entry that can't be logged.
     *
     * @param int            $number    Its position in the config.
     * @param RecurringEntry $recurring The configured entry.
     * @param string         $problem   Why it can't be logged.
     *
     * @return PreparedRecurringEntry
     */
    private function unavailable(int $number, RecurringEntry $recurring, string $problem): PreparedRecurringEntry
    {
        return new PreparedRecurringEntry($number, $recurring, RecurringStatus::Unavailable, null, $problem);
    }
}
