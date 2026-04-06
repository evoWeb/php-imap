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
use PhpImap\Mailbox;
use PhpImap\Tests\Fixtures\Constants;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Random\RandomException;

/**
 * @phpstan-import-type MAILBOX_ARGS from AbstractMailboxTest
 */
class MailboxIssue501Test extends AbstractMailboxTest
{
    /**
     * @throws InvalidParameterException
     * @throws \Exception
     */
    #[Test]
    #[Group('offline')]
    #[Group('offline-issue-501')]
    public function testDecodeMimeStrEmpty(): void
    {
        self::assertSame([], \imap_mime_header_decode(''));

        // example credentials copied from MailboxTest::testConstructorTrimsPossibleVariables()
        $imapPath = ' ' . Constants::IMAP_PATH_INBOX_SSL . '     ';
        $login = '    ' . Constants::LOGIN;
        $password = '  ' . Constants::PASSWORD;
        // directory names can contain spaces before AND after on
        // Linux/Unix systems. Windows trims these spaces automatically.
        $attachmentsDir = '.';
        $serverEncoding = 'UTF-8  ';

        $mailbox = new Mailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        self::assertSame('', $mailbox->decodeMimeStr(''));
    }

    /**
     * @throws InvalidParameterException
     * @throws \Exception
     * @throws RandomException
     * @throws ConnectionException
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
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

        /** @var ?\Exception $exception */
        $exception = null;

        try {
            $envelope = [
                'subject' => 'barbushin/php-imap#501: ' . \bin2hex(\random_bytes(16)),
            ];

            [$searchCriteria] = $this->subjectSearchCriteriaAndSubject($envelope);

            $search = $mailbox->searchMailbox($searchCriteria);

            self::assertCount(0, $search, Constants::SUBJECT_INSUFFICIENT_UNIQUE);

            $mailbox->appendMessageToMailbox(Imap::mailCompose(
                $envelope,
                [
                    [
                        'type' => \TYPETEXT,
                        'contents.data' => '',
                    ],
                ]
            ));

            $search = $mailbox->searchMailbox($searchCriteria);

            self::assertCount(1, $search, Constants::SUBJECT_NOT_FOUND);

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
