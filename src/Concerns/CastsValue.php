<?php

declare(strict_types=1);

namespace TimurTurdyev\SimpleSettings\Concerns;

trait CastsValue
{
    /**
     * PHP's gettype() returns 'double'; the package stores 'float'.
     * Reading must keep accepting 'double' forever: rows written by old
     * versions and setting:export files may still carry it.
     *
     * The trim() is required, not cosmetic: the type column was historically
     * char(20), and PostgreSQL (bpchar) returns such values padded with
     * trailing spaces — 'array               '. MySQL strips them on select,
     * PostgreSQL does not, so without trim() every value falls through to the
     * default branch and is read back as a string. The column is varchar now,
     * but installs created before that migration still have char, and export
     * files written from them carry the padding too.
     */
    public static function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));

        return $type === 'double' ? 'float' : $type;
    }

    public static function valueToString(mixed $val, string $type): string
    {
        return match (self::normalizeType($type)) {
            'array', 'object' => json_encode($val, JSON_THROW_ON_ERROR),
            default => (string)$val,
        };
    }

    public static function castValue(string|null $val, string $castTo): mixed
    {
        return match (self::normalizeType($castTo)) {
            'integer' => (int)$val,
            'float'   => (float)$val,
            'boolean' => (bool)$val,
            'array'   => json_decode($val, true),
            'object'  => json_decode($val, false),
            'null'    => null,
            default   => (string)$val,
        };
    }
}
