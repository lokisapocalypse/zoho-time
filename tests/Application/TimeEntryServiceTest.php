<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Application;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ZohoTime\Application\LogTimeRequest;
use ZohoTime\Application\TimeEntryService;
use ZohoTime\Domain\BillingPolicy;
use ZohoTime\Domain\Duration;
use ZohoTime\Domain\Issue;
use ZohoTime\Domain\NotBillableException;
use ZohoTime\Domain\Project;
use ZohoTime\Domain\Task;
use ZohoTime\Domain\TaskNotFoundException;
use ZohoTime\Tests\Fakes\FakeIssueRepository;
use ZohoTime\Tests\Fakes\FakeProjectRepository;
use ZohoTime\Tests\Fakes\FakeTaskRepository;
use ZohoTime\Tests\Fakes\FakeTimeEntryRepository;

/**
 * Tests preparing and logging time on issues.
 */
final class TimeEntryServiceTest extends TestCase
{
    private FakeTaskRepository $tasks;

    private FakeTimeEntryRepository $entries;

    private TimeEntryService $service;

    private DateTimeImmutable $today;

    private Project $site;

    /**
     * Set up a billable bluebird-www issue and an unbilled studio-www one.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $issues = new FakeIssueRepository();
        $issues->add(
            new Issue('acme-studio', 'bluebird-www', 39, 'Nightly database backups', 'https://example.test/39'),
            ['client-bluebird']
        );
        $issues->add(new Issue('acme-studio', 'studio-www', 54, 'Blog', 'https://example.test/54'), []);

        $projects = new FakeProjectRepository();
        $this->site = new Project('p1', 'bluebird-www');
        $projects->add($this->site);
        $projects->add(new Project('p2', 'studio-www'));

        $this->tasks = new FakeTaskRepository();
        $this->entries = new FakeTimeEntryRepository();
        $this->service = new TimeEntryService(
            $issues,
            $projects,
            $this->tasks,
            $this->entries,
            new BillingPolicy(['harbor-clinic'], ['client-bluebird'])
        );
        $this->today = new DateTimeImmutable('2026-10-01');
    }

    /**
     * Check that preparing an entry saves nothing.
     *
     * @return void
     */
    public function testPreparingSavesNothing(): void
    {
        $prepared = $this->service->prepare(
            new LogTimeRequest('bluebird-www#39', new Duration(65), $this->today),
            $this->today
        );

        $this->assertTrue($prepared->createsTask());
        $this->assertSame('Nightly database backups', $prepared->entry()->task()->name());
        $this->assertSame('Nightly database backups', $prepared->entry()->notes());
        $this->assertSame('bluebird-www', $prepared->entry()->task()->project()->name());
        $this->assertSame(75, $prepared->entry()->duration()->minutes());
        $this->assertSame([], $this->tasks->saved);
        $this->assertSame([], $this->entries->saved);
    }

    /**
     * Check that logging creates the issue's task, then the entry on it.
     *
     * @return void
     */
    public function testLoggingCreatesTheTaskAndTheEntry(): void
    {
        $saved = $this->service->log(
            new LogTimeRequest('bluebird-www#39', new Duration(65), $this->today),
            $this->today
        );

        $this->assertCount(1, $this->tasks->saved);
        $this->assertSame('t1', $saved->entry()->task()->id());
        $this->assertSame('e1', $saved->entry()->id());
    }

    /**
     * Check that a second entry reuses the task and warns about the earlier one that day.
     *
     * @return void
     */
    public function testReusesTheTaskAndWarnsAboutSameDayEntries(): void
    {
        $this->service->log(
            new LogTimeRequest('bluebird-www#39', new Duration(40), $this->today),
            $this->today
        );

        $prepared = $this->service->prepare(
            new LogTimeRequest('bluebird-www#39', new Duration(30), $this->today),
            $this->today
        );

        $this->assertFalse($prepared->createsTask());
        $this->assertCount(1, $prepared->sameDayEntries());
        $this->assertSame(45, $prepared->sameDayEntries()[0]->duration()->minutes());
    }

    /**
     * Check that issues in repositories that aren't billed are refused before anything is saved.
     *
     * @return void
     */
    public function testRefusesIssuesThatAreNotBillable(): void
    {
        $this->expectException(NotBillableException::class);

        try {
            $this->service->log(
                new LogTimeRequest('studio-www#54', new Duration(60), $this->today),
                $this->today
            );
        } finally {
            $this->assertSame([], $this->tasks->saved);
            $this->assertSame([], $this->entries->saved);
        }
    }

    /**
     * Check that time can go on an existing task, found by name ignoring case, without creating one.
     *
     * @return void
     */
    public function testLogsOnAnExistingTask(): void
    {
        $this->tasks->save(new Task(null, $this->site, 'Monthly maintenance'));

        $saved = $this->service->log(
            new LogTimeRequest('bluebird-www#39', new Duration(30), $this->today, 'monthly MAINTENANCE'),
            $this->today
        );

        $this->assertFalse($saved->createsTask());
        $this->assertSame('t1', $saved->entry()->task()->id());
        $this->assertCount(1, $this->tasks->saved);
        $this->assertSame('Nightly database backups', $saved->entry()->notes());
    }

    /**
     * Check that an existing task can be named by its ID.
     *
     * @return void
     */
    public function testFindsAnExistingTaskById(): void
    {
        $this->tasks->save(new Task(null, $this->site, 'Monthly maintenance'));

        $prepared = $this->service->prepare(
            new LogTimeRequest('bluebird-www#39', new Duration(30), $this->today, 't1'),
            $this->today
        );

        $this->assertSame('Monthly maintenance', $prepared->entry()->task()->name());
    }

    /**
     * Check that an unknown existing task is refused rather than created.
     *
     * @return void
     */
    public function testRefusesAnUnknownExistingTask(): void
    {
        $this->expectException(TaskNotFoundException::class);

        try {
            $this->service->log(
                new LogTimeRequest('bluebird-www#39', new Duration(30), $this->today, 'Nope'),
                $this->today
            );
        } finally {
            $this->assertSame([], $this->tasks->saved);
            $this->assertSame([], $this->entries->saved);
        }
    }

    /**
     * Check that a repository's tasks are listed from its project.
     *
     * @return void
     */
    public function testListsARepositorysTasks(): void
    {
        $this->tasks->save(new Task(null, $this->site, 'Monthly maintenance'));

        $tasks = $this->service->tasksFor('bluebird-www');

        $this->assertCount(1, $tasks);
        $this->assertSame('Monthly maintenance', $tasks[0]->name());
    }

    /**
     * Check that given notes replace the issue's title on the entry.
     *
     * @return void
     */
    public function testUsesGivenNotes(): void
    {
        $saved = $this->service->log(
            new LogTimeRequest('bluebird-www#39', new Duration(30), $this->today, null, 'Set up nightly integrity checks'),
            $this->today
        );

        $this->assertSame('Set up nightly integrity checks', $saved->entry()->notes());
        $this->assertSame('Nightly database backups', $saved->entry()->task()->name());
    }
}
