<?php

declare(strict_types=1);

namespace ZohoTime\Infrastructure;

use RuntimeException;

/**
 * Thrown when Zoho refuses a request.
 */
final class ZohoApiException extends RuntimeException
{
}
