<?php

declare(strict_types=1);

namespace ZohoTime\Domain;

use DateTimeImmutable;
use DomainException;
use LogicException;

/**
 * Time logged on a Zoho task on a given day.
 */
final class TimeEntry
{
    public const MAX_NOTES_LENGTH = 500;

    private ?string $id;

    private readonly Task $task;

    private readonly string $userId;

    private readonly DateTimeImmutable $date;

    private readonly Duration $duration;

    private readonly string $notes;

    /**
     * Create a time entry.
     *
     * @param string|null       $id       Zoho time entry ID, or null while it isn't saved yet.
     * @param Task              $task     The task the time was spent on.
     * @param string            $userId   Zoho ID of the user who did the work.
     * @param DateTimeImmutable $date     The day the work was done.
     * @param Duration          $duration Time spent.
     * @param string            $notes    Notes shown on the entry.
     */
    public function __construct(
        ?string $id,
        Task $task,
        string $userId,
        DateTimeImmutable $date,
        Duration $duration,
        string $notes
    ) {
        $this->id = $id;
        $this->task = $task;
        $this->userId = $userId;
        $this->date = $date->setTime(0, 0);
        $this->duration = $duration;
        $this->notes = $notes;
    }

    /**
     * Bill time worked on an issue, with hours rounded up to the quarter hour.
     *
     * The notes are for the client: the given wording, or else the issue's title. They never carry
     * the repository, issue number or GitHub link.
     *
     * @param Issue             $issue  The issue worked on.
     * @param Task              $task   The Zoho task to log on: the issue's own, or a shared one.
     * @param string            $userId Zoho ID of the user who did the work.
     * @param DateTimeImmutable $date   The day the work was done.
     * @param Duration          $worked Time actually spent, before rounding.
     * @param DateTimeImmutable $today  Today's date, so future days can be refused.
     * @param string|null       $notes  Client-facing notes, or null for the issue's title.
     *
     * @return self
     *
     * @throws DomainException When the date is in the future or the notes are too long for Zoho.
     */
    public static function forIssue(
        Issue $issue,
        Task $task,
        string $userId,
        DateTimeImmutable $date,
        Duration $worked,
        DateTimeImmutable $today,
        ?string $notes = null
    ): self {
        if ($date->setTime(0, 0) > $today->setTime(0, 0)) {
            throw new DomainException("Can't log time on {$date->format('Y-m-d')}: it's in the future.");
        }

        $notes = $notes === null || trim($notes) === '' ? $issue->title() : trim($notes);

        if (mb_strlen($notes) > self::MAX_NOTES_LENGTH) {
            throw new DomainException('Notes can be at most ' . self::MAX_NOTES_LENGTH . ' characters.');
        }

        return new self(null, $task, $userId, $date, $worked->roundedUpToQuarterHour(), $notes);
    }

    /**
     * Record the ID Zoho gave the entry when it was saved.
     *
     * @param string $id Zoho time entry ID.
     *
     * @return void
     *
     * @throws LogicException When the entry was already saved.
     */
    public function markSaved(string $id): void
    {
        if ($this->id !== null) {
            throw new LogicException('This time entry is already saved.');
        }

        $this->id = $id;
    }

    /**
     * Get the Zoho time entry ID, or null while it isn't saved.
     *
     * @return string|null
     */
    public function id(): ?string
    {
        return $this->id;
    }

    /**
     * Get the task the time was spent on.
     *
     * @return Task
     */
    public function task(): Task
    {
        return $this->task;
    }

    /**
     * Get the Zoho ID of the user who did the work.
     *
     * @return string
     */
    public function userId(): string
    {
        return $this->userId;
    }

    /**
     * Get the day the work was done.
     *
     * @return DateTimeImmutable
     */
    public function date(): DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * Get the time billed.
     *
     * @return Duration
     */
    public function duration(): Duration
    {
        return $this->duration;
    }

    /**
     * Get the entry's notes.
     *
     * @return string
     */
    public function notes(): string
    {
        return $this->notes;
    }
}
