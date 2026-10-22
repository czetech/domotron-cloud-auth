<?php

declare(strict_types=1);

namespace Domotron\CloudAuth\Tests;

use Domotron\CloudAuth\Cache\ArrayCache;
use PHPUnit\Framework\TestCase;

final class ArrayCacheTest extends TestCase
{
    public function testTtlAndClear(): void
    {
        $now = 1000;
        $cache = new ArrayCache(function () use (&$now): int {
            return $now;
        });

        self::assertNull($cache->get('a'));
        $cache->set('a', ['x' => 1], 60);
        self::assertSame(['x' => 1], $cache->get('a'));

        $now = 1060;
        self::assertSame(['x' => 1], $cache->get('a'), 'still valid at the boundary');
        $now = 1061;
        self::assertNull($cache->get('a'), 'expired');

        $cache->set('b', 'v', 10);
        $cache->clear();
        self::assertNull($cache->get('b'));
    }

    public function testSurvivesSerialization(): void
    {
        $cache = new ArrayCache();
        $cache->set('k', 'v', 3600);

        $restored = unserialize(serialize($cache));
        self::assertInstanceOf(ArrayCache::class, $restored);
        self::assertSame('v', $restored->get('k'));
    }
}
