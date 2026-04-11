<?php

declare(strict_types=1);

namespace PhpImap;

use PhpImap\Exceptions\ConnectionException;

/**
 * @see https://github.com/barbushin/php-imap
 *
 * @author nickl- http://github.com/nickl-
 */
class DataPartInfo
{
    public const TEXT_PLAIN = 0;

    public const TEXT_HTML = 1;

    public ?string $charset;

    protected bool|string|null $data = null;

    public function __construct(
        public readonly Mailbox $mail,
        public readonly int $id,
        public readonly string|int $part,
        public readonly mixed $encoding,
        public readonly int $options
    ) {}

    /**
     * @throws ConnectionException
     */
    public function fetch(): string
    {
        if ($this->part === 0) {
            $this->data = Imap::body($this->mail->getImapStream(), $this->id, $this->options);
        } else {
            if ($this->data !== null) {
                return is_string($this->data) ? $this->data : '';
            }
            $this->data = Imap::fetchBody($this->mail->getImapStream(), $this->id, $this->part, $this->options);
        }

        return $this->decodeAfterFetch($this->data);
    }

    public function decodeAfterFetch(string $data): string
    {
        $this->data = match ($this->encoding) {
            \ENCBINARY => \imap_binary($data),
            \ENCBASE64 => \base64_decode($data),
            \ENCQUOTEDPRINTABLE => \quoted_printable_decode($data),
            // also responsible for \ENC8BIT
            default => \imap_utf8($data),
        };

        return $this->convertEncodingAfterFetch();
    }

    protected function convertEncodingAfterFetch(): string
    {
        if (isset($this->charset) && !empty(\trim($this->charset))) {
            $this->data = $this->mail->decodeMimeStr(
                (string)$this->data // Data to convert
            );

            $this->data = $this->mail->convertToUtf8($this->data, $this->charset);
        }

        return is_string($this->data) ? $this->data : '';
    }
}
