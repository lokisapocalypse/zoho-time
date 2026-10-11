<?php

declare(strict_types=1);

namespace ZohoTime\Application;

use DateTimeImmutable;
use ZohoTime\Domain\BillingPolicy;
use ZohoTime\Domain\IssueRepository;
use ZohoTime\Domain\Project;
use ZohoTime\Domain\ProjectRepository;
use ZohoTime\Domain\Task;
use ZohoTime\Domain\TaskNotFoundException;
use ZohoTime\Domain\TaskRepository;
use ZohoTime\Domain\TimeEntry;
use ZohoTime\Domain\TimeEntryRepository;

/**
 * Prepares, saves, lists and deletes time entries for issues.
 */
final class TimeEntryService
{
    private readonly IssueRepository $issues;

    private readonly ProjectRepository $projects;

    private readonly TaskRepository $tasks;

    private readonly TimeEntryRepository $entries;

    private readonly BillingPolicy $policy;

    /**
     * Create the service.
     *
     * @param IssueRepository     $issues   GitHub issues.
     * @param ProjectRepository   $projects Zoho projects.
     * @param TaskRepository      $tasks    Zoho tasks.
     * @param TimeEntryRepository $entries  Zoho time entries.
     * @param BillingPolicy       $policy   Which repositories are billed.
     */
    public function __construct(
        IssueRepository $issues,
        ProjectRepository $projects,
        TaskRepository $tasks,
        TimeEntryRepository $entries,
        BillingPolicy $policy
    ) {
        $this->issues = $issues;
        $this->projects = $projects;
        $this->tasks = $tasks;
        $this->entries = $entries;
        $this->policy = $policy;
    }

    /**
     * Work out the entry for time spent on an issue without saving anything.
     *
     * @param LogTimeRequest    $request What to log.
     * @param DateTimeImmutable $today   Today's date.
     *
     * @return PreparedEntry
     *
     * @throws \ZohoTime\Domain\NotBillableException When the issue's repository isn't billed.
     * @throws TaskNotFoundException When the existing task isn't on the project.
     * @throws \DomainException When the date is in the future or the notes are too long.
     * @throws \RuntimeException When GitHub or Zoho can't be read.
     */
    public function prepare(LogTimeRequest $request, DateTimeImmutable $today): PreparedEntry
    {
        $issue = $this->issues->find($request->reference());
        $this->policy->assertBillable($issue, $this->issues->topicsOf($issue));

        $project = $this->projects->findForRepository($issue->repository());
        $task = $request->existingTask() === null
            ? $this->tasks->findByName($project, $issue->taskName()) ?? Task::forIssue($project, $issue)
            : $this->existingTask($project, $request->existingTask());
        $entry = TimeEntry::forIssue(
            $issue,
            $task,
            $this->projects->currentUserIdOn($project),
            $request->date(),
            $request->worked(),
            $today,
            $request->notes()
        );

        return new PreparedEntry(
            $issue,
            $entry,
            $request->worked(),
            !$task->isSaved(),
            $task->isSaved() ? $this->entriesOnTask($task, $request->date()) : []
        );
    }

    /**
     * Save time spent on an issue, creating the issue's task first when it's new.
     *
     * @param LogTimeRequest    $request What to log.
     * @param DateTimeImmutable $today   Today's date.
     *
     * @return PreparedEntry The saved entry.
     *
     * @throws \ZohoTime\Domain\NotBillableException When the issue's repository isn't billed.
     * @throws TaskNotFoundException When the existing task isn't on the project.
     * @throws \DomainException When the date is in the future or the notes are too long.
     * @throws \RuntimeException When GitHub or Zoho can't be read or written.
     */
    public function log(LogTimeRequest $request, DateTimeImmutable $today): PreparedEntry
    {
        $prepared = $this->prepare($request, $today);
        $entry = $prepared->entry();

        if (!$entry->task()->isSaved()) {
            $this->tasks->save($entry->task());
        }

        $this->entries->save($entry);

        return $prepared;
    }

    /**
     * List the time entries logged on a day.
     *
     * @param DateTimeImmutable $date The day.
     *
     * @return list<TimeEntry>
     */
    public function entriesOn(DateTimeImmutable $date): array
    {
        return $this->entries->onDate($date);
    }

    /**
     * List the tasks on a repository's Zoho project.
     *
     * @param string $repository The repository name.
     *
     * @return list<Task>
     *
     * @throws \RuntimeException When the repository has no Zoho project.
     */
    public function tasksFor(string $repository): array
    {
        return $this->tasks->forProject($this->projects->findForRepository($repository));
    }

    /**
     * List all active Zoho projects.
     *
     * @return list<Project>
     */
    public function projects(): array
    {
        return $this->projects->all();
    }

    /**
     * Find a time entry by its ID.
     *
     * @param string $id Zoho time entry ID.
     *
     * @return TimeEntry
     *
     * @throws \RuntimeException When there's no such entry.
     */
    public function find(string $id): TimeEntry
    {
        return $this->entries->find($id);
    }

    /**
     * Delete a time entry.
     *
     * @param string $id Zoho time entry ID.
     *
     * @return void
     */
    public function delete(string $id): void
    {
        $this->entries->delete($id);
    }

    /**
     * Find an existing task on the project; it's never created.
     *
     * @param Project $project  The project.
     * @param string  $nameOrId The task's name or ID.
     *
     * @return Task
     *
     * @throws TaskNotFoundException When the project has no such task.
     */
    private function existingTask(Project $project, string $nameOrId): Task
    {
        return $this->tasks->findByNameOrId($project, $nameOrId) ?? throw new TaskNotFoundException(
            "The Zoho project \"{$project->name()}\" has no task \"{$nameOrId}\"; "
            . 'list its tasks with: zoho-time tasks <repo>'
        );
    }

    /**
     * List the entries already logged on a task on a day.
     *
     * @param Task              $task The task.
     * @param DateTimeImmutable $date The day.
     *
     * @return list<TimeEntry>
     */
    private function entriesOnTask(Task $task, DateTimeImmutable $date): array
    {
        return array_values(array_filter(
            $this->entries->onDate($date, $task->project()),
            static fn (TimeEntry $entry): bool => $entry->task()->isSaved() && $entry->task()->id() === $task->id()
        ));
    }
}
