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

    #[Scope]
    public function group(Builder $query, string $name): Builder
    {
        return $query->where('group', $name);
    }
}