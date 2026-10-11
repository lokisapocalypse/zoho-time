<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Fakes;

use RuntimeException;
use ZohoTime\Infrastructure\ProcessResult;
use ZohoTime\Infrastructure\ProcessRunner;

/**
 * Answers commands with canned output and records what was run.
 */
final class FakeProcessRunner extends ProcessRunner
{
    /** @var array<string, ProcessResult> */
    private array $results = [];

    /** @var list<list<string>> */
    public array $commands = [];

    /**
     * Set the result of a command.
     *
     * @param list<string> $command The command.
     * @param string       $output  Its standard output.
     * @param int          $exitCode Its exit code.
     *
     * @return void
     */
    public function answer(array $command, string $output, int $exitCode = 0): void
    {
        $this->results[implode(' ', $command)] = new ProcessResult($exitCode, $output, '');
    }

    /**
     * Record the command and return its canned result.
     *
     * @param list<string> $command The command.
     *
     * @return ProcessResult
     *
     * @throws RuntimeException When the command wasn't expected.
     */
    public function run(array $command): ProcessResult
    {
        $this->commands[] = $command;

        return $this->results[implode(' ', $command)]
            ?? throw new RuntimeException('Unexpected command: ' . implode(' ', $command));
    }
}
