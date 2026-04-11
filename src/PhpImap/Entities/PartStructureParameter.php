<?php

declare(strict_types=1);

namespace PhpImap\Entities;

final readonly class PartStructureParameter
{
    public function __construct(
        public string $attribute,
        public string $value,
    ) {}

    public static function fromStdClass(\stdClass $obj): self
    {
        return new self(
            attribute: isset($obj->attribute) && \is_string($obj->attribute) ? $obj->attribute : '',
            value: isset($obj->value) && \is_string($obj->value) ? $obj->value : '',
        );
    }
}
