<?php

/**
 * Mailbox - PHPUnit tests.
 *
 * @author Sebastian Kraetzig <sebastian-kraetzig@gmx.de>
 */

declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Tests\Fixtures\Mailbox as FixtureMailbox;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MailboxDecodingTest extends TestCase
{
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
     * Provides test data for testing base64 string decoding.
     *
     * @return string[][]
     */
    public static function base64DecodeProvider(): array
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

    /**
     * Test, that strings encoded to UTF-7 can be decoded back to UTF-8.
     *
     * @throws \Exception
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
     * Test, that mime encoding returns correct strings.
     *
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mimeEncodingProvider')]
    public function testMimeEncoding(string $str, string $expected): void
    {
        $mailbox = $this->getMailbox();

        self::assertEquals($expected, $mailbox->decodeMimeStr($str));
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
        self::assertEquals($expectedStr, $mailbox->decodeMimeStr($str));
    }

    #[Test]
    #[DataProvider('base64DecodeProvider')]
    public function testBase64Decode(string $input, string $expected): void
    {
        self::assertSame($expected, \imap_base64(\preg_replace('~[^a-zA-Z0-9+=/]+~', '', $input)));
        self::assertSame($expected, \base64_decode($input));
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
    public function testDecodeMimeStrFalseReturn(): void
    {
        $mailbox = $this->getMailbox();

        $result = $mailbox->decodeMimeStr('plain ascii text');

        self::assertSame('plain ascii text', $result);
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
