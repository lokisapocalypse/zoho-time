<?php

declare(strict_types=1);

namespace ZohoTime\Infrastructure;

/**
 * The outcome of a finished command.
 */
final class ProcessResult
{
    private readonly int $exitCode;

    private readonly string $output;

    private readonly string $error;

    /**
     * Create a result.
     *
     * @param int    $exitCode The exit code.
     * @param string $output   Standard output.
     * @param string $error    Standard error.
     */
    public function __construct(int $exitCode, string $output, string $error)
    {
        $this->exitCode = $exitCode;
        $this->output = $output;
        $this->error = $error;
    }

    /**
     * Get the exit code.
     *
     * @return int
     */
    public function exitCode(): int
    {
        return $this->exitCode;
    }

    /**
     * Tell whether the command exited with 0.
     *
     * @return bool
     */
    public function succeeded(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * Get standard output.
     *
     * @return string
     */
    public function output(): string
    {
        return $this->output;
    }

    /**
     * Get standard error, trimmed.
     *
     * @return string
     */
    public function error(): string
    {
        return trim($this->error);
    }
}
