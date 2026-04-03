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

use PhpImap\Exceptions\ConnectionException;
use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Imap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Random\RandomException;

/**
 * Provides testAppend for test classes that implement ComposeProvider.
 *
 * @phpstan-import-type MAILBOX_ARGS from AbstractMailboxTest
 * @phpstan-import-type COMPOSE_ENVELOPE from AbstractMailboxTest
 * @phpstan-import-type COMPOSE_BODY from AbstractMailboxTest
 */
trait MailboxAppendTestTrait
{
    /**
     * @phpstan-return \Generator<int, array{
     *      0: MAILBOX_ARGS,
     *      1: COMPOSE_ENVELOPE,
     *      2: COMPOSE_BODY,
     *      3: bool,
     *      4: string
     * }, mixed, void>
     */
    public static function AppendProvider(): \Generator
    {
        foreach (static::MailBoxProvider() as $mailboxArguments) {
            foreach (static::ComposeProvider() as $composeArguments) {
                [$envelope, $body, $expectedComposeResult] = $composeArguments;

                yield [$mailboxArguments, $envelope, $body, false, $expectedComposeResult];
            }

            foreach (static::ComposeProvider() as $composeArguments) {
                [$envelope, $body, $expectedComposeResult] = $composeArguments;

                yield [$mailboxArguments, $envelope, $body, true, $expectedComposeResult];
            }
        }
    }

    /**
     * @depends testGetImapStream
     * @depends testMailCompose
     *
     * @phpstan-param MAILBOX_ARGS $mailboxArguments
     * @phpstan-param COMPOSE_ENVELOPE $envelope
     * @phpstan-param COMPOSE_BODY $body
     *
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('AppendProvider')]
    public function testAppend(
        array $mailboxArguments,
        array $envelope,
        array $body,
        bool $preCompose,
    ): void {
        $this->runAppendTest($mailboxArguments, $envelope, $body, $preCompose);
    }

    /**
     * @phpstan-param MAILBOX_ARGS $mailboxArguments
     * @phpstan-param COMPOSE_ENVELOPE $envelope
     * @phpstan-param COMPOSE_BODY $body
     *
     * @throws ConnectionException
     * @throws \Exception
     * @throws InvalidParameterException
     * @throws RandomException
     */
    protected function runAppendTest(
        array $mailboxArguments,
        array $envelope,
        array $body,
        bool $preCompose
    ): void {
        if ($this->MaybeSkipAppendTest($envelope)) {
            return;
        }

        [$searchCriteria] = $this->SubjectSearchCriteriaAndSubject($envelope);

        [$mailbox, $removeMailbox, $path] = $this->getMailboxFromArgs($mailboxArguments);

        /** @var ?\Exception $exception */
        $exception = null;

        $mailboxDeleted = false;

        try {
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

            $message = [$envelope, $body];

            if ($preCompose) {
                $message = Imap::mail_compose($envelope, $body);
            }

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

            $mailbox->deleteMail($search[0]);

            $mailbox->expungeDeletedMails();

            $mailbox->switchMailbox($path->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailboxDeleted = true;

            self::assertCount(
                0,
                $mailbox->searchMailbox($searchCriteria),
                (
                    'If a subject was found,' .
                    ' then the message is was not expunged as requested.'
                )
            );
        } catch (\Exception $exception) {
        } finally {
            $mailbox->switchMailbox($path->getString());
            if (!$mailboxDeleted) {
                $mailbox->deleteMailbox($removeMailbox);
            }
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }
}
