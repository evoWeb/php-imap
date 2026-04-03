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

use ParagonIE\HiddenString\HiddenString;
use PhpImap\Imap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * @phpstan-import-type MAILBOX_ARGS from AbstractLiveMailboxTest
 * @phpstan-import-type COMPOSE_ENVELOPE from AbstractLiveMailboxTest
 * @phpstan-import-type COMPOSE_BODY from AbstractLiveMailboxTest
 */
class LiveMailboxTest extends AbstractLiveMailboxTest
{
    use LiveMailboxAppendTestTrait;

    public const RANDOM_MAILBOX_SAMPLE_SIZE = 3;

    public const ISSUE_EXPECTED_ATTACHMENT_COUNT = [
        448 => 1,
        391 => 2,
    ];

    #[Test]
    #[DataProvider('MailBoxProvider')]
    #[Group('live')]
    public function testGetImapStream(
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
            $mailbox->getImapStream();
            self::assertTrue($mailbox->hasImapStream());

            $mailboxes = $mailbox->getMailboxes();
            \shuffle($mailboxes);

            $limit = \min(\count($mailboxes), self::RANDOM_MAILBOX_SAMPLE_SIZE);

            for ($i = 0; $i < $limit; ++$i) {
                self::assertIsArray($mailboxes[$i]);
                self::assertTrue(isset($mailboxes[$i]['shortpath']));
                self::assertIsString($mailboxes[$i]['shortpath']);
                $mailbox->switchMailbox($mailboxes[$i]['shortpath']);

                $check = $mailbox->checkMailbox();

                foreach ([
                    'Date',
                    'Driver',
                    'Mailbox',
                    'Nmsgs',
                    'Recent',
                ] as $expectedProperty) {
                    self::assertTrue(\property_exists($check, $expectedProperty));
                }

                self::assertIsString(
                    $check->Date,
                    'Date property of Mailbox::checkMailbox() result was not a string!'
                );

                $unix = \strtotime($check->Date);

                if ($unix === false && \preg_match('/[+-]\d{1,2}:?\d{2} \([^)]+\)$/', $check->Date)) {
                    /** @var int $pos */
                    $pos = \strrpos($check->Date, '(');

                    // Although the date property is likely RFC2822-compliant, it will not be parsed by strtotime()
                    $unix = \strtotime(\substr($check->Date, 0, $pos));
                }

                self::assertIsInt($unix, 'Date property of Mailbox::checkMailbox() result was not a valid date!');
                self::assertTrue(
                    \in_array($check->Driver, ['POP3', 'IMAP', 'NNTP', 'pop3', 'imap', 'nntp'], true),
                    'Driver property of Mailbox::checkMailbox() result was not of an expected value!'
                );
                self::assertIsInt(
                    $check->Nmsgs,
                    'Nmsgs property of Mailbox::checkMailbox() result was not of an expected type!'
                );
                self::assertIsInt(
                    $check->Recent,
                    'Recent property of Mailbox::checkMailbox() result was not of an expected type!'
                );

                $status = $mailbox->statusMailbox();

                foreach ([
                    'messages',
                    'recent',
                    'unseen',
                    'uidnext',
                    'uidvalidity',
                ] as $expectedProperty) {
                    self::assertTrue(\property_exists($status, $expectedProperty));
                }

                self::assertSame(
                    $check->Nmsgs,
                    $mailbox->countMails(),
                    'Mailbox::checkMailbox()->Nmsgs did not match Mailbox::countMails()!'
                );
            }
        } catch (\Throwable $exception) {
        } finally {
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * @phpstan-return \Generator<int, array{
     *      0: COMPOSE_ENVELOPE,
     *      1: COMPOSE_BODY,
     *      2: string
     * }, mixed, void>
     */
    public static function ComposeProvider(): \Generator
    {
        $randomSubject = 'test: ' . \bin2hex(\random_bytes(16));
        yield [
            ['subject' => $randomSubject],
            [
                [
                    'type' => \TYPETEXT,
                    'contents.data' => 'test',
                ],
            ],
            implode(LF, [
                'Subject: ' . $randomSubject,
                'MIME-Version: 1.0',
                'Content-Type: TEXT/PLAIN; CHARSET=US-ASCII',
                '',
                'test',
                '',
            ]),
        ];

        $randomSubject = 'barbushin/php-imap#448: dot first:' . \bin2hex(\random_bytes(16));
        $contentsData = \base64_encode(\file_get_contents(__DIR__ . '/../../.gitignore'));
        yield [
            ['subject' => $randomSubject],
            [
                [
                    'type' => \TYPEAPPLICATION,
                    'encoding' => \ENCBASE64,
                    'subtype' => 'octet-stream',
                    'description' => '.gitignore',
                    'disposition.type' => 'attachment',
                    'disposition' => ['filename' => '.gitignore'],
                    'type.parameters' => ['name' => '.gitignore'],
                    'contents.data' => $contentsData,
                ],
            ],
            implode(LF, [
                'Subject: ' . $randomSubject,
                'MIME-Version: 1.0',
                'Content-Type: APPLICATION/octet-stream; name=.gitignore',
                'Content-Transfer-Encoding: BASE64',
                'Content-Description: .gitignore',
                'Content-Disposition: attachment; filename=.gitignore',
                '',
                $contentsData,
                '',
            ]),
        ];

        $randomSubject = 'barbushin/php-imap#448: dot last: ' . \bin2hex(\random_bytes(16));
        yield [
            ['subject' => $randomSubject],
            [
                [
                    'type' => \TYPEAPPLICATION,
                    'encoding' => \ENCBASE64,
                    'subtype' => 'octet-stream',
                    'description' => 'gitignore.',
                    'disposition.type' => 'attachment',
                    'disposition' => ['filename' => 'gitignore.'],
                    'type.parameters' => ['name' => 'gitignore.'],
                    'contents.data' => $contentsData,
                ],
            ],
            implode(LF, [
                'Subject: ' . $randomSubject,
                'MIME-Version: 1.0',
                'Content-Type: APPLICATION/octet-stream; name=gitignore.',
                'Content-Transfer-Encoding: BASE64',
                'Content-Description: gitignore.',
                'Content-Disposition: attachment; filename=gitignore.',
                '',
                $contentsData,
                '',
            ]),
        ];

        $randomSubject = 'barbushin/php-imap#391: ' . \bin2hex(\random_bytes(16));
        $randomAttachmentA = \base64_encode(\random_bytes(16));
        $randomAttachmentB = \base64_encode(\random_bytes(16));
        yield [
            ['subject' => $randomSubject],
            [
                [
                    'type' => \TYPEMULTIPART,
                ],
                [
                    'type' => \TYPETEXT,
                    'contents.data' => 'test',
                ],
                [
                    'type' => \TYPEAPPLICATION,
                    'encoding' => \ENCBASE64,
                    'subtype' => 'octet-stream',
                    'description' => 'foo.bin',
                    'disposition.type' => 'attachment',
                    'disposition' => ['filename' => 'foo.bin'],
                    'type.parameters' => ['name' => 'foo.bin'],
                    'contents.data' => $randomAttachmentA,
                ],
                [
                    'type' => \TYPEAPPLICATION,
                    'encoding' => \ENCBASE64,
                    'subtype' => 'octet-stream',
                    'description' => 'foo.bin',
                    'disposition.type' => 'attachment',
                    'disposition' => ['filename' => 'foo.bin'],
                    'type.parameters' => ['name' => 'foo.bin'],
                    'contents.data' => $randomAttachmentB,
                ],
            ],
            implode(LF, [
                'Subject: ' . $randomSubject,
                'MIME-Version: 1.0',
                'Content-Type: MULTIPART/MIXED; BOUNDARY="{{REPLACE_BOUNDARY_HERE}}"',
                '',
                '--{{REPLACE_BOUNDARY_HERE}}',
                'Content-Type: TEXT/PLAIN; CHARSET=US-ASCII',
                '',
                'test',
                '--{{REPLACE_BOUNDARY_HERE}}',
                'Content-Type: APPLICATION/octet-stream; name=foo.bin',
                'Content-Transfer-Encoding: BASE64',
                'Content-Description: foo.bin',
                'Content-Disposition: attachment; filename=foo.bin',
                '',
                $randomAttachmentA,
                '--{{REPLACE_BOUNDARY_HERE}}',
                'Content-Type: APPLICATION/octet-stream; name=foo.bin',
                'Content-Transfer-Encoding: BASE64',
                'Content-Description: foo.bin',
                'Content-Disposition: attachment; filename=foo.bin',
                '',
                $randomAttachmentB,
                '--{{REPLACE_BOUNDARY_HERE}}--',
                '',
            ]),
        ];
    }

    /**
     * @phpstan-param COMPOSE_ENVELOPE $envelope
     * @phpstan-param COMPOSE_BODY $body
     */
    #[Test]
    #[DataProvider('ComposeProvider')]
    #[Group('compose')]
    public function testMailCompose(array $envelope, array $body, string $expectedResult): void
    {
        $actualResult = Imap::mail_compose($envelope, $body);

        $expectedResult = $this->ReplaceBoundaryHere($expectedResult, $actualResult);

        self::assertSame($expectedResult, $actualResult);
    }

    /**
     * @depends testAppend
     *
     * @phpstan-param MAILBOX_ARGS $mailboxArguments
     * @phpstan-param COMPOSE_ENVELOPE $envelope
     * @phpstan-param COMPOSE_BODY $body
     */
    #[Test]
    #[DataProvider('AppendProvider')]
    #[Group('live')]
    public function testAppendNudgesMailboxCount(
        array $mailboxArguments,
        array $envelope,
        array $body,
        string $expectedComposeResult,
        bool $preCompose
    ): void {
        if ($this->MaybeSkipAppendTest($envelope)) {
            return;
        }

        [$searchCriteria] = $this->SubjectSearchCriteriaAndSubject($envelope);

        [$mailbox, $removeMailbox, $path] = $this->getMailboxFromArgs($mailboxArguments);

        $count = $mailbox->countMails();

        $message = [$envelope, $body];

        if ($preCompose) {
            $message = Imap::mail_compose($envelope, $body);
        }

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

        self::assertSame(
            $count + 1,
            $mailbox->countMails(),
            (
                'If the message count did not increase' .
                ' then either the message was not appended,' .
                ' or a mesage was removed while the test was running.'
            )
        );

        $mailbox->deleteMail($search[0]);

        $mailbox->expungeDeletedMails();

        $mailbox->switchMailbox($path->getString());
        $mailbox->deleteMailbox($removeMailbox);

        self::assertCount(
            0,
            $mailbox->searchMailbox($searchCriteria),
            (
                'If a subject was found,' .
                ' then the message is was not expunged as requested.'
            )
        );
    }

    /**
     * @depends testAppend
     *
     * @phpstan-param MAILBOX_ARGS $mailboxArguments
     * @phpstan-param COMPOSE_ENVELOPE $envelope
     * @phpstan-param COMPOSE_BODY $body
     */
    #[Test]
    #[DataProvider('AppendProvider')]
    #[Group('live')]
    public function testAppendSingleSearchMatchesSort(
        array $mailboxArguments,
        array $envelope,
        array $body,
        string $expectedComposeResult,
        bool $preCompose
    ): void {
        if ($this->MaybeSkipAppendTest($envelope)) {
            return;
        }

        [$searchCriteria] = $this->SubjectSearchCriteriaAndSubject($envelope);

        [$mailbox, $removeMailbox, $path] = $this->getMailboxFromArgs($mailboxArguments);

        $message = [$envelope, $body];

        if ($preCompose) {
            $message = Imap::mail_compose($envelope, $body);
        }

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

        self::assertSame($search, $mailbox->sortMails(\SORTARRIVAL, true, $searchCriteria));

        self::assertSame($search, $mailbox->sortMails(\SORTARRIVAL, false, $searchCriteria));

        self::assertSame($search, $mailbox->sortMails(\SORTARRIVAL, false, $searchCriteria, 'UTF-8'));

        self::assertTrue(\in_array($search[0], $mailbox->sortMails(\SORTARRIVAL, false, null), true));

        $mailbox->deleteMail($search[0]);

        $mailbox->expungeDeletedMails();

        $mailbox->switchMailbox($path->getString());
        $mailbox->deleteMailbox($removeMailbox);

        self::assertCount(
            0,
            $mailbox->searchMailbox($searchCriteria),
            (
                'If a subject was found,' .
                ' then the message is was not expunged as requested.'
            )
        );
    }

    /**
     * @depends testAppend
     *
     * @phpstan-param MAILBOX_ARGS $mailboxArguments
     * @phpstan-param COMPOSE_ENVELOPE $envelope
     * @phpstan-param COMPOSE_BODY $body
     */
    #[Test]
    #[DataProvider('AppendProvider')]
    #[Group('live')]
    public function testAppendRetrievalMatchesExpected(
        array $mailboxArguments,
        array $envelope,
        array $body,
        string $expectedComposeResult,
        bool $preCompose
    ): void {
        if ($this->MaybeSkipAppendTest($envelope)) {
            return;
        }

        [$searchCriteria, $searchSubject] = $this->SubjectSearchCriteriaAndSubject($envelope);

        [$mailbox, $removeMailbox, $path] = $this->getMailboxFromArgs($mailboxArguments);

        $message = [$envelope, $body];

        if ($preCompose) {
            $message = Imap::mail_compose($envelope, $body);
        }

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

        $actualResult = $mailbox->getMailMboxFormat($search[0]);

        self::assertSame(
            $this->ReplaceBoundaryHere(
                $expectedComposeResult,
                $actualResult
            ),
            $actualResult
        );

        $actualResult = $mailbox->getRawMail($search[0]);

        self::assertSame($this->ReplaceBoundaryHere($expectedComposeResult, $actualResult), $actualResult);

        $mail = $mailbox->getMail($search[0], false);

        self::assertSame(
            $searchSubject,
            $mail->subject,
            (
                'If a retrieved mail did not have a matching subject' .
                ' despite being found via search,' .
                ' then something has gone wrong.'
            )
        );

        $info = $mailbox->getMailsInfo($search);

        self::assertCount(1, $info);

        self::assertSame(
            $searchSubject,
            $info[0]->subject,
            (
                'If a retrieved mail did not have a matching subject' .
                ' despite being found via search,' .
                ' then something has gone wrong.'
            )
        );

        if (
            \preg_match(
                '/^barbushin\/php-imap#(448|391):/',
                $envelope['subject'] ?? '',
                $matches
            ) === 1
        ) {
            self::assertTrue($mail->hasAttachments());

            $attachments = $mail->getAttachments();

            self::assertCount(self::ISSUE_EXPECTED_ATTACHMENT_COUNT[(int)$matches[1]], $attachments);

            if ($matches[1] === '448') {
                self::assertSame(
                    \file_get_contents(__DIR__ . '/../../.gitignore'),
                    \current($attachments)->getContents()
                );
            }
        }

        $mailbox->deleteMail($search[0]);

        $mailbox->expungeDeletedMails();

        $mailbox->switchMailbox($path->getString());
        $mailbox->deleteMailbox($removeMailbox);

        self::assertCount(
            0,
            $mailbox->searchMailbox($searchCriteria),
            (
                'If a subject was found,' .
                ' then the message is was not expunged as requested.'
            )
        );
    }

    protected function ReplaceBoundaryHere(string $expectedResult, string $actualResult): string
    {
        if (
            \preg_match('/{{REPLACE_BOUNDARY_HERE}}/', $expectedResult) === 1
            && \preg_match(
                '/Content-Type: MULTIPART\/MIXED; BOUNDARY="([^"]+)"/',
                $actualResult,
                $matches
            ) === 1
        ) {
            $expectedResult = \str_replace('{{REPLACE_BOUNDARY_HERE}}', $matches[1], $expectedResult);
        }

        return $expectedResult;
    }
}
