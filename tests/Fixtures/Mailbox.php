<?php

declare(strict_types=1);

namespace PhpImap\Tests\Fixtures;

use PhpImap\Entities\HostnameAndAddress;
use PhpImap\Entities\PartStructure;
use PhpImap\IncomingMail;
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

    /**
     * @throws \Exception
     */
    public function exposeInitMailPart(
        IncomingMail $mail,
        PartStructure $partStructure,
        string|int $partNumber,
        bool $markAsSeen = true
    ): void {
        $this->initMailPart($mail, $partStructure, $partNumber, $markAsSeen);
    }

    /**
     * @return string[]
     */
    public function exposedLowercaseMbListEncodings(): array
    {
        return $this->lowercaseMbListEncodings();
    }

    /**
     * @phpstan-return array{0: string, 1: null|string}|null
     *
     * @throws \Exception
     */
    public function exposedPossiblyGetEmailAndNameFromRecipient(HostnameAndAddress $recipient): ?array
    {
        return $this->possiblyGetEmailAndNameFromRecipient($recipient);
    }

    /**
     * @phpstan-param array{0: HostnameAndAddress, 1?: HostnameAndAddress} $mailboxes
     *
     * @phpstan-return array{0: string|null, 1: string|null, 2: string}
     *
     * @throws \Exception
     */
    public function exposedPossiblyGetHostNameAndAddress(array $mailboxes): array
    {
        return $this->possiblyGetHostNameAndAddress($mailboxes);
    }

    /**
     * @param HostnameAndAddress[] $recipients
     *
     * @return array{0: array<string, string|null>, 1: string}
     *
     * @throws \Exception
     */
    public function exposeParseRecipientList(array $recipients): array
    {
        return $this->parseRecipientList($recipients);
    }
}
