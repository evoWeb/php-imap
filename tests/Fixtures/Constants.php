<?php

declare(strict_types=1);

namespace PhpImap\Tests\Fixtures;

class Constants
{
    public const SUBJECT_INSUFFICIENT_UNIQUE = 'If a subject was found, then the message is insufficiently unique to'
        . ' assert that a newly-appended message was actually created.';
    public const SUBJECT_NOT_FOUND = 'If a subject was not found, then Mailbox::appendMessageToMailbox() failed'
        . ' despite not throwing an exception.';

    public const JANE = 'jane@example.com';
    public const JOHN = 'john@example.com';
    public const JOHN_DOE = 'John Doe';

    public const MIME1 = 'MIME-Version: 1.0';
    public const CONTENT_PLAIN = 'Content-Type: TEXT/PLAIN; CHARSET=US-ASCII';
    public const HELLO_WORLD = 'Hello World';
    public const TRANSFER_BASE64 = 'Content-Transfer-Encoding: BASE64';
    public const BOUNDARY = '--{{REPLACE_BOUNDARY_HERE}}';

    public const ERROR_FIRST = 'first error';
    public const ERROR_SECOND = 'second error';
    public const ERROR_THIRD = 'third error';
    public const ERROR_ONLY = 'only error';
    public const ERROR_ATTACHMENTS_DIR = 'setAttachmentsDir() expects a string as first parameter!';

    public const IMAP_PATH = '{imap.example.com:993}';
    public const IMAP_PATH_INBOX = '{imap.example.com:993}INBOX';
    public const IMAP_PATH_INBOX_SSL = '{imap.example.com:993/imap/ssl}INBOX';
    public const IMAP_PATH_INBOX_NO_VALID_SSL = '{imap.example.com:993/imap/ssl/novalidate-cert}INBOX';

    public const LOGIN = 'php-imap@example.com';
    public const PASSWORD = 'v3rY!53cEt&P4sSWöRd$';

    public const EMAIL_A = '<bde36ec8-9710-47bc-9ea3-bf0425078e33@php.imap>';
    public const EMAIL_B = '<CAKBqNfyKo+ZXtkz6DUAHw6FjmsDjWDB-pvHkJy6kwO82jTbkNA@mail.gmail.com>';
    public const EMAIL_C = '<CAE78dO7vwnd_rkozHLZ5xSUnFEQA9fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>';
    public const EMAIL_D = '<CAE78dO7vwnd_rkozHLZ5xSU-=nFE_QA9+fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>';
    public const SUBJECT_SMILE = 'Some subject here 😘';
    public const SOMETHING_KEY = 'مقتطفات من: صن تزو. "فن الحرب". كتب أبل. Something';
    public const MOUNTAIN_GUAN = 'mountainguan测试';

    public const DATE_2005 = '2005-08-14T06:13:03+00:00';

    public const ENVOYES = 'Éléments envoyés';
    public const GITIGNORE = '.gitignore';
    public const SUBJECT = 'Subject: %s';
    public const LF = "\r\n";
}
