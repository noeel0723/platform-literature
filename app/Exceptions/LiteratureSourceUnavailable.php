<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class LiteratureSourceUnavailable extends RuntimeException
{
    public function __construct(
        public readonly string $source,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
