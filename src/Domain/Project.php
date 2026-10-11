<?php

declare(strict_types=1);

namespace ZohoTime\Domain;

/**
 * A Zoho Invoice project; each billable repository has its own.
 */
final class Project
{
    private readonly string $id;

    private readonly string $name;

    /**
     * Create a project.
     *
     * @param string $id   Zoho project ID.
     * @param string $name Zoho project name.
     */
    public function __construct(string $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    /**
     * Get the Zoho project ID.
     *
     * @return string
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Get the project name.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }
}
