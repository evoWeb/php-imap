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

use PhpImap\Entities\ComposeBody;
use PhpImap\Entities\ComposeEnvelope;
use PhpImap\Tests\Fixtures\Constants;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Random\RandomException;

/**
 * @phpstan-import-type MAILBOX_ARGS from AbstractMailboxTest
 */
class MailboxIssue250Test extends AbstractMailboxTest
{
    use MailboxAppendTestTrait;

    /**
     * @phpstan-return \Generator<int, array{
     *       0: ComposeEnvelope,
     *       1: ComposeBody[],
     *      2: string
     * }, mixed, void>
     *
     * @throws RandomException
     */
    public static function composeProvider(): \Generator
    {
        $randomSubject = 'barbushin/php-imap#250 测试: ' . \bin2hex(\random_bytes(16));

        yield [
            new ComposeEnvelope($randomSubject),
            [
                ComposeBody::fromArray([
                    'type' => \TYPETEXT,
                    'contents.data' => 'test',
                ]),
            ],
            implode(Constants::LF, [
                sprintf(Constants::SUBJECT, $randomSubject),
                Constants::MIME1,
                Constants::CONTENT_PLAIN,
                '',
                'test',
                '',
            ]),
        ];
    }

    /**
     * @phpstan-param MAILBOX_ARGS $mailboxArguments
     * @phpstan-param ComposeEnvelope $envelope
     * @phpstan-param ComposeBody[] $body
     *
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('appendProvider')]
    #[Group('live')]
    #[Group('live-issue-250')]
    public function testAppend(
        array $mailboxArguments,
        ComposeEnvelope $envelope,
        array $body,
        bool $preCompose,
        string $expectedComposeResult,
    ): void {
        $this->runAppendTest($mailboxArguments, $envelope, $body, $preCompose);
    }
}
