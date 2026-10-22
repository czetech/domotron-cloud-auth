<?php

declare(strict_types=1);

namespace Domotron\CloudAuth\Tests;

use Domotron\CloudAuth\Exception\TransportException;
use Domotron\CloudAuth\Http\Response;
use Domotron\CloudAuth\Http\Transport;

/**
 * Scripted transport: responses are matched by "METHOD path" (query string included).
 */
final class FakeTransport implements Transport
{
    /** @var array<string, Response|TransportException> */
    private array $responses = [];

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $requests = [];

    public function on(string $method, string $path, int $status, mixed $json = null): self
    {
        $this->responses["$method $path"] = new Response($status, $json === null ? '' : json_encode($json, JSON_THROW_ON_ERROR));
        return $this;
    }

    public function onRaw(string $method, string $path, int $status, string $body): self
    {
        $this->responses["$method $path"] = new Response($status, $body);
        return $this;
    }

    public function failing(string $method, string $path): self
    {
        $this->responses["$method $path"] = new TransportException("$method $path unreachable");
        return $this;
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null): Response
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        $path = (string) parse_url($url, PHP_URL_PATH) . (parse_url($url, PHP_URL_QUERY) ? '?' . parse_url($url, PHP_URL_QUERY) : '');
        $key = "$method $path";
        if (!isset($this->responses[$key])) {
            throw new \LogicException("No scripted response for $key");
        }
        $response = $this->responses[$key];
        if ($response instanceof TransportException) {
            throw $response;
        }

        return $response;
    }

    public function count(): int
    {
        return count($this->requests);
    }
}
