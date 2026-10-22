<?php

declare(strict_types=1);

namespace Domotron\CloudAuth\Tests;

use Domotron\CloudAuth\Exception\CloudAuthException;
use Domotron\CloudAuth\Identity;
use PHPUnit\Framework\TestCase;

final class IdentityTest extends TestCase
{
    /**
     * @return array<string, mixed> the "result" of GET v1/user/1 as Cloud returns it
     */
    public static function ownerData(): array
    {
        return [
            'id' => 1,
            'name' => 'Owner User',
            'username' => 'owner@test.local',
            'is_demo' => false,
            'language' => ['id' => 1, 'name' => 'Slovenčina', 'code' => 'sk'],
            'favorite' => ['project' => [
                'id' => 1,
                'name' => 'Project A',
                'plc' => ['data' => ['sn' => 'SN-A', 'control_host' => ['id' => 1, 'identifier' => 'ctl1', 'global_identifier' => 'ctl1_srv1', 'url' => 'http://127.0.0.1:8090']]],
            ]],
        ];
    }

    public function testAccessors(): void
    {
        $identity = Identity::fromArray(self::ownerData());

        self::assertSame(1, $identity->getId());
        self::assertSame('Owner User', $identity->getName());
        self::assertSame('owner@test.local', $identity->getUsername());
        self::assertFalse($identity->isDemo());
        self::assertSame(['id' => 1, 'name' => 'Slovenčina', 'code' => 'sk'], $identity->getLanguage());
        self::assertSame('Project A', $identity->getFavoriteProject()['name']);
        self::assertSame(self::ownerData(), $identity->getData());
    }

    public function testStringIdAndMissingOptionalFields(): void
    {
        $identity = Identity::fromArray(['id' => '7', 'is_demo' => 1]);

        self::assertSame(7, $identity->getId());
        self::assertTrue($identity->isDemo());
        self::assertNull($identity->getName());
        self::assertNull($identity->getUsername());
        self::assertNull($identity->getLanguage());
        self::assertNull($identity->getFavoriteProject());
    }

    public function testNoFavoriteProjectIsAnEmptyList(): void
    {
        // Cloud returns [] (JSON array) when the user has no favorite project
        $identity = Identity::fromArray(['id' => 3, 'favorite' => ['project' => []]]);
        self::assertNull($identity->getFavoriteProject());
    }

    public function testIdIsRequired(): void
    {
        $this->expectException(CloudAuthException::class);
        Identity::fromArray(['name' => 'nobody']);
    }
}
