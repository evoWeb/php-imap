<?php

declare(strict_types=1);

namespace PhpImap\Entities;

trait StdClassPropertyTrait
{
    private static function boolProperty(\stdClass $obj, string $key): bool
    {
        return (bool)($obj->$key ?? false);
    }

    private static function intProperty(\stdClass $obj, string $key, int $default = 0): int
    {
        return isset($obj->$key) && \is_int($obj->$key) ? $obj->$key : $default;
    }

    private static function nullableIntProperty(\stdClass $obj, string $key): ?int
    {
        return isset($obj->$key) && \is_int($obj->$key) ? $obj->$key : null;
    }

    private static function stringProperty(\stdClass $obj, string $key, string $default = ''): string
    {
        return isset($obj->$key) && \is_string($obj->$key) ? $obj->$key : $default;
    }

    private static function nullableStringProperty(\stdClass $obj, string $key): ?string
    {
        return isset($obj->$key) && \is_string($obj->$key) ? $obj->$key : null;
    }
}
