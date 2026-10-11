<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Infrastructure;

use DateTimeImmutable;
use RuntimeException;
use ZohoTime\Domain\Duration;
use ZohoTime\Domain\Project;
use ZohoTime\Domain\Task;
use ZohoTime\Domain\TimeEntry;
use ZohoTime\Infrastructure\ZohoApiException;
use ZohoTime\Infrastructure\ZohoProjectRepository;
use ZohoTime\Infrastructure\ZohoTaskRepository;
use ZohoTime\Infrastructure\ZohoTimeEntryRepository;

/**
 * Tests the Zoho project, task and time entry repositories against canned API responses.
 */
final class ZohoRepositoriesTest extends ZohoTestCase
{
    /**
     * Check that requests carry the token and organization headers and go to the data center's API.
     *
     * @return void
     */
    public function testSendsAuthAndOrganizationHeaders(): void
    {
        $this->http->respond(['code' => 0, 'projects' => []]);

        (new ZohoProjectRepository($this->client, $this->config))->all();

        $request = $this->http->requests[0];
        $this->assertStringStartsWith('https://www.zohoapis.eu/invoice/v3/projects?', $request['url']);
        $this->assertSame('Zoho-oauthtoken cached', $request['headers']['Authorization']);
        $this->assertSame('org1', $request['headers']['X-com-zoho-invoice-organizationid']);
    }

    /**
     * Check that a repository's project is found by its mapped name, across pages, ignoring case.
     *
     * @return void
     */
    public function testFindsTheRepositorysProjectAcrossPages(): void
    {
        $this->http->respond([
            'code' => 0,
            'projects' => [['project_id' => 'p1', 'project_name' => 'bluebird-www']],
            'page_context' => ['has_more_page' => true],
        ]);
        $this->http->respond([
            'code' => 0,
            'projects' => [['project_id' => 'p2', 'project_name' => 'harbor family clinic']],
            'page_context' => ['has_more_page' => false],
        ]);

        $project = (new ZohoProjectRepository($this->client, $this->config))->findForRepository('harbor');

        $this->assertSame('p2', $project->id());
    }

    /**
     * Check that a repository without a project is reported.
     *
     * @return void
     */
    public function testReportsARepositoryWithoutAProject(): void
    {
        $this->http->respond(['code' => 0, 'projects' => []]);

        $this->expectException(RuntimeException::class);

        (new ZohoProjectRepository($this->client, $this->config))->findForRepository('bluebird-shop');
    }

    /**
     * Check that the signed-in user is picked from the project's users.
     *
     * @return void
     */
    public function testFindsTheCurrentUser(): void
    {
        $this->http->respond(['code' => 0, 'users' => [
            ['user_id' => 'u1', 'is_current_user' => false],
            ['user_id' => 'u2', 'is_current_user' => true],
        ]]);

        $userId = (new ZohoProjectRepository($this->client, $this->config))->currentUserIdOn(new Project('p1', 'x'));

        $this->assertSame('u2', $userId);
        $this->assertStringContainsString('/projects/p1/users', $this->http->requests[0]['url']);
    }

    /**
     * Check that tasks are found by exact name and new ones are created on the project.
     *
     * @return void
     */
    public function testFindsAndCreatesTasks(): void
    {
        $project = new Project('p1', 'bluebird-www');
        $tasks = new ZohoTaskRepository($this->client);
        $this->http->respond(['code' => 0, 'task' => [['task_id' => 't1', 'task_name' => 'bluebird-www #1: A']]]);
        $this->http->respond(['code' => 0, 'task' => ['task_id' => 't2', 'task_name' => 'bluebird-www #2: B']]);

        $this->assertSame('t1', $tasks->findByName($project, 'bluebird-www #1: A')?->id());

        $task = new Task(null, $project, 'bluebird-www #2: B');
        $tasks->save($task);

        $this->assertSame('t2', $task->id());
        $this->assertSame(['task_name' => 'bluebird-www #2: B'], json_decode((string) $this->http->requests[1]['body'], true));
    }

    /**
     * Check that an existing task is found by ID or by name ignoring case.
     *
     * @return void
     */
    public function testFindsAnExistingTaskByNameOrId(): void
    {
        $project = new Project('p1', 'bluebird-www');
        $tasks = new ZohoTaskRepository($this->client);
        $list = ['code' => 0, 'task' => [
            ['task_id' => 't1', 'task_name' => 'bluebird-www #1: A'],
            ['task_id' => 't2', 'task_name' => 'Monthly maintenance'],
        ]];
        $this->http->respond($list);
        $this->http->respond($list);
        $this->http->respond($list);

        $this->assertSame('t2', $tasks->findByNameOrId($project, 'monthly maintenance')?->id());
        $this->assertSame('bluebird-www #1: A', $tasks->findByNameOrId($project, 't1')?->name());
        $this->assertNull($tasks->findByNameOrId($project, 'Hosting'));
    }

    /**
     * Check that a time entry is posted as a billable HH:MM entry and takes its new ID.
     *
     * @return void
     */
    public function testSavesABillableTimeEntry(): void
    {
        $entry = new TimeEntry(
            null,
            new Task('t1', new Project('p1', 'bluebird-www'), 'bluebird-www #39: X'),
            'u1',
            new DateTimeImmutable('2026-10-01'),
            new Duration(75),
            'https://example.test/39'
        );
        $this->http->respond(['code' => 0, 'time_entry' => ['time_entry_id' => 'e1']], 201);

        (new ZohoTimeEntryRepository($this->client))->save($entry);

        $this->assertSame('e1', $entry->id());
        $this->assertSame('POST', $this->http->requests[0]['method']);
        $this->assertSame(
            [
                'project_id' => 'p1',
                'task_id' => 't1',
                'user_id' => 'u1',
                'log_date' => '2026-10-01',
                'log_time' => '01:15',
                'is_billable' => true,
                'notes' => 'https://example.test/39',
            ],
            json_decode((string) $this->http->requests[0]['body'], true)
        );
    }

    /**
     * Check that a day's entries are listed with a custom date filter and read back.
     *
     * @return void
     */
    public function testListsADaysEntries(): void
    {
        $this->http->respond(['code' => 0, 'time_entries' => [[
            'time_entry_id' => 'e1',
            'project_id' => 'p1',
            'project_name' => 'bluebird-www',
            'task_id' => 't1',
            'task_name' => 'bluebird-www #39: X',
            'user_id' => 'u1',
            'log_date' => '2026-10-01',
            'log_time' => '01:15',
            'notes' => '',
        ]]]);

        $entries = (new ZohoTimeEntryRepository($this->client))->onDate(new DateTimeImmutable('2026-10-01'));

        parse_str((string) parse_url($this->http->requests[0]['url'], PHP_URL_QUERY), $query);
        $this->assertSame('Date.CustomDate', $query['filter_by']);
        $this->assertSame('2026-10-01', $query['from_date']);
        $this->assertSame('2026-10-01', $query['to_date']);
        $this->assertCount(1, $entries);
        $this->assertSame(75, $entries[0]->duration()->minutes());
        $this->assertSame('t1', $entries[0]->task()->id());
    }

    /**
     * Check that Zoho's error responses are reported with Zoho's message.
     *
     * @return void
     */
    public function testReportsZohoErrors(): void
    {
        $this->http->respond(['code' => 1002, 'message' => 'Time entry does not exist.'], 404);

        $this->expectException(ZohoApiException::class);
        $this->expectExceptionMessage('Time entry does not exist.');

        (new ZohoTimeEntryRepository($this->client))->delete('missing');
    }
}
