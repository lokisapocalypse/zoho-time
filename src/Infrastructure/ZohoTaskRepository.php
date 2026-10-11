<?php

declare(strict_types=1);

namespace ZohoTime\Infrastructure;

use ZohoTime\Domain\Project;
use ZohoTime\Domain\Task;
use ZohoTime\Domain\TaskRepository;

/**
 * Reads and writes tasks on Zoho Invoice projects.
 */
final class ZohoTaskRepository implements TaskRepository
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
     * Find a project's task by its exact name.
     *
     * @param Project $project The project.
     * @param string  $name    The task name.
     *
     * @return Task|null
     */
    public function findByName(Project $project, string $name): ?Task
    {
        foreach ($this->forProject($project) as $task) {
            if ($task->name() === $name) {
                return $task;
            }
        }

        return null;
    }

    /**
     * Find a project's task by its ID, or by its name ignoring case.
     *
     * @param Project $project  The project.
     * @param string  $nameOrId The task's Zoho ID or name.
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
     * List a project's tasks.
     *
     * @param Project $project The project.
     *
     * @return list<Task>
     */
    public function forProject(Project $project): array
    {
        // The list comes back under "task" in the API docs' examples; accept "tasks" too.
        return array_map(
            static fn (array $row): Task => new Task((string) $row['task_id'], $project, (string) $row['task_name']),
            $this->client->all("/projects/{$project->id()}/tasks", ['task', 'tasks'])
        );
    }

    /**
     * Create the task on its project and mark it with its Zoho ID.
     *
     * @param Task $task The unsaved task.
     *
     * @return void
     *
     * @throws ZohoApiException When Zoho refuses the task.
     */
    public function save(Task $task): void
    {
        $data = $this->client->post(
            "/projects/{$task->project()->id()}/tasks",
            ['task_name' => $task->name()]
        );

        if (!isset($data['task']['task_id'])) {
            throw new ZohoApiException('Zoho created the task but returned no task ID.');
        }

        $task->markSaved((string) $data['task']['task_id']);
    }
}
