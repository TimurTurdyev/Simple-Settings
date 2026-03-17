<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleSettings\Contracts;

interface SettingStorageInterface
{
    public function get(string $key, mixed $default = null, bool $fresh = false): mixed;

    public function all(bool $fresh = false): \Illuminate\Support\Collection;

    public function set(string|array $key, mixed $val = null): void;

    public function has(string $key): bool;

    public function remove(string $key): int;

    public function removeAll(): int;

    public function list(?string $group = null): \Illuminate\Support\Collection;

    public function groups(): array;

    public function flushCache(): bool;

    public function group(string $group): self;

    public function forGroup(string $group): self;

    public function withEvents(): self;

    public function withoutEvents(): self;
}
