<?php

namespace TimurTurdyev\SimpleSettings\Contracts;

interface SettingStorageInterface
{
    public function get(string $key, mixed $default = null, bool $fresh = false): mixed;

    public function all(bool $fresh = false): \Illuminate\Support\Collection;

    public function set(string|array $key, mixed $val = null): mixed;

    public function has(string $key): bool;

    public function remove(?string $key = null): int;

    public function flushCache(): bool;

    public function group(string $group): self;

    public function forGroup(string $group): self;
}
