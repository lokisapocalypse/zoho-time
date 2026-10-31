<?php

declare(strict_types=1);

namespace ZohoTime\Domain;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * The same time entry every month, such as a maintenance or hosting block, dated the month's first day.
 */
final class RecurringEntry
{
    private readonly string $projectName;

    private readonly string $taskName;

    private readonly Duration $duration;

    private readonly string $notes;

    /**
     * Create a recurring entry.
     *
     * @param string   $projectName The Zoho project's name.
     * @param string   $taskName    The task's name on that project; it's created if the project doesn't have it.
     * @param Duration $duration    Time billed each month, exactly as given (not rounded).
     * @param string   $notes       Client-facing notes.
     *
     * @throws InvalidArgumentException When the project, task or notes are empty, or the notes are too long.
     */
    public function __construct(string $projectName, string $taskName, Duration $duration, string $notes)
    {
        foreach (['a project' => $projectName, 'a task' => $taskName, 'notes' => $notes] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("A recurring entry needs {$field}.");
            }
        }

        if (mb_strlen(trim($notes)) > TimeEntry::MAX_NOTES_LENGTH) {
            throw new InvalidArgumentException(
                'Recurring entry notes can be at most ' . TimeEntry::MAX_NOTES_LENGTH . ' characters.'
            );
        }

        $this->projectName = trim($projectName);
        $this->taskName = trim($taskName);
        $this->duration = $duration;
        $this->notes = trim($notes);
    }

    /**
     * Get the day a month's entry is dated: the month's first day, so it can be billed as the month starts.
     *
     * @param DateTimeImmutable $month Any day in the month.
     *
     * @return DateTimeImmutable
     */
    public static function dateFor(DateTimeImmutable $month): DateTimeImmutable
    {
        return $month->modify('first day of this month')->setTime(0, 0);
    }

    /**
     * Get the month to bill by default: the current one, which is due from its first day.
     *
     * @param DateTimeImmutable $today Today's date.
     *
     * @return DateTimeImmutable The month's first day.
     */
    public static function currentMonth(DateTimeImmutable $today): DateTimeImmutable
    {
        return self::dateFor($today);
    }

    /**
     * Refuse a month that hasn't started yet: its entries are dated its first day.
     *
     * @param DateTimeImmutable $month Any day in the month being billed.
     * @param DateTimeImmutable $today Today's date.
     *
     * @return void
     *
     * @throws DomainException When the month's first day is after today.
     */
    public static function assertDue(DateTimeImmutable $month, DateTimeImmutable $today): void
    {
        $date = self::dateFor($month);

        if ($date > $today->setTime(0, 0)) {
            throw new DomainException(
                "{$month->format('F Y')}'s recurring entries aren't due until {$date->format('Y-m-d')}."
            );
        }
    }

    /**
     * Build the month's entry on the task, with the duration exactly as configured.
     *
     * @param Task              $task   The task to log on.
     * @param string            $userId Zoho ID of the user logging it.
     * @param DateTimeImmutable $month  Any day in the month being billed.
     * @param DateTimeImmutable $today  Today's date, so months that aren't over can be refused.
     *
     * @return TimeEntry
     *
     * @throws DomainException When the month hasn't started yet.
     */
    public function entryFor(Task $task, string $userId, DateTimeImmutable $month, DateTimeImmutable $today): TimeEntry
    {
        self::assertDue($month, $today);

        return new TimeEntry(null, $task, $userId, self::dateFor($month), $this->duration, $this->notes);
    }

    /**
     * Start the entry's task on a project that doesn't have it yet; it's created when the entry is saved.
     *
     * @param Project $project The entry's Zoho project.
     *
     * @return Task
     */
    public function newTask(Project $project): Task
    {
        return new Task(null, $project, $this->taskName);
    }

    /**
     * Find the month's entry among those already logged on the project that day, if there is one.
     *
     * Any entry on the same task counts, whatever its hours or notes, so a month is never billed twice.
     *
     * @param Task            $task    The entry's task.
     * @param list<TimeEntry> $entries Entries logged on the project on the month's first day.
     *
     * @return TimeEntry|null
     */
    public function loggedAmong(Task $task, array $entries): ?TimeEntry
    {
        if (!$task->isSaved()) {
            return null;
        }

        foreach ($entries as $entry) {
            if ($entry->task()->isSaved() && $entry->task()->id() === $task->id()) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Get the Zoho project's name.
     *
     * @return string
     */
    public function projectName(): string
    {
        return $this->projectName;
    }

    /**
     * Get the task's name.
     *
     * @return string
     */
    public function taskName(): string
    {
        return $this->taskName;
    }

    /**
     * Get the time billed each month.
     *
     * @return Duration
     */
    public function duration(): Duration
    {
        return $this->duration;
    }

    /**
     * Get the client-facing notes.
     *
     * @return string
     */
    public function notes(): string
    {
        return $this->notes;
    }
}
