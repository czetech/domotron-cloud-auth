<?php

declare(strict_types=1);

namespace Domotron\CloudAuth\Tests;

use Domotron\CloudAuth\CloudApi;
use Domotron\CloudAuth\Exception\CloudApiException;
use Domotron\CloudAuth\Exception\TransportException;
use PHPUnit\Framework\TestCase;

final class CloudApiTest extends TestCase
{
    private FakeTransport $transport;

    private CloudApi $api;

    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
        $this->api = new CloudApi($this->transport, 'https://cloud.domotron.com/', 'api-token');
    }

    public function testBaseUrlIsNormalized(): void
    {
        self::assertSame('https://cloud.domotron.com', $this->api->getBaseUrl());
    }

    public function testSessionCallsCarryNoTokenAndUseTheHash(): void
    {
        $hash = sha1('key');
        $this->transport
            ->on('GET', "/api/v1/user/session/$hash", 200, ['status' => 'ok', 'result' => ['id' => 1]])
            ->on('POST', "/api/v1/user/session/$hash", 200, ['status' => 'ok', 'result' => ['valid_until' => 1_800_000_000]])
            ->on('DELETE', "/api/v1/user/session/$hash", 200, ['status' => 'ok', 'result' => 'ok']);

        self::assertSame(1, $this->api->userIdFromSession($hash));
        self::assertSame(1_800_000_000, $this->api->extendSession($hash));
        self::assertTrue($this->api->invalidateSession($hash));

        foreach ($this->transport->requests as $request) {
            self::assertSame([], $request['headers']);
            self::assertNull($request['body']);
        }
        self::assertSame("https://cloud.domotron.com/api/v1/user/session/$hash", $this->transport->requests[0]['url']);
    }

    public function testUnknownSessionIs404(): void
    {
        $this->transport
            ->on('GET', '/api/v1/user/session/x', 404, ['status' => 'error', 'message' => 'Unknown hash'])
            ->on('POST', '/api/v1/user/session/x', 404, ['status' => 'error', 'message' => 'Unknown hash'])
            ->on('DELETE', '/api/v1/user/session/x', 404, ['status' => 'error', 'message' => 'Unknown hash']);

        self::assertNull($this->api->userIdFromSession('x'));
        self::assertNull($this->api->extendSession('x'));
        self::assertTrue($this->api->invalidateSession('x'), 'already gone counts as invalidated');
    }

    public function testDataCallsCarryTheApiToken(): void
    {
        $this->transport
            ->on('GET', '/api/v1/user/1', 200, ['status' => 'ok', 'result' => IdentityTest::ownerData()])
            ->on('GET', '/api/v1/user/permissions/1?key%5B0%5D=global&key%5B1%5D=control-ctl1', 200, [
                'status' => 'ok',
                'result' => ['global' => ['cloud:project' => ['listing']], 'control-ctl1' => ['global' => ['access']]],
            ]);

        $identity = $this->api->identity(1);
        self::assertSame('owner@test.local', $identity?->getUsername());

        $permissions = $this->api->permissions(1, ['global', 'control-ctl1']);
        self::assertTrue($permissions['global']->isAllowed('cloud:project', 'listing'));
        self::assertTrue($permissions['control-ctl1']->isAllowed('global', 'access'));

        foreach ($this->transport->requests as $request) {
            self::assertSame(['Authorization' => 'Bearer api-token'], $request['headers']);
        }
    }

    public function testPermissionsAlwaysAnswerEveryRequestedKey(): void
    {
        $this->transport->on('GET', '/api/v1/user/permissions/2?key%5B0%5D=project-1&key%5B1%5D=nonsense', 200, [
            'status' => 'ok',
            'result' => ['project-1' => [], 'nonsense' => []],
        ]);

        $permissions = $this->api->permissions(2, ['project-1', 'nonsense']);
        self::assertSame(['project-1', 'nonsense'], array_keys($permissions));
        self::assertTrue($permissions['project-1']->isEmpty());
        self::assertSame([], $this->api->permissions(2, []));
        self::assertCount(1, $this->transport->requests, 'no request for an empty key list');
    }

    public function testUnknownUserIs404(): void
    {
        $this->transport->on('GET', '/api/v1/user/999', 404, ['status' => 'error', 'message' => 'User not found']);
        self::assertNull($this->api->identity(999));
    }

    public function testRejectedTokenIsAnApiException(): void
    {
        $this->transport->on('GET', '/api/v1/user/1', 403, ['message' => 'Permission denied, invalid token']);

        try {
            $this->api->identity(1);
            self::fail('expected CloudApiException');
        } catch (CloudApiException $e) {
            self::assertSame(403, $e->status);
            self::assertStringContainsString('invalid token', $e->getMessage());
        }
    }

    public function testMalformedAnswersAreApiExceptions(): void
    {
        $this->transport
            ->onRaw('GET', '/api/v1/user/session/h', 200, '<html>oops</html>')
            ->on('POST', '/api/v1/user/session/h', 200, ['status' => 'ok', 'result' => []]);

        try {
            $this->api->userIdFromSession('h');
            self::fail('expected CloudApiException');
        } catch (CloudApiException $e) {
            self::assertStringContainsString('not a JSON object', $e->getMessage());
            self::assertSame('<html>oops</html>', $e->body);
        }

        $this->expectException(CloudApiException::class);
        $this->api->extendSession('h');
    }

    public function testTransportErrorsPropagate(): void
    {
        $this->transport->failing('GET', '/api/v1/user/session/h');

        $this->expectException(TransportException::class);
        $this->api->userIdFromSession('h');
    }
}
