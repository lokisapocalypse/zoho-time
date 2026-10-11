<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Domain;

use DateTimeImmutable;
use DomainException;
use LogicException;
use PHPUnit\Framework\TestCase;
use ZohoTime\Domain\Duration;
use ZohoTime\Domain\Issue;
use ZohoTime\Domain\Project;
use ZohoTime\Domain\Task;
use ZohoTime\Domain\TimeEntry;

/**
 * Tests billing time on an issue.
 */
final class TimeEntryTest extends TestCase
{
    private Issue $issue;

    private Task $task;

    /**
     * Set up an issue and its unsaved task.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->issue = new Issue(
            'acme-studio',
            'bluebird-www',
            39,
            'Nightly database backups',
            'https://github.com/acme-studio/bluebird-www/issues/39'
        );
        $this->task = Task::forIssue(new Project('p1', 'bluebird-www'), $this->issue);
    }

    /**
     * Check that the issue's task is named by its title alone, since clients see it.
     *
     * @return void
     */
    public function testNamesTheTaskAfterTheIssuesTitle(): void
    {
        $this->assertSame('Nightly database backups', $this->task->name());
        $this->assertFalse($this->task->isSaved());
    }

    /**
     * Check that billed time is rounded up and the notes default to the issue's title.
     *
     * @return void
     */
    public function testRoundsUpAndDescribesTheIssueByItsTitle(): void
    {
        $entry = $this->bill('2026-10-01', '2026-10-01');

        $this->assertSame(75, $entry->duration()->minutes());
        $this->assertSame('Nightly database backups', $entry->notes());
        $this->assertNull($entry->id());
    }

    /**
     * Check that notes never carry the repository, issue number or GitHub link, even on a shared task.
     *
     * @return void
     */
    public function testKeepsInternalDetailsOutOfTheNotesOnASharedTask(): void
    {
        $entry = $this->billOnSharedTask(null);

        $this->assertSame('Nightly database backups', $entry->notes());
    }

    /**
     * Check that given notes are used, trimmed, and blank ones fall back to the title.
     *
     * @return void
     */
    public function testUsesGivenNotes(): void
    {
        $this->assertSame('Nightly integrity checks', $this->billOnSharedTask('  Nightly integrity checks ')->notes());
        $this->assertSame('Nightly database backups', $this->billOnSharedTask('   ')->notes());
    }

    /**
     * Check that notes longer than Zoho allows are refused.
     *
     * @return void
     */
    public function testRefusesNotesThatAreTooLong(): void
    {
        $this->expectException(DomainException::class);

        $this->billOnSharedTask(str_repeat('a', TimeEntry::MAX_NOTES_LENGTH + 1));
    }

    /**
     * Check that time can't be billed on a future day.
     *
     * @return void
     */
    public function testRefusesAFutureDate(): void
    {
        $this->expectException(DomainException::class);

        $this->bill('2026-10-02', '2026-10-01');
    }

    /**
     * Check that an entry takes its Zoho ID once, and only once.
     *
     * @return void
     */
    public function testIsMarkedSavedOnlyOnce(): void
    {
        $entry = $this->bill('2026-10-01', '2026-10-01');
        $entry->markSaved('e1');

        $this->assertSame('e1', $entry->id());
        $this->expectException(LogicException::class);
        $entry->markSaved('e2');
    }

    /**
     * Bill 1h 05m on the issue.
     *
     * @param string $date  The day worked.
     * @param string $today Today's date.
     *
     * @return TimeEntry
     */
    private function bill(string $date, string $today): TimeEntry
    {
        return TimeEntry::forIssue(
            $this->issue,
            $this->task,
            'u1',
            new DateTimeImmutable($date),
            new Duration(65),
            new DateTimeImmutable($today)
        );
    }

    /**
     * Bill 30 minutes of the issue on a shared task.
     *
     * @param string|null $notes The notes, or null for the default.
     *
     * @return TimeEntry
     */
    private function billOnSharedTask(?string $notes): TimeEntry
    {
        return TimeEntry::forIssue(
            $this->issue,
            new Task('t9', new Project('p1', 'bluebird-www'), 'Database Backup'),
            'u1',
            new DateTimeImmutable('2026-10-01'),
            new Duration(30),
            new DateTimeImmutable('2026-10-01'),
            $notes
        );
    }
}
