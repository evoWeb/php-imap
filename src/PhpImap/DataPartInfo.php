<?php

declare(strict_types=1);

namespace PhpImap;

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

    protected ?string $data = null;

    public function __construct(
        public readonly Mailbox $mail,
        public readonly int $id,
        public readonly string|int $part,
        public readonly mixed $encoding,
        public readonly int $options
    ) {
    }

    public function fetch(): string
    {
        if ($this->part === 0) {
            $this->data = Imap::body($this->mail->getImapStream(), $this->id, $this->options);
        } else {
            if ($this->data !== null) {
                return $this->data;
            }
            $this->data = Imap::fetchbody($this->mail->getImapStream(), $this->id, $this->part, $this->options);
        }

        return $this->decodeAfterFetch($this->data);
    }

    public function decodeAfterFetch(string $data): string
    {
        switch ($this->encoding) {
            case \ENC8BIT:
                $this->data = \imap_utf8($data);
                break;
            case \ENCBINARY:
                $this->data = \imap_binary($data);
                break;
            case \ENCBASE64:
                $this->data = \base64_decode($data);
                break;
            case \ENCQUOTEDPRINTABLE:
                $this->data = \quoted_printable_decode($data);
                break;
        }

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

        return ($this->data === null) ? '' : $this->data;
    }
}
