<?php

declare(strict_types=1);

namespace ZohoTime\Domain;

use LogicException;

/**
 * A task on a Zoho project; each billed issue has its own.
 */
final class Task
{
    /** Zoho refuses task names of 100 characters or more. */
    public const MAX_NAME_LENGTH = 99;

    private ?string $id;

    private readonly Project $project;

    private readonly string $name;

    /**
     * Create a task.
     *
     * @param string|null $id      Zoho task ID, or null while it isn't saved yet.
     * @param Project     $project The project the task belongs to.
     * @param string      $name    Task name.
     */
    public function __construct(?string $id, Project $project, string $name)
    {
        $this->id = $id;
        $this->project = $project;
        $this->name = $name;
    }

    /**
     * Start a new, unsaved task for an issue on its repository's project, named by its title unless named otherwise.
     *
     * @param Project     $project The repository's Zoho project.
     * @param Issue       $issue   The issue being billed.
     * @param string|null $name    A name for the task, or null for the issue's title.
     *
     * @return self
     *
     * @throws TaskNameTooLongException When the name is longer than Zoho accepts.
     */
    public static function forIssue(Project $project, Issue $issue, ?string $name = null): self
    {
        return self::named($project, $name ?? $issue->taskName());
    }

    /**
     * Start a new, unsaved task with a given name on a project.
     *
     * @param Project $project The Zoho project.
     * @param string  $name    The task's name.
     *
     * @return self
     *
     * @throws TaskNameTooLongException When the name is longer than Zoho accepts.
     */
    public static function named(Project $project, string $name): self
    {
        $length = mb_strlen($name);

        if ($length > self::MAX_NAME_LENGTH) {
            throw new TaskNameTooLongException(sprintf(
                'Zoho task names can be at most %d characters, and "%s" is %d.',
                self::MAX_NAME_LENGTH,
                $name,
                $length
            ));
        }

        return new self(null, $project, $name);
    }

    /**
     * Record the ID Zoho gave the task when it was saved.
     *
     * @param string $id Zoho task ID.
     *
     * @return void
     *
     * @throws LogicException When the task was already saved.
     */
    public function markSaved(string $id): void
    {
        if ($this->id !== null) {
            throw new LogicException("Task \"{$this->name}\" is already saved.");
        }

        $this->id = $id;
    }

    /**
     * Tell whether the task exists in Zoho yet.
     *
     * @return bool
     */
    public function isSaved(): bool
    {
        return $this->id !== null;
    }

    /**
     * Get the Zoho task ID.
     *
     * @return string
     *
     * @throws LogicException When the task isn't saved yet.
     */
    public function id(): string
    {
        if ($this->id === null) {
            throw new LogicException("Task \"{$this->name}\" isn't saved yet.");
        }

        return $this->id;
    }

    /**
     * Get the project the task belongs to.
     *
     * @return Project
     */
    public function project(): Project
    {
        return $this->project;
    }

    /**
     * Get the task name.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }
}
