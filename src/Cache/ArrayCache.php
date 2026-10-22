<?php

declare(strict_types=1);

namespace Domotron\CloudAuth\Cache;

/**
 * In-memory cache. Also usable as the storage behind a session-backed cache: keep one instance
 * per session by serializing it into the session (it holds plain values and expiry timestamps).
 */
final class ArrayCache implements Cache
{
    /** @var array<string, array{value: mixed, expiresAt: int}> */
    private array $items = [];

    /** @var callable(): int */
    private $clock;

    /**
     * @param callable(): int|null $clock returns the current Unix timestamp (tests)
     */
    public function __construct(?callable $clock = null)
    {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function get(string $key): mixed
    {
        if (!isset($this->items[$key])) {
            return null;
        }
        if ($this->items[$key]['expiresAt'] < ($this->clock)()) {
            unset($this->items[$key]);
            return null;
        }

        return $this->items[$key]['value'];
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        $this->items[$key] = ['value' => $value, 'expiresAt' => ($this->clock)() + $ttlSeconds];
    }

    public function clear(): void
    {
        $this->items = [];
    }

    public function __serialize(): array
    {
        return ['items' => $this->items];
    }

    /**
     * @param array{items?: array<string, array{value: mixed, expiresAt: int}>} $data
     */
    public function __unserialize(array $data): void
    {
        $this->items = $data['items'] ?? [];
        $this->clock = static fn (): int => time();
    }
}
