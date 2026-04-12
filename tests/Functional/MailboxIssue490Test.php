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
use PhpImap\Entities\ComposeBody;
use PhpImap\Entities\ComposeEnvelope;
use PhpImap\Exceptions\ConnectionException;
use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Imap;
use PhpImap\Tests\Fixtures\Constants;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Random\RandomException;

class MailboxIssue490Test extends AbstractMailboxTest
{
    /**
     * @throws InvalidParameterException
     * @throws \Exception
     * @throws RandomException
     * @throws ConnectionException
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
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
            $envelope = new ComposeEnvelope('barbushin/php-imap#501: ' . \bin2hex(\random_bytes(16)));

            [$searchCriteria] = $this->subjectSearchCriteriaAndSubject($envelope);

            $search = $mailbox->searchMailbox($searchCriteria);

            self::assertCount(0, $search, Constants::SUBJECT_INSUFFICIENT_UNIQUE);

            $message = Imap::mailCompose(
                $envelope,
                [
                    ComposeBody::fromArray([
                        'type' => \TYPEMULTIPART,
                    ]),
                    ComposeBody::fromArray([
                        'type' => \TYPETEXT,
                        'contents.data' => 'foo',
                    ]),
                    ComposeBody::fromArray([
                        'type' => \TYPEMULTIPART,
                        'subtype' => 'plain',
                        'description' => 'bar.txt',
                        'disposition.type' => 'attachment',
                        'disposition' => ['filename' => 'bar.txt'],
                        'type.parameters' => ['name' => 'bar.txt'],
                        'contents.data' => 'bar',
                    ]),
                    ComposeBody::fromArray([
                        'type' => \TYPEMULTIPART,
                        'subtype' => 'plain',
                        'description' => 'baz.txt',
                        'disposition.type' => 'attachment',
                        'disposition' => ['filename' => 'baz.txt'],
                        'type.parameters' => ['name' => 'baz.txt'],
                        'contents.data' => 'baz',
                    ]),
                ]
            );

            $mailbox->appendMessageToMailbox($message);

            $search = $mailbox->searchMailbox($searchCriteria);

            self::assertCount(1, $search, Constants::SUBJECT_NOT_FOUND);

            $mail = $mailbox->getMail($search[0], false);

            self::assertSame('foo', $mail->textPlain);

            $attachments = $mail->getAttachments();
            $keys = \array_keys($attachments);

            self::assertCount(2, $attachments);

            self::assertSame('bar', $attachments[$keys[0]]->getContents());
            self::assertSame('baz', $attachments[$keys[1]]->getContents());
        } catch (\Exception $exception) {
            // delaying throw to clean up and close connections before that
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
