<?php

declare(strict_types=1);

namespace ZohoTime\Application;

/**
 * Where a month's recurring entry stands.
 */
enum RecurringStatus
{
    /** Ready to save. */
    case Due;

    /** Already in Zoho for the month. */
    case Logged;

    /** Left out this time by request. */
    case Skipped;

    /** The project can't be found, so it can't be logged. */
    case Unavailable;

    /** Saved in this run. */
    case Saved;
}
