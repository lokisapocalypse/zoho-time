<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Domain;

use PHPUnit\Framework\TestCase;
use ZohoTime\Domain\BillingPolicy;
use ZohoTime\Domain\Issue;
use ZohoTime\Domain\NotBillableException;
use ZohoTime\Domain\Project;

/**
 * Tests which repositories are billed.
 */
final class BillingPolicyTest extends TestCase
{
    private BillingPolicy $policy;

    /**
     * Bill harbor-clinic and the client-bluebird repos, as in the billing rules.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->policy = new BillingPolicy(['harbor-clinic'], ['client-bluebird'], ['Bluebird Research']);
    }

    /**
     * Check that a named repository is billed whatever its topics.
     *
     * @return void
     */
    public function testBillsNamedRepositories(): void
    {
        $this->assertTrue($this->policy->isBillable('harbor-clinic', []));
    }

    /**
     * Check that a repository with a client topic is billed.
     *
     * @return void
     */
    public function testBillsRepositoriesWithAClientTopic(): void
    {
        $this->assertTrue($this->policy->isBillable('bluebird-www', ['laravel', 'client-bluebird']));
    }

    /**
     * Check that other repositories aren't billed.
     *
     * @return void
     */
    public function testDoesNotBillOtherRepositories(): void
    {
        $this->assertFalse($this->policy->isBillable('studio-www', ['website']));
    }

    /**
     * Check that billing an issue in another repository is refused.
     *
     * @return void
     */
    public function testRefusesToBillAnIssueInAnotherRepository(): void
    {
        $this->expectException(NotBillableException::class);

        $this->policy->assertBillable(
            new Issue('acme-studio', 'studio-www', 54, 'Something', 'https://github.com/x'),
            []
        );
    }

    /**
     * Check that a listed project can be billed without an issue, ignoring case.
     *
     * @return void
     */
    public function testBillsListedProjectsWithoutAnIssue(): void
    {
        $this->policy->assertProjectBillable(new Project('p1', 'bluebird RESEARCH'));

        $this->addToAssertionCount(1);
    }

    /**
     * Check that any other project is refused without an issue.
     *
     * @return void
     */
    public function testRefusesOtherProjectsWithoutAnIssue(): void
    {
        $this->expectException(NotBillableException::class);

        $this->policy->assertProjectBillable(new Project('p2', 'bluebird-www'));
    }
}
