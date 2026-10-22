<?php

declare(strict_types=1);

namespace Domotron\CloudAuth;

use Domotron\CloudAuth\Cache\Cache;
use Domotron\CloudAuth\Exception\CloudAuthException;

/**
 * The current end user of a Control instance, resolved from the SSO cookie through the Cloud API
 * and cached per browser session.
 *
 * The library stays out of PHP's request globals: the application passes the cookie value in and
 * sends out whatever {@see cookieToSet()} returns (a fresh session extension or a deletion).
 */
final class CloudUser
{
    private bool $initialized = false;

    private ?Identity $identity = null;

    private ?Cookie $cookieToSet = null;

    /** @var array<string, Permissions> permissions resolved in this request, by real key */
    private array $permissions = [];

    /**
     * @param string|null $sessionKey value of the SSO cookie, null when the browser sent none
     * @param string $host request host, used for the domain of cookies to set
     * @param array<string, string> $keyAliases alias => real key; "%alias" in permission calls resolves through it
     * @param bool $active false for installations without Cloud: nobody is logged in and
     *                     permissionsIsAllowed() returns its $inactiveDefault
     */
    public function __construct(
        private readonly CloudApi $api,
        private readonly Cache $cache,
        private readonly ?string $sessionKey,
        private readonly string $host,
        private readonly array $keyAliases = [],
        private readonly int $identityTtl = 600,
        private readonly int $permissionsTtl = 600,
        private readonly bool $active = true,
        private readonly bool $secureCookies = false,
    ) {
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getSessionKey(): ?string
    {
        return $this->sessionKey;
    }

    public function isLoggedIn(): bool
    {
        $this->init();
        return $this->identity !== null;
    }

    public function getIdentity(): ?Identity
    {
        $this->init();
        return $this->identity;
    }

    /**
     * @param string $key "global", "project-<id>", "control-<identifier>" or "%alias"
     * @return Permissions|null null when nobody is logged in
     */
    public function permissions(string $key): ?Permissions
    {
        $this->init();
        if ($this->identity === null) {
            return null;
        }

        $key = $this->resolveKey($key);
        if (isset($this->permissions[$key])) {
            return $this->permissions[$key];
        }

        $cacheKey = $this->cacheKey('permissions', $key);
        $cached = $this->cache->get($cacheKey);
        if ($cached instanceof Permissions) {
            return $this->permissions[$key] = $cached;
        }

        $fetched = $this->api->permissions($this->identity->getId(), [$key]);
        $permissions = $fetched[$key] ?? Permissions::none();
        $this->cache->set($cacheKey, $permissions, $this->permissionsTtl);

        return $this->permissions[$key] = $permissions;
    }

    /**
     * Shorthand for permissions($key)?->isAllowed($resource, $privilege).
     * @param bool $inactiveDefault answer for installations without Cloud (isActive() false)
     */
    public function permissionsIsAllowed(string $key, string $resource, ?string $privilege = null, bool $inactiveDefault = true): bool
    {
        if (!$this->active) {
            return $inactiveDefault;
        }

        $permissions = $this->permissions($key);
        return $permissions !== null && $permissions->isAllowed($resource, $privilege);
    }

    /**
     * Invalidate the session at the Cloud, forget everything cached, and schedule the cookie
     * deletion for the application to send.
     */
    public function logout(): void
    {
        $this->cache->clear();
        $this->permissions = [];
        $this->identity = null;
        $this->initialized = true;

        if ($this->sessionKey !== null && $this->sessionKey !== '') {
            $this->api->invalidateSession(Contract::sessionHash($this->sessionKey));
        }
        $this->cookieToSet = Cookie::deletion($this->host, $this->secureCookies);
    }

    /**
     * Cookie the application must send with its response, if any: the extended session after a
     * fresh identity fetch, or the deletion after logout().
     */
    public function cookieToSet(): ?Cookie
    {
        return $this->cookieToSet;
    }

    /**
     * URL of the Cloud sign-in page; with portal credentials the Cloud redirects back after login.
     */
    public function getLoginUrl(?string $portalId = null, ?string $portalSecret = null, ?string $redirectUri = null): string
    {
        $base = $this->api->getBaseUrl();
        if ($portalId !== null && $portalSecret !== null && $redirectUri !== null) {
            return $base . '/?' . http_build_query([
                'sso_portal_id' => $portalId,
                'sso_portal_secret' => $portalSecret,
                'sso_redirect_uri' => $redirectUri,
            ]);
        }

        return $base;
    }

    public function getLogoutUrl(): string
    {
        return $this->api->getBaseUrl() . '/sign/out';
    }

    private function init(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;

        if (!$this->active || $this->sessionKey === null || $this->sessionKey === '') {
            return;
        }

        $cacheKey = $this->cacheKey('identity');
        $cached = $this->cache->get($cacheKey);
        if ($cached instanceof Identity) {
            $this->identity = $cached;
            return;
        }

        $hash = Contract::sessionHash($this->sessionKey);
        $userId = $this->api->userIdFromSession($hash);
        if ($userId === null) {
            return;
        }
        $identity = $this->api->identity($userId);
        if ($identity === null) {
            return;
        }

        $this->identity = $identity;
        $this->cache->set($cacheKey, $identity, $this->identityTtl);

        // A freshly resolved session is extended on the Cloud and the cookie re-issued accordingly
        $validUntil = $this->api->extendSession($hash);
        if ($validUntil !== null) {
            $this->cookieToSet = Cookie::forSession($this->sessionKey, $validUntil, $this->host, $this->secureCookies);
        }
    }

    private function resolveKey(string $key): string
    {
        if (!str_starts_with($key, '%')) {
            return $key;
        }
        $alias = substr($key, 1);
        if (!isset($this->keyAliases[$alias])) {
            throw new CloudAuthException("Unknown permission key alias \"$alias\"");
        }

        return $this->keyAliases[$alias];
    }

    private function cacheKey(string $kind, string $suffix = ''): string
    {
        return 'cloud-auth:' . $kind . ':' . Contract::sessionHash((string) $this->sessionKey) . ($suffix !== '' ? ':' . $suffix : '');
    }
}
