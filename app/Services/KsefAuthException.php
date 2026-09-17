<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/** KSeF rejected the access token (HTTP 401); the cached token should be dropped and authentication retried. */
class KsefAuthException extends InvalidArgumentException
{
}
