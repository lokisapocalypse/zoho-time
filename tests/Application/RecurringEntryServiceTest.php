<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Application;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ZohoTime\Application\PreparedRecurringEntry;
use ZohoTime\Application\RecurringEntryService;
use ZohoTime\Application\RecurringStatus;
use ZohoTime\Domain\Duration;
use ZohoTime\Domain\Project;
use ZohoTime\Domain\RecurringEntry;
use ZohoTime\Domain\Task;
use ZohoTime\Domain\TimeEntry;
use ZohoTime\Tests\Fakes\FakeProjectRepository;
use ZohoTime\Tests\Fakes\FakeTaskRepository;
use ZohoTime\Tests\Fakes\FakeTimeEntryRepository;

/**
 * Tests preparing and saving a month's recurring entries.
 */
final class RecurringEntryServiceTest extends TestCase
{
    private FakeTimeEntryRepository $entries;

    private RecurringEntryService $service;

    private FakeTaskRepository $tasks;

    private Task $backup;

    private DateTimeImmutable $september;

    private DateTimeImmutable $today;

    /**
     * Set up two entries on Bluebird Clinic's existing tasks, one on a task it lacks and one on a missing project.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $projects = new FakeProjectRepository();
        $clinic = new Project('p1', 'Bluebird Clinic');
        $projects->add($clinic);

        $this->tasks = new FakeTaskRepository();
        $this->tasks->save(new Task(null, $clinic, 'Site Backup'));
        $this->tasks->save(new Task(null, $clinic, 'Hosting Fee'));
        $this->backup = $this->tasks->saved[0];

        $this->entries = new FakeTimeEntryRepository();
        $this->service = new RecurringEntryService($projects, $this->tasks, $this->entries, [
            new RecurringEntry('bluebird clinic', 'Site Backup', new Duration(30), 'Tier 3 Support Services'),
            new RecurringEntry('Bluebird Clinic', 'hosting fee', new Duration(6), 'Tier 1 Support Services'),
            new RecurringEntry('Bluebird Clinic', 'SSL Certificate Management', new Duration(15), 'Tier 2'),
            new RecurringEntry('Harbor Clinic', 'Hosting Fee', new Duration(6), 'Tier 1 Support Services'),
        ]);
        $this->september = new DateTimeImmutable('2026-09-01');
        $this->today = new DateTimeImmutable('2026-10-31');
    }

    /**
     * Check that preparing shows where each entry stands and saves nothing.
     *
     * @return void
     */
    public function testPreparingShowsEachEntryAndSavesNothing(): void
    {
        $prepared = $this->service->prepare($this->september, $this->today);

        $this->assertSame(
            [RecurringStatus::Due, RecurringStatus::Due, RecurringStatus::Due, RecurringStatus::Unavailable],
            $this->statuses($prepared)
        );
        $this->assertSame('2026-09-30', $prepared[1]->entry()?->date()->format('Y-m-d'));
        $this->assertSame(6, $prepared[1]->entry()?->duration()->minutes());
        $this->assertFalse($prepared[1]->createsTask());
        $this->assertTrue($prepared[2]->createsTask());
        $this->assertSame('Zoho has no active project named "Harbor Clinic".', $prepared[3]->problem());
        $this->assertCount(2, $this->tasks->saved);
        $this->assertSame([], $this->entries->saved);
    }

    /**
     * Check that logging saves the due entries and marks them saved.
     *
     * @return void
     */
    public function testLoggingSavesTheDueEntries(): void
    {
        $prepared = $this->service->log($this->september, $this->today);

        $this->assertSame(
            [
                RecurringStatus::Saved,
                RecurringStatus::Saved,
                RecurringStatus::Saved,
                RecurringStatus::Unavailable,
            ],
            $this->statuses($prepared)
        );
        $this->assertCount(3, $this->entries->saved);
        $this->assertSame('SSL Certificate Management', $this->tasks->saved[2]->name());
        $this->assertSame($this->tasks->saved[2], $this->entries->saved['e3']->task());
        $this->assertSame('u1', $this->entries->saved['e1']->userId());
    }

