<?php

declare(strict_types=1);

namespace PhpImap\Entities;

final readonly class PartStructure
{
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
        $parameters = [];
        if (isset($obj->parameters) && \is_array($obj->parameters)) {
            foreach ($obj->parameters as $parameter) {
                if ($parameter instanceof \stdClass) {
                    $parameters[] = PartStructureParameter::fromStdClass($parameter);
                }
            }
        }

        $dparameters = [];
        if (isset($obj->dparameters) && \is_array($obj->dparameters)) {
            foreach ($obj->dparameters as $parameter) {
                if ($parameter instanceof \stdClass) {
                    $dparameters[] = PartStructureParameter::fromStdClass($parameter);
                }
            }
        }

        $parts = [];
        if (isset($obj->parts) && \is_array($obj->parts)) {
            foreach ($obj->parts as $part) {
                if ($part instanceof \stdClass) {
                    $parts[] = self::fromStdClass($part);
                }
            }
        }

        return new self(
            type: isset($obj->type) && \is_int($obj->type) ? $obj->type : null,
            encoding: isset($obj->encoding) && \is_int($obj->encoding) ? $obj->encoding : null,
            ifsubtype: (bool)($obj->ifsubtype ?? false),
            subtype: isset($obj->subtype) && \is_string($obj->subtype) ? $obj->subtype : null,
            ifdescription: (bool)($obj->ifdescription ?? false),
            description: isset($obj->description) && \is_string($obj->description) ? $obj->description : null,
            ifid: (bool)($obj->ifid ?? false),
            id: isset($obj->id) && \is_string($obj->id) ? $obj->id : null,
            lines: isset($obj->lines) && \is_int($obj->lines) ? $obj->lines : null,
            bytes: isset($obj->bytes) && \is_int($obj->bytes) ? $obj->bytes : null,
            ifdisposition: (bool)($obj->ifdisposition ?? false),
            disposition: isset($obj->disposition) && \is_string($obj->disposition) ? $obj->disposition : null,
            ifdparameters: (bool)($obj->ifdparameters ?? false),
            dparameters: $dparameters,
            ifparameters: (bool)($obj->ifparameters ?? false),
            parameters: $parameters,
            parts: $parts,
        );
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
