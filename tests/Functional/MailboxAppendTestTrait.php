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
use PhpImap\Tests\Fixtures\Constants;
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
    public static function appendProvider(): \Generator
    {
        foreach (static::mailBoxProvider() as $mailboxArguments) {
            foreach (static::composeProvider() as $composeArguments) {
                [$envelope, $body, $expectedComposeResult] = $composeArguments;

                yield [$mailboxArguments, $envelope, $body, false, $expectedComposeResult];
            }

            foreach (static::composeProvider() as $composeArguments) {
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
    #[DataProvider('appendProvider')]
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
        if ($this->maybeSkipAppendTest($envelope)) {
            return;
        }

        [$searchCriteria] = $this->subjectSearchCriteriaAndSubject($envelope);

        [$mailbox, $removeMailbox, $path] = $this->getMailboxFromArgs($mailboxArguments);

        /** @var ?\Exception $exception */
        $exception = null;

        $mailboxDeleted = false;

        try {
            $search = $mailbox->searchMailbox($searchCriteria);

            self::assertCount(0, $search, Constants::SUBJECT_INSUFFICIENT_UNIQUE);

            $message = [$envelope, $body];

            if ($preCompose) {
                $message = Imap::mailCompose($envelope, $body);
            }

            $mailbox->appendMessageToMailbox($message);

            $search = $mailbox->searchMailbox($searchCriteria);

            self::assertCount(1, $search, Constants::SUBJECT_NOT_FOUND);

            $mailbox->deleteMail($search[0]);

            $mailbox->expungeDeletedMails();

            $mailbox->switchMailbox($path->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailboxDeleted = true;

            self::assertCount(
                0,
                $mailbox->searchMailbox($searchCriteria),
                Constants::SUBJECT_INSUFFICIENT_UNIQUE
            );
        } catch (\Exception $exception) {
            // delaying throw to clean up and close connections before that
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