    /**
     * Check that an entry already in Zoho for the month isn't logged again.
     *
     * @return void
     */
    public function testEntryAlreadyLoggedIsNotLoggedAgain(): void
    {
        $existing = new TimeEntry(
            null,
            $this->backup,
            'u1',
            new DateTimeImmutable('2026-09-30'),
            new Duration(30),
            'Tier 3 Support Services'
        );
        $this->entries->save($existing);

        $prepared = $this->service->log($this->september, $this->today);

        $this->assertSame(RecurringStatus::Logged, $prepared[0]->status());
        $this->assertSame($existing, $prepared[0]->entry());
        $this->assertSame(RecurringStatus::Saved, $prepared[1]->status());
        $this->assertCount(3, $this->entries->saved);
    }

    /**
     * Check that logging the same month twice saves nothing the second time.
     *
     * @return void
     */
    public function testLoggingTwiceSavesNothingMore(): void
    {
        $this->service->log($this->september, $this->today);
        $prepared = $this->service->log($this->september, $this->today);

        $this->assertSame(RecurringStatus::Logged, $prepared[0]->status());
        $this->assertSame(RecurringStatus::Logged, $prepared[1]->status());
        $this->assertSame(RecurringStatus::Logged, $prepared[2]->status());
        $this->assertFalse($prepared[2]->createsTask());
        $this->assertCount(3, $this->tasks->saved);
        $this->assertCount(3, $this->entries->saved);
    }

    /**
     * Check that two entries on the same missing task create it only once.
     *
     * @return void
     */
    public function testEntriesOnTheSameMissingTaskCreateItOnce(): void
    {
        $projects = new FakeProjectRepository();
        $projects->add(new Project('p1', 'Bluebird Clinic'));
        $service = new RecurringEntryService($projects, $this->tasks, $this->entries, [
            new RecurringEntry('Bluebird Clinic', 'Server Maintenance', new Duration(15), 'Tier 2'),
            new RecurringEntry('Bluebird Clinic', 'server maintenance', new Duration(15), 'Tier 2 extra'),
        ]);

        $service->log($this->september, $this->today);

        $this->assertCount(3, $this->tasks->saved);
        $this->assertSame($this->entries->saved['e1']->task(), $this->entries->saved['e2']->task());
    }

    /**
     * Check that skipped entries aren't saved.
     *
     * @return void
     */
    public function testSkippedEntriesAreNotSaved(): void
    {
        $prepared = $this->service->log($this->september, $this->today, [2, 3]);

        $this->assertSame(RecurringStatus::Saved, $prepared[0]->status());
        $this->assertSame(RecurringStatus::Skipped, $prepared[1]->status());
        $this->assertSame(RecurringStatus::Skipped, $prepared[2]->status());
        $this->assertCount(1, $this->entries->saved);
        $this->assertCount(2, $this->tasks->saved);
    }

    /**
     * Check that skipping a number that isn't an entry is refused.
     *
     * @return void
     */
    public function testSkippingAnUnknownNumberIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("There's no recurring entry 5; they're numbered 1 to 4.");

        $this->service->prepare($this->september, $this->today, [5]);
    }

    /**
     * Check that a month that isn't over yet is refused.
     *
     * @return void
     */
    public function testMonthThatIsNotOverIsRefused(): void
    {
        $this->expectException(DomainException::class);

        $this->service->prepare(new DateTimeImmutable('2026-11-01'), $this->today);
    }

    /**
     * List the statuses of prepared entries.
     *
     * @param list<PreparedRecurringEntry> $prepared The prepared entries.
     *
     * @return list<RecurringStatus>
     */
    private function statuses(array $prepared): array
    {
        return array_map(static fn (PreparedRecurringEntry $item): RecurringStatus => $item->status(), $prepared);
    }
}
