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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Provides testAppend for test classes that implement ComposeProvider.
 *
 * @phpstan-import-type MAILBOX_ARGS from AbstractLiveMailboxTest
 * @phpstan-import-type COMPOSE_ENVELOPE from AbstractLiveMailboxTest
 * @phpstan-import-type COMPOSE_BODY from AbstractLiveMailboxTest
 */
trait LiveMailboxAppendTestTrait
{
    /**
     * @phpstan-return \Generator<int, array{
     *	0:MAILBOX_ARGS,
     *	1:COMPOSE_ENVELOPE,
     *	2:COMPOSE_BODY,
     *	3:string,
     *	4:bool
     * }, mixed, void>
     */
    public static function AppendProvider(): \Generator
    {
        foreach (static::MailBoxProvider() as $mailbox_args) {
            foreach (static::ComposeProvider() as $compose_args) {
                [$envelope, $body, $expected_compose_result] = $compose_args;

                yield [$mailbox_args, $envelope, $body, $expected_compose_result, false];
            }

            foreach (static::ComposeProvider() as $compose_args) {
                [$envelope, $body, $expected_compose_result] = $compose_args;

                yield [$mailbox_args, $envelope, $body, $expected_compose_result, true];
            }
        }
    }

    /**
     * @depends testGetImapStream
     * @depends testMailCompose
     *
     * @phpstan-param MAILBOX_ARGS $mailbox_args
     * @phpstan-param COMPOSE_ENVELOPE $envelope
     * @phpstan-param COMPOSE_BODY $body
     */
    #[Test]
    #[DataProvider('AppendProvider')]
    public function testAppend(
        array $mailbox_args,
        array $envelope,
        array $body,
        string $_expected_compose_result,
        bool $pre_compose
    ): void {
        $this->runAppendTest($mailbox_args, $envelope, $body, $_expected_compose_result, $pre_compose);
    }

    /**
     * @phpstan-param MAILBOX_ARGS $mailbox_args
     * @phpstan-param COMPOSE_ENVELOPE $envelope
     * @phpstan-param COMPOSE_BODY $body
     */
    protected function runAppendTest(
        array $mailbox_args,
        array $envelope,
        array $body,
        string $_expected_compose_result,
        bool $pre_compose
    ): void {
        if ($this->MaybeSkipAppendTest($envelope)) {
            return;
        }

        [$search_criteria] = $this->SubjectSearchCriteriaAndSubject($envelope);

        [$mailbox, $remove_mailbox, $path] = $this->getMailboxFromArgs(
            $mailbox_args
        );

        /** @var \Throwable|null */
        $exception = null;

        $mailboxDeleted = false;

        try {
            $search = $mailbox->searchMailbox($search_criteria);

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

            if ($pre_compose) {
                $message = Imap::mail_compose($envelope, $body);
            }

            $mailbox->appendMessageToMailbox($message);

            $search = $mailbox->searchMailbox($search_criteria);

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
            $mailbox->deleteMailbox($remove_mailbox);
            $mailboxDeleted = true;

            self::assertCount(
                0,
                $mailbox->searchMailbox($search_criteria),
                (
                    'If a subject was found,' .
                    ' then the message is was not expunged as requested.'
                )
            );
        } catch (\Throwable $ex) {
            $exception = $ex;
        } finally {
            $mailbox->switchMailbox($path->getString());
            if (!$mailboxDeleted) {
                $mailbox->deleteMailbox($remove_mailbox);
            }
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }
}