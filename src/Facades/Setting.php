<?php

namespace TimurTurdyev\SimpleSettings\Facades;

use Illuminate\Support\Facades\Facade;
use TimurTurdyev\SimpleSettings\Contracts\SettingStorageInterface;

/**
 * @method static mixed get(string $key, mixed $default = null, bool $fresh = false)
 * @method static \Illuminate\Support\Collection all(bool $fresh = false)
 * @method static mixed set(string|array $key, mixed $val = null)
 * @method static bool has(string $key)
 * @method static int remove(?string $key = null)
 * @method static bool flushCache()
 * @method static self group(string $group)
 * @method static self forGroup(string $group)
 * @method static self withEvents()
 * @method static self withoutEvents()
 *
 * @see \TimurTurdyev\SimpleSettings\Contracts\SettingStorageInterface
 */
class Setting extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingStorageInterface::class;
    }
}
