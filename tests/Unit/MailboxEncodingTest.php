<?php

declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Mailbox;
use PhpImap\Tests\Fixtures\Constants;
use PhpImap\Tests\Fixtures\Mailbox as FixtureMailbox;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MailboxEncodingTest extends TestCase
{
    private string $imapPath = Constants::IMAP_PATH_INBOX_NO_VALID_SSL;

    private string $login = Constants::LOGIN;

    private string $password = Constants::PASSWORD;

    private string $attachmentsDir = '.';

    /**
     * @phpstan-return non-empty-list<array{0: 'UTF-8'|'Windows-1251'|'Windows-1252'}>
     *
     * @return string[][]
     */
    public static function setAndGetServerEncodingProvider(): array
    {
        $data = [
            ['UTF-8'],
        ];

        $supported = \mb_list_encodings();

        foreach (
            [
                'Windows-1251',
                'Windows-1252',
            ] as $perhaps
        ) {
            if (
                \in_array(\trim($perhaps), $supported, true)
                || \in_array(\strtoupper(\trim($perhaps)), $supported, true)
            ) {
                $data[] = [$perhaps];
            }
        }

        return $data;
    }

    /**
     * Provides test data for testing server encodings.
     *
     * @phpstan-return array{
     *      'UTF-7': array{0: true, 1: 'UTF-7'},
     *      UTF7-IMAP: array{0: true, 1: 'UTF7-IMAP'},
     *      UTF-8: array{0: true, 1: 'UTF-8'},
     *      ASCII: array{0: true, 1: 'ASCII'},
     *      US-ASCII: array{0: true, 1: 'US-ASCII'},
     *      ISO-8859-1: array{0: true, 1: 'ISO-8859-1'},
     *      UTF7: array{0: false, 1: 'UTF7'},
     *      UTF-7-IMAP: array{0: false, 1: 'UTF-7-IMAP'},
     *      UTF-7IMAP: array{0: false, 1: 'UTF-7IMAP'},
     *      UTF8: array{0: false, 1: 'UTF8'},
     *      USASCII: array{0: false, 1: 'USASCII'},
     *      ASC11: array{0: false, 1: 'ASC11'},
     *      ISO-8859-0: array{0: false, 1: 'ISO-8859-0'},
     *      ISO-8855-1: array{0: false, 1: 'ISO-8855-1'},
     *      ISO-8859: array{0: false, 1: 'ISO-8859'}
     * }
     */
    public static function serverEncodingProvider(): array
    {
        return [
            // Supported encodings
            'UTF-7' => [true, 'UTF-7'],
            'UTF7-IMAP' => [true, 'UTF7-IMAP'],
            'UTF-8' => [true, 'UTF-8'],
            'ASCII' => [true, 'ASCII'],
            'US-ASCII' => [true, 'US-ASCII'],
            'ISO-8859-1' => [true, 'ISO-8859-1'],
            // NOT supported encodings
            'UTF7' => [false, 'UTF7'],
            'UTF-7-IMAP' => [false, 'UTF-7-IMAP'],
            'UTF-7IMAP' => [false, 'UTF-7IMAP'],
            'UTF8' => [false, 'UTF8'],
            'USASCII' => [false, 'USASCII'],
            'ASC11' => [false, 'ASC11'],
            'ISO-8859-0' => [false, 'ISO-8859-0'],
            'ISO-8855-1' => [false, 'ISO-8855-1'],
            'ISO-8859' => [false, 'ISO-8859'],
        ];
    }

    /**
     * @phpstan-return array<string, array{0: string, 1: bool}>
     */
    public static function isUrlEncodedProvider(): array
    {
        return [
            'encoded string' => ['hello%20world', true],
            'encoded special chars' => ['file%2Fname%3Fquery', true],
            'not encoded - plain' => [Constants::HELLO_WORLD, false],
            'not encoded - no percent' => ['helloworld', false],
            'encoded with plus' => ['hello+world%20test', true],
            'only percent without valid hex' => ['100%', false],
        ];
    }

    /**
     * @phpstan-return array<string, array{0: string, 1: string}>
     */
    public static function convertToUtf8Provider(): array
    {
        return [
            'UTF-8 passthrough' => [Constants::HELLO_WORLD, 'utf-8'],
            'default charset passthrough' => [Constants::HELLO_WORLD, 'default'],
            'ASCII passthrough' => [Constants::HELLO_WORLD, 'default'],
            'ISO-8859-1 umlaut' => [\mb_convert_encoding('Ä', 'ISO-8859-1', 'UTF-8'), 'iso-8859-1'],
            'unknown charset fallback' => ['test string', 'x-unknown-charset-999'],
        ];
    }

    /**
     * Test, that the server encoding can be set.
     *
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('setAndGetServerEncodingProvider')]
    public function testSetAndGetServerEncoding(string $encoding): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setServerEncoding($encoding);

        $encoding = \strtoupper(\trim($encoding));

        self::assertEquals($encoding, $mailbox->getServerEncoding());
    }

    /**
     * Test, that server encoding that all functions uppers the server encoding setting.
     *
     * @throws InvalidParameterException
     */
    #[Test]
    public function testServerEncodingUppersSetting(): void
    {
        $mailbox = new Mailbox(
            $this->imapPath,
            $this->login,
            $this->password,
            $this->attachmentsDir,
            'utf-8'
        );
        self::assertSame('UTF-8', $mailbox->getServerEncoding());

        $mailbox = new Mailbox(
            $this->imapPath,
            $this->login,
            $this->password,
            $this->attachmentsDir,
            'UTF7-IMAP'
        );
        $mailbox->setServerEncoding('uTf-8');
        self::assertSame('UTF-8', $mailbox->getServerEncoding());
    }

    /**
     * Test, that server encoding only can use supported character encodings.
     *
     * @throws InvalidParameterException
     */
    #[Test]
    #[DataProvider('serverEncodingProvider')]
    public function testServerEncodingOnlyUseSupportedSettings(bool $bool, string $encoding): void
    {
        $mailbox = $this->getMailbox();

        if ($bool) {
            $mailbox->setServerEncoding($encoding);
            self::assertEquals($encoding, $mailbox->getServerEncoding());
        } else {
            $this->expectException(InvalidParameterException::class);
            $mailbox->setServerEncoding($encoding);
            self::assertNotEquals($encoding, $mailbox->getServerEncoding());
        }
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    #[DataProvider('isUrlEncodedProvider')]
    public function testIsUrlEncoded(string $string, bool $expected): void
    {
        $mailbox = $this->getMailbox();

        self::assertSame($expected, $mailbox->isUrlEncoded($string));
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    #[DataProvider('convertToUtf8Provider')]
    public function testConvertToUtf8(string $input, string $charset): void
    {
        $mailbox = $this->getMailbox();

        $result = $mailbox->convertToUtf8($input, $charset);

        self::assertIsString($result);
        self::assertNotEmpty($result);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testConvertToUtf8FromIso88591(): void
    {
        $mailbox = $this->getMailbox();

        $iso = \mb_convert_encoding('Ärger mit Ölförderung', 'ISO-8859-1', 'UTF-8');

        $result = $mailbox->convertToUtf8($iso, 'iso-8859-1');

        self::assertSame('Ärger mit Ölförderung', $result);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testConvertToUtf8Utf8Passthrough(): void
    {
        $mailbox = $this->getMailbox();

        $result = $mailbox->convertToUtf8('日本語テスト', 'utf-8');

        self::assertSame('日本語テスト', $result);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testConvertToUtf8DefaultCharset(): void
    {
        $mailbox = $this->getMailbox();

        $result = $mailbox->convertToUtf8('Test', 'default');

        self::assertSame('Test', $result);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testConvertToUtf8WithDefaultDefaultCharset(): void
    {
        $mailbox = $this->getMailbox();
        $mailbox->decodeMimeStrDefaultCharset = 'utf-8';

        $result = $mailbox->convertToUtf8('Test', 'default');

        self::assertSame('Test', $result);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testLowercaseMbListEncodings(): void
    {
        $mailbox = $this->getMailbox();

        $result = $mailbox->exposedLowercaseMbListEncodings();

        self::assertIsArray($result);
        self::assertNotEmpty($result);

        foreach ($result as $encoding) {
            self::assertSame(\strtolower($encoding), $encoding);
        }

        self::assertContains('utf-8', $result);
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
