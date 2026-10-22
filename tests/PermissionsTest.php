<?php

declare(strict_types=1);

namespace Domotron\CloudAuth\Tests;

use Domotron\CloudAuth\Permissions;
use PHPUnit\Framework\TestCase;

final class PermissionsTest extends TestCase
{
    public function testIsAllowed(): void
    {
        $permissions = Permissions::fromArray(['cloud:project' => ['read', 'update'], 'control:global' => ['access']]);

        self::assertTrue($permissions->isAllowed('cloud:project', 'read'));
        self::assertTrue($permissions->isAllowed('cloud:project', 'update'));
        self::assertFalse($permissions->isAllowed('cloud:project', 'delete'));
        self::assertFalse($permissions->isAllowed('cloud:market', 'read'));
        self::assertTrue($permissions->isAllowed('control:global', 'access'));
        self::assertFalse($permissions->isEmpty());
    }

    public function testAnyPrivilegeOnResource(): void
    {
        $permissions = Permissions::fromArray(['global' => ['access']]);

        self::assertTrue($permissions->isAllowed('global'));
        self::assertTrue($permissions->isAllowed('global', ''));
        self::assertFalse($permissions->isAllowed('secretUser'));
    }

    public function testNoneAndEmptyApiAnswer(): void
    {
        self::assertTrue(Permissions::none()->isEmpty());
        self::assertFalse(Permissions::none()->isAllowed('global', 'access'));

        // Cloud answers [] (JSON array) for keys the user has nothing on
        self::assertTrue(Permissions::fromArray([])->isEmpty());

        // A missing key or a malformed answer is no permissions, not an error
        self::assertTrue(Permissions::fromArray(null)->isEmpty());
        self::assertTrue(Permissions::fromArray('garbage')->isEmpty());
        self::assertFalse(Permissions::fromArray(['global' => 'x'])->isAllowed('global', 'access'));
    }

    public function testToArrayNormalizesValues(): void
    {
        self::assertSame(['a:b' => ['x', '1']], Permissions::fromArray(['a:b' => ['x', 1]])->toArray());
        self::assertSame(['a:b' => []], Permissions::fromArray(['a:b' => 'garbage'])->toArray());
    }
}
