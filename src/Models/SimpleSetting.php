<?php

namespace TimurTurdyev\SimpleSettings\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use TimurTurdyev\SimpleSettings\Concerns\CastsValue;

class SimpleSetting extends Model
{
    use CastsValue;

    protected $table = 'simple_settings';
    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = ['group', 'name', 'val', 'type'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('simple-settings.table_name', $this->table);
    }

    #[Scope]
    public function group(Builder $query, string $name): Builder
    {
        return $query->where('group', $name);
    }

    public function getValueAttribute(): mixed
    {
        return self::castValue($this->attributes['val'], $this->attributes['type']);
    }
}
