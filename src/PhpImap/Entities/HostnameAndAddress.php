<?php

declare(strict_types=1);

namespace PhpImap\Entities;

final readonly class HostnameAndAddress
{
    public function __construct(
        public string $mailbox,
        public ?string $host = null,
        public ?string $personal = null,
    ) {}

    public static function fromStdClass(\stdClass $obj): self
    {
        return new self(
            mailbox: isset($obj->mailbox) && \is_string($obj->mailbox) ? $obj->mailbox : '',
            host: isset($obj->host) && \is_string($obj->host) ? $obj->host : null,
            personal: isset($obj->personal) && \is_string($obj->personal) ? $obj->personal : null,
        );
    }
}
