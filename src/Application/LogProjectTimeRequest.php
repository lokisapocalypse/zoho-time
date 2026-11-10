<?php

declare(strict_types=1);

namespace ZohoTime\Application;

use DateTimeImmutable;
use InvalidArgumentException;
use ZohoTime\Domain\Duration;

/**
 * What to log without an issue: time worked on a Zoho project's task on a day, with client-facing notes.
 */
final class LogProjectTimeRequest
{
    private readonly string $projectName;

    private readonly Duration $worked;

    private readonly DateTimeImmutable $date;

    private readonly string $task;

    private readonly string $notes;

    /**
     * Create a request.
     *
     * @param string            $projectName The Zoho project's name.
     * @param Duration          $worked      Time worked, before rounding.
     * @param DateTimeImmutable $date        The day the work was done.
     * @param string            $task        The task's name or ID; a name the project doesn't have yet is created.
     * @param string            $notes       Client-facing notes.
     *
     * @throws InvalidArgumentException When the project or task is empty.
     */
    public function __construct(
        string $projectName,
        Duration $worked,
        DateTimeImmutable $date,
        string $task,
        string $notes
    ) {
        if (trim($projectName) === '') {
            throw new InvalidArgumentException('Name the Zoho project to log on.');
        }

        if (trim($task) === '') {
            throw new InvalidArgumentException('Name the task to log on, with --task="<name>".');
        }

        $this->projectName = trim($projectName);
        $this->worked = $worked;
        $this->date = $date;
        $this->task = trim($task);
        $this->notes = $notes;
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
     * Get the time worked, before rounding.
     *
     * @return Duration
     */
    public function worked(): Duration
    {
        return $this->worked;
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
     * Get the task's name or ID.
     *
     * @return string
     */
    public function task(): string
    {
        return $this->task;
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
