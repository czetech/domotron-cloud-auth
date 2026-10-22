<?php

declare(strict_types=1);

namespace Domotron\CloudAuth\Http;

final readonly class Response
{
    public function __construct(
        public int $status,
        public string $body,
    ) {
    }

    /**
     * @return array<string, mixed>|null decoded JSON object, null when the body is not one
     */
    public function json(): ?array
    {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : null;
    }
}
