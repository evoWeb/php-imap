<?php

declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Mailbox;
use PhpImap\Tests\Fixtures\Mailbox as FixtureMailbox;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MailboxExtendedTest extends TestCase
{
    private string $imapPath = '{imap.example.com:993/imap/ssl/novalidate-cert}INBOX';

    private string $login = 'php-imap@example.com';

    private string $password = 'v3rY!53cEt&P4sSWöRd$';

    private string $attachmentsDir = '.';

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testConstructorWithTrimImapPathFalse(): void
    {
        $imapPath = '  {imap.example.com:993/imap/ssl}INBOX  ';
        $mailbox = new FixtureMailbox($imapPath, $this->login, $this->password, $this->attachmentsDir, 'UTF-8', false);

        self::assertSame($imapPath, $mailbox->getImapPath());
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testConstructorWithTrimImapPathTrue(): void
    {
        $imapPath = '  {imap.example.com:993/imap/ssl}INBOX  ';
        $mailbox = new FixtureMailbox($imapPath, $this->login, $this->password, $this->attachmentsDir, 'UTF-8', true);

        self::assertSame('{imap.example.com:993/imap/ssl}INBOX', $mailbox->getImapPath());
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testConstructorWithAttachmentFilenameModeTrue(): void
    {
        $mailbox = new Mailbox(
            $this->imapPath,
            $this->login,
            $this->password,
            $this->attachmentsDir,
            'UTF-8',
            true,
            true
        );

        self::assertTrue($mailbox->getAttachmentFilenameMode());
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testConstructorWithAttachmentFilenameModeFalse(): void
    {
        $mailbox = new Mailbox(
            $this->imapPath,
            $this->login,
            $this->password,
            $this->attachmentsDir,
            'UTF-8',
            true,
            false
        );

        self::assertFalse($mailbox->getAttachmentFilenameMode());
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testConstructorWithNullAttachmentsDir(): void
    {
        new Mailbox($this->imapPath, $this->login, $this->password, null);

        // attachmentsDir is uninitialized when null is passed, so we just verify construction succeeds
        $this->addToAssertionCount(1);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testSetAndGetAttachmentFilenameMode(): void
    {
        $mailbox = $this->getMailbox();

        self::assertFalse($mailbox->getAttachmentFilenameMode());

        $mailbox->setAttachmentFilenameMode(true);
        self::assertTrue($mailbox->getAttachmentFilenameMode());

        $mailbox->setAttachmentFilenameMode(false);
        self::assertFalse($mailbox->getAttachmentFilenameMode());
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
    public function testGetImapPath(): void
    {
        $mailbox = $this->getMailbox();

        self::assertSame($this->imapPath, $mailbox->getImapPath());
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testSetAttachmentsDirSuccess(): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setAttachmentsDir(\sys_get_temp_dir());

        self::assertSame(\rtrim(\realpath(\sys_get_temp_dir()), '\\/'), $mailbox->getAttachmentsDir());
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testSetAttachmentsDirWithNonExistentDirectory(): void
    {
        $mailbox = $this->getMailbox();

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Directory "/nonexistent/path" not found');

        $mailbox->setAttachmentsDir('/nonexistent/path');
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testSetAttachmentsDirWithEmptyString(): void
    {
        $mailbox = $this->getMailbox();

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('setAttachmentsDir() expects a string as first parameter!');

        $mailbox->setAttachmentsDir('');
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
     * @phpstan-return array<string, array{0: string, 1: bool}>
     */
    public static function isUrlEncodedProvider(): array
    {
        return [
            'encoded string' => ['hello%20world', true],
            'encoded special chars' => ['file%2Fname%3Fquery', true],
            'not encoded - plain' => ['hello world', false],
            'not encoded - no percent' => ['helloworld', false],
            'encoded with plus' => ['hello+world%20test', true],
            'only percent without valid hex' => ['100%', false],
        ];
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
     * @phpstan-return array<string, array{0: string, 1: string}>
     */
    public static function convertToUtf8Provider(): array
    {
        return [
            'UTF-8 passthrough' => ['Hello World', 'utf-8'],
            'default charset passthrough' => ['Hello World', 'default'],
            'ASCII passthrough' => ['Hello World', 'default'],
            'ISO-8859-1 umlaut' => [\mb_convert_encoding('Ä', 'ISO-8859-1', 'UTF-8'), 'iso-8859-1'],
            'unknown charset fallback' => ['test string', 'x-unknown-charset-999'],
        ];
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
     * @phpstan-return array<string, array{0: string, 1: string}>
     */
    public static function getMailHeaderFieldValueProvider(): array
    {
        $headers = "From: sender@example.com\r\nTo: recipient@example.com\r\nSubject: Test Mail\r\nContent-Type: text/plain; charset=UTF-8\r\nX-Mailer: PHPUnit\r\nMIME-Version: 1.0";

        return [
            'From header' => [$headers, 'From'],
            'To header' => [$headers, 'To'],
            'Subject header' => [$headers, 'Subject'],
            'Content-Type header' => [$headers, 'Content-Type'],
            'X-Mailer header' => [$headers, 'X-Mailer'],
            'MIME-Version header' => [$headers, 'MIME-Version'],
        ];
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    #[DataProvider('getMailHeaderFieldValueProvider')]
    public function testGetMailHeaderFieldValue(string $headers, string $fieldName): void
    {
        $mailbox = $this->getMailbox();

        $result = $mailbox->getMailHeaderFieldValue($headers, $fieldName);

        self::assertNotEmpty($result);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testGetMailHeaderFieldValueReturnsCorrectValue(): void
    {
        $mailbox = $this->getMailbox();

        $headers = "From: sender@example.com\r\nSubject: My Subject\r\nTo: to@example.com";

        self::assertSame('sender@example.com', $mailbox->getMailHeaderFieldValue($headers, 'From'));
        self::assertSame('My Subject', $mailbox->getMailHeaderFieldValue($headers, 'Subject'));
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testGetMailHeaderFieldValueMissingField(): void
    {
        $mailbox = $this->getMailbox();

        $headers = "From: sender@example.com\r\nSubject: Test";

        self::assertSame('', $mailbox->getMailHeaderFieldValue($headers, 'X-NonExistent'));
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testFlattenPartsSimple(): void
    {
        $mailbox = $this->getMailbox();

        $part1 = new \stdClass();
        $part1->type = 0;
        $part1->subtype = 'PLAIN';

        $part2 = new \stdClass();
        $part2->type = 0;
        $part2->subtype = 'HTML';

        $result = $mailbox->flattenParts([$part1, $part2]);

        self::assertCount(2, $result);
        self::assertArrayHasKey('1', $result);
        self::assertArrayHasKey('2', $result);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testFlattenPartsNested(): void
    {
        $mailbox = $this->getMailbox();

        $subPart = new \stdClass();
        $subPart->type = 0;
        $subPart->subtype = 'PLAIN';

        $parent = new \stdClass();
        $parent->type = 1; // TYPEMULTIPART
        $parent->subtype = 'MIXED';
        $parent->parts = [$subPart];

        $result = $mailbox->flattenParts([$parent]);

        self::assertArrayHasKey('1', $result);
        self::assertArrayHasKey('1.1', $result);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testFlattenPartsTypeTwo(): void
    {
        $mailbox = $this->getMailbox();

        $subPart = new \stdClass();
        $subPart->type = 0;
        $subPart->subtype = 'PLAIN';

        $parent = new \stdClass();
        $parent->type = Mailbox::PART_TYPE_TWO;
        $parent->subtype = 'RFC822';
        $parent->parts = [$subPart];

        $result = $mailbox->flattenParts([$parent]);

        self::assertArrayHasKey('1', $result);
        self::assertArrayHasKey('1.1', $result);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testFlattenPartsEmpty(): void
    {
        $mailbox = $this->getMailbox();

        $result = $mailbox->flattenParts([]);

        self::assertSame([], $result);
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
     * @phpstan-return array<string, array{0: string, 1: string}>
     */
    public static function getCombinedPathProvider(): array
    {
        return [
            'empty folder returns imapPath' => [
                '{imap.example.com:993}INBOX',
                '',
            ],
            'spaces only returns imapPath' => [
                '{imap.example.com:993}INBOX',
                '   ',
            ],
            'folder appended with delimiter' => [
                '{imap.example.com:993}INBOX',
                'Sent',
            ],
            'path ending with brace' => [
                '{imap.example.com:993}',
                'INBOX',
            ],
        ];
    }

    #[Test]
    public function testGetCombinedPathEmptyFolder(): void
    {
        $mailbox = new FixtureMailbox('{imap.example.com:993}INBOX', '', '');

        self::assertSame('{imap.example.com:993}INBOX', $mailbox->exposedGetCombinedPath(''));
    }

    #[Test]
    public function testGetCombinedPathSpacesOnly(): void
    {
        $mailbox = new FixtureMailbox('{imap.example.com:993}INBOX', '', '');

        self::assertSame('{imap.example.com:993}INBOX', $mailbox->exposedGetCombinedPath('   '));
    }

    #[Test]
    public function testGetCombinedPathWithFolder(): void
    {
        $mailbox = new FixtureMailbox('{imap.example.com:993}INBOX', '', '');

        self::assertSame('{imap.example.com:993}INBOX.Sent', $mailbox->exposedGetCombinedPath('Sent'));
    }

    #[Test]
    public function testGetCombinedPathEndingWithBrace(): void
    {
        $mailbox = new FixtureMailbox('{imap.example.com:993}', '', '');

        self::assertSame('{imap.example.com:993}INBOX', $mailbox->exposedGetCombinedPath('INBOX'));
    }

    #[Test]
    public function testGetCombinedPathAbsolute(): void
    {
        $mailbox = new FixtureMailbox('{imap.example.com:993}INBOX', '', '');

        self::assertSame('{imap.example.com:993}Sent', $mailbox->exposedGetCombinedPath('Sent', true));
    }

    #[Test]
    public function testGetCombinedPathAbsoluteWithSlash(): void
    {
        $mailbox = new FixtureMailbox('{imap.example.com:993}INBOX', '', '');

        self::assertSame('{imap.example.com:993}', $mailbox->exposedGetCombinedPath('/', true));
    }

    #[Test]
    public function testGetCombinedPathAbsoluteWithoutBrace(): void
    {
        $mailbox = new FixtureMailbox('no-brace-path', '', '');

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('"}" was not present in IMAP path!');

        $mailbox->exposedGetCombinedPath('Folder', true);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testGetCombinedPathWithSlashDelimiter(): void
    {
        $mailbox = new FixtureMailbox('{imap.example.com:993}INBOX', '', '');
        $mailbox->setPathDelimiter('/');

        self::assertSame('{imap.example.com:993}INBOX/Sent', $mailbox->exposedGetCombinedPath('Sent'));
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testDecodeRFC2231Plain(): void
    {
        $mailbox = $this->getMailbox();

        self::assertSame('plain.txt', $mailbox->exposedDecodeRFC2231('plain.txt'));
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testDecodeRFC2231Encoded(): void
    {
        $mailbox = $this->getMailbox();

        $result = $mailbox->exposedDecodeRFC2231("utf-8''t%C3%A9st.txt");

        self::assertSame('tést.txt', $result);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testDecodeRFC2231NotUrlEncoded(): void
    {
        $mailbox = $this->getMailbox();

        $result = $mailbox->exposedDecodeRFC2231("utf-8''plainfile.txt");

        self::assertSame("utf-8''plainfile.txt", $result);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetEmailAndNameFromRecipientWithMailboxAndHost(): void
    {
        $mailbox = $this->getMailbox();

        $recipient = new \stdClass();
        $recipient->mailbox = 'john';
        $recipient->host = 'example.com';
        $recipient->personal = 'John Doe';

        $result = $mailbox->exposedPossiblyGetEmailAndNameFromRecipient($recipient);

        self::assertNotNull($result);
        self::assertSame('john@example.com', $result[0]);
        self::assertSame('John Doe', $result[1]);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetEmailAndNameFromRecipientWithoutPersonal(): void
    {
        $mailbox = $this->getMailbox();

        $recipient = new \stdClass();
        $recipient->mailbox = 'jane';
        $recipient->host = 'example.com';

        $result = $mailbox->exposedPossiblyGetEmailAndNameFromRecipient($recipient);

        self::assertNotNull($result);
        self::assertSame('jane@example.com', $result[0]);
        self::assertNull($result[1]);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetEmailAndNameFromRecipientWithEmptyMailbox(): void
    {
        $mailbox = $this->getMailbox();

        $recipient = new \stdClass();
        $recipient->mailbox = '';
        $recipient->host = 'example.com';

        $result = $mailbox->exposedPossiblyGetEmailAndNameFromRecipient($recipient);

        self::assertNull($result);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetEmailAndNameFromRecipientWithEmptyHost(): void
    {
        $mailbox = $this->getMailbox();

        $recipient = new \stdClass();
        $recipient->mailbox = 'john';
        $recipient->host = '';

        $result = $mailbox->exposedPossiblyGetEmailAndNameFromRecipient($recipient);

        self::assertNull($result);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetEmailAndNameFromRecipientWithoutProperties(): void
    {
        $mailbox = $this->getMailbox();

        $recipient = new \stdClass();

        $result = $mailbox->exposedPossiblyGetEmailAndNameFromRecipient($recipient);

        self::assertNull($result);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetEmailAndNameFromRecipientWithEmptyPersonal(): void
    {
        $mailbox = $this->getMailbox();

        $recipient = new \stdClass();
        $recipient->mailbox = 'john';
        $recipient->host = 'example.com';
        $recipient->personal = '   ';

        $result = $mailbox->exposedPossiblyGetEmailAndNameFromRecipient($recipient);

        self::assertNotNull($result);
        self::assertSame('john@example.com', $result[0]);
        self::assertNull($result[1]);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetHostNameAndAddress(): void
    {
        $mailbox = $this->getMailbox();

        $entry = new \stdClass();
        $entry->mailbox = 'john';
        $entry->host = 'example.com';
        $entry->personal = 'John Doe';

        $result = $mailbox->exposedPossiblyGetHostNameAndAddress([$entry]);

        self::assertSame('example.com', $result[0]);
        self::assertSame('John Doe', $result[1]);
        self::assertSame('john@example.com', $result[2]);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetHostNameAndAddressWithTwoEntries(): void
    {
        $mailbox = $this->getMailbox();

        $entry0 = new \stdClass();
        $entry0->mailbox = 'john';
        $entry0->host = 'example.com';

        $entry1 = new \stdClass();
        $entry1->host = 'fallback.com';
        $entry1->personal = 'Fallback Name';

        $result = $mailbox->exposedPossiblyGetHostNameAndAddress([$entry0, $entry1]);

        self::assertSame('example.com', $result[0]);
        self::assertSame('Fallback Name', $result[1]);
        self::assertSame('john@example.com', $result[2]);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetHostNameAndAddressFallbackHost(): void
    {
        $mailbox = $this->getMailbox();

        $entry0 = new \stdClass();
        $entry0->mailbox = 'john';

        $entry1 = new \stdClass();
        $entry1->host = 'fallback.com';
        $entry1->personal = 'Name';

        $result = $mailbox->exposedPossiblyGetHostNameAndAddress([$entry0, $entry1]);

        self::assertSame('fallback.com', $result[0]);
    }

    #[Test]
    public function testSetMailboxFolderFromImapPath(): void
    {
        $mailbox = new Mailbox('{imap.example.com:993}Sent', '', '');

        self::assertSame('{imap.example.com:993}Sent', $mailbox->getImapPath());
    }

    #[Test]
    public function testSetMailboxFolderDefaultsToInbox(): void
    {
        $mailbox = new Mailbox('{imap.example.com:993}', '', '');

        self::assertSame('{imap.example.com:993}', $mailbox->getImapPath());
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
     * @throws \Exception
     */
    #[Test]
    public function testParseDateTimeWithValidDate(): void
    {
        $mailbox = $this->getMailbox();

        $result = $mailbox->parseDateTime('2023-01-15 10:30:00');

        self::assertNotEmpty($result);
        self::assertNotSame('2023-01-15 10:30:00', $result);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testParseDateTimeWithUnparseable(): void
    {
        $mailbox = $this->getMailbox();

        $result = $mailbox->parseDateTime('not a real date at all xyz');

        self::assertSame('not a real date at all xyz', $result);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testSetImapSearchOptionSeUid(): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setImapSearchOption(\SE_UID);
        self::assertSame(\SE_UID, $mailbox->getImapSearchOption());
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testSetImapSearchOptionSeFree(): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setImapSearchOption(\SE_FREE);
        self::assertSame(\SE_FREE, $mailbox->getImapSearchOption());
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testDecodeMimeStrFalseReturn(): void
    {
        $mailbox = $this->getMailbox();

        $result = $mailbox->decodeMimeStr('plain ascii text');

        self::assertSame('plain ascii text', $result);
    }

    /**
     * @throws \Exception
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
    protected function getMailbox(): FixtureMailbox
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
