# domotron-cloud-auth

The single sign-on contract between **Domotron Cloud** and the **Domotron Control** instances, as a
small framework-free PHP library. It replaces `domotron/cloud-user` and `domotron/cloud-client`
(1.x), keeping only what the two applications use.

- `Contract` — the cookie name and domain rule, the session hash, permission key helpers. Both
  sides use this and nothing else may redefine it.
- `Cookie` — a cookie the application has to send (the library never calls `setcookie()`).
- `Identity`, `Permissions` — read-only value objects for what the Cloud API returns.
- `CloudApi` — the five Cloud calls a Control needs (session lookup / extend / invalidate, user
  data, permissions) over a pluggable `Http\Transport` (`CurlTransport` is included; no Guzzle).
- `CloudUser` — the current user of a Control instance: cookie in, identity and permissions out,
  cached per browser session through a small `Cache\Cache` interface.

Requires PHP 8.4+, `ext-curl`, `ext-json`. No other dependencies.

```sh
composer require czetech/domotron-cloud-auth
composer test
```

## The contract

| Item | Value |
|---|---|
| Cookie | `domotron-gw`, path `/`, `HttpOnly`, domain `.` + last two labels of the host (`.domotron.com`) |
| Cookie value | the Cloud's `sso_session.key` |
| Session reference on the API | `sha1(cookie value)` |
| `GET /api/v1/user/session/<hash>` | `{"status":"ok","result":{"id":<userId>}}`, 404 when unknown or expired |
| `POST /api/v1/user/session/<hash>` | extends; `{"result":{"valid_until":<unix ts>}}` |
| `DELETE /api/v1/user/session/<hash>` | `{"result":"ok"}` |
| `GET /api/v1/user/<id>` | `Authorization: Bearer <api token>`; `result` is the identity |
| `GET /api/v1/user/permissions/<id>?key[]=…` | bearer; `result` maps each key to `{resource: [actions]}` |
| Permission keys | `global`, `project-<id>`, `control-<host identifier>` (the latter answers without the module prefix: `{"global": ["access"]}`) |

The server side of this table is Domotron Cloud's API and is pinned by Cloud's characterization
tests; the client side is this library and is pinned by `tests/`.

## Control (consumer)

```php
use Domotron\CloudAuth\{CloudApi, CloudUser, Contract};
use Domotron\CloudAuth\Http\CurlTransport;

$api = new CloudApi(new CurlTransport(), 'https://cloud.domotron.com', $apiToken);
$user = new CloudUser(
    $api,
    $sessionCache,                                   // Cache\Cache backed by the PHP/Nette session
    $request->getCookie(Contract::COOKIE_NAME),      // string|null
    $request->getUrl()->getHost(),
    ['control' => Contract::controlKey($globalIdentifier)],
    identityTtl: 600,
    permissionsTtl: 600,
    active: $cloudEnabled,
);

if (!$user->isLoggedIn()) {
    redirect($user->getLoginUrl($portalId, $portalSecret, $redirectUri));
}
$user->permissionsIsAllowed('%control', 'global', 'access');
$user->getIdentity()->getName();

// at the end of the request (or in a response listener):
if ($cookie = $user->cookieToSet()) {
    if ($cookie->isDeletion()) {
        $response->deleteCookie($cookie->name, $cookie->path, $cookie->domain);
    } else {
        $response->setCookie($cookie->name, $cookie->value, $cookie->expires(), $cookie->path, $cookie->domain, $cookie->secure, $cookie->httpOnly);
    }
}
```

`Cookie::$domain` is null for a host-only cookie (localhost, IP) and `expires()` is the expiry as a
`DateTimeImmutable` (`expiresAt` keeps the Unix timestamp for plain `setcookie()`).

A session-backed cache is a few lines: keep one `Cache\ArrayCache` in the session (it serializes)
or implement `Cache\Cache` directly on the framework's session section.

`CloudUser` resolves a session once per request: cache hit → no traffic; cache miss → session
lookup, user data, then a session extension whose new validity is handed back as `cookieToSet()`.
A Cloud outage surfaces as `Exception\TransportException` (not reachable) or
`Exception\CloudApiException` (unexpected answer); unknown sessions are simply anonymous.

## Cloud (issuer)

The Cloud issues sessions from its own database and only needs the contract:

```php
use Domotron\CloudAuth\{Contract, Cookie, Permissions};

$cookie = Cookie::forSession($session->key, $session->valid_until->getTimestamp(), $request->getUrl()->getHost());
$hash = Contract::sessionHash($cookieValue);          // to look a session up by its API hash
$allowed = Permissions::fromArray($resolved['project-1'] ?? null)->isAllowed('cloud:project', 'read');
```

`Permissions::fromArray()` takes whatever the resolver returned for a key: a missing key or a
non-array means no permissions. Cloud's characterization suite also drives `CloudApi` and
`CloudUser` from this library against the real API, so a contract change breaks there first.

## Versioning

1.x of this package is a new API; it is not compatible with `domotron/cloud-user` 1.x.
