<?php

declare(strict_types=1);

namespace ZohoTime\Domain;

/**
 * Reads Zoho projects.
 */
interface ProjectRepository
{
    /**
     * Find the Zoho project for a repository.
     *
     * @param string $repository The repository name.
     *
     * @return Project
     *
     * @throws \RuntimeException When the repository has no Zoho project.
     */
    public function findForRepository(string $repository): Project;

    /**
     * Find the active project with a name (case-insensitive).
     *
     * @param string $name The Zoho project name.
     *
     * @return Project|null
     */
    public function findByName(string $name): ?Project;

    /**
     * Find the Zoho ID of the signed-in user on a project.
     *
     * @param Project $project The project.
     *
     * @return string
     *
     * @throws \RuntimeException When the signed-in user isn't on the project.
     */
    public function currentUserIdOn(Project $project): string;

    /**
     * List all active projects.
     *
     * @return list<Project>
     */
    public function all(): array;
}
