<?php

namespace TimurTurdyev\SimpleSettings;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use TimurTurdyev\SimpleSettings\Concerns\CastsValue;
use TimurTurdyev\SimpleSettings\Contracts\SettingStorageInterface;
use TimurTurdyev\SimpleSettings\Events\SettingDeleted;
use TimurTurdyev\SimpleSettings\Events\SettingRetrieved;
use TimurTurdyev\SimpleSettings\Events\SettingSaved;
use TimurTurdyev\SimpleSettings\Models\SimpleSetting;

final class SettingStorage implements SettingStorageInterface
{
    use CastsValue;

    protected string $cacheKey = 'simple_settings';

    public function __construct(
        protected string $group = 'global',
    )
    {
        if ($cacheKey = config('simple-settings.path_cache_key')) {
            $this->cacheKey = $cacheKey;
        }
    }

    public function get(string $key, mixed $default = null, bool $fresh = false): mixed
    {
        $value = $this->all($fresh)->get($key, $default);

        event(new SettingRetrieved($key, $value, $this->group));

        return $value;
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

    public function set(string|array $key, mixed $val = null): mixed
    {
        if (is_array($key)) {
            foreach ($key as $name => $value) {
                $this->set($name, $value);
            }

            return true;
        }

        $this->validate($key, $val);

        $setting = $this
            ->modelQuery()
            ->firstOrNew([
                'name' => $key,
            ]);

        $type = strtolower(gettype($val));

        $setting->group = $this->group;
        $setting->name = $key;
        $setting->val = self::valueToString($val, $type);
        $setting->type = $type;

        $setting->updateTimestamps();

        $setting->save();

        event(new SettingSaved($key, $val, $this->group));

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

    public function remove(string $key = null): int
    {
        if (!is_null($key)) {
            event(new SettingDeleted($key, $this->group));
        }

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
                return [
                    'name' => $setting->name,
                    'val' => self::castValue($setting->val, $setting->type),
                ];
            })->mapWithKeys(fn($item) => [$item['name'] => $item['val']]);
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

    public function forGroup(string $group): self
    {
        return new self($group);
    }

    private function getCacheKey(): string
    {
        return $this->cacheKey . '.' . $this->group;
    }

    private function validate(string $key, mixed $val): void
    {
        $rules = config('simple-settings.validation_rules', []);

        if (isset($rules[$key])) {
            $validator = \Illuminate\Support\Facades\Validator::make(
                ['value' => $val],
                ['value' => $rules[$key]]
            );

            if ($validator->fails()) {
                throw new \InvalidArgumentException(
                    "Validation failed for setting [{$key}]: " .
                    implode(', ', $validator->errors()->all())
                );
            }
        }
    }
}
