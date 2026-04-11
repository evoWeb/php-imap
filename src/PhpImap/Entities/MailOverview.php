<?php

declare(strict_types=1);

namespace PhpImap\Entities;

final readonly class MailOverview
{
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
            subject: isset($obj->subject) && \is_string($obj->subject) ? $obj->subject : null,
            from: isset($obj->from) && \is_string($obj->from) ? $obj->from : null,
            to: isset($obj->to) && \is_string($obj->to) ? $obj->to : null,
            date: isset($obj->date) && \is_string($obj->date) ? $obj->date : '',
            message_id: isset($obj->message_id) && \is_string($obj->message_id) ? $obj->message_id : '',
            references: isset($obj->references) && \is_string($obj->references) ? $obj->references : null,
            in_reply_to: isset($obj->in_reply_to) && \is_string($obj->in_reply_to) ? $obj->in_reply_to : null,
            size: isset($obj->size) && \is_int($obj->size) ? $obj->size : 0,
            uid: isset($obj->uid) && \is_int($obj->uid) ? $obj->uid : 0,
            msgno: isset($obj->msgno) && \is_int($obj->msgno) ? $obj->msgno : 0,
            recent: isset($obj->recent) && \is_int($obj->recent) ? $obj->recent : 0,
            flagged: isset($obj->flagged) && \is_int($obj->flagged) ? $obj->flagged : 0,
            answered: isset($obj->answered) && \is_int($obj->answered) ? $obj->answered : 0,
            deleted: isset($obj->deleted) && \is_int($obj->deleted) ? $obj->deleted : 0,
            seen: isset($obj->seen) && \is_int($obj->seen) ? $obj->seen : 0,
            draft: isset($obj->draft) && \is_int($obj->draft) ? $obj->draft : 0,
            udate: isset($obj->udate) && \is_int($obj->udate) ? $obj->udate : 0,
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
