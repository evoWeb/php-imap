<?php

/**
 * Mailbox - PHPUnit tests.
 *
 * @author Sebastian Kraetzig <sebastian-kraetzig@gmx.de>
 */
declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Mailbox;
use PhpImap\Tests\Fixtures\Mailbox as FixtureMailbox;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MailboxTest extends TestCase
{
    public const ANYTHING = 0;

    /**
     * Holds the imap path.
     */
    private string $imapPath = '{imap.example.com:993/imap/ssl/novalidate-cert}INBOX';

    /**
     * Holds the imap username.
     */
    private string $login = 'php-imap@example.com';

    /**
     * Holds the imap user password.
     */
    private string $password = 'v3rY!53cEt&P4sSWöRd$';

    /**
     * Holds the relative name of the directory, where email attachments will be saved.
     */
    private string $attachmentsDir = '.';

    /**
     * Holds the server encoding setting.
     */
    private string $serverEncoding = 'UTF-8';

    /**
     * Test, that the constructor trims possible variables
     * Leading and ending spaces are not even possible in some variables.
     *
     * @throws \Exception
     */
    public function testConstructorTrimsPossibleVariables(): void
    {
        $imapPath = ' {imap.example.com:993/imap/ssl}INBOX     ';
        $login = '    php-imap@example.com';
        $password = '  v3rY!53cEt&P4sSWöRd$';
        // directory names can contain spaces before AND after on
        // Linux/Unix systems. Windows trims these spaces automatically.
        $attachmentsDir = '.';
        $serverEncoding = 'UTF-8  ';

        $mailbox = new FixtureMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        self::assertSame('{imap.example.com:993/imap/ssl}INBOX', $mailbox->getImapPath());
        self::assertSame('php-imap@example.com', $mailbox->getLogin());
        self::assertSame('  v3rY!53cEt&P4sSWöRd$', $mailbox->getImapPassword());
        self::assertSame(\realpath('.'), $mailbox->getAttachmentsDir());
        self::assertSame('UTF-8', $mailbox->getServerEncoding());
    }

    /**
     * @phpstan-return non-empty-list<array{0: 'UTF-8'|'Windows-1251'|'Windows-1252'}>
     *
     * @return string[][]
     */
    public static function SetAndGetServerEncodingProvider(): array
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
     * Test, that the server encoding can be set.
     *
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('SetAndGetServerEncodingProvider')]
    public function testSetAndGetServerEncoding(string $encoding): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setServerEncoding($encoding);

        $encoding = \strtoupper(\trim($encoding));

        self::assertEquals($mailbox->getServerEncoding(), $encoding);
    }

    /**
     * Test, that server encoding is set to a default value.
     *
     * @throws InvalidParameterException
     */
    public function testServerEncodingHasDefaultSetting(): void
    {
        // Default character encoding should be set
        $mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir);
        self::assertSame('UTF-8', $mailbox->getServerEncoding());
    }

    /**
     * Test, that server encoding that all functions uppers the server encoding setting.
     *
     * @throws InvalidParameterException
     */
    public function testServerEncodingUppersSetting(): void
    {
        // Server encoding should be always upper formatted
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
     * Provides test data for testing server encodings.
     *
     * @return array<string, (string|bool)>
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
     * Test, that the IMAP search option has a default value
     * 1 => \SE_UID
     * 2 => \SE_FREE.
     */
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
    public function testSetAndGetImapSearchOption(): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setImapSearchOption(\SE_FREE);
        self::assertEquals(2, $mailbox->getImapSearchOption());

        $this->expectException(InvalidParameterException::class);
        $mailbox->setImapSearchOption(self::ANYTHING);

        $mailbox->setImapSearchOption(\SE_UID);
        self::assertEquals(1, $mailbox->getImapSearchOption());
    }

    /**
     * Test, that the imap login can be retrieved.
     */
    public function testGetLogin(): void
    {
        self::assertEquals('php-imap@example.com', $this->getMailbox()->getLogin());
    }

    /**
     * Test, that the path delimiter has a default value.
     */
    public function testPathDelimiterHasADefault(): void
    {
        self::assertNotEmpty($this->getMailbox()->getPathDelimiter());
    }

    /**
     * Provides test data for testing path delimiter.
     *
     * @return array<int|string, string[]>
     */
    public static function pathDelimiterProvider(): array
    {
        return [
            '0' => ['0'],
            '1' => ['1'],
            '2' => ['2'],
            '3' => ['3'],
            '4' => ['4'],
            '5' => ['5'],
            '6' => ['6'],
            '7' => ['7'],
            '8' => ['8'],
            '9' => ['9'],
            'a' => ['a'],
            'b' => ['b'],
            'c' => ['c'],
            'd' => ['d'],
            'e' => ['e'],
            'f' => ['f'],
            'g' => ['g'],
            'h' => ['h'],
            'i' => ['i'],
            'j' => ['j'],
            'k' => ['k'],
            'l' => ['l'],
            'm' => ['m'],
            'n' => ['n'],
            'o' => ['o'],
            'p' => ['p'],
            'q' => ['q'],
            'r' => ['r'],
            's' => ['s'],
            't' => ['t'],
            'u' => ['u'],
            'v' => ['v'],
            'w' => ['w'],
            'x' => ['x'],
            'y' => ['y'],
            'z' => ['z'],
            '!' => ['!'],
            '\\' => ['\\'],
            '$' => ['$'],
            '%' => ['%'],
            '§' => ['§'],
            '&' => ['&'],
            '/' => ['/'],
            '(' => ['('],
            ')' => [')'],
            '=' => ['='],
            '#' => ['#'],
            '~' => ['~'],
            '*' => ['*'],
            '+' => ['+'],
            ',' => [','],
            ';' => [';'],
            '.' => ['.'],
            ':' => [':'],
            '<' => ['<'],
            '>' => ['>'],
            '|' => ['|'],
            '_' => ['_'],
        ];
    }

    /**
     * Test, that the path delimiter is checked for supported chars.
     */
    #[Test]
    #[DataProvider('pathDelimiterProvider')]
    public function testPathDelimiterIsBeingChecked(string $str): void
    {
        $supported_delimiters = ['.', '/'];

        $mailbox = $this->getMailbox();

        if (\in_array($str, $supported_delimiters)) {
            self::assertTrue($mailbox->validatePathDelimiter($str));
        } else {
            $this->expectException(InvalidParameterException::class);
            $mailbox->setPathDelimiter($str);
        }
    }

    /**
     * Test, that the path delimiter can be set.
     *
     * @throws InvalidParameterException
     */
    public function testSetAndGetPathDelimiter(): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setPathDelimiter('.');
        self::assertEquals('.', $mailbox->getPathDelimiter());

        $mailbox->setPathDelimiter('/');
        self::assertEquals('/', $mailbox->getPathDelimiter());
    }

    /**
     * Test, that the attachments are not ignored by default.
     */
    public function testGetAttachmentsAreNotIgnoredByDefault(): void
    {
        self::assertFalse($this->getMailbox()->getAttachmentsIgnore());
    }

    /**
     * Provides test data for testing attachments ignore.
     *
     * @phpstan-return array<string, array{0: bool}>
     */
    public static function attachmentsIgnoreProvider(): array
    {
        /** @phpstan-var array<string, array{0: bool}> */
        return [
            'true' => [true],
            'false' => [false],
        ];
    }

    /**
     * Test, that attachments can be ignored and only valid values are accepted.
     */
    #[Test]
    #[DataProvider('attachmentsIgnoreProvider')]
    public function testSetAttachmentsIgnore(bool $paramValue): void
    {
        $mailbox = $this->getMailbox();
        $mailbox->setAttachmentsIgnore($paramValue);
        self::assertEquals($mailbox->getAttachmentsIgnore(), $paramValue);
    }

    /**
     * Provides test data for testing encoding.
     *
     * @phpstan-return array{
     *      Avañe’ẽ: array{0: 'Avañe’ẽ'},
     *      azərbaycanca: array{0: 'azərbaycanca'},
     *      Bokmål: array{0: 'Bokmål'},
     *      chiCheŵa: array{0: 'chiCheŵa'},
     *      Deutsch: array{0: 'Deutsch'},
     *      'U.S. English': array{0: 'U.S. English'},
     *      français: array{0: 'français'},
     *      'Éléments envoyés': array{0: 'Éléments envoyés'},
     *      føroyskt: array{0: 'føroyskt'},
     *      Kĩmĩrũ: array{0: 'Kĩmĩrũ'},
     *      Kɨlaangi: array{0: 'Kɨlaangi'},
     *      oʼzbekcha: array{0: 'oʼzbekcha'},
     *      Plattdüütsch: array{0: 'Plattdüütsch'},
     *      română: array{0: 'română'}, Sängö: array{0: 'Sängö'},
     *      'Tiếng Việt': array{0: 'Tiếng Việt'},
     *      ɔl-Maa: array{0: 'ɔl-Maa'},
     *      Ελληνικά: array{0: 'Ελληνικά'},
     *      Ўзбек: array{0: 'Ўзбек'},
     *      Азәрбајҹан: array{0: 'Азәрбајҹан'},
     *      Српски: array{0: 'Српски'},
     *      русский: array{0: 'русский'},
     *      'ѩзыкъ словѣньскъ': array{0: 'ѩзыкъ словѣньскъ'},
     *      العربية: array{0: 'العربية'},
     *      नेपाली: array{0: 'नेपाली'},
     *      日本語: array{0: '日本語'},
     *      简体中文: array{0: '简体中文'},
     *      繁體中文: array{0: '繁體中文'},
     *      한국어: array{0: '한국어'},
     *      ąčęėįšųūžĄČĘĖĮŠŲŪŽ: array{0: 'ąčęėįšųūžĄČĘĖĮŠŲŪŽ'}}
     *
     * @return string[][]
     */
    public static function encodingTestStringsProvider(): array
    {
        return [
            'Avañe’ẽ' => ['Avañe’ẽ'], // Guaraní
            'azərbaycanca' => ['azərbaycanca'], // Azerbaijani (Latin)
            'Bokmål' => ['Bokmål'], // Norwegian Bokmål
            'chiCheŵa' => ['chiCheŵa'], // Chewa
            'Deutsch' => ['Deutsch'], // German
            'U.S. English' => ['U.S. English'], // U.S. English
            'français' => ['français'], // French
            'Éléments envoyés' => ['Éléments envoyés'], // issue 499
            'føroyskt' => ['føroyskt'], // Faroese
            'Kĩmĩrũ' => ['Kĩmĩrũ'], // Kimîîru
            'Kɨlaangi' => ['Kɨlaangi'], // Langi
            'oʼzbekcha' => ['oʼzbekcha'], // Uzbek (Latin)
            'Plattdüütsch' => ['Plattdüütsch'], // Low German
            'română' => ['română'], // Romanian
            'Sängö' => ['Sängö'], // Sango
            'Tiếng Việt' => ['Tiếng Việt'], // Vietnamese
            'ɔl-Maa' => ['ɔl-Maa'], // Masai
            'Ελληνικά' => ['Ελληνικά'], // Greek
            'Ўзбек' => ['Ўзбек'], // Uzbek (Cyrillic)
            'Азәрбајҹан' => ['Азәрбајҹан'], // Azerbaijani (Cyrillic)
            'Српски' => ['Српски'], // Serbian (Cyrillic)
            'русский' => ['русский'], // Russian
            'ѩзыкъ словѣньскъ' => ['ѩзыкъ словѣньскъ'], // Church Slavic
            'العربية' => ['العربية'], // Arabic
            'नेपाली' => ['नेपाली'], // Nepali
            '日本語' => ['日本語'], // Japanese
            '简体中文' => ['简体中文'], // Chinese (Simplified)
            '繁體中文' => ['繁體中文'], // Chinese (Traditional)
            '한국어' => ['한국어'], // Korean
            'ąčęėįšųūžĄČĘĖĮŠŲŪŽ' => ['ąčęėįšųūžĄČĘĖĮŠŲŪŽ'], // Lithuanian letters
        ];
    }

    /**
     * Test, that strings encoded to UTF-7 can be decoded back to UTF-8.
     */
    #[Test]
    #[DataProvider('encodingTestStringsProvider')]
    public function testEncodingToUtf7DecodeBackToUtf8(string $string): void
    {
        $mailbox = $this->getMailbox();

        $utf7EncodedString = $mailbox->encodeStringToUtf7Imap($string);
        $utf8DecodedString = $mailbox->decodeStringFromUtf7ImapToUtf8($utf7EncodedString);

        self::assertEquals($string, $utf8DecodedString);
    }

    /**
     * Test, that strings encoded to UTF-7 can be decoded back to UTF-8.
     *
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('encodingTestStringsProvider')]
    public function testMimeDecodingReturnsCorrectValues(string $string): void
    {
        self::assertEquals($string, $this->getMailbox()->decodeMimeStr($string));
    }

    /**
     * Provides test data for testing parsing datetimes.
     *
     * @phpstan-return array{
     *      'Sun, 14 Aug 2005 16:13:03 +0000 (CEST)': array{0: '2005-08-14T16:13:03+00:00', 1: 1124035983},
     *      'Sun, 14 Aug 2005 16:13:03 +0000': array{0: '2005-08-14T16:13:03+00:00', 1: 1124035983},
     *      'Sun, 14 Aug 2005 16:13:03 +1000 (CEST)': array{0: '2005-08-14T06:13:03+00:00', 1: 1123999983},
     *      'Sun, 14 Aug 2005 16:13:03 +1000': array{0: '2005-08-14T06:13:03+00:00', 1: 1123999983},
     *      'Sun, 14 Aug 2005 16:13:03 -1000': array{0: '2005-08-15T02:13:03+00:00', 1: 1124071983},
     *      'Sun, 14 Aug 2005 16:13:03 +1100 (CEST)': array{0: '2005-08-14T05:13:03+00:00', 1: 1123996383},
     *      'Sun, 14 Aug 2005 16:13:03 +1100': array{0: '2005-08-14T05:13:03+00:00', 1: 1123996383},
     *      'Sun, 14 Aug 2005 16:13:03 -1100': array{0: '2005-08-15T03:13:03+00:00', 1: 1124075583},
     *      '14 Aug 2005 16:13:03 +1000 (CEST)': array{0: '2005-08-14T06:13:03+00:00', 1: 1123999983},
     *      '14 Aug 2005 16:13:03 +1000': array{0: '2005-08-14T06:13:03+00:00', 1: 1123999983},
     *      '14 Aug 2005 16:13:03 -1000': array{0: '2005-08-15T02:13:03+00:00', 1: 1124071983}
     * }
     *
     * @return array<string, array<int, (string|int)>>
     */
    public static function datetimeProvider(): array
    {
        return [
            'Sun, 14 Aug 2005 16:13:03 +0000 (CEST)' => ['2005-08-14T16:13:03+00:00', 1124035983],
            'Sun, 14 Aug 2005 16:13:03 +0000' => ['2005-08-14T16:13:03+00:00', 1124035983],

            'Sun, 14 Aug 2005 16:13:03 +1000 (CEST)' => ['2005-08-14T06:13:03+00:00', 1123999983],
            'Sun, 14 Aug 2005 16:13:03 +1000' => ['2005-08-14T06:13:03+00:00', 1123999983],
            'Sun, 14 Aug 2005 16:13:03 -1000' => ['2005-08-15T02:13:03+00:00', 1124071983],

            'Sun, 14 Aug 2005 16:13:03 +1100 (CEST)' => ['2005-08-14T05:13:03+00:00', 1123996383],
            'Sun, 14 Aug 2005 16:13:03 +1100' => ['2005-08-14T05:13:03+00:00', 1123996383],
            'Sun, 14 Aug 2005 16:13:03 -1100' => ['2005-08-15T03:13:03+00:00', 1124075583],

            '14 Aug 2005 16:13:03 +1000 (CEST)' => ['2005-08-14T06:13:03+00:00', 1123999983],
            '14 Aug 2005 16:13:03 +1000' => ['2005-08-14T06:13:03+00:00', 1123999983],
            '14 Aug 2005 16:13:03 -1000' => ['2005-08-15T02:13:03+00:00', 1124071983],
        ];
    }

    /**
     * Test, different datetimes conversions using differents timezones.
     *
     * @throws InvalidParameterException
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
     * Provides test data for testing parsing invalid / unparseable datetimes.
     *
     * @phpstan-return array{
     *      'Sun, 14 Aug 2005 16:13:03 +9000 (CEST)': array{0: 'Sun, 14 Aug 2005 16:13:03 +9000 (CEST)'},
     *      'Sun, 14 Aug 2005 16:13:03 +9000': array{0: 'Sun, 14 Aug 2005 16:13:03 +9000'},
     *      'Sun, 14 Aug 2005 16:13:03 -9000': array{0: 'Sun, 14 Aug 2005 16:13:03 -9000'}
     * }
     *
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
     * Test, different invalid / unparseable datetimes conversions.
     *
     * @throws InvalidParameterException
     */
    #[Test]
    #[DataProvider('invalidDatetimeProvider')]
    public function testParsedDateWithUnparseableDateTime(string $dateToParse): void
    {
        $parsedDt = $this->getMailbox()->parseDateTime($dateToParse);
        self::assertEquals($parsedDt, $dateToParse);
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
     * Provides test data for testing mime encoding.
     *
     * @return string[][]
     */
    public static function mimeEncodingProvider(): array
    {
        return [
            [
                '=?iso-8859-1?Q?Sebastian_Kr=E4tzig?= <sebastian.kraetzig@example.com>',
                'Sebastian Krätzig <sebastian.kraetzig@example.com>',
            ],
            [
                '=?iso-8859-1?Q?Sebastian_Kr=E4tzig?=',
                'Sebastian Krätzig',
            ],
            [
                'sebastian.kraetzig',
                'sebastian.kraetzig',
            ],
            [
                '=?US-ASCII?Q?Keith_Moore?= <km@ab.example.edu>',
                'Keith Moore <km@ab.example.edu>',
            ],
            [
                '   ',
                '   ',
            ],
            [
                '=?ISO-8859-1?Q?Max_J=F8rn_Simsen?= <max.joern.s@example.dk>',
                'Max Jørn Simsen <max.joern.s@example.dk>',
            ],
            [
                '=?ISO-8859-1?Q?Andr=E9?= Muster <andre.muster@vm1.ulg.ac.be>',
                'André Muster <andre.muster@vm1.ulg.ac.be>',
            ],
            [
                '=?ISO-8859-1?B?SWYgeW91IGNhbiByZWFkIHRoaXMgeW8=?='
                . ' =?ISO-8859-2?B?dSB1bmRlcnN0YW5kIHRoZSBleGFtcGxlLg==?=',
                'If you can read this you understand the example.',
            ],
            [
                '',
                '',
            ], // barbushin/php-imap#501
        ];
    }

    /**
     * Test, that mime encoding returns correct strings.
     *
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mimeEncodingProvider')]
    public function testMimeEncoding(string $str, string $expected): void
    {
        $mailbox = $this->getMailbox();

        self::assertEquals($mailbox->decodeMimeStr($str), $expected);
    }

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
                yield ('INVALID + ' . $key) => ['expectException', $value | 128, 0, []];
            }
        }
    }

    /**
     * Test, that only supported and valid connection args can be set.
     *
     * @phpstan-param array{DISABLE_AUTHENTICATOR?: string}|array<empty, empty> $param
     *
     * @throws InvalidParameterException
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
     * Provides test data for testing mime string decoding.
     *
     * @return string[][]
     */
    public static function mimeStrDecodingProvider(): array
    {
        return [
            '<bde36ec8-9710-47bc-9ea3-bf0425078e33@php.imap>' => [
                '<bde36ec8-9710-47bc-9ea3-bf0425078e33@php.imap>',
                '<bde36ec8-9710-47bc-9ea3-bf0425078e33@php.imap>',
            ],
            '<CAKBqNfyKo+ZXtkz6DUAHw6FjmsDjWDB-pvHkJy6kwO82jTbkNA@mail.gmail.com>' => [
                '<CAKBqNfyKo+ZXtkz6DUAHw6FjmsDjWDB-pvHkJy6kwO82jTbkNA@mail.gmail.com>',
                '<CAKBqNfyKo+ZXtkz6DUAHw6FjmsDjWDB-pvHkJy6kwO82jTbkNA@mail.gmail.com>',
            ],
            '<CAE78dO7vwnd_rkozHLZ5xSUnFEQA9fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>' => [
                '<CAE78dO7vwnd_rkozHLZ5xSUnFEQA9fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>',
                '<CAE78dO7vwnd_rkozHLZ5xSUnFEQA9fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>',
            ],
            '<CAE78dO7vwnd_rkozHLZ5xSU-=nFE_QA9+fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>' => [
                '<CAE78dO7vwnd_rkozHLZ5xSU-=nFE_QA9+fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>',
                '<CAE78dO7vwnd_rkozHLZ5xSU-=nFE_QA9+fymcYREW2cwQ8DA2v7BTA@mail.gmail.com>',
            ],
            'Some subject here 😘' => [
                '=?UTF-8?q?Some_subject_here_?= =?UTF-8?q?=F0=9F=98=98?=',
                'Some subject here 😘',
            ],
            'mountainguan测试' => [
                '=?UTF-8?Q?mountainguan=E6=B5=8B=E8=AF=95?=',
                'mountainguan测试',
            ],
            "This is the Euro symbol ''." => [
                "This is the Euro symbol ''.",
                "This is the Euro symbol ''.",
            ],
            'Some subject here 😘 US-ASCII' => [
                '=?UTF-8?q?Some_subject_here_?= =?UTF-8?q?=F0=9F=98=98?=',
                'Some subject here 😘', 'US-ASCII',
            ],
            'mountainguan测试 US-ASCII' => [
                '=?UTF-8?Q?mountainguan=E6=B5=8B=E8=AF=95?=',
                'mountainguan测试', 'US-ASCII',
            ],
            'مقتطفات من: صن تزو. "فن الحرب". كتب أبل. Something' => [
                'مقتطفات من: صن تزو. "فن الحرب". كتب أبل. Something',
                'مقتطفات من: صن تزو. "فن الحرب". كتب أبل. Something',
            ],
            '(事件单编号:TESTA-111111)(通报)入口有陌生人' => [
                '=?utf-8?b?KOS6i+S7tuWNlee8luWPtzpURVNUQS0xMTExMTEpKOmAmuaKpSnl?= =?utf-8?b?haXlj6PmnInpmYznlJ/kuro=?=',
                '(事件单编号:TESTA-111111)(通报)入口有陌生人',
            ],
        ];
    }

    /**
     * Test, that decoding mime strings return unchanged / not broken strings.
     *
     * @throws InvalidParameterException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mimeStrDecodingProvider')]
    public function testDecodeMimeStr(string $str, string $expectedStr, string $serverEncoding = 'utf-8'): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setServerEncoding($serverEncoding);
        self::assertEquals($mailbox->decodeMimeStr($str), $expectedStr);
    }

    /**
     * Provides test data for testing base64 string decoding.
     *
     * @return string[][]
     */
    public static function Base64DecodeProvider(): array
    {
        return [
            ['bm8tcmVwbHlAZXhhbXBsZS5jb20=', 'no-reply@example.com'],
            [
                'TWFuIGlzIGRpc3Rpbmd1aXNoZWQsIG5vdCBvbmx5IGJ5IGhpcyByZWFzb24sIGJ1dCBieSB0aGlzIHNpbmd1bGFyIHBhc3Npb'
                . '24gZnJvbSBvdGhlciBhbmltYWxzLCB3aGljaCBpcyBhIGx1c3Qgb2YgdGhlIG1pbmQsIHRoYXQgYnkgYSBwZXJzZXZlcmFu'
                . 'Y2Ugb2YgZGVsaWdodCBpbiB0aGUgY29udGludWVkIGFuZCBpbmRlZmF0aWdhYmxlIGdlbmVyYXRpb24gb2Yga25vd2xlZGd'
                . 'lLCBleGNlZWRzIHRoZSBzaG9ydCB2ZWhlbWVuY2Ugb2YgYW55IGNhcm5hbCBwbGVhc3VyZS4=',
                'Man is distinguished, not only by his reason, but by this singular passion from other animals, which'
                . ' is a lust of the mind, that by a perseverance of delight in the continued and indefatigable'
                . ' generation of knowledge, exceeds the short vehemence of any carnal pleasure.',
            ],
            ['SSBjYW4gZWF0IGdsYXNzIGFuZCBpdCBkb2VzIG5vdCBodXJ0IG1lLg==', 'I can eat glass and it does not hurt me.'],
            [
                '77u/4KSV4KS+4KSa4KSCIOCktuCkleCljeCkqOCli+CkruCljeCkr+CkpOCljeCkpOClgeCkruCljSDgpaQg4KSo4KWL4KSq4K'
                . 'S54KS/4KSo4KS44KWN4KSk4KS/IOCkruCkvuCkruCljSDgpaU=',
                '﻿काचं शक्नोम्यत्तुम् । नोपहिनस्ति माम् ॥',
            ],
            [
                'SmUgcGV1eCBtYW5nZXIgZHUgdmVycmUsIMOnYSBuZSBtZSBmYWl0IHBhcyBtYWwu',
                'Je peux manger du verre, ça ne me fait pas mal.',
            ],
            [
                'UG90IHPEgyBtxINuw6JuYyBzdGljbMSDIMiZaSBlYSBudSBtxIMgcsSDbmXImXRlLg==',
                'Pot să mănânc sticlă și ea nu mă rănește.',
            ],
            ['5oiR6IO95ZCe5LiL546755KD6ICM5LiN5YK36Lqr6auU44CC', '我能吞下玻璃而不傷身體。'],
        ];
    }

    #[Test]
    #[DataProvider('Base64DecodeProvider')]
    public function testBase64Decode(string $input, string $expected): void
    {
        self::assertSame($expected, \imap_base64(\preg_replace('~[^a-zA-Z0-9+=/]+~s', '', $input)));
        self::assertSame($expected, \base64_decode($input, false));
    }

    /**
     * @return string[][]
     */
    public static function attachmentDirFailureProvider(): array
    {
        return [
            [
                __DIR__,
                '',
                InvalidParameterException::class,
                'setAttachmentsDir() expects a string as first parameter!',
            ],
            [
                __DIR__,
                ' ',
                InvalidParameterException::class,
                'setAttachmentsDir() expects a string as first parameter!',
            ],
            [
                __DIR__,
                __FILE__,
                InvalidParameterException::class,
                'Directory "' . __FILE__ . '" not found',
            ],
        ];
    }

    /**
     * Test that setting the attachments directory fails when expected.
     *
     * @phpstan-param class-string<\Exception> $expectedException
     *
     * @throws InvalidParameterException
     */
    #[Test]
    #[DataProvider('attachmentDirFailureProvider')]
    public function testAttachmentDirFailure(
        string $initialDir,
        string $attachmentsDir,
        string $expectedException,
        string $expectedExceptionMessage
    ): void {
        $mailbox = new Mailbox('', '', '', $initialDir);

        self::assertSame(\trim($initialDir), $mailbox->getAttachmentsDir());

        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $mailbox->setAttachmentsDir($attachmentsDir);
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
            $this->serverEncoding
        );
    }
}
