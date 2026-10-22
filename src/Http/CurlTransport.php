<?php

declare(strict_types=1);

namespace Domotron\CloudAuth\Http;

use Domotron\CloudAuth\Exception\TransportException;

final readonly class CurlTransport implements Transport
{
    public function __construct(
        private int $timeoutSeconds = 5,
        private int $connectTimeoutSeconds = 2,
        private string $userAgent = 'domotron-cloud-auth',
    ) {
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null): Response
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new TransportException("curl_init failed for $url");
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "$name: $value";
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        // No curl_close(): a no-op since PHP 8.0 and deprecated in 8.5; the handle is freed with $curl
        $responseBody = curl_exec($curl);
        if ($responseBody === false) {
            throw new TransportException("$method $url failed: " . curl_error($curl));
        }

        return new Response((int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), (string) $responseBody);
    }
}
