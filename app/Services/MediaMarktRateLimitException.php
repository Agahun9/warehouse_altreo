<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class MediaMarktRateLimitException extends RuntimeException
{
    /** @var int|null */
    private $retryAfterSeconds;

    public function __construct(string $message, ?int $retryAfterSeconds = null)
    {
        parent::__construct($message);
        $this->retryAfterSeconds = $retryAfterSeconds;
    }

    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}
