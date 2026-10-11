<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Domain;

use PHPUnit\Framework\TestCase;
use ZohoTime\Domain\BillingPolicy;
use ZohoTime\Domain\Issue;
use ZohoTime\Domain\NotBillableException;

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
        $this->policy = new BillingPolicy(['harbor-clinic'], ['client-bluebird']);
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
}
