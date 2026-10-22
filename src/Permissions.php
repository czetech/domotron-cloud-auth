<?php

declare(strict_types=1);

namespace Domotron\CloudAuth;

/**
 * Resolved permissions for one key, e.g. {"cloud:project": ["read"], "control:global": ["access"]}.
 * For control-* keys the Cloud omits the module prefix: {"global": ["access"]}.
 */
final readonly class Permissions
{
    /**
     * @param array<string, list<string>> $byResource
     */
    private function __construct(private array $byResource)
    {
    }

    /**
     * @param mixed $data resource => list of actions, as the API returns it; anything that is not
     *                    an array (a missing key, null, a malformed answer) means no permissions
     */
    public static function fromArray(mixed $data): self
    {
        if (!is_array($data)) {
            return self::none();
        }

        $byResource = [];
        foreach ($data as $resource => $actions) {
            $byResource[(string) $resource] = array_values(array_map('strval', is_array($actions) ? $actions : []));
        }

        return new self($byResource);
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @param string|null $privilege null = any privilege on the resource
     */
    public function isAllowed(string $resource, ?string $privilege = null): bool
    {
        if (!isset($this->byResource[$resource])) {
            return false;
        }

        return $privilege === null || $privilege === '' || in_array($privilege, $this->byResource[$resource], true);
    }

    public function isEmpty(): bool
    {
        return $this->byResource === [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function toArray(): array
    {
        return $this->byResource;
    }
}
