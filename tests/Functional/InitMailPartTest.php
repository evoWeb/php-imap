<?php

declare(strict_types=1);

namespace PhpImap\Tests\Functional;

use ParagonIE\HiddenString\HiddenString;
use PhpImap\Exceptions\ConnectionException;
use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Tests\Fixtures\Constants;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Random\RandomException;

/**
 * Live tests for Mailbox::initMailPart, covering code paths that require a real
 * IMAP connection (fetch of body or attachment data).
 *
 * @phpstan-import-type MAILBOX_ARGS from AbstractMailboxTest
 */
class InitMailPartTest extends AbstractMailboxTest
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Joins RFC-822 lines with CRLF.
     *
     * @param string[] $lines
     */
    private static function rfc822(array $lines): string
    {
        return \implode("\r\n", $lines);
    }

    // -------------------------------------------------------------------------
    // multipart/alternative — both plain and html parts populated
    // -------------------------------------------------------------------------

    /**
     * A multipart/alternative message must deliver both textPlain and textHtml.
     *
     * Exercises the TYPETEXT/PLAIN → TEXT_PLAIN path and the
     * TYPETEXT/HTML (ifdisposition=0) → TEXT_HTML path for sub-parts that are
     * individually passed to initMailPart by getMail after flattenParts.
     *
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testMultipartAlternativeDeliversBothPlainAndHtml(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        $subject = 'php-imap/initMailPart/alt: ' . \bin2hex(\random_bytes(8));
        $boundary = 'alt_' . \bin2hex(\random_bytes(6));

        $message = self::rfc822([
            Constants::MIME1,
            sprintf(Constants::SUBJECT, $subject),
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            '',
            '--' . $boundary,
            Constants::CONTENT_PLAIN_UTF8,
            '',
            'Plain text body',
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            '',
            '<p>HTML body</p>',
            '--' . $boundary . '--',
            '',
        ]);

        [$mailbox, $removeMailbox, $path] = $this->getMailbox(
            $imapPath,
            $login,
            $password,
            $attachmentsDir,
            $serverEncoding
        );

        /** @var ?\Exception $exception */
        $exception = null;

        try {
            $search = $mailbox->searchMailbox(sprintf(Constants::SUBJECT2, $subject));
            self::assertCount(0, $search, Constants::SUBJECT_INSUFFICIENT_UNIQUE);

            $mailbox->appendMessageToMailbox($message);

            $search = $mailbox->searchMailbox(sprintf(Constants::SUBJECT2, $subject));
            self::assertCount(1, $search, Constants::SUBJECT_NOT_FOUND);

            $mail = $mailbox->getMail($search[0], false);

            self::assertSame('Plain text body', $mail->textPlain);
            self::assertSame('<p>HTML body</p>', $mail->textHtml);
        } catch (\Exception $exception) {
            // delaying throw to clean up and close connections before that
        } finally {
            $mailbox->switchMailbox($path->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    // -------------------------------------------------------------------------
    // text/plain with disposition:attachment — must NOT be added to textPlain
    // -------------------------------------------------------------------------

    /**
     * A text/plain part with Content-Disposition: attachment must be placed in
     * the attachment list and must NOT be appended to textPlain.
     *
     * Covers the early return at lines 2008-2010 in Mailbox.php:
     *   if ($dispositionAttachment) { return; }
     *
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testTextPlainAttachmentIsNotAddedToBody(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        $subject = 'php-imap/initMailPart/txt-att: ' . \bin2hex(\random_bytes(8));
        $boundary = 'mix_' . \bin2hex(\random_bytes(6));

        $message = self::rfc822([
            Constants::MIME1,
            sprintf(Constants::SUBJECT, $subject),
            sprintf(Constants::CONTENT_MIXED, $boundary),
            '',
            '--' . $boundary,
            Constants::CONTENT_PLAIN_UTF8,
            '',
            'Body of the message',
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8; name="readme.txt"',
            'Content-Disposition: attachment; filename="readme.txt"',
            '',
            'Contents of the text file',
            '--' . $boundary . '--',
            '',
        ]);

        [$mailbox, $removeMailbox, $path] = $this->getMailbox(
            $imapPath,
            $login,
            $password,
            $attachmentsDir,
            $serverEncoding
        );

        /** @var ?\Exception $exception */
        $exception = null;

        try {
            $search = $mailbox->searchMailbox(sprintf(Constants::SUBJECT2, $subject));
            self::assertCount(0, $search, Constants::SUBJECT_INSUFFICIENT_UNIQUE);

            $mailbox->appendMessageToMailbox($message);

            $search = $mailbox->searchMailbox(sprintf(Constants::SUBJECT2, $subject));
            self::assertCount(1, $search, Constants::SUBJECT_NOT_FOUND);

            $mail = $mailbox->getMail($search[0], false);

            self::assertSame('Body of the message', $mail->textPlain);

            $attachments = $mail->getAttachments();
            self::assertCount(1, $attachments);

            $attachment = \current($attachments);
            self::assertSame('readme.txt', $attachment->name);
            self::assertSame('Contents of the text file', $attachment->getContents());
        } catch (\Exception $exception) {
            // delaying throw to clean up and close connections before that
        } finally {
            $mailbox->switchMailbox($path->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    // -------------------------------------------------------------------------
    // message/rfc822 with disposition:attachment — EML attachment downloaded
    // -------------------------------------------------------------------------

    /**
     * A message/rfc822 part with Content-Disposition: attachment must be
     * downloaded as a single file named "rfc822.eml".
     *
     * Covers lines 1938-1944 in Mailbox.php:
     *   if ($partStructure->subtype === 'RFC822' && $dispositionAttachment) {
     *       $attachment = self::downloadAttachment(...);
     *       $mail->addAttachment($attachment);
     *   }
     *
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testEmlAttachmentIsDownloaded(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        $subject = 'php-imap/initMailPart/eml-att: ' . \bin2hex(\random_bytes(8));
        $boundary = 'mix_' . \bin2hex(\random_bytes(6));

        $message = self::rfc822([
            Constants::MIME1,
            sprintf(Constants::SUBJECT, $subject),
            sprintf(Constants::CONTENT_MIXED, $boundary),
            '',
            '--' . $boundary,
            Constants::CONTENT_PLAIN_UTF8,
            '',
            'Outer message body',
            '--' . $boundary,
            'Content-Type: message/rfc822',
            'Content-Disposition: attachment',
            '',
            sprintf(Constants::SUBJECT, 'Inner subject'),
            Constants::MIME1,
            Constants::CONTENT_PLAIN_UTF8,
            '',
            'Inner message body',
            '--' . $boundary . '--',
            '',
        ]);

        [$mailbox, $removeMailbox, $path] = $this->getMailbox(
            $imapPath,
            $login,
            $password,
            $attachmentsDir,
            $serverEncoding
        );

        /** @var ?\Exception $exception */
        $exception = null;

        try {
            $search = $mailbox->searchMailbox(sprintf(Constants::SUBJECT2, $subject));
            self::assertCount(0, $search, Constants::SUBJECT_INSUFFICIENT_UNIQUE);

            $mailbox->appendMessageToMailbox($message);

            $search = $mailbox->searchMailbox(sprintf(Constants::SUBJECT2, $subject));
            self::assertCount(1, $search, Constants::SUBJECT_NOT_FOUND);

            $mail = $mailbox->getMail($search[0], false);

            self::assertTrue($mail->hasAttachments());

            $emlAttachments = \array_filter(
                $mail->getAttachments(),
                static fn($a) => $a->name === 'rfc822.eml'
            );

            self::assertNotEmpty(
                $emlAttachments,
                'Expected at least one attachment named "rfc822.eml"'
            );
        } catch (\Exception $exception) {
            // delaying throw to clean up and close connections before that
        } finally {
            $mailbox->switchMailbox($path->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    // -------------------------------------------------------------------------
    // message/rfc822 inline — TYPEMESSAGE leaf written to TEXT_PLAIN
    // -------------------------------------------------------------------------

    /**
     * A message/rfc822 part without Content-Disposition: attachment is an
     * inline forwarded message.  After flattenParts strips its sub-parts, the
     * wrapper is a TYPEMESSAGE leaf and its body part number is added as
     * TEXT_PLAIN (lines 2023-2024 in Mailbox.php).  The inner text/plain
     * sub-part is added independently, so textPlain contains both.
     *
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testInlineRfc822BodyIsAvailable(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        $subject = 'php-imap/initMailPart/rfc822-inline: ' . \bin2hex(\random_bytes(8));
        $boundary = 'mix_' . \bin2hex(\random_bytes(6));

        $message = self::rfc822([
            Constants::MIME1,
            sprintf(Constants::SUBJECT, $subject),
            sprintf(Constants::CONTENT_MIXED, $boundary),
            '',
            '--' . $boundary,
            Constants::CONTENT_PLAIN_UTF8,
            '',
            'Outer text',
            '--' . $boundary,
            'Content-Type: message/rfc822',
            '',
            sprintf(Constants::SUBJECT, 'Forwarded message'),
            Constants::MIME1,
            Constants::CONTENT_PLAIN_UTF8,
            '',
            'Forwarded body text',
            '--' . $boundary . '--',
            '',
        ]);

        [$mailbox, $removeMailbox, $path] = $this->getMailbox(
            $imapPath,
            $login,
            $password,
            $attachmentsDir,
            $serverEncoding
        );

        /** @var ?\Exception $exception */
        $exception = null;

        try {
            $search = $mailbox->searchMailbox(sprintf(Constants::SUBJECT2, $subject));
            self::assertCount(0, $search, Constants::SUBJECT_INSUFFICIENT_UNIQUE);

            $mailbox->appendMessageToMailbox($message);

            $search = $mailbox->searchMailbox(sprintf(Constants::SUBJECT2, $subject));
            self::assertCount(1, $search, Constants::SUBJECT_NOT_FOUND);

            $mail = $mailbox->getMail($search[0], false);

            // textPlain must contain at minimum the inner forwarded body
            self::assertStringContainsString('Forwarded body text', $mail->textPlain);
        } catch (\Exception $exception) {
            // delaying throw to clean up and close connections before that
        } finally {
            $mailbox->switchMailbox($path->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    // -------------------------------------------------------------------------
    // dparameters — filename comes exclusively from Content-Disposition
    // -------------------------------------------------------------------------

    /**
     * When a filename appears only in the Content-Disposition parameters
     * (dparameters), not in the Content-Type parameters, initMailPart must
     * build $params['filename'] from dparameters and pass it to downloadAttachment.
     *
     * Covers the dparameters loop at lines 1907-1918 in Mailbox.php.
     *
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testFilenameFromDparameterContentDispositionOnly(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        $subject = 'php-imap/initMailPart/dparams: ' . \bin2hex(\random_bytes(8));
        $boundary = 'mix_' . \bin2hex(\random_bytes(6));

        // Content-Type has no 'name' parameter — filename only in Content-Disposition
        $message = self::rfc822([
            Constants::MIME1,
            sprintf(Constants::SUBJECT, $subject),
            sprintf(Constants::CONTENT_MIXED, $boundary),
            '',
            '--' . $boundary,
            Constants::CONTENT_PLAIN_UTF8,
            '',
            'Body',
            '--' . $boundary,
            'Content-Type: application/octet-stream',
            'Content-Disposition: attachment; filename="dparams-only.bin"',
            '',
            'binary data',
            '--' . $boundary . '--',
            '',
        ]);

        [$mailbox, $removeMailbox, $path] = $this->getMailbox(
            $imapPath,
            $login,
            $password,
            $attachmentsDir,
            $serverEncoding
        );

        /** @var ?\Exception $exception */
        $exception = null;

        try {
            $search = $mailbox->searchMailbox(sprintf(Constants::SUBJECT2, $subject));
            self::assertCount(0, $search, Constants::SUBJECT_INSUFFICIENT_UNIQUE);

            $mailbox->appendMessageToMailbox($message);

            $search = $mailbox->searchMailbox(sprintf(Constants::SUBJECT2, $subject));
            self::assertCount(1, $search, Constants::SUBJECT_NOT_FOUND);

            $mail = $mailbox->getMail($search[0], false);

            $attachments = $mail->getAttachments();
            self::assertCount(1, $attachments);
            self::assertSame('dparams-only.bin', \current($attachments)->name);
        } catch (\Exception $exception) {
            // delaying throw to clean up and close connections before that
        } finally {
            $mailbox->switchMailbox($path->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    // -------------------------------------------------------------------------
    // text/html with Content-Disposition: inline → TEXT_HTML (lines 2020-2021)
    // -------------------------------------------------------------------------

    /**
     * A text/html part with Content-Disposition: inline (ifdisposition=1,
     * disposition != 'attachment') must be added to textHtml via the branch
     * at lines 2020-2021 in Mailbox.php, distinct from the ifdisposition=0
     * branch at lines 2013-2014.
     *
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testHtmlPartWithInlineDispositionIsAddedToTextHtml(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        $subject = 'php-imap/initMailPart/html-inline: ' . \bin2hex(\random_bytes(8));
        $boundary = 'mix_' . \bin2hex(\random_bytes(6));

        $message = self::rfc822([
            Constants::MIME1,
            sprintf(Constants::SUBJECT, $subject),
            sprintf(Constants::CONTENT_MIXED, $boundary),
            '',
            '--' . $boundary,
            Constants::CONTENT_PLAIN_UTF8,
            '',
            'Plain text part',
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Disposition: inline',
            '',
            '<p>HTML with inline disposition</p>',
            '--' . $boundary . '--',
            '',
        ]);

        [$mailbox, $removeMailbox, $path] = $this->getMailbox(
            $imapPath,
            $login,
            $password,
            $attachmentsDir,
            $serverEncoding
        );

        /** @var ?\Exception $exception */
        $exception = null;

        try {
            $search = $mailbox->searchMailbox(sprintf(Constants::SUBJECT2, $subject));
            self::assertCount(0, $search, Constants::SUBJECT_INSUFFICIENT_UNIQUE);

            $mailbox->appendMessageToMailbox($message);

            $search = $mailbox->searchMailbox(sprintf(Constants::SUBJECT2, $subject));
            self::assertCount(1, $search, Constants::SUBJECT_NOT_FOUND);

            $mail = $mailbox->getMail($search[0], false);

            self::assertSame('<p>HTML with inline disposition</p>', $mail->textHtml);
        } catch (\Exception $exception) {
            // delaying throw to clean up and close connections before that
        } finally {
            $mailbox->switchMailbox($path->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }
}
