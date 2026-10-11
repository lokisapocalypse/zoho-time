<?php

declare(strict_types=1);

namespace ZohoTime\Infrastructure;

use RuntimeException;
use ZohoTime\Domain\Project;
use ZohoTime\Domain\ProjectRepository;

/**
 * Reads projects and their users from Zoho Invoice.
 */
final class ZohoProjectRepository implements ProjectRepository
{
    private readonly ZohoClient $client;

    private readonly Config $config;

    /** @var list<Project>|null */
    private ?array $projects = null;

    /**
     * Create the repository.
     *
     * @param ZohoClient $client Calls Zoho.
     * @param Config     $config Maps repositories to project names.
     */
    public function __construct(ZohoClient $client, Config $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * Find the active project named for the repository (case-insensitive).
     *
     * @param string $repository The repository name.
     *
     * @return Project
     *
     * @throws RuntimeException When no active project has that name.
     */
    public function findForRepository(string $repository): Project
    {
        $name = $this->config->projectNameFor($repository);

        foreach ($this->all() as $project) {
            if (strcasecmp($project->name(), $name) === 0) {
                return $project;
            }
        }

        throw new RuntimeException(
            "Zoho has no active project named \"{$name}\" for {$repository}; create it in Zoho Invoice, "
            . 'or map the repository to an existing project under "projects" in the config.'
        );
    }

    /**
     * Find the signed-in user's ID among the project's users.
     *
     * @param Project $project The project.
     *
     * @return string
     *
     * @throws RuntimeException When the signed-in user isn't on the project.
     */
    public function currentUserIdOn(Project $project): string
    {
        foreach ($this->client->get("/projects/{$project->id()}/users")['users'] ?? [] as $user) {
            if (($user['is_current_user'] ?? false) === true) {
                return (string) $user['user_id'];
            }
        }

        throw new RuntimeException("You aren't a user on the Zoho project \"{$project->name()}\".");
    }

    /**
     * List the active projects.
     *
     * @return list<Project>
     */
    public function all(): array
    {
        return $this->projects ??= array_map(
            static fn (array $row): Project => new Project((string) $row['project_id'], (string) $row['project_name']),
            $this->client->all('/projects', 'projects', ['filter_by' => 'Status.Active'])
        );
    }
}
