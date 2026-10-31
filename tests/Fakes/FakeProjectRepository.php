<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Fakes;

use RuntimeException;
use ZohoTime\Domain\Project;
use ZohoTime\Domain\ProjectRepository;

/**
 * Serves projects from memory, one per repository.
 */
final class FakeProjectRepository implements ProjectRepository
{
    /** @var array<string, Project> */
    private array $projects = [];

    /**
     * Add a project named after its repository.
     *
     * @param Project $project The project.
     *
     * @return void
     */
    public function add(Project $project): void
    {
        $this->projects[$project->name()] = $project;
    }

    /**
     * Find the project named for the repository.
     *
     * @param string $repository The repository name.
     *
     * @return Project
     *
     * @throws RuntimeException When there's none.
     */
    public function findForRepository(string $repository): Project
    {
        return $this->projects[$repository] ?? throw new RuntimeException("No project for {$repository}.");
    }

    /**
     * Find an added project by name, ignoring case.
     *
     * @param string $name The project name.
     *
     * @return Project|null
     */
    public function findByName(string $name): ?Project
    {
        foreach ($this->projects as $project) {
            if (strcasecmp($project->name(), $name) === 0) {
                return $project;
            }
        }

        return null;
    }

    /**
     * Give the same user ID on every project.
     *
     * @param Project $project The project.
     *
     * @return string
     */
    public function currentUserIdOn(Project $project): string
    {
        return 'u1';
    }

    /**
     * List the added projects.
     *
     * @return list<Project>
     */
    public function all(): array
    {
        return array_values($this->projects);
    }
}
