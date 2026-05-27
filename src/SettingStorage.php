<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleSettings;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use TimurTurdyev\SimpleSettings\Concerns\CastsValue;
use TimurTurdyev\SimpleSettings\Contracts\SettingStorageInterface;
use TimurTurdyev\SimpleSettings\Events\SettingDeleted;
use TimurTurdyev\SimpleSettings\Events\SettingRetrieved;
use TimurTurdyev\SimpleSettings\Events\SettingSaved;
use TimurTurdyev\SimpleSettings\Events\SettingsFlushed;
use TimurTurdyev\SimpleSettings\Models\SimpleSetting;

final class SettingStorage implements SettingStorageInterface
{
    use CastsValue;

    protected string $cacheKey = 'simple_settings';
    protected bool $fireEvents;

    public function __construct(
        protected string $group = 'global',
        ?bool $fireEvents = null,
    ) {
        if ($cacheKey = config('simple-settings.cache_key_prefix')) {
            $this->cacheKey = $cacheKey;
        }

        $this->fireEvents = $fireEvents ?? (bool) config('simple-settings.events', false);
    }

    // -------------------------------------------------------------------------
    // Group selection
    // -------------------------------------------------------------------------

    public function group(string $group): self
    {
        return $this->forGroup($group);
    }

    public function forGroup(string $group): self
    {
        return new self($group, $this->fireEvents);
    }

    // -------------------------------------------------------------------------
    // Events
    // -------------------------------------------------------------------------

    public function withEvents(): self
    {
        return new self($this->group, true);
    }

    public function withoutEvents(): self
    {
        return new self($this->group, false);
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    public function get(string $key, mixed $default = null, bool $fresh = false): mixed
    {
        $value = $this->all($fresh)->get($key, $default);

        if ($this->fireEvents) {
            event(new SettingRetrieved($key, $value, $this->group));
        }

        return $value;
    }

    public function all(bool $fresh = false): Collection
    {
        if ($fresh) {
            return $this->getMapWithKeys();
        }

        $cached = Cache::memo()->rememberForever($this->getCacheKey(), function () {
            return $this->getMapWithKeys()->toArray();
        });

        if (is_array($cached)) {
            return collect($cached);
        }

        $this->flushCache();

        return $this->getMapWithKeys();
    }

    public function has(string $key): bool
    {
        return $this->all()->has($key);
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    public function set(string|array $key, mixed $val = null): void
    {
        if (is_array($key)) {
            foreach ($key as $name => $value) {
                $this->persistSetting($name, $value);
            }
        } else {
            $this->persistSetting($key, $val);
        }

        $this->flushCache();
    }

    public function remove(string $key): int
    {
        $oldValue = $this->captureOldValue($key);

        $deleted = $this->modelQuery()
            ->where('name', $key)
            ->delete();

        if ($this->fireEvents) {
            event(new SettingDeleted($key, $this->group, $oldValue));
        }

        $this->flushCache();

        return $deleted;
    }

    public function removeAll(): int
    {
        $deleted = $this->modelQuery()->delete();

        if ($this->fireEvents) {
            event(new SettingsFlushed($this->group));
        }

        $this->flushCache();

        return $deleted;
    }

    // -------------------------------------------------------------------------
    // Listing
    // -------------------------------------------------------------------------

    public function list(?string $group = null): Collection
    {
        return SimpleSetting::query()
            ->when($group, fn($q) => $q->where('group', $group))
            ->get(['group', 'name', 'val', 'type']);
    }

    public function groups(): array
    {
        return SimpleSetting::query()
            ->distinct()
            ->pluck('group')
            ->all();
    }

    // -------------------------------------------------------------------------
    // Cache
    // -------------------------------------------------------------------------

    public function flushCache(): bool
    {
        return Cache::memo()->forget($this->getCacheKey());
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function persistSetting(string $key, mixed $val): void
    {
        $this->validate($key, $val);

        $type = strtolower(gettype($val));

        if ($type === 'double') {
            $type = 'float';
        }

        $existed = false;
        $oldValue = null;
        if ($this->shouldCaptureOldValue()) {
            $existed = $this->has($key);
            $oldValue = $existed ? $this->get($key) : null;
        }

        SimpleSetting::upsert(
            [[
                'group' => $this->group,
                'name'  => $key,
                'val'   => self::valueToString($val, $type),
                'type'  => $type,
            ]],
            ['group', 'name'],
            ['val', 'type'],
        );

        if ($this->fireEvents) {
            event(new SettingSaved($key, $val, $this->group, $oldValue, $existed));
        }
    }

    private function captureOldValue(string $key): mixed
    {
        if (!$this->shouldCaptureOldValue()) {
            return null;
        }

        return $this->has($key) ? $this->get($key) : null;
    }

    private function shouldCaptureOldValue(): bool
    {
        return $this->fireEvents && (bool) config('simple-settings.audit.enabled', false);
    }

    private function validate(string $key, mixed $val): void
    {
        $rules = config('simple-settings.validation_rules', []);

        if (isset($rules[$key])) {
            $validator = Validator::make(
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

    private function getMapWithKeys(): Collection
    {
        return $this->modelQuery()
            ->get(['val', 'name', 'type'])
            ->mapWithKeys(fn(SimpleSetting $setting) => [
                $setting->name => self::castValue($setting->val, $setting->type),
            ]);
    }

    private function modelQuery(): Builder
    {
        return SimpleSetting::query()->group($this->group);
    }

    private function getCacheKey(): string
    {
        return $this->cacheKey . '.' . $this->group;
    }
}
