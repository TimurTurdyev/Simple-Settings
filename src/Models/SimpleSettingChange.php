<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleSettings\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SimpleSettingChange extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'simple_setting_changes';

    protected $fillable = [
        'group',
        'name',
        'event',
        'old_payload',
        'new_payload',
        'causer_type',
        'causer_id',
    ];

    protected $casts = [
        'old_payload' => 'array',
        'new_payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('simple-settings.audit.table', $this->table);
    }

    #[Scope]
    public function forSetting(Builder $query, string $group, string $name): Builder
    {
        return $query->where('group', $group)->where('name', $name);
    }

    #[Scope]
    public function event(Builder $query, string $event): Builder
    {
        return $query->where('event', $event);
    }
}
