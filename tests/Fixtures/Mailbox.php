<?php

declare(strict_types=1);

namespace PhpImap\Tests\Fixtures;

use PhpImap\Mailbox as BaseMailbox;

class Mailbox extends BaseMailbox
{
    public function getImapPassword(): string
    {
        return $this->imapPassword;
    }

    public function getImapOptions(): int
    {
        return $this->imapOptions;
    }
}
