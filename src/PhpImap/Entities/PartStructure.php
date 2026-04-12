<?php

declare(strict_types=1);

namespace PhpImap\Entities;

final readonly class PartStructure
{
    use StdClassPropertyTrait;

    /**
     * @param PartStructureParameter[] $parameters
     * @param PartStructureParameter[] $dparameters
     * @param PartStructure[] $parts
     */
    public function __construct(
        public ?int $type = null,
        public ?int $encoding = null,
        public bool $ifsubtype = false,
        public ?string $subtype = null,
        public bool $ifdescription = false,
        public ?string $description = null,
        public bool $ifid = false,
        public ?string $id = null,
        public ?int $lines = null,
        public ?int $bytes = null,
        public bool $ifdisposition = false,
        public ?string $disposition = null,
        public bool $ifdparameters = false,
        public array $dparameters = [],
        public bool $ifparameters = false,
        public array $parameters = [],
        public array $parts = [],
    ) {}

    public static function fromStdClass(\stdClass $obj): self
    {
        return new self(
            type: self::nullableIntProperty($obj, 'type'),
            encoding: self::nullableIntProperty($obj, 'encoding'),
            ifsubtype: self::boolProperty($obj, 'ifsubtype'),
            subtype: self::nullableStringProperty($obj, 'subtype'),
            ifdescription: self::boolProperty($obj, 'ifdescription'),
            description: self::nullableStringProperty($obj, 'description'),
            ifid: self::boolProperty($obj, 'ifid'),
            id: self::nullableStringProperty($obj, 'id'),
            lines: self::nullableIntProperty($obj, 'lines'),
            bytes: self::nullableIntProperty($obj, 'bytes'),
            ifdisposition: self::boolProperty($obj, 'ifdisposition'),
            disposition: self::nullableStringProperty($obj, 'disposition'),
            ifdparameters: self::boolProperty($obj, 'ifdparameters'),
            dparameters: self::prepareDparameters($obj),
            ifparameters: self::boolProperty($obj, 'ifparameters'),
            parameters: self::prepareParameters($obj),
            parts: self::prepareParts($obj),
        );
    }

    /**
     * @return PartStructureParameter[]
     */
    private static function prepareDparameters(\stdClass $obj): array
    {
        $dparameters = [];
        if (isset($obj->dparameters) && \is_array($obj->dparameters)) {
            foreach ($obj->dparameters as $parameter) {
                if ($parameter instanceof \stdClass) {
                    $dparameters[] = PartStructureParameter::fromStdClass($parameter);
                }
            }
        }
        return $dparameters;
    }

    /**
     * @return PartStructureParameter[]
     */
    private static function prepareParameters(\stdClass $obj): array
    {
        $parameters = [];
        if (isset($obj->parameters) && \is_array($obj->parameters)) {
            foreach ($obj->parameters as $parameter) {
                if ($parameter instanceof \stdClass) {
                    $parameters[] = PartStructureParameter::fromStdClass($parameter);
                }
            }
        }
        return $parameters;
    }

    /**
     * @return PartStructure[]
     */
    private static function prepareParts(\stdClass $obj): array
    {
        $parts = [];
        if (isset($obj->parts) && \is_array($obj->parts)) {
            foreach ($obj->parts as $part) {
                if ($part instanceof \stdClass) {
                    $parts[] = self::fromStdClass($part);
                }
            }
        }
        return $parts;
    }

    /**
     * Returns a copy of this instance with an empty parts list.
     * Used by flattenParts() to prevent nested structure in the result.
     */
    public function withEmptyParts(): self
    {
        return new self(
            type: $this->type,
            encoding: $this->encoding,
            ifsubtype: $this->ifsubtype,
            subtype: $this->subtype,
            ifdescription: $this->ifdescription,
            description: $this->description,
            ifid: $this->ifid,
            id: $this->id,
            lines: $this->lines,
            bytes: $this->bytes,
            ifdisposition: $this->ifdisposition,
            disposition: $this->disposition,
            ifdparameters: $this->ifdparameters,
            dparameters: $this->dparameters,
            ifparameters: $this->ifparameters,
            parameters: $this->parameters,
            parts: [],
        );
    }
}
