<?php

declare(strict_types=1);

namespace ZohoTime\Domain;

use DomainException;

/**
 * Thrown when time is logged against a repository that isn't billed.
 */
final class NotBillableException extends DomainException
{
}
