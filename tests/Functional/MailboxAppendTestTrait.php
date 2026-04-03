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

use PhpImap\Imap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

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
     *      3: string,
     *      4: bool
     * }, mixed, void>
     */
    public static function AppendProvider(): \Generator
    {
        foreach (static::MailBoxProvider() as $mailboxArguments) {
            foreach (static::ComposeProvider() as $composeArguments) {
                [$envelope, $body, $expectedComposeResult] = $composeArguments;

                yield [$mailboxArguments, $envelope, $body, $expectedComposeResult, false];
            }

            foreach (static::ComposeProvider() as $composeArguments) {
                [$envelope, $body, $expectedComposeResult] = $composeArguments;

                yield [$mailboxArguments, $envelope, $body, $expectedComposeResult, true];
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
     */
    #[Test]
    #[DataProvider('AppendProvider')]
    public function testAppend(
        array $mailboxArguments,
        array $envelope,
        array $body,
        string $expectedComposeResult,
        bool $preCompose
    ): void {
        $this->runAppendTest($mailboxArguments, $envelope, $body, $expectedComposeResult, $preCompose);
    }

    /**
     * @phpstan-param MAILBOX_ARGS $mailboxArguments
     * @phpstan-param COMPOSE_ENVELOPE $envelope
     * @phpstan-param COMPOSE_BODY $body
     */
    protected function runAppendTest(
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

        /** @var \Throwable|null $exception */
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
        } catch (\Throwable $exception) {
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
