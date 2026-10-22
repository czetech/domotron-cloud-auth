<?php

declare(strict_types=1);

namespace Domotron\CloudAuth\Tests;

use Domotron\CloudAuth\Cookie;
use PHPUnit\Framework\TestCase;

final class CookieTest extends TestCase
{
    public function testSessionCookie(): void
    {
        $cookie = Cookie::forSession('abc', 1_800_000_000, 'sk1.control.domotron.com');

        self::assertSame('domotron-gw', $cookie->name);
        self::assertSame('abc', $cookie->value);
        self::assertSame(1_800_000_000, $cookie->expiresAt);
        self::assertSame('.domotron.com', $cookie->domain);
        self::assertSame('/', $cookie->path);
        self::assertFalse($cookie->secure);
        self::assertTrue($cookie->httpOnly);
        self::assertFalse($cookie->isDeletion());

        $expires = $cookie->expires();
        self::assertInstanceOf(\DateTimeImmutable::class, $expires);
        self::assertSame(1_800_000_000, $expires->getTimestamp());
    }

    public function testHostOnlyCookieHasNoDomain(): void
    {
        self::assertNull(Cookie::forSession('abc', 1_800_000_000, 'localhost')->domain);
        self::assertNull(Cookie::deletion('127.0.0.1')->domain);
    }

    public function testDeletionCookie(): void
    {
        $cookie = Cookie::deletion('cloud.domotron.com', true);

        self::assertSame('', $cookie->value);
        self::assertSame(0, $cookie->expiresAt);
        self::assertSame('.domotron.com', $cookie->domain);
        self::assertTrue($cookie->secure);
        self::assertTrue($cookie->isDeletion());
        self::assertNull($cookie->expires());
    }
}
