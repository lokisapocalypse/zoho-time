<?php

declare(strict_types=1);

namespace ZohoTime\Domain;

use RuntimeException;

/**
 * Thrown when time is to be logged on an existing task that the project doesn't have.
 */
final class TaskNotFoundException extends RuntimeException
{
}
