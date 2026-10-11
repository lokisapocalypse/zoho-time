<?php

declare(strict_types=1);

namespace ZohoTime\Domain;

/**
 * Reads and writes tasks on Zoho projects.
 */
interface TaskRepository
{
    /**
     * Find a project's task by its exact name.
     *
     * @param Project $project The project.
     * @param string  $name    The task name.
     *
     * @return Task|null
     */
    public function findByName(Project $project, string $name): ?Task;

    /**
     * Find a project's task by its ID, or by its name ignoring case.
     *
     * @param Project $project  The project.
     * @param string  $nameOrId The task's Zoho ID or name.
     *
     * @return Task|null
     */
    public function findByNameOrId(Project $project, string $nameOrId): ?Task;

    /**
     * List a project's tasks.
     *
     * @param Project $project The project.
     *
     * @return list<Task>
     */
    public function forProject(Project $project): array;

    /**
     * Save a new task and mark it with its Zoho ID.
     *
     * @param Task $task The unsaved task.
     *
     * @return void
     */
    public function save(Task $task): void;
}
