<?php

declare(strict_types=1);

namespace ZohoTime\Application;

use DateTimeImmutable;
use ZohoTime\Domain\Duration;

/**
 * What to log: time worked on an issue on a day, optionally on an existing task and with set notes.
 */
final class LogTimeRequest
{
    private readonly string $reference;

    private readonly Duration $worked;

    private readonly DateTimeImmutable $date;

    private readonly ?string $existingTask;

    private readonly ?string $notes;

    /**
     * Create a request.
     *
     * @param string            $reference    The issue, e.g. "bluebird-www#39".
     * @param Duration          $worked       Time worked, before rounding.
     * @param DateTimeImmutable $date         The day the work was done.
     * @param string|null       $existingTask Name or ID of an existing task to log on instead of the issue's own.
     * @param string|null       $notes        Client-facing notes, or null for the issue's title.
     */
    public function __construct(
        string $reference,
        Duration $worked,
        DateTimeImmutable $date,
        ?string $existingTask = null,
        ?string $notes = null
    ) {
        $this->reference = $reference;
        $this->worked = $worked;
        $this->date = $date;
        $this->existingTask = $existingTask;
        $this->notes = $notes;
    }

    /**
     * Get the issue reference.
     *
     * @return string
     */
    public function reference(): string
    {
        return $this->reference;
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
     * Get the existing task's name or ID, or null for the issue's own task.
     *
     * @return string|null
     */
    public function existingTask(): ?string
    {
        return $this->existingTask;
    }

    /**
     * Get the notes, or null for the issue's title.
     *
     * @return string|null
     */
    public function notes(): ?string
    {
        return $this->notes;
    }
}
