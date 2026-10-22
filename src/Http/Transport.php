<?php

declare(strict_types=1);

namespace Domotron\CloudAuth\Http;

use Domotron\CloudAuth\Exception\TransportException;

interface Transport
{
    /**
     * @param array<string, string> $headers
     * @throws TransportException when no HTTP response was obtained
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): Response;
}
