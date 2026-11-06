<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Domain;

use PHPUnit\Framework\TestCase;
use ZohoTime\Domain\Issue;
use ZohoTime\Domain\Project;
use ZohoTime\Domain\Task;
use ZohoTime\Domain\TaskNameTooLongException;

/**
 * Tests naming an issue's new task.
 */
final class TaskTest extends TestCase
{
    private Project $project;

    /**
     * Set up the project the tasks belong to.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->project = new Project('p1', 'bluebird-www');
    }

    /**
     * Check that a given name replaces the issue's title.
     *
     * @return void
     */
    public function testUsesAGivenName(): void
    {
        $task = Task::forIssue($this->project, $this->issue('Nightly database backups'), 'Database backups');

        $this->assertSame('Database backups', $task->name());
        $this->assertFalse($task->isSaved());
    }

    /**
     * Check that a title as long as Zoho allows is kept whole.
     *
     * @return void
     */
    public function testAcceptsATitleAtTheLimit(): void
    {
        $title = str_repeat('é', Task::MAX_NAME_LENGTH);

        $this->assertSame($title, Task::forIssue($this->project, $this->issue($title))->name());
    }

    /**
     * Check that a title too long for Zoho is refused rather than cut off.
     *
     * @return void
     */
    public function testRefusesATitleThatIsTooLong(): void
    {
        $this->expectException(TaskNameTooLongException::class);
        $this->expectExceptionMessage('is 100.');

        Task::forIssue($this->project, $this->issue(str_repeat('a', Task::MAX_NAME_LENGTH + 1)));
    }

    /**
     * Check that a given name too long for Zoho is refused too.
     *
     * @return void
     */
    public function testRefusesAGivenNameThatIsTooLong(): void
    {
        $this->expectException(TaskNameTooLongException::class);

        Task::forIssue($this->project, $this->issue('Backups'), str_repeat('a', Task::MAX_NAME_LENGTH + 1));
    }

    /**
     * Make an issue with a title.
     *
     * @param string $title The issue's title.
     *
     * @return Issue
     */
    private function issue(string $title): Issue
    {
        return new Issue('acme-studio', 'bluebird-www', 39, $title, 'https://example.test/39');
    }
}
