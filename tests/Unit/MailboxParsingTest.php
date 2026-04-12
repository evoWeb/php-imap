<?php

declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use PhpImap\Entities\PartStructure;
use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Mailbox;
use PhpImap\Tests\Fixtures\Constants;
use PhpImap\Tests\Fixtures\Mailbox as FixtureMailbox;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MailboxParsingTest extends TestCase
{
    private string $imapPath = Constants::IMAP_PATH_INBOX_NO_VALID_SSL;

    private string $login = Constants::LOGIN;

    private string $password = Constants::PASSWORD;

    private string $attachmentsDir = '.';

    /**
     * @phpstan-return array<string, array{0: string, 1: string}>
     */
    public static function getMailHeaderFieldValueProvider(): array
    {
        $headers = 'From: sender@example.com
        To: recipient@example.com
        ' . sprintf(Constants::SUBJECT, 'Test Mail') . '
        Content-Type: text/plain; charset=UTF-8
        X-Mailer: PHPUnit
        MIME-Version: 1.0';

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
     * @return array<string, array<int, (string|int)>>
     */
    public static function datetimeProvider(): array
    {
        return [
            'Sun, 14 Aug 2005 16:13:03 +0000 (CEST)' => ['2005-08-14T16:13:03+00:00', 1124035983],
            'Sun, 14 Aug 2005 16:13:03 +0000' => ['2005-08-14T16:13:03+00:00', 1124035983],

            'Sun, 14 Aug 2005 16:13:03 +1000 (CEST)' => [Constants::DATE_2005, 1123999983],
            'Sun, 14 Aug 2005 16:13:03 +1000' => [Constants::DATE_2005, 1123999983],
            'Sun, 14 Aug 2005 16:13:03 -1000' => ['2005-08-15T02:13:03+00:00', 1124071983],

            'Sun, 14 Aug 2005 16:13:03 +1100 (CEST)' => ['2005-08-14T05:13:03+00:00', 1123996383],
            'Sun, 14 Aug 2005 16:13:03 +1100' => ['2005-08-14T05:13:03+00:00', 1123996383],
            'Sun, 14 Aug 2005 16:13:03 -1100' => ['2005-08-15T03:13:03+00:00', 1124075583],

            '14 Aug 2005 16:13:03 +1000 (CEST)' => [Constants::DATE_2005, 1123999983],
            '14 Aug 2005 16:13:03 +1000' => [Constants::DATE_2005, 1123999983],
            '14 Aug 2005 16:13:03 -1000' => ['2005-08-15T02:13:03+00:00', 1124071983],
        ];
    }

    /**
     * @return array<string, string[]>
     */
    public static function invalidDatetimeProvider(): array
    {
        return [
            'Sun, 14 Aug 2005 16:13:03 +9000 (CEST)' => ['Sun, 14 Aug 2005 16:13:03 +9000 (CEST)'],
            'Sun, 14 Aug 2005 16:13:03 +9000' => ['Sun, 14 Aug 2005 16:13:03 +9000'],
            'Sun, 14 Aug 2005 16:13:03 -9000' => ['Sun, 14 Aug 2005 16:13:03 -9000'],
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

        $headers = 'From: sender@example.com
        ' . sprintf(Constants::SUBJECT, 'My Subject') . '
        To: to@example.com';

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

        $headers = 'From: sender@example.com
        ' . sprintf(Constants::SUBJECT, 'Test');

        self::assertSame('', $mailbox->getMailHeaderFieldValue($headers, 'X-NonExistent'));
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testFlattenPartsSimple(): void
    {
        $mailbox = $this->getMailbox();

        $part1 = new PartStructure(type: 0, subtype: 'PLAIN');
        $part2 = new PartStructure(type: 0, subtype: 'HTML');

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

        $subPart = new PartStructure(type: 0, subtype: 'PLAIN');
        $parent = new PartStructure(type: 1, subtype: 'MIXED', parts: [$subPart]);

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

        $subPart = new PartStructure(type: 0, subtype: 'PLAIN');
        $parent = new PartStructure(type: Mailbox::PART_TYPE_TWO, subtype: 'RFC822', parts: [$subPart]);

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
     * Test, different datetimes conversions using differents timezones.
     *
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('datetimeProvider')]
    public function testParsedDateDifferentTimeZones(string $dateToParse, int $epochToCompare): void
    {
        $parsedDatetime = $this->getMailbox()->parseDateTime($dateToParse);
        $parsedDateTime = new \DateTime($parsedDatetime);
        self::assertEquals($epochToCompare, (int)$parsedDateTime->format('U'));
    }

    /**
     * Test, different invalid / unparseable datetimes conversions.
     *
     * @throws InvalidParameterException
     */
    #[Test]
    #[DataProvider('invalidDatetimeProvider')]
    public function testParsedDateWithUnparseableDateTime(string $dateToParse): void
    {
        $parsedDt = $this->getMailbox()->parseDateTime($dateToParse);
        self::assertEquals($dateToParse, $parsedDt);
    }

    /**
     * Test, parsed datetime being empty the header date.
     */
    #[Test]
    public function testParsedDateTimeWithEmptyHeaderDate(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->getMailbox()->parseDateTime('');
    }

    /**
     * Test, that the IMAP search option has a default value
     * 1 => \SE_UID
     * 2 => \SE_FREE.
     *
     * @throws \Exception
     */
    #[Test]
    public function testImapSearchOptionHasADefault(): void
    {
        self::assertEquals(1, $this->getMailbox()->getImapSearchOption());
    }

    /**
     * Test, that the IMAP search option can be changed
     * 1 => \SE_UID
     * 2 => \SE_FREE.
     *
     * @throws InvalidParameterException
     */
    #[Test]
    public function testSetAndGetImapSearchOption(): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setImapSearchOption(\SE_FREE);
        self::assertEquals(2, $mailbox->getImapSearchOption());

        $this->expectException(InvalidParameterException::class);
        $mailbox->setImapSearchOption(0);

        $mailbox->setImapSearchOption(\SE_UID);
        self::assertEquals(1, $mailbox->getImapSearchOption());
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
