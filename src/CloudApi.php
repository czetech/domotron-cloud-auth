<?php

declare(strict_types=1);

namespace Domotron\CloudAuth;

use Domotron\CloudAuth\Exception\CloudApiException;
use Domotron\CloudAuth\Http\Response;
use Domotron\CloudAuth\Http\Transport;

/**
 * The five Cloud API calls a Control instance needs. Session calls carry no credential other
 * than the session hash; data calls carry the server-to-server API token.
 */
final readonly class CloudApi
{
    private string $baseUrl;

    public function __construct(
        private Transport $transport,
        string $baseUrl,
        private string $apiToken,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * GET v1/user/session/<hash>
     * @return int|null the user id, null when the session is unknown or expired
     */
    public function userIdFromSession(string $sessionHash): ?int
    {
        $response = $this->transport->request('GET', $this->url('v1/user/session/' . rawurlencode($sessionHash)));
        if ($response->status === 404) {
            return null;
        }
        $data = $this->expectOk($response, 'session lookup');
        if (!isset($data['result']['id']) || !is_numeric($data['result']['id'])) {
            throw $this->unexpected($response, 'session lookup', 'result.id missing');
        }

        return (int) $data['result']['id'];
    }

    /**
     * POST v1/user/session/<hash>
     * @return int|null new validity as Unix timestamp, null when the session is unknown or expired
     */
    public function extendSession(string $sessionHash): ?int
    {
        $response = $this->transport->request('POST', $this->url('v1/user/session/' . rawurlencode($sessionHash)));
        if ($response->status === 404) {
            return null;
        }
        $data = $this->expectOk($response, 'session extend');
        if (!isset($data['result']['valid_until']) || !is_numeric($data['result']['valid_until'])) {
            throw $this->unexpected($response, 'session extend', 'result.valid_until missing');
        }

        return (int) $data['result']['valid_until'];
    }

    /**
     * DELETE v1/user/session/<hash>
     * @return bool true when the session is gone (deleted now or already unknown)
     */
    public function invalidateSession(string $sessionHash): bool
    {
        $response = $this->transport->request('DELETE', $this->url('v1/user/session/' . rawurlencode($sessionHash)));
        if ($response->status === 404) {
            return true;
        }
        $data = $this->expectOk($response, 'session invalidate');

        return ($data['result'] ?? null) === 'ok';
    }

    /**
     * GET v1/user/<id> (API token)
     */
    public function identity(int $userId): ?Identity
    {
        $response = $this->transport->request('GET', $this->url('v1/user/' . $userId), $this->tokenHeaders());
        if ($response->status === 404) {
            return null;
        }
        $data = $this->expectOk($response, 'user data');
        if (!isset($data['result']) || !is_array($data['result'])) {
            throw $this->unexpected($response, 'user data', 'result missing');
        }

        return Identity::fromArray($data['result']);
    }

    /**
     * GET v1/user/permissions/<id>?key[]=… (API token)
     * @param list<string> $keys
     * @return array<string, Permissions> one entry per requested key (empty permissions when unknown)
     */
    public function permissions(int $userId, array $keys): array
    {
        if ($keys === []) {
            return [];
        }
        $query = http_build_query(['key' => array_values($keys)]);
        $response = $this->transport->request('GET', $this->url('v1/user/permissions/' . $userId . '?' . $query), $this->tokenHeaders());
        $data = $this->expectOk($response, 'permissions');
        if (!isset($data['result']) || !is_array($data['result'])) {
            throw $this->unexpected($response, 'permissions', 'result missing');
        }

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = Permissions::fromArray($data['result'][$key] ?? null);
        }

        return $result;
    }

    private function url(string $path): string
    {
        return $this->baseUrl . '/api/' . $path;
    }

    /**
     * @return array<string, string>
     */
    private function tokenHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->apiToken];
    }

    /**
     * @return array<string, mixed>
     */
    private function expectOk(Response $response, string $what): array
    {
        $data = $response->json();
        if ($response->status !== 200) {
            $message = is_array($data) ? ($data['message'] ?? $data['error'] ?? '') : '';
            throw new CloudApiException(
                sprintf('Cloud %s failed with HTTP %d%s', $what, $response->status, $message !== '' ? ": $message" : ''),
                $response->status,
                $response->body
            );
        }
        if ($data === null) {
            throw $this->unexpected($response, $what, 'body is not a JSON object');
        }

        return $data;
    }

    private function unexpected(Response $response, string $what, string $detail): CloudApiException
    {
        return new CloudApiException("Cloud $what answered unexpectedly: $detail", $response->status, $response->body);
    }
}
