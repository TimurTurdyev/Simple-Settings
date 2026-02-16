<?php

namespace TimurTurdyev\SimpleSettings\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SimpleSetting extends Model
{
    protected $table = 'simple_settings';
    protected $guarded = ['id'];
    protected $fillable = ['group', 'name', 'val', 'type', 'created_at', 'updated_at'];

    protected $casts = [
        'group' => 'string',
        'name' => 'string',
        'val' => 'string',
        'type' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('simple-settings.table_name', $this->table);
    }

    public function getValueAttribute(): mixed
    {
        return $this->castValue($this->attributes['val'], $this->attributes['type']);
    }

    #[Scope]
    public function group(Builder $query, string $name): Builder
    {
        return $query->where('group', $name);
    }

    private function castValue(string|null $val, string $castTo): mixed
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
}