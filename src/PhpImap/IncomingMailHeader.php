<?php

declare(strict_types=1);

namespace PhpImap;

/**
 * @see https://github.com/barbushin/php-imap
 *
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 */
class IncomingMailHeader
{
    /**
     * The IMAP message ID - not the "Message-ID:"-header of the email
     */
    public ?int $id;

    public ?string $imapPath;

    public ?string $mailboxFolder;

    public bool $isSeen = false;

    public bool $isAnswered = false;

    public bool $isRecent = false;

    public bool $isFlagged = false;

    public bool $isDeleted = false;

    public bool $isDraft = false;

    public ?string $date;

    public ?string $headersRaw;

    public ?object $headers;

    public ?string $mimeVersion;

    public ?string $xVirusScanned;

    public ?string $organization;

    public ?string $contentType;

    public ?string $xMailer;

    public ?string $contentLanguage;

    public ?string $xSenderIp;

    public ?string $priority;

    public ?string $importance;

    public ?string $sensitivity;

    public ?string $autoSubmitted;

    public ?string $precedence;

    public ?string $failedRecipients;

    public ?string $subject;

    public ?string $fromHost;

    public ?string $fromName;

    public ?string $fromAddress;

    public ?string $senderHost;

    public ?string $senderName;

    public ?string $senderAddress;

    public ?string $xOriginalTo;

    /**
     * @var (string|null)[]
     *
     * @phpstan-var array<string, string|null>
     */
    public array $to = [];

    public ?string $toString;

    /**
     * @var (string|null)[]
     *
     * @phpstan-var array<string, string|null>
     */
    public array $cc = [];

    public ?string $ccString;

    /**
     * @var (string|null)[]
     *
     * @phpstan-var array<string, string|null>
     */
    public array $bcc = [];

    /**
     * @var (string|null)[]
     *
     * @phpstan-var array<string, string|null>
     */
    public array $replyTo = [];

    public ?string $messageId;
}
