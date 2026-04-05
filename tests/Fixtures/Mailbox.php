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

    public function exposedGetCombinedPath(string $folder, bool $absolute = false): string
    {
        return $this->getCombinedPath($folder, $absolute);
    }

    /**
     * @throws \Exception
     */
    public function exposedDecodeRFC2231(string $string): string
    {
        return $this->decodeRFC2231($string);
    }

    public function exposedLowercaseMbListEncodings(): array
    {
        return $this->lowercaseMbListEncodings();
    }

    /**
     * @throws \Exception
     */
    public function exposedPossiblyGetEmailAndNameFromRecipient(object $recipient): ?array
    {
        return $this->possiblyGetEmailAndNameFromRecipient($recipient);
    }

    /**
     * @throws \Exception
     */
    public function exposedPossiblyGetHostNameAndAddress(array $t): array
    {
        return $this->possiblyGetHostNameAndAddress($t);
    }
}
