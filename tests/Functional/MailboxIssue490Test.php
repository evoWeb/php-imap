<?php

/**
 * Live Mailbox - PHPUnit tests.
 *
 * Runs tests on a live mailbox
 *
 * @author BAPCLTD-Marv
 */
declare(strict_types=1);

namespace PhpImap\Tests\Functional;

use ParagonIE\HiddenString\HiddenString;
use PhpImap\Exceptions\ConnectionException;
use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Imap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Random\RandomException;

/**
 * @phpstan-import-type MAILBOX_ARGS from AbstractMailboxTest
 */
class MailboxIssue490Test extends AbstractMailboxTest
{
    /**
     * @throws InvalidParameterException
     * @throws \Exception
     * @throws RandomException
     * @throws ConnectionException
     */
    #[Test]
    #[DataProvider('MailBoxProvider')]
    #[Group('live')]
    #[Group('live-issue-490')]
    public function testGetTextAttachments(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        [$mailbox, $removeMailbox] = $this->getMailbox(
            $imapPath,
            $login,
            $password,
            $attachmentsDir,
            $serverEncoding
        );

        /** @var ?\Exception $exception */
        $exception = null;

        try {
            $envelope = [
                'subject' => 'barbushin/php-imap#501: ' . \bin2hex(\random_bytes(16)),
            ];

            [$searchCriteria] = $this->SubjectSearchCriteriaAndSubject($envelope);

            $search = $mailbox->searchMailbox($searchCriteria);

            self::assertCount(
                0,
                $search,
                (
                    'If a subject was found,' .
                    ' then the message is insufficiently unique to assert that' .
                    ' a newly-appended message was actually created.'
                )
            );

            $message = Imap::mail_compose(
                $envelope,
                [
                    [
                        'type' => \TYPEMULTIPART,
                    ],
                    [
                        'type' => \TYPETEXT,
                        'contents.data' => 'foo',
                    ],
                    [
                        'type' => \TYPEMULTIPART,
                        'subtype' => 'plain',
                        'description' => 'bar.txt',
                        'disposition.type' => 'attachment',
                        'disposition' => ['filename' => 'bar.txt'],
                        'type.parameters' => ['name' => 'bar.txt'],
                        'contents.data' => 'bar',
                    ],
                    [
                        'type' => \TYPEMULTIPART,
                        'subtype' => 'plain',
                        'description' => 'baz.txt',
                        'disposition.type' => 'attachment',
                        'disposition' => ['filename' => 'baz.txt'],
                        'type.parameters' => ['name' => 'baz.txt'],
                        'contents.data' => 'baz',
                    ],
                ]
            );

            $mailbox->appendMessageToMailbox($message);

            $search = $mailbox->searchMailbox($searchCriteria);

            self::assertCount(
                1,
                $search,
                (
                    'If a subject was not found, ' .
                    ' then Mailbox::appendMessageToMailbox() failed' .
                    ' despite not throwing an exception.'
                )
            );

            $mail = $mailbox->getMail($search[0], false);

            self::assertSame('foo', $mail->textPlain);

            $attachments = $mail->getAttachments();
            $keys = \array_keys($attachments);

            self::assertCount(2, $attachments);

            self::assertSame('bar', $attachments[$keys[0]]->getContents());
            self::assertSame('baz', $attachments[$keys[1]]->getContents());
        } catch (\Exception $exception) {
        } finally {
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }
}
