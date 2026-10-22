<?php

declare(strict_types=1);

namespace Domotron\CloudAuth;

/**
 * The single sign-on contract between Domotron Cloud (the issuer) and every Control instance
 * (the consumers). Anything that both sides must agree on lives here and nowhere else.
 *
 *  - Cloud stores the SSO session as a random key; the browser carries that key in the
 *    {@see COOKIE_NAME} cookie, scoped to the shared second-level domain so that
 *    cloud.domotron.com and *.control.domotron.com see the same cookie.
 *  - Over the API the session is never referenced by its key but by {@see sessionHash()} of it
 *    (routes v1/user/session/<hash>).
 *  - Identity data is the "result" object of GET v1/user/<id>; permissions are the "result" object
 *    of GET v1/user/permissions/<id>?key[]=…, a map of key => {module:resource => [action, …]}.
 */
final class Contract
{
    public const COOKIE_NAME = 'domotron-gw';

    public const COOKIE_PATH = '/';

    /** Permission key for global (unscoped) permissions */
    public const KEY_GLOBAL = 'global';

    /**
     * Domain attribute of the SSO cookie for the given request host: the last two labels, with a
     * leading dot (".domotron.com" for "cloud.domotron.com"). A port is ignored. Hosts with fewer
     * than two labels (localhost), IP addresses and an empty host yield null, meaning a host-only
     * cookie (what frameworks expect for "no domain attribute").
     */
    public static function cookieDomain(string $host): ?string
    {
        $host = strtolower((string) preg_replace('/:\d+$/', '', trim($host)));
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        $labels = explode('.', $host);
        if (count($labels) < 2) {
            return null;
        }

        return '.' . implode('.', array_slice($labels, -2));
    }

    /**
     * The value that identifies a session on the API: sha1 of the cookie value.
     */
    public static function sessionHash(string $sessionKey): string
    {
        return sha1($sessionKey);
    }

    /**
     * Permission key for a Control instance identified by its global host identifier.
     */
    public static function controlKey(string $hostIdentifier): string
    {
        return 'control-' . $hostIdentifier;
    }

    /**
     * Permission key for a project.
     */
    public static function projectKey(int $projectId): string
    {
        return 'project-' . $projectId;
    }
}
