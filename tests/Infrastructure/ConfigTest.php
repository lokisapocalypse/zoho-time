<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Infrastructure;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZohoTime\Infrastructure\Config;

/**
 * Tests reading the recurring entries from the config file.
 */
final class ConfigTest extends TestCase
{
    private string $path;

    /**
     * Pick a temporary path for the config file.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'zoho-time-config-');
    }

    /**
     * Remove the temporary config file.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    /**
     * Check that recurring entries are read with their exact times.
     *
     * @return void
     */
    public function testReadsRecurringEntries(): void
    {
        $config = $this->load([
            ['project' => 'Bluebird Clinic', 'task' => 'Hosting Fee', 'time' => '0:06', 'notes' => 'Tier 1'],
            ['project' => 'Bluebird Clinic', 'task' => 'Site Backup', 'time' => '1:00', 'notes' => 'Tier 3'],
        ]);

        $entries = $config->recurringEntries();

        $this->assertCount(2, $entries);
        $this->assertSame('Hosting Fee', $entries[0]->taskName());
        $this->assertSame(6, $entries[0]->duration()->minutes());
        $this->assertSame(60, $entries[1]->duration()->minutes());
    }

    /**
     * Check that a config without recurring entries has none.
     *
     * @return void
     */
    public function testRecurringEntriesAreOptional(): void
    {
        $this->assertSame([], $this->load(null)->recurringEntries());
    }

    /**
     * Check that a malformed entry is reported with its number.
     *
     * @return void
     */
    public function testMalformedEntryIsReportedWithItsNumber(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('recurring entry 2: A recurring entry needs notes.');

        $this->load([
            ['project' => 'Bluebird Clinic', 'task' => 'Hosting Fee', 'time' => '0:06', 'notes' => 'Tier 1'],
            ['project' => 'Bluebird Clinic', 'task' => 'Site Backup', 'time' => '1:00'],
        ]);
    }

    /**
     * Write a config with the given recurring entries and load it.
     *
     * @param list<array<string, string>>|null $recurring The "recurring" list, or null to leave it out.
     *
     * @return Config
     */
    private function load(?array $recurring): Config
    {
        $data = ['data_center' => 'com', 'organization_id' => '1', 'github_owner' => 'acme-studio'];

        if ($recurring !== null) {
            $data['recurring'] = $recurring;
        }

        file_put_contents($this->path, json_encode($data));

        return Config::load($this->path);
    }
}
