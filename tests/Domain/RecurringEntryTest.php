<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Domain;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ZohoTime\Domain\Duration;
use ZohoTime\Domain\Project;
use ZohoTime\Domain\RecurringEntry;
use ZohoTime\Domain\Task;
use ZohoTime\Domain\TimeEntry;

/**
 * Tests the same time entry logged every month.
 */
final class RecurringEntryTest extends TestCase
{
    private RecurringEntry $hosting;

    private Task $task;

    /**
     * Set up a six-minute monthly hosting entry and its task.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->hosting = new RecurringEntry(
            'Bluebird Clinic',
            'Hosting Fee',
            new Duration(6),
            'Tier 1 Support Services'
        );
        $this->task = new Task('t1', new Project('p1', 'Bluebird Clinic'), 'Hosting Fee');
    }

    /**
     * Check that a month's entry is dated its last day, including in February.
     *
     * @return void
     */
    public function testEntriesAreDatedTheMonthsLastDay(): void
    {
        $this->assertSame('2026-09-30', RecurringEntry::dateFor(new DateTimeImmutable('2026-09-12'))->format('Y-m-d'));
        $this->assertSame('2028-02-29', RecurringEntry::dateFor(new DateTimeImmutable('2028-02-01'))->format('Y-m-d'));
    }

    /**
     * Check that the latest due month is this one on its last day, and last month before that.
     *
     * @return void
     */
    public function testLatestDueMonthIsThisOneOnlyOnItsLastDay(): void
    {
        $this->assertSame(
            '2026-10-01',
            RecurringEntry::latestDueMonth(new DateTimeImmutable('2026-10-31 09:00'))->format('Y-m-d')
        );
        $this->assertSame(
            '2026-09-01',
            RecurringEntry::latestDueMonth(new DateTimeImmutable('2026-10-30'))->format('Y-m-d')
        );
        $this->assertSame(
            '2026-12-01',
            RecurringEntry::latestDueMonth(new DateTimeImmutable('2027-01-05'))->format('Y-m-d')
        );
    }

    /**
     * Check that the entry keeps its exact duration rather than rounding up to the quarter hour.
     *
     * @return void
     */
    public function testEntryKeepsItsExactDuration(): void
    {
        $entry = $this->hosting->entryFor(
            $this->task,
            'u1',
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2026-10-31')
        );

        $this->assertSame(6, $entry->duration()->minutes());
        $this->assertSame('2026-09-30', $entry->date()->format('Y-m-d'));
        $this->assertSame('Tier 1 Support Services', $entry->notes());
        $this->assertSame($this->task, $entry->task());
        $this->assertNull($entry->id());
    }

    /**
     * Check that a month that isn't over yet is refused.
     *
     * @return void
     */
    public function testMonthThatIsNotOverIsRefused(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("October 2026's recurring entries aren't due until 2026-10-31.");

        $this->hosting->entryFor(
            $this->task,
            'u1',
            new DateTimeImmutable('2026-10-01'),
            new DateTimeImmutable('2026-10-30')
        );
    }

    /**
     * Check that any entry already on the task that day counts as logged, whatever its hours.
     *
     * @return void
     */
    public function testAnyEntryOnTheTaskCountsAsLogged(): void
    {
        $date = new DateTimeImmutable('2026-09-30');
        $other = new Task('t2', $this->task->project(), 'Site Backup');
        $onOther = new TimeEntry('e1', $other, 'u1', $date, new Duration(30), 'Tier 1 Support Services');
        $onTask = new TimeEntry('e2', $this->task, 'u1', $date, new Duration(15), 'Hosting');

        $this->assertNull($this->hosting->loggedAmong($this->task, [$onOther]));
        $this->assertSame($onTask, $this->hosting->loggedAmong($this->task, [$onOther, $onTask]));
    }

    /**
     * Check that an entry without a task is refused.
     *
     * @return void
     */
    public function testEntryNeedsATask(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A recurring entry needs a task.');

        new RecurringEntry('Bluebird Clinic', ' ', new Duration(6), 'Tier 1 Support Services');
    }
}
