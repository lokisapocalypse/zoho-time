<?php

declare(strict_types=1);

namespace ZohoTime\Domain;

use DomainException;

/**
 * Thrown when a new task's name is longer than Zoho accepts.
 */
final class TaskNameTooLongException extends DomainException
{
}
