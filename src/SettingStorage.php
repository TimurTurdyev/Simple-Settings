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
use TimurTurdyev\SimpleSettings\Events\SettingSaved;
use TimurTurdyev\SimpleSettings\Events\SettingsFlushed;
use TimurTurdyev\SimpleSettings\Models\SimpleSetting;
use TimurTurdyev\SimpleSettings\Models\SimpleSettingChange;

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
        return $this->all($fresh)->get($key, $default);
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
        $pairs = is_array($key) ? $key : [$key => $val];

        if ($pairs === []) {
            return;
        }

        $this->persistSettings($pairs);

        $this->flushCache();
    }

    public function remove(string $key): int
    {
        $oldValue = $this->captureOldValue($key);

        $deleted = $this->modelQuery()
            ->where('name', $key)
            ->delete();

        if ($deleted === 0) {
            return 0;
        }

        if ($this->fireEvents) {
            event(new SettingDeleted($key, $this->group, $oldValue));
        }

        if ($this->auditEnabled()) {
            $this->recordAudit($key, 'deleted', $oldValue, null);
        }

        $this->flushCache();

        return $deleted;
    }

    public function removeAll(): int
    {
        $deleted = $this->modelQuery()->delete();

        if ($deleted === 0) {
            return 0;
        }

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

    private function persistSettings(array $pairs): void
    {
        foreach ($pairs as $name => $value) {
            $this->validate((string) $name, $value);
        }

        $previous = [];
        if ($this->shouldCaptureOld()) {
            $current = $this->all();
            foreach ($pairs as $name => $value) {
                $previous[$name] = [
                    'existed'  => $current->has((string) $name),
                    'oldValue' => $current->get((string) $name),
                ];
            }
        }

        $rows = [];
        foreach ($pairs as $name => $value) {
            $type = self::normalizeType(gettype($value));

            $rows[] = [
                'group' => $this->group,
                'name'  => (string) $name,
                'val'   => self::valueToString($value, $type),
                'type'  => $type,
            ];
        }

        SimpleSetting::upsert($rows, ['group', 'name'], ['val', 'type']);

        foreach ($pairs as $name => $value) {
            $existed = $previous[$name]['existed'] ?? false;
            $oldValue = $previous[$name]['oldValue'] ?? null;

            if ($this->fireEvents) {
                event(new SettingSaved((string) $name, $value, $this->group, $oldValue, $existed));
            }

            if ($this->auditEnabled()) {
                $this->recordAudit((string) $name, $existed ? 'updated' : 'created', $oldValue, $value);
            }
        }
    }

    private function auditEnabled(): bool
    {
        return (bool) config('simple-settings.audit.enabled', false);
    }

    private function recordAudit(string $key, string $event, mixed $oldValue, mixed $newValue): void
    {
        SimpleSettingChange::create([
            'group'       => $this->group,
            'name'        => $key,
            'event'       => $event,
            'old_payload' => $oldValue,
            'new_payload' => $newValue,
            'causer_type' => auth()->user()?->getMorphClass(),
            'causer_id'   => auth()->user()?->getKey(),
        ]);
    }

    private function captureOldValue(string $key): mixed
    {
        if (!$this->shouldCaptureOld()) {
            return null;
        }

        return $this->all()->get($key);
    }

    private function shouldCaptureOld(): bool
    {
        return $this->fireEvents || $this->auditEnabled();
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
