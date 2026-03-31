<?php

/**
 * Live Mailbox - PHPUnit tests.
 *
 * Runs tests on a live mailbox
 *
 * @author BAPCLTD-Marv
 */
declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use PhpImap\Imap;
use PhpImap\Mailbox;
use ParagonIE\HiddenString\HiddenString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * @phpstan-import-type MAILBOX_ARGS from AbstractLiveMailboxTest
 */
class LiveMailboxIssue501Test extends AbstractLiveMailboxTest
{
    #[Test]
    #[Group('offline')]
    #[Group('offline-issue-501')]
    public function testDecodeMimeStrEmpty(): void
    {
        self::assertSame([], \imap_mime_header_decode(''));

        // example credentials copied from MailboxTest::testConstructorTrimsPossibleVariables()
        $imapPath = ' {imap.example.com:993/imap/ssl}INBOX     ';
        $login = '    php-imap@example.com';
        $password = '  v3rY!53cEt&P4sSWöRd$';
        // directory names can contain spaces before AND after on Linux/Unix systems. Windows trims these spaces automatically.
        $attachmentsDir = '.';
        $serverEncoding = 'UTF-8  ';

        $mailbox = new Mailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        self::assertSame('', $mailbox->decodeMimeStr(''));
    }

    #[Test]
    #[DataProvider('MailBoxProvider')]
    #[Group('live')]
    #[Group('live-issue-501')]
    public function testGetEmptyBody(
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

        /** @var \Throwable|null $exception */
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

            $mailbox->appendMessageToMailbox(Imap::mail_compose(
                $envelope,
                [
                    [
                        'type' => \TYPETEXT,
                        'contents.data' => '',
                    ],
                ]
            ));

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

            self::assertSame('', $mail->textPlain);
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
