<?php

declare(strict_types=1);

namespace Domotron\CloudAuth;

/**
 * An SSO cookie the application has to send. The library never calls setcookie() itself; the
 * application passes this to its HTTP layer (e.g. Nette\Http\Response::setCookie()).
 *
 * A deletion ({@see isDeletion()}) must go through the framework's delete call (or a setcookie()
 * with an expiry in the past); passing its expiresAt of 0 to setcookie() would create a session
 * cookie with an empty value instead.
 */
final readonly class Cookie
{
    public function __construct(
        public string $value,
        /** Unix timestamp; 0 deletes the cookie */
        public int $expiresAt,
        /** null = host-only cookie (no Domain attribute) */
        public ?string $domain,
        public bool $secure = false,
        public string $name = Contract::COOKIE_NAME,
        public string $path = Contract::COOKIE_PATH,
        public bool $httpOnly = true,
    ) {
    }

    /**
     * Cookie carrying a session key, valid until the given timestamp.
     */
    public static function forSession(string $sessionKey, int $expiresAt, string $host, bool $secure = false): self
    {
        return new self($sessionKey, $expiresAt, Contract::cookieDomain($host), $secure);
    }

    /**
     * Cookie that removes the session cookie from the browser.
     */
    public static function deletion(string $host, bool $secure = false): self
    {
        return new self('', 0, Contract::cookieDomain($host), $secure);
    }

    public function isDeletion(): bool
    {
        return $this->expiresAt === 0;
    }

    /**
     * The expiry as a date object (UTC), for HTTP layers that no longer take a Unix timestamp
     * (Nette\Http\Response::setCookie() since 3.1). Null for a deletion cookie.
     */
    public function expires(): ?\DateTimeImmutable
    {
        if ($this->isDeletion()) {
            return null;
        }

        return new \DateTimeImmutable('@' . $this->expiresAt);
    }
}
