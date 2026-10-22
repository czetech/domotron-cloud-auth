<?php

declare(strict_types=1);

namespace Domotron\CloudAuth;

/**
 * The logged-in Cloud user as returned by GET v1/user/<id> ("result" object). Read-only.
 */
final readonly class Identity
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(private array $data)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['id']) || !is_numeric($data['id'])) {
            throw new Exception\CloudAuthException('Identity data must contain a numeric "id"');
        }

        return new self($data);
    }

    public function getId(): int
    {
        return (int) $this->data['id'];
    }

    public function getUsername(): ?string
    {
        return isset($this->data['username']) ? (string) $this->data['username'] : null;
    }

    public function getName(): ?string
    {
        return isset($this->data['name']) ? (string) $this->data['name'] : null;
    }

    public function isDemo(): bool
    {
        return (bool) ($this->data['is_demo'] ?? false);
    }

    /**
     * @return array{id: int, name: string, code: string}|null
     */
    public function getLanguage(): ?array
    {
        $language = $this->data['language'] ?? null;
        if (!is_array($language) || !isset($language['code'])) {
            return null;
        }

        return [
            'id' => (int) ($language['id'] ?? 0),
            'name' => (string) ($language['name'] ?? ''),
            'code' => (string) $language['code'],
        ];
    }

    /**
     * @return array<string, mixed>|null the favorite project block, null when the user has none
     */
    public function getFavoriteProject(): ?array
    {
        $project = $this->data['favorite']['project'] ?? null;
        return is_array($project) && $project !== [] ? $project : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
