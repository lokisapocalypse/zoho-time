<?php

declare(strict_types=1);

namespace ZohoTime\Domain;

use InvalidArgumentException;

/**
 * A length of time worked, held in whole minutes.
 */
final class Duration
{
    private const QUARTER_HOUR = 15;

    private readonly int $minutes;

    /**
     * Create a duration of the given number of minutes.
     *
     * @param int $minutes Minutes worked; must be positive.
     *
     * @throws InvalidArgumentException When the duration isn't positive.
     */
    public function __construct(int $minutes)
    {
        if ($minutes <= 0) {
            throw new InvalidArgumentException('A duration must be longer than zero minutes.');
        }

        $this->minutes = $minutes;
    }

    /**
     * Parse a duration such as "1h05m", "1h", "40m", "1.25h", "1.5" (hours) or "1:05".
     *
     * @param string $text The duration as typed.
     *
     * @return self
     *
     * @throws InvalidArgumentException When the text isn't a recognizable duration.
     */
    public static function parse(string $text): self
    {
        $text = strtolower(str_replace(' ', '', trim($text)));

        if (preg_match('/^(\d+):([0-5]\d)$/', $text, $match)) {
            return new self((int) $match[1] * 60 + (int) $match[2]);
        }

        if (preg_match('/^(?:(\d+(?:\.\d+)?)h)?(?:(\d+)m)?$/', $text, $match) && $text !== '') {
            $hours = isset($match[1]) && $match[1] !== '' ? (float) $match[1] : 0.0;
            $minutes = isset($match[2]) ? (int) $match[2] : 0;

            return new self((int) ceil($hours * 60) + $minutes);
        }

        if (preg_match('/^\d+(?:\.\d+)?$/', $text)) {
            return new self((int) ceil((float) $text * 60));
        }

        throw new InvalidArgumentException(
            "Can't read \"{$text}\" as a duration; use e.g. 1h05m, 40m, 1.25h or 1:05."
        );
    }

    /**
     * Parse Zoho's "HH:MM" log time.
     *
     * @param string $logTime The time as Zoho reports it, e.g. "01:15".
     *
     * @return self
     *
     * @throws InvalidArgumentException When the text isn't in HH:MM form.
     */
    public static function fromZohoLogTime(string $logTime): self
    {
        if (!preg_match('/^(\d+):(\d{2})$/', $logTime, $match)) {
            throw new InvalidArgumentException("Unexpected Zoho log time \"{$logTime}\".");
        }

        return new self((int) $match[1] * 60 + (int) $match[2]);
    }

    /**
     * Round up to the nearest quarter hour, e.g. 65 minutes becomes 75.
     *
     * @return self
     */
    public function roundedUpToQuarterHour(): self
    {
        return new self((int) ceil($this->minutes / self::QUARTER_HOUR) * self::QUARTER_HOUR);
    }

    /**
     * Get the duration in minutes.
     *
     * @return int
     */
    public function minutes(): int
    {
        return $this->minutes;
    }

    /**
     * Get the duration in decimal hours, e.g. 1.25.
     *
     * @return float
     */
    public function hours(): float
    {
        return $this->minutes / 60;
    }

    /**
     * Format as Zoho's "HH:MM" log time, e.g. "01:15".
     *
     * @return string
     */
    public function toZohoLogTime(): string
    {
        return sprintf('%02d:%02d', intdiv($this->minutes, 60), $this->minutes % 60);
    }

    /**
     * Format for people, e.g. "1h 05m".
     *
     * @return string
     */
    public function toHuman(): string
    {
        return sprintf('%dh %02dm', intdiv($this->minutes, 60), $this->minutes % 60);
    }
}
