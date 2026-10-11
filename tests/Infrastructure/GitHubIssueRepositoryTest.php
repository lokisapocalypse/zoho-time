<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Infrastructure;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ZohoTime\Infrastructure\GitHubIssueRepository;
use ZohoTime\Tests\Fakes\FakeProcessRunner;

/**
 * Tests reading issues and topics through gh.
 */
final class GitHubIssueRepositoryTest extends TestCase
{
    private FakeProcessRunner $runner;

    private GitHubIssueRepository $issues;

    /**
     * Set up the repository with acme-studio as the default owner.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->runner = new FakeProcessRunner();
        $this->issues = new GitHubIssueRepository($this->runner, 'acme-studio');
    }

    /**
     * Check that a short reference uses the default owner and reads the issue's title and URL.
     *
     * @return void
     */
    public function testFindsAnIssueUnderTheDefaultOwner(): void
    {
        $this->runner->answer(
            ['gh', 'issue', 'view', '39', '-R', 'acme-studio/bluebird-www', '--json', 'title,url'],
            '{"title":"Nightly database backups","url":"https://github.com/acme-studio/bluebird-www/issues/39"}'
        );

        $issue = $this->issues->find('bluebird-www#39');

        $this->assertSame('Nightly database backups', $issue->taskName());
        $this->assertSame('bluebird-www #39', $issue->reference());
        $this->assertSame('https://github.com/acme-studio/bluebird-www/issues/39', $issue->url());
    }

    /**
     * Check that a reference with an owner uses that owner.
     *
     * @return void
     */
    public function testFindsAnIssueUnderAGivenOwner(): void
    {
        $this->runner->answer(
            ['gh', 'issue', 'view', '1', '-R', 'lokisapocalypse/zoho-time', '--json', 'title,url'],
            '{"title":"Log time","url":"https://github.com/lokisapocalypse/zoho-time/issues/1"}'
        );

        $this->assertSame('lokisapocalypse', $this->issues->find('lokisapocalypse/zoho-time#1')->owner());
    }

    /**
     * Check that the repository's topics are read.
     *
     * @return void
     */
    public function testReadsTopics(): void
    {
        $this->runner->answer(
            ['gh', 'issue', 'view', '39', '-R', 'acme-studio/bluebird-www', '--json', 'title,url'],
            '{"title":"T","url":"U"}'
        );
        $this->runner->answer(['gh', 'api', 'repos/acme-studio/bluebird-www/topics'], '{"names":["client-bluebird"]}');

        $this->assertSame(['client-bluebird'], $this->issues->topicsOf($this->issues->find('bluebird-www#39')));
    }

    /**
     * Check that an unreadable reference is refused without running gh.
     *
     * @return void
     */
    public function testRefusesAnUnreadableReference(): void
    {
        $this->expectException(InvalidArgumentException::class);

        try {
            $this->issues->find('bluebird-www');
        } finally {
            $this->assertSame([], $this->runner->commands);
        }
    }
}
