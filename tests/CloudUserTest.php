<?php

declare(strict_types=1);

namespace Domotron\CloudAuth\Tests;

use Domotron\CloudAuth\Cache\ArrayCache;
use Domotron\CloudAuth\CloudApi;
use Domotron\CloudAuth\CloudUser;
use Domotron\CloudAuth\Contract;
use Domotron\CloudAuth\Exception\CloudAuthException;
use Domotron\CloudAuth\Exception\TransportException;
use Domotron\CloudAuth\Identity;
use PHPUnit\Framework\TestCase;

final class CloudUserTest extends TestCase
{
    private const KEY = 'owner-session-key';

    private FakeTransport $transport;

    private ArrayCache $cache;

    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
        $this->cache = new ArrayCache();
    }

    private function user(?string $sessionKey = self::KEY, bool $active = true, array $aliases = ['control' => 'control-ctl1']): CloudUser
    {
        $api = new CloudApi($this->transport, 'https://cloud.domotron.com', 'api-token');
        return new CloudUser($api, $this->cache, $sessionKey, 'sk1.control.domotron.com', $aliases, 600, 300, $active);
    }

    private function scriptValidSession(): string
    {
        $hash = Contract::sessionHash(self::KEY);
        $this->transport
            ->on('GET', "/api/v1/user/session/$hash", 200, ['status' => 'ok', 'result' => ['id' => 1]])
            ->on('GET', '/api/v1/user/1', 200, ['status' => 'ok', 'result' => IdentityTest::ownerData()])
            ->on('POST', "/api/v1/user/session/$hash", 200, ['status' => 'ok', 'result' => ['valid_until' => 1_800_000_000]])
            ->on('DELETE', "/api/v1/user/session/$hash", 200, ['status' => 'ok', 'result' => 'ok'])
            ->on('GET', '/api/v1/user/permissions/1?key%5B0%5D=control-ctl1', 200, ['status' => 'ok', 'result' => ['control-ctl1' => ['global' => ['access']]]])
            ->on('GET', '/api/v1/user/permissions/1?key%5B0%5D=global', 200, ['status' => 'ok', 'result' => ['global' => []]]);
        return $hash;
    }

    public function testNoCookieMeansAnonymousWithoutAnyRequest(): void
    {
        foreach ([null, ''] as $key) {
            $user = $this->user($key);
            self::assertFalse($user->isLoggedIn());
            self::assertNull($user->getIdentity());
            self::assertNull($user->permissions('global'));
            self::assertFalse($user->permissionsIsAllowed('global', 'global', 'access'));
            self::assertNull($user->cookieToSet());
        }
        self::assertSame(0, $this->transport->count());
    }

    public function testFreshSessionIsResolvedExtendedAndCookieReissued(): void
    {
        $this->scriptValidSession();
        $user = $this->user();

        self::assertTrue($user->isLoggedIn());
        self::assertSame(1, $user->getIdentity()?->getId());
        self::assertSame(['GET', 'GET', 'POST'], array_column($this->transport->requests, 'method'), 'lookup, user data, extend');

        $cookie = $user->cookieToSet();
        self::assertNotNull($cookie);
        self::assertSame(self::KEY, $cookie->value);
        self::assertSame(1_800_000_000, $cookie->expiresAt);
        self::assertSame('.domotron.com', $cookie->domain);

        // Second call in the same request: no further traffic
        self::assertTrue($user->isLoggedIn());
        self::assertSame(3, $this->transport->count());
    }

    public function testCachedIdentityNeedsNoRequestAndNoCookie(): void
    {
        $this->scriptValidSession();
        $this->user()->isLoggedIn();
        $this->transport->requests = [];

        $user = $this->user();
        self::assertTrue($user->isLoggedIn());
        self::assertSame('owner@test.local', $user->getIdentity()?->getUsername());
        self::assertSame(0, $this->transport->count());
        self::assertNull($user->cookieToSet());
    }

    public function testCacheIsBoundToTheSessionKey(): void
    {
        $this->scriptValidSession();
        $this->user()->isLoggedIn();

        $other = $this->user('someone-else');
        $this->transport->on('GET', '/api/v1/user/session/' . Contract::sessionHash('someone-else'), 404, ['status' => 'error', 'message' => 'Unknown hash']);
        self::assertFalse($other->isLoggedIn(), 'another cookie must not reuse the cached identity');
    }

    public function testUnknownSessionIsAnonymousAndNotCached(): void
    {
        $hash = Contract::sessionHash(self::KEY);
        $this->transport->on('GET', "/api/v1/user/session/$hash", 404, ['status' => 'error', 'message' => 'Unknown hash']);

        $user = $this->user();
        self::assertFalse($user->isLoggedIn());
        self::assertNull($user->cookieToSet());
        self::assertSame(1, $this->transport->count());

        self::assertFalse($this->user()->isLoggedIn());
        self::assertSame(2, $this->transport->count(), 'negative answers are asked again');
    }

    public function testPermissionsAreFetchedPerKeyCachedAndAliased(): void
    {
        $this->scriptValidSession();
        $user = $this->user();

        self::assertTrue($user->permissionsIsAllowed('%control', 'global', 'access'));
        self::assertTrue($user->permissionsIsAllowed('control-ctl1', 'global', 'access'));
        self::assertFalse($user->permissionsIsAllowed('%control', 'secretUser', 'access', false));
        self::assertFalse($user->permissionsIsAllowed('global', 'cloud:project', 'listing'));
        self::assertSame(5, $this->transport->count(), 'two permission requests, one per distinct key');

        $this->transport->requests = [];
        $again = $this->user();
        self::assertTrue($again->permissionsIsAllowed('%control', 'global', 'access'));
        self::assertSame(0, $this->transport->count(), 'identity and permissions served from cache');
    }

    public function testUnknownAliasIsAnError(): void
    {
        $this->scriptValidSession();
        $this->expectException(CloudAuthException::class);
        $this->user()->permissions('%tuner');
    }

    public function testInactiveInstallationHasNoUserButPermissiveDefaults(): void
    {
        $user = $this->user(self::KEY, false);

        self::assertFalse($user->isActive());
        self::assertFalse($user->isLoggedIn());
        self::assertTrue($user->permissionsIsAllowed('%control', 'global', 'access'));
        self::assertFalse($user->permissionsIsAllowed('%control', 'secretUser', 'access', false));
        self::assertSame(0, $this->transport->count());
    }

    public function testLogoutInvalidatesClearsAndDeletesCookie(): void
    {
        $hash = $this->scriptValidSession();
        $user = $this->user();
        self::assertTrue($user->isLoggedIn());

        $user->logout();

        self::assertFalse($user->isLoggedIn());
        self::assertNull($user->getIdentity());
        self::assertSame('DELETE', end($this->transport->requests)['method']);
        self::assertStringEndsWith("/api/v1/user/session/$hash", end($this->transport->requests)['url']);
        self::assertTrue($user->cookieToSet()?->isDeletion());
        self::assertNull($this->cache->get('cloud-auth:identity:' . $hash));
    }

    public function testLogoutWithoutCookieOnlyDeletesTheCookie(): void
    {
        $user = $this->user(null);
        $user->logout();
        self::assertSame(0, $this->transport->count());
        self::assertTrue($user->cookieToSet()?->isDeletion());
    }

    public function testCloudOutageSurfacesAsException(): void
    {
        $this->transport->failing('GET', '/api/v1/user/session/' . Contract::sessionHash(self::KEY));
        $this->expectException(TransportException::class);
        $this->user()->isLoggedIn();
    }

    public function testLoginAndLogoutUrls(): void
    {
        $user = $this->user();
        self::assertSame('https://cloud.domotron.com', $user->getLoginUrl());
        self::assertSame(
            'https://cloud.domotron.com/?sso_portal_id=sk1&sso_portal_secret=s3cret&sso_redirect_uri=https%3A%2F%2Fsk1.control.domotron.com%2F',
            $user->getLoginUrl('sk1', 's3cret', 'https://sk1.control.domotron.com/')
        );
        self::assertSame('https://cloud.domotron.com/sign/out', $user->getLogoutUrl());
    }

    public function testIdentityInCacheIsTheValueObject(): void
    {
        $this->scriptValidSession();
        $this->user()->isLoggedIn();
        self::assertInstanceOf(Identity::class, $this->cache->get('cloud-auth:identity:' . Contract::sessionHash(self::KEY)));
    }
}
