<?php

declare(strict_types=1);

namespace Domotron\CloudAuth\Exception;

/**
 * The Cloud answered, but not the way the contract says (unexpected status or body).
 */
class CloudApiException extends CloudAuthException
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $body = null,
    ) {
        parent::__construct($message);
    }
}
