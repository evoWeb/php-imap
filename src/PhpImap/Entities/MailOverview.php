<?php

declare(strict_types=1);

namespace PhpImap\Entities;

final readonly class MailOverview
{
    use StdClassPropertyTrait;

    public function __construct(
        public ?string $subject,
        public ?string $from,
        public ?string $to,
        public string $date,
        public string $message_id,
        public ?string $references,
        public ?string $in_reply_to,
        public int $size,
        public int $uid,
        public int $msgno,
        public int $recent,
        public int $flagged,
        public int $answered,
        public int $deleted,
        public int $seen,
        public int $draft,
        public int $udate,
    ) {}

    public static function fromStdClass(\stdClass $obj): self
    {
        return new self(
            subject: self::nullableStringProperty($obj, 'subject'),
            from: self::nullableStringProperty($obj, 'from'),
            to: self::nullableStringProperty($obj, 'to'),
            date: self::stringProperty($obj, 'date'),
            message_id: self::stringProperty($obj, 'message_id'),
            references: self::nullableStringProperty($obj, 'references'),
            in_reply_to: self::nullableStringProperty($obj, 'in_reply_to'),
            size: self::intProperty($obj, 'size'),
            uid: self::intProperty($obj, 'uid'),
            msgno: self::intProperty($obj, 'msgno'),
            recent: self::intProperty($obj, 'recent'),
            flagged: self::intProperty($obj, 'flagged'),
            answered: self::intProperty($obj, 'answered'),
            deleted: self::intProperty($obj, 'deleted'),
            seen: self::intProperty($obj, 'seen'),
            draft: self::intProperty($obj, 'draft'),
            udate: self::intProperty($obj, 'udate'),
        );
    }

    /**
     * Returns a new instance with MIME-decoded string properties.
     *
     * @param callable(string): string $decoder
     */
    public function withDecodedStrings(callable $decoder): self
    {
        return new self(
            subject: $this->subject !== null ? $decoder($this->subject) : $this->subject,
            from: $this->from !== null ? $decoder($this->from) : $this->from,
            to: $this->to !== null ? $decoder($this->to) : $this->to,
            date: $this->date,
            message_id: $this->message_id,
            references: $this->references,
            in_reply_to: $this->in_reply_to,
            size: $this->size,
            uid: $this->uid,
            msgno: $this->msgno,
            recent: $this->recent,
            flagged: $this->flagged,
            answered: $this->answered,
            deleted: $this->deleted,
            seen: $this->seen,
            draft: $this->draft,
            udate: $this->udate,
        );
    }
}
