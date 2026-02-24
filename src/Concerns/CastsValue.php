<?php

namespace TimurTurdyev\SimpleSettings\Concerns;

trait CastsValue
{
    public static function valueToString(mixed $val, string $type): string
    {
        return match ($type) {
            'array', 'object' => json_encode($val, JSON_THROW_ON_ERROR),
            default => (string)$val,
        };
    }

    public static function castValue(string|null $val, string $castTo): mixed
    {
        return match ($castTo) {
            'integer' => (int)$val,
            'double'  => (float)$val,
            'boolean' => (bool)$val,
            'array'   => json_decode($val, true),
            'object'  => json_decode($val, false),
            'null'    => null,
            default   => (string)$val,
        };
    }
}
