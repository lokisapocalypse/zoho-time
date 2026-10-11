<?php

declare(strict_types=1);

namespace ZohoTime\Tests\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZohoTime\Domain\Duration;

/**
 * Tests parsing, rounding and formatting durations.
 */
final class DurationTest extends TestCase
{
    /**
     * Supply durations as typed, with their length in minutes.
     *
     * @return array<string, array{string, int}>
     */
    public static function durations(): array
    {
        return [
            'hours and minutes' => ['1h05m', 65],
            'hours with a space' => ['1h 05m', 65],
            'minutes only' => ['40m', 40],
            'whole hours' => ['3h', 180],
            'decimal hours' => ['1.25h', 75],
            'bare decimal hours' => ['1.5', 90],
            'clock style' => ['1:05', 65],
        ];
    }

    /**
     * Check that each accepted format parses to the right number of minutes.
     *
     * @param string $text    The duration as typed.
     * @param int    $minutes The expected minutes.
     *
     * @return void
     */
    #[DataProvider('durations')]
    public function testParsesCommonFormats(string $text, int $minutes): void
    {
        $this->assertSame($minutes, Duration::parse($text)->minutes());
    }

    /**
     * Check that unreadable text is refused.
     *
     * @return void
     */
    public function testRefusesUnreadableText(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Duration::parse('a while');
    }

    /**
     * Check that a zero duration is refused.
     *
     * @return void
     */
    public function testRefusesZero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Duration::parse('0m');
    }

    /**
     * Supply worked minutes with the minutes they round up to.
     *
     * @return array<string, array{int, int}>
     */
    public static function roundings(): array
    {
        return [
            '1h 05m becomes 1.25h' => [65, 75],
            '40m becomes 0.75h' => [40, 45],
            'a quarter hour stays put' => [45, 45],
            'one minute becomes 0.25h' => [1, 15],
            '2h 46m becomes 3h' => [166, 180],
        ];
    }

    /**
     * Check that durations round up to the nearest quarter hour.
     *
     * @param int $worked  Minutes worked.
     * @param int $billed  Minutes after rounding.
     *
     * @return void
     */
    #[DataProvider('roundings')]
    public function testRoundsUpToTheQuarterHour(int $worked, int $billed): void
    {
        $this->assertSame($billed, (new Duration($worked))->roundedUpToQuarterHour()->minutes());
    }

    /**
     * Check that durations convert to and from Zoho's HH:MM log time.
     *
     * @return void
     */
    public function testConvertsToAndFromZohoLogTime(): void
    {
        $this->assertSame('01:15', (new Duration(75))->toZohoLogTime());
        $this->assertSame(75, Duration::fromZohoLogTime('01:15')->minutes());
        $this->assertSame(1.25, (new Duration(75))->hours());
    }
}
