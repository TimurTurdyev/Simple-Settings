<?php

namespace TimurTurdyev\SimpleSettings;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use TimurTurdyev\SimpleSettings\Models\SimpleSetting;

final class SettingStorage
{
    protected string $cacheKey = 'simple_settings';

    public function __construct(
        protected string $group = 'global',
    )
    {
        if ($cacheKey = config('simple-settings.path_cache_key')) {
            $this->cacheKey = $cacheKey;
        }
    }

    public function get(string $key, bool|int|float|array|string|null|object $default = null, bool $fresh = false)
    {
        return $this->all($fresh)->get($key, $default);
    }

    public function all(bool $fresh = false): Collection
    {
        if ($fresh) {
            return $this->getMapWithKeys();
        }

        return Cache::memo()->rememberForever($this->getCacheKey(), function () {
            return $this->getMapWithKeys();
        });
    }

    public function set(array|string $key, bool|int|float|array|string|null|object $val = null): float|object|int|bool|array|string|null
    {
        if (is_array($key)) {
            foreach ($key as $name => $value) {
                $this->set($name, $value);
            }

            return true;
        }

        $setting = $this
            ->modelQuery()
            ->firstOrNew([
                'name' => $key,
            ]);

        $type = strtolower(gettype($val));

        $setting->group = $this->group;
        $setting->name = $key;
        $setting->val = $this->valueToString($val, $type);
        $setting->type = $type;

        $setting->updateTimestamps();

        $setting->save();

        $this->flushCache();

        return $val;
    }

    public function flushCache(): bool
    {
        return Cache::forget($this->getCacheKey());
    }

    public function has(string $key): bool
    {
        return $this->all()->has($key);
    }

    public function remove(string $key = null)
    {
        $deleted = $this->modelQuery()
            ->when(!is_null($key), static fn($query) => $query->where('name', $key))
            ->delete();

        $this->flushCache();

        return $deleted;
    }

    private function getMapWithKeys(): Collection
    {
        return $this->modelQuery()
            ->get(['val', 'name', 'type'])
            ->map(function (SimpleSetting $setting) {
                $item = $setting->toArray();
                $item['val'] = $this->castValue($item['val'], $item['type']);
                return $item;
            })->mapWithKeys(static fn($item) => [$item['name'] => $item['val']]);
    }

    private function modelQuery(): Builder
    {
        return SimpleSetting::query()->group($this->group);
    }

    public function group(string $group): self
    {
        $this->group = $group;

        return $this;
    }

    private static function castValue(bool|int|float|array|string|null|object $val, string $castTo): bool|int|float|array|string|null|object
    {
        return match ($castTo) {
            'integer' => (int)$val,
            'boolean' => (bool)$val,
            'array' => json_decode($val, true),
            'double' => (float)$val,
            'object' => json_decode($val, false),
            'null' => null,
            default => (string)$val,
        };
    }

    private function getCacheKey(): string
    {
        return $this->cacheKey . '.' . $this->group;
    }

    private function valueToString(bool|int|float|array|string|null|object $val, string $type): string
    {
        return match ($type) {
            'array' => (string)json_encode($val, true),
            'object' => (string)json_encode($val),
            default => (string)$val,
        };
    }
}