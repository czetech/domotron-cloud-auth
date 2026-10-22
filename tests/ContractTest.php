<?php

declare(strict_types=1);

namespace Domotron\CloudAuth\Tests;

use Domotron\CloudAuth\Contract;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContractTest extends TestCase
{
    public function testCookieName(): void
    {
        self::assertSame('domotron-gw', Contract::COOKIE_NAME);
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function hosts(): iterable
    {
        yield 'portal' => ['cloud.domotron.com', '.domotron.com'];
        yield 'control subdomain' => ['sk1234.control.domotron.com', '.domotron.com'];
        yield 'apex' => ['domotron.com', '.domotron.com'];
        yield 'with port' => ['cloud.test:8080', '.cloud.test'];
        yield 'upper case' => ['Cloud.Domotron.COM', '.domotron.com'];
        yield 'single label' => ['localhost', null];
        yield 'ip address' => ['127.0.0.1', null];
        yield 'empty' => ['', null];
    }

    #[DataProvider('hosts')]
    public function testCookieDomain(string $host, ?string $expected): void
    {
        self::assertSame($expected, Contract::cookieDomain($host));
    }

    public function testSessionHashIsSha1OfTheCookieValue(): void
    {
        self::assertSame(sha1('owner-session-key'), Contract::sessionHash('owner-session-key'));
        self::assertSame(40, strlen(Contract::sessionHash('x')));
    }

    public function testKeys(): void
    {
        self::assertSame('global', Contract::KEY_GLOBAL);
        self::assertSame('control-ctl1_srv1', Contract::controlKey('ctl1_srv1'));
        self::assertSame('project-12', Contract::projectKey(12));
    }
}
