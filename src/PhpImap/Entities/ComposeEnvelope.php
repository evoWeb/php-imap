<?php

declare(strict_types=1);

namespace PhpImap\Entities;

class ComposeEnvelope
{
    public function __construct(
        public ?string $subject = null,
    ) {}

    /**
     * @param array<string, string> $parameters
     */
    public static function fromArray(array $parameters): self
    {
        return new self($parameters['subject'] ?? '');
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
        ];
    }
}
