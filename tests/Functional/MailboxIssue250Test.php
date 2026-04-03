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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * @phpstan-import-type MAILBOX_ARGS from AbstractMailboxTest
 * @phpstan-import-type COMPOSE_ENVELOPE from AbstractMailboxTest
 * @phpstan-import-type COMPOSE_BODY from AbstractMailboxTest
 */
class MailboxIssue250Test extends AbstractMailboxTest
{
    use MailboxAppendTestTrait;

    /**
     * @phpstan-return \Generator<int, array{
     *      0: COMPOSE_ENVELOPE,
     *      1: COMPOSE_BODY,
     *      2: string
     * }, mixed, void>
     */
    public static function ComposeProvider(): \Generator
    {
        $randomSubject = 'barbushin/php-imap#250 测试: ' . \bin2hex(\random_bytes(16));

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
    }

    /**
     * @phpstan-param MAILBOX_ARGS $mailboxArguments
     * @phpstan-param COMPOSE_ENVELOPE $envelope
     * @phpstan-param COMPOSE_BODY $body
     */
    #[Test]
    #[DataProvider('AppendProvider')]
    #[Group('live')]
    #[Group('live-issue-250')]
    public function testAppend(
        array $mailboxArguments,
        array $envelope,
        array $body,
        string $expectedComposeResult,
        bool $preCompose
    ): void {
        $this->runAppendTest($mailboxArguments, $envelope, $body, $expectedComposeResult, $preCompose);
    }
}
