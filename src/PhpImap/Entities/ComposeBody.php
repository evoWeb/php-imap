<?php

declare(strict_types=1);

namespace PhpImap\Entities;

/**
 * @see https://www.php.net/manual/de/function.imap-mail-compose.php
 *
 * @phpstan-type BodyParameters array{
 *     id?: string,
 *     type?: int,
 *     encoding?: int,
 *     charset?: string,
 *     subtype?: string,
 *     description?: string,
 *     disposition?: array{filename?: string, type?: string},
 *     lines?: int,
 *     bytes?: int,
 *     md5?: string,
 *     'disposition.type'?: string,
 *     'type.parameters'?: array{name?: string},
 *     'contents.data'?: string,
 * }
 */
class ComposeBody
{
    /**
     * @param array{filename?: string, type?: string} $disposition
     * @param array{}|array{name?: string} $typeParameters
     */
    public function __construct(
        public string $id = '',
        public int $type = 0,
        public int $encoding = 0,
        public string $charset = '',
        public string $subtype = '',
        public string $description = '',
        public array $disposition = ['filename' => ''],
        public string $dispositionType = '',
        public array $typeParameters = ['name' => ''],
        public string $contentsData = '',
        public int $lines = 0,
        public int $bytes = 0,
        public string $md5 = '',
    ) {}

    /**
     * @param BodyParameters $parameters
     */
    public static function fromArray(array $parameters): self
    {
        return new self(
            id: $parameters['id'] ?? '',
            type: $parameters['type'] ?? 0,
            encoding: $parameters['encoding'] ?? 0,
            charset: $parameters['charset'] ?? '',
            subtype: $parameters['subtype'] ?? '',
            description: $parameters['description'] ?? '',
            disposition: ['filename' => (string)($parameters['disposition']['filename'] ?? '')],
            dispositionType: $parameters['disposition.type'] ?? '',
            typeParameters: $parameters['type.parameters'] ?? [],
            contentsData: $parameters['contents.data'] ?? '',
            lines: $parameters['lines'] ?? 0,
            bytes: $parameters['bytes'] ?? 0,
            md5: $parameters['md5'] ?? '',
        );
    }

    /**
     * @return BodyParameters
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'type' => $this->type,
            'encoding' => $this->encoding,
            'charset' => $this->charset,
            'subtype' => $this->subtype,
            'description' => $this->description,
            'disposition' => $this->disposition,
            'disposition.type' => $this->dispositionType,
            'type.parameters' => $this->typeParameters,
            'contents.data' => $this->contentsData,
            'lines' => $this->lines,
            'bytes' => $this->bytes,
            'md5' => $this->md5,
        ];

        return $this->filterOutEmptyValues($data);
    }

    /**
     * @param BodyParameters $data
     *
     * @return BodyParameters
     */
    private function filterOutEmptyValues(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\in_array($key, ['type'])) {
                continue;
            }
            if ($value === '' || $value === 0 || $value === []) {
                unset($data[$key]);
            } elseif (\is_array($value)) {
                $filteredValues = \array_filter($value, static fn(string $valueItem): bool => $valueItem !== '');
                if ($filteredValues === []) {
                    unset($data[$key]);
                } else {
                    $data[$key] = $filteredValues;
                }
            }
        }
        /** @var BodyParameters $data */
        return $data;
    }
}
