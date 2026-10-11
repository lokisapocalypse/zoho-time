<?php

declare(strict_types=1);

namespace ZohoTime\Infrastructure;

use RuntimeException;

/**
 * Runs external commands (gh, security) without a shell.
 */
class ProcessRunner
{
    /**
     * Run a command and capture its output.
     *
     * @param list<string> $command The program and its arguments.
     *
     * @return ProcessResult
     *
     * @throws RuntimeException When the command can't be started.
     */
    public function run(array $command): ProcessResult
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if ($process === false) {
            throw new RuntimeException("Can't run {$command[0]}.");
        }

        $output = (string) stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return new ProcessResult(proc_close($process), $output, $error);
    }
}
