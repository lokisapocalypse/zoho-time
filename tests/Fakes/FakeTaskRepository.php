<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Fakes;

use ZohoTime\Domain\Project;
use ZohoTime\Domain\Task;
use ZohoTime\Domain\TaskRepository;

/**
 * Keeps tasks in memory.
 */
final class FakeTaskRepository implements TaskRepository
{
    /** @var list<Task> */
    public array $saved = [];

    /**
     * Find a saved task by project and name.
     *
     * @param Project $project The project.
     * @param string  $name    The task name.
     *
     * @return Task|null
     */
    public function findByName(Project $project, string $name): ?Task
    {
        foreach ($this->saved as $task) {
            if ($task->project()->id() === $project->id() && $task->name() === $name) {
                return $task;
            }
        }

        return null;
    }

    /**
     * Find a saved task by ID, or by name ignoring case.
     *
     * @param Project $project  The project.
     * @param string  $nameOrId The task's ID or name.
     *
     * @return Task|null
     */
    public function findByNameOrId(Project $project, string $nameOrId): ?Task
    {
        foreach ($this->forProject($project) as $task) {
            if ($task->id() === $nameOrId || strcasecmp($task->name(), $nameOrId) === 0) {
                return $task;
            }
        }

        return null;
    }

    /**
     * List a project's saved tasks.
     *
     * @param Project $project The project.
     *
     * @return list<Task>
     */
    public function forProject(Project $project): array
    {
        return array_values(array_filter(
            $this->saved,
            static fn (Task $task): bool => $task->project()->id() === $project->id()
        ));
    }

    /**
     * Save a task with the next ID.
     *
     * @param Task $task The task.
     *
     * @return void
     */
    public function save(Task $task): void
    {
        $task->markSaved('t' . (count($this->saved) + 1));
        $this->saved[] = $task;
    }
}
