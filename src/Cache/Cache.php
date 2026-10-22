<?php

declare(strict_types=1);

namespace Domotron\CloudAuth\Cache;

/**
 * Per-user cache for identity and permissions. Applications back it with their session
 * (one cache per browser session); {@see ArrayCache} is the in-memory reference implementation.
 */
interface Cache
{
    /**
     * @return mixed null when absent or expired
     */
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $ttlSeconds): void;

    /**
     * Drop everything this cache holds (logout).
     */
    public function clear(): void;
}
