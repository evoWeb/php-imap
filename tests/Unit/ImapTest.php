<?php

declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use PhpImap\Exceptions\ConnectionException;
use PhpImap\Imap;
use PhpImap\Tests\Fixtures\Constants;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImapTest extends TestCase
{
    #[Test]
    public function testSortCriteriaContainsExpectedValues(): void
    {
        self::assertContains(\SORTARRIVAL, Imap::SORT_CRITERIA);
        self::assertContains(\SORTCC, Imap::SORT_CRITERIA);
        self::assertContains(\SORTDATE, Imap::SORT_CRITERIA);
        self::assertContains(\SORTFROM, Imap::SORT_CRITERIA);
        self::assertContains(\SORTSIZE, Imap::SORT_CRITERIA);
        self::assertContains(\SORTSUBJECT, Imap::SORT_CRITERIA);
        self::assertContains(\SORTTO, Imap::SORT_CRITERIA);
        self::assertCount(7, Imap::SORT_CRITERIA);
    }

    #[Test]
    public function testTimeoutTypesContainsExpectedValues(): void
    {
        self::assertContains(\IMAP_CLOSETIMEOUT, Imap::TIMEOUT_TYPES);
        self::assertContains(\IMAP_OPENTIMEOUT, Imap::TIMEOUT_TYPES);
        self::assertContains(\IMAP_READTIMEOUT, Imap::TIMEOUT_TYPES);
        self::assertContains(\IMAP_WRITETIMEOUT, Imap::TIMEOUT_TYPES);
        self::assertCount(4, Imap::TIMEOUT_TYPES);
    }

    #[Test]
    public function testCloseFlagsContainsExpectedValues(): void
    {
        self::assertContains(0, Imap::CLOSE_FLAGS);
        self::assertContains(\CL_EXPUNGE, Imap::CLOSE_FLAGS);
        self::assertCount(2, Imap::CLOSE_FLAGS);
    }

    /**
     * @phpstan-return array<string, array{0: string, 1: string}>
     */
    public static function encodeDecodeUtf7ImapProvider(): array
    {
        return [
            'ASCII' => ['INBOX', 'INBOX'],
            'German umlauts' => ['Über uns', 'Über uns'],
            'French accents' => [Constants::ENVOYES, Constants::ENVOYES],
            'Japanese' => ['日本語', '日本語'],
            'Chinese' => ['简体中文', '简体中文'],
            'Russian' => ['русский', 'русский'],
            'Arabic' => ['العربية', 'العربية'],
            'Korean' => ['한국어', '한국어'],
            'empty string' => ['', ''],
            'special chars' => ['Sent Items/Archive', 'Sent Items/Archive'],
        ];
    }

    #[Test]
    #[DataProvider('encodeDecodeUtf7ImapProvider')]
    public function testEncodeStringToUtf7ImapAndBack(string $input, string $expected): void
    {
        $encoded = Imap::encodeStringToUtf7Imap($input);
        $decoded = Imap::decodeStringFromUtf7ImapToUtf8($encoded);

        self::assertSame($expected, $decoded);
    }

    #[Test]
    public function testEncodeStringToUtf7ImapProducesValidUtf7(): void
    {
        $result = Imap::encodeStringToUtf7Imap(Constants::ENVOYES);

        self::assertSame('&AMk-l&AOk-ments envoy&AOk-s', $result);
    }

    #[Test]
    public function testEnsureConnectionWithInvalidArgument(): void
    {
        $this->expectException(ConnectionException::class);

        Imap::ensureConnection('not a connection', 'testMethod', 1);
    }

    #[Test]
    public function testEnsureConnectionWithNull(): void
    {
        $this->expectException(ConnectionException::class);

        Imap::ensureConnection(null, 'testMethod', 1);
    }

    #[Test]
    public function testEnsureConnectionWithInteger(): void
    {
        $this->expectException(ConnectionException::class);

        Imap::ensureConnection(42, 'someMethod', 2);
    }

    #[Test]
    public function testMailComposeSimple(): void
    {
        $envelope = ['subject' => 'Test Subject'];
        $body = [
            [
                'type' => \TYPETEXT,
                'contents.data' => Constants::HELLO_WORLD,
            ],
        ];

        $result = Imap::mailCompose($envelope, $body);

        self::assertStringContainsString(sprintf(Constants::SUBJECT, 'Test Subject'), $result);
        self::assertStringContainsString(Constants::HELLO_WORLD, $result);
    }

    #[Test]
    public function testMailComposeMultipart(): void
    {
        $envelope = ['subject' => 'Multipart Test'];
        $body = [
            [
                'type' => \TYPEMULTIPART,
            ],
            [
                'type' => \TYPETEXT,
                'contents.data' => 'plain text',
            ],
            [
                'type' => \TYPETEXT,
                'subtype' => 'html',
                'contents.data' => '<b>html</b>',
            ],
        ];

        $result = Imap::mailCompose($envelope, $body);

        self::assertStringContainsString(sprintf(Constants::SUBJECT, 'Multipart Test'), $result);
        self::assertStringContainsString('MULTIPART/MIXED', $result);
        self::assertStringContainsString('plain text', $result);
        self::assertStringContainsString('<b>html</b>', $result);
    }

    #[Test]
    public function testFlushImapErrorsDoesNotThrow(): void
    {
        Imap::flushImapErrors();

        $this->addToAssertionCount(1);
    }
}
