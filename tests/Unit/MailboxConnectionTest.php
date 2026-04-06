<?php

declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Tests\Fixtures\Constants;
use PhpImap\Tests\Fixtures\Mailbox as FixtureMailbox;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MailboxConnectionTest extends TestCase
{
    private string $imapPath = Constants::IMAP_PATH_INBOX_NO_VALID_SSL;

    private string $login = Constants::LOGIN;

    private string $password = Constants::PASSWORD;

    private string $attachmentsDir = '.';

    /**
     * Provides test data for testing timeouts.
     *
     * @phpstan-return array<string, array{0: 'assertNull'|'expectException', 1: int, 2: list<int>}>
     */
    public static function timeoutsProvider(): array
    {
        /** @phpstan-var array<string, array{0: 'assertNull'|'expectException', 1: int, 2: list<int>}> */
        return [
            'array(IMAP_OPENTIMEOUT)' => [
                'assertNull',
                1,
                [\IMAP_OPENTIMEOUT],
            ],
            'array(IMAP_READTIMEOUT)' => [
                'assertNull',
                1,
                [\IMAP_READTIMEOUT],
            ],
            'array(IMAP_WRITETIMEOUT)' => [
                'assertNull',
                1,
                [\IMAP_WRITETIMEOUT],
            ],
            'array(IMAP_CLOSETIMEOUT)' => [
                'assertNull',
                1,
                [\IMAP_CLOSETIMEOUT],
            ],
            'array(IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT)' => [
                'assertNull',
                1,
                [\IMAP_OPENTIMEOUT, \IMAP_READTIMEOUT, \IMAP_WRITETIMEOUT, \IMAP_CLOSETIMEOUT],
            ],
        ];
    }

    /**
     * Provides test data for testing connection args.
     *
     * @phpstan-return \Generator<string, array{
     *     0: 'assertNull'|'expectException',
     *     1: int,
     *     2: 0,
     *     3: array<empty, empty>
     * }, mixed, void>
     */
    public static function connectionArgsProvider(): \Generator
    {
        yield from [
            'readonly, disable gssapi' => ['assertNull', \OP_READONLY, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'anonymous, disable gssapi' => ['assertNull', \OP_ANONYMOUS, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'half open, disable gssapi' => ['assertNull', \OP_HALFOPEN, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'expunge on close, disable gssapi' => ['assertNull', \CL_EXPUNGE, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'debug, disable gssapi' => ['assertNull', \OP_DEBUG, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'short cache, disable gssapi' => ['assertNull', \OP_SHORTCACHE, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'silent, disable gssapi' => ['assertNull', \OP_SILENT, 0, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'return driver prototype, disable gssapi' => [
                'assertNull',
                \OP_PROTOTYPE,
                0,
                ['DISABLE_AUTHENTICATOR' => 'GSSAPI'],
            ],
            'don\'t do non-secure authentication, disable gssapi' => [
                'assertNull',
                \OP_SECURE,
                0,
                ['DISABLE_AUTHENTICATOR' => 'GSSAPI'],
            ],
            'readonly, disable gssapi, 1 retry' => [
                'assertNull',
                \OP_READONLY,
                1,
                ['DISABLE_AUTHENTICATOR' => 'GSSAPI'],
            ],
            'readonly, disable gssapi, 3 retries' => [
                'assertNull',
                \OP_READONLY,
                3,
                ['DISABLE_AUTHENTICATOR' => 'GSSAPI'],
            ],
            'readonly, disable gssapi, 12 retries' => [
                'assertNull',
                \OP_READONLY,
                12,
                ['DISABLE_AUTHENTICATOR' => 'GSSAPI'],
            ],
            'readonly debug, disable gssapi' => [
                'assertNull',
                \OP_READONLY | \OP_DEBUG,
                0,
                ['DISABLE_AUTHENTICATOR' => 'GSSAPI'],
            ],
            'readonly, -1 retries' => ['expectException', \OP_READONLY, -1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'readonly, -3 retries' => ['expectException', \OP_READONLY, -3, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'readonly, -12 retries' => ['expectException', \OP_READONLY, -12, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']],
            'readonly, null options' => ['expectException', \OP_READONLY, 0, [null]],
        ];

        /** @phpstan-var list<array{0:int, 1:string}> $options */
        $options = [
            [\OP_DEBUG, 'debug'], // 1
            [\OP_READONLY, 'readonly'], // 2
            [\OP_ANONYMOUS, 'anonymous'], // 4
            [\OP_SHORTCACHE, 'short cache'], // 8
            [\OP_SILENT, 'silent'], // 16
            [\OP_PROTOTYPE, 'return driver prototype'], // 32
            [\OP_HALFOPEN, 'half-open'], // 64
            [\OP_SECURE, 'don\'t do non-secure authnetication'], // 256
            [\CL_EXPUNGE, 'expunge on close'], // 32768
        ];

        foreach ($options as $i => $optionOuter) {
            $value = $optionOuter[0];

            for ($j = $i + 1; $j < \count($options); ++$j) {
                $value |= $options[$j][0];

                $fields = [];

                foreach ($options as $optionInner) {
                    if (($value & $optionInner[0]) !== 0) {
                        $fields[] = $optionInner[1];
                    }
                }

                $key = \implode(', ', $fields);

                yield $key => ['assertNull', $value, 0, []];
                yield 'INVALID + ' . $key => ['expectException', $value | 128, 0, []];
            }
        }
    }

    /**
     * Test, that only supported timeouts can be set.
     *
     * @param int[] $types
     *
     * @phpstan-param 'assertNull'|'expectException' $assertMethod
     * @phpstan-param list<1|2|3|4> $types
     *
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('timeoutsProvider')]
    public function testSetTimeouts(string $assertMethod, int $timeout, array $types): void
    {
        $mailbox = $this->getMailbox();

        if ($assertMethod == 'expectException') {
            $this->expectException(InvalidParameterException::class);
        } else {
            $this->addToAssertionCount(1);
        }
        $mailbox->setTimeouts($timeout, $types);
    }

    /**
     * Test, that only supported and valid connection args can be set.
     *
     * @phpstan-param array{DISABLE_AUTHENTICATOR?: string}|array<empty, empty> $param
     *
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('connectionArgsProvider')]
    public function testSetConnectionArgs(
        string $assertMethod,
        int $option,
        int $retriesNum,
        ?array $param = null
    ): void {
        $mailbox = $this->getMailbox();

        if ($assertMethod == 'expectException') {
            $this->expectException(InvalidParameterException::class);
            $mailbox->setConnectionArgs($option, $retriesNum, $param);
            self::assertSame($option, $mailbox->getImapOptions());
        } elseif ($assertMethod == 'assertNull') {
            $mailbox->setConnectionArgs($option, $retriesNum, $param);
            $this->addToAssertionCount(1);
        }

        $mailbox->disconnect();
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testSetConnectionRetry(): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setConnectionRetry(5);
        $this->addToAssertionCount(1);

        $mailbox->setConnectionRetry(0);
        $this->addToAssertionCount(1);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testSetConnectionRetryDelay(): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setConnectionRetryDelay(500);
        $this->addToAssertionCount(1);

        $mailbox->setConnectionRetryDelay(0);
        $this->addToAssertionCount(1);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testSetExpungeOnDisconnect(): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setExpungeOnDisconnect(false);
        $this->addToAssertionCount(1);

        $mailbox->setExpungeOnDisconnect(true);
        $this->addToAssertionCount(1);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testHasImapStreamReturnsFalseWithoutConnection(): void
    {
        $mailbox = $this->getMailbox();

        self::assertFalse($mailbox->hasImapStream());
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testSetTimeoutsWithUnsupportedType(): void
    {
        $mailbox = $this->getMailbox();

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('You have provided at least one unsupported timeout type.');

        $mailbox->setTimeouts(1, [999]);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testSetTimeoutsWithMixedValidAndInvalidTypes(): void
    {
        $mailbox = $this->getMailbox();

        $this->expectException(InvalidParameterException::class);

        $mailbox->setTimeouts(1, [\IMAP_OPENTIMEOUT, 999]);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testSetConnectionArgsWithDefaultOptions(): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setConnectionArgs();
        $this->addToAssertionCount(1);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testSetConnectionArgsWithUnsupportedOption(): void
    {
        $mailbox = $this->getMailbox();

        $this->expectException(InvalidParameterException::class);

        $mailbox->setConnectionArgs(128);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testSetConnectionArgsWithNegativeRetries(): void
    {
        $mailbox = $this->getMailbox();

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Invalid number of retries');

        $mailbox->setConnectionArgs(\OP_READONLY, -1);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testSetConnectionArgsWithInvalidParams(): void
    {
        $mailbox = $this->getMailbox();

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Invalid array key of params');

        $mailbox->setConnectionArgs(\OP_READONLY, 0, ['INVALID_KEY' => 'value']);
    }

    /**
     * @throws InvalidParameterException
     */
    private function getMailbox(): FixtureMailbox
    {
        return new FixtureMailbox(
            $this->imapPath,
            $this->login,
            $this->password,
            $this->attachmentsDir,
            'UTF-8'
        );
    }
}
