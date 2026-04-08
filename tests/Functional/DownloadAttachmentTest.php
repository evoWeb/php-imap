<?php

declare(strict_types=1);

namespace PhpImap\Tests\Functional;

use PhpImap\Mailbox;
use PhpImap\Tests\Fixtures\DataPartInfo as FixtureDataPartInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class DownloadAttachmentTest extends TestCase
{
    private Mailbox $mailbox;

    private FixtureDataPartInfo $dataPartInfo;

    protected function setUp(): void
    {
        $this->mailbox = new Mailbox('', '', '');

        $helperMailbox = new Mailbox('', '', '');
        // ENCQUOTEDPRINTABLE is used so that repeated fetch() calls are idempotent:
        // quoted_printable_decode('test') === 'test' on every invocation.
        $this->dataPartInfo = new FixtureDataPartInfo($helperMailbox, 0, 0, \ENCQUOTEDPRINTABLE, 0);
        $this->dataPartInfo->setData('test');
    }

    /**
     * Creates a minimal valid partStructure object with sensible defaults.
     *
     * @param array<string, mixed> $props
     */
    private function buildPartStructure(array $props = []): object
    {
        return (object)\array_merge([
            'subtype' => 'PLAIN',
            'encoding' => \ENCBASE64,
            'ifid' => false,
            'ifsubtype' => true,
            'ifdescription' => false,
        ], $props);
    }

    // -------------------------------------------------------------------------
    // fileName determination
    // -------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    #[Test]
    public function testFileNameIsRfc822EmlWhenSubtypeIsRfc822AndDispositionIsAttachment(): void
    {
        $partStructure = $this->buildPartStructure([
            'subtype' => 'RFC822',
            'disposition' => 'attachment',
        ]);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertSame('rfc822.eml', $attachment->name);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testFileNameIsSubtypeLowercaseWhenSubtypeIsRfc822ButDispositionIsNotAttachment(): void
    {
        $partStructure = $this->buildPartStructure([
            'subtype' => 'RFC822',
            'disposition' => 'inline',
        ]);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertSame('rfc822', $attachment->name);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testFileNameIsAlternativeEmlWhenSubtypeIsAlternative(): void
    {
        $partStructure = $this->buildPartStructure(['subtype' => 'ALTERNATIVE']);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertSame('alternative.eml', $attachment->name);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testFileNameIsLowercaseSubtypeWhenNoParamsFilenameOrName(): void
    {
        $partStructure = $this->buildPartStructure(['subtype' => 'HTML']);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertSame('html', $attachment->name);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testFileNameIsLowercaseSubtypeWhenBothFilenameAndNameAreEmpty(): void
    {
        $partStructure = $this->buildPartStructure(['subtype' => 'JPEG']);

        $attachment = $this->mailbox->downloadAttachment(
            $this->dataPartInfo,
            ['filename' => '', 'name' => ''],
            $partStructure
        );

        self::assertSame('jpeg', $attachment->name);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testFileNameIsLowercaseSubtypeWhenBothFilenameAndNameAreWhitespaceOnly(): void
    {
        $partStructure = $this->buildPartStructure(['subtype' => 'PDF']);

        $attachment = $this->mailbox->downloadAttachment(
            $this->dataPartInfo,
            ['filename' => '   ', 'name' => '   '],
            $partStructure
        );

        self::assertSame('pdf', $attachment->name);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testFileNameFromParamsFilenameWhenSet(): void
    {
        $partStructure = $this->buildPartStructure();

        $attachment = $this->mailbox->downloadAttachment(
            $this->dataPartInfo,
            ['filename' => 'document.pdf'],
            $partStructure
        );

        self::assertSame('document.pdf', $attachment->name);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testFileNameFromParamsNameWhenFilenameIsEmpty(): void
    {
        $partStructure = $this->buildPartStructure();

        $attachment = $this->mailbox->downloadAttachment(
            $this->dataPartInfo,
            ['filename' => '', 'name' => 'report.xlsx'],
            $partStructure
        );

        self::assertSame('report.xlsx', $attachment->name);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testFileNameFromParamsNameWhenFilenameIsWhitespaceOnly(): void
    {
        $partStructure = $this->buildPartStructure();

        $attachment = $this->mailbox->downloadAttachment(
            $this->dataPartInfo,
            ['filename' => '  ', 'name' => 'image.png'],
            $partStructure
        );

        self::assertSame('image.png', $attachment->name);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testSlashesInFileNameAreReplacedWithUnderscores(): void
    {
        $partStructure = $this->buildPartStructure();

        $attachment = $this->mailbox->downloadAttachment(
            $this->dataPartInfo,
            ['filename' => 'path/to/file.txt'],
            $partStructure
        );

        self::assertSame('path_to_file.txt', $attachment->name);
    }

    // -------------------------------------------------------------------------
    // Assertion failures (invalid partStructure values)
    // -------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    #[Test]
    public function testThrowsWhenBytesIsNotInteger(): void
    {
        $partStructure = $this->buildPartStructure(['bytes' => 'not-an-int']);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('sizeInBytes');

        $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testThrowsWhenEncodingIsNotInteger(): void
    {
        $partStructure = $this->buildPartStructure(['encoding' => 'bad-encoding']);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('encoding');

        $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testThrowsWhenTypeIsNotInteger(): void
    {
        $partStructure = $this->buildPartStructure(['type' => 'not-an-int']);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('type');

        $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testThrowsWhenCharsetIsNotString(): void
    {
        $partStructure = $this->buildPartStructure();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('charset');

        $this->mailbox->downloadAttachment($this->dataPartInfo, ['charset' => 42], $partStructure);
    }

    // -------------------------------------------------------------------------
    // contentId
    // -------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    #[Test]
    public function testContentIdIsNullWhenIfIdIsFalse(): void
    {
        $partStructure = $this->buildPartStructure(['ifid' => false]);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertNull($attachment->contentId);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testContentIdIsSetWhenIfIdIsTrue(): void
    {
        $partStructure = $this->buildPartStructure(['ifid' => true, 'id' => 'foo@bar']);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertSame('foo@bar', $attachment->contentId);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testContentIdHasAngleBracketsStripped(): void
    {
        $partStructure = $this->buildPartStructure(['ifid' => true, 'id' => '<foo@bar>']);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertSame('foo@bar', $attachment->contentId);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testContentIdIsTrimmed(): void
    {
        $partStructure = $this->buildPartStructure(['ifid' => true, 'id' => '  < foo@bar >  ']);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertSame('foo@bar', $attachment->contentId);
    }

    // -------------------------------------------------------------------------
    // type
    // -------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    #[Test]
    public function testTypeIsSetWhenPresentOnPartStructure(): void
    {
        $partStructure = $this->buildPartStructure(['type' => \TYPETEXT]);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertSame(\TYPETEXT, $attachment->type);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testTypeIsNullWhenAbsentFromPartStructure(): void
    {
        $partStructure = $this->buildPartStructure(); // no 'type' key

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertNull($attachment->type);
    }

    // -------------------------------------------------------------------------
    // subtype
    // -------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    #[Test]
    public function testSubtypeIsSetAndTrimmedWhenIfSubtypeIsTrue(): void
    {
        $partStructure = $this->buildPartStructure(['ifsubtype' => true, 'subtype' => '  PLAIN  ']);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertSame('PLAIN', $attachment->subtype);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testSubtypeIsNullWhenIfSubtypeIsFalse(): void
    {
        $partStructure = $this->buildPartStructure(['ifsubtype' => false]);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertNull($attachment->subtype);
    }

    // -------------------------------------------------------------------------
    // description
    // -------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    #[Test]
    public function testDescriptionIsSetAndTrimmedWhenIfDescriptionIsTrue(): void
    {
        $partStructure = $this->buildPartStructure([
            'ifdescription' => true,
            'description' => '  An attached file  ',
        ]);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertSame('An attached file', $attachment->description);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testDescriptionIsNullWhenIfDescriptionIsFalse(): void
    {
        $partStructure = $this->buildPartStructure(['ifdescription' => false]);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertNull($attachment->description);
    }

    // -------------------------------------------------------------------------
    // charset
    // -------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    #[Test]
    public function testCharsetIsNullWhenAbsentFromParams(): void
    {
        $partStructure = $this->buildPartStructure();

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertNull($attachment->charset);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testCharsetIsNullWhenEmptyString(): void
    {
        $partStructure = $this->buildPartStructure();

        $attachment = $this->mailbox->downloadAttachment(
            $this->dataPartInfo,
            ['charset' => ''],
            $partStructure
        );

        self::assertNull($attachment->charset);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testCharsetIsNullWhenWhitespaceOnly(): void
    {
        $partStructure = $this->buildPartStructure();

        $attachment = $this->mailbox->downloadAttachment(
            $this->dataPartInfo,
            ['charset' => '   '],
            $partStructure
        );

        self::assertNull($attachment->charset);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testCharsetIsSetWhenNonEmpty(): void
    {
        $partStructure = $this->buildPartStructure();

        $attachment = $this->mailbox->downloadAttachment(
            $this->dataPartInfo,
            ['charset' => 'UTF-8'],
            $partStructure
        );

        self::assertSame('UTF-8', $attachment->charset);
    }

    // -------------------------------------------------------------------------
    // emlOrigin
    // -------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    #[Test]
    public function testEmlOriginIsFalseByDefault(): void
    {
        $attachment = $this->mailbox->downloadAttachment(
            $this->dataPartInfo,
            [],
            $this->buildPartStructure()
        );

        self::assertFalse($attachment->emlOrigin);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testEmlOriginCanBeSetToTrue(): void
    {
        $attachment = $this->mailbox->downloadAttachment(
            $this->dataPartInfo,
            [],
            $this->buildPartStructure(),
            true
        );

        self::assertTrue($attachment->emlOrigin);
    }

    // -------------------------------------------------------------------------
    // sizeInBytes / encoding / disposition
    // -------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    #[Test]
    public function testSizeInBytesIsSetFromBytesProperty(): void
    {
        $partStructure = $this->buildPartStructure(['bytes' => 2048]);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertSame(2048, $attachment->sizeInBytes);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testSizeInBytesIsNullWhenBytesIsAbsent(): void
    {
        $partStructure = $this->buildPartStructure(); // no 'bytes'

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertNull($attachment->sizeInBytes);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testEncodingIsSetFromPartStructure(): void
    {
        $partStructure = $this->buildPartStructure(['encoding' => \ENCQUOTEDPRINTABLE]);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertSame(\ENCQUOTEDPRINTABLE, $attachment->encoding);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testEncodingIsNullWhenAbsent(): void
    {
        $partStructure = (object)[
            'subtype' => 'PLAIN',
            'ifid' => false,
            'ifsubtype' => true,
            'ifdescription' => false,
        ];

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertNull($attachment->encoding);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testDispositionIsSetFromPartStructure(): void
    {
        $partStructure = $this->buildPartStructure(['disposition' => 'inline']);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertSame('inline', $attachment->disposition);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testDispositionIsNullWhenAbsent(): void
    {
        $partStructure = $this->buildPartStructure(); // no 'disposition'

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertNull($attachment->disposition);
    }

    // -------------------------------------------------------------------------
    // id (random hex)
    // -------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    #[Test]
    public function testAttachmentIdIsA40CharHexString(): void
    {
        $attachment = $this->mailbox->downloadAttachment(
            $this->dataPartInfo,
            [],
            $this->buildPartStructure()
        );

        self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', (string)$attachment->id);
    }

    // -------------------------------------------------------------------------
    // Disk saving
    // -------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    #[Test]
    public function testNoFileIsSavedWhenAttachmentsDirIsNull(): void
    {
        // Mailbox with no attachmentsDir (null by default)
        $mailbox = new Mailbox('', '', '');

        $tempDir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'phpimaptest_nodir_' . \uniqid();
        \mkdir($tempDir);

        try {
            $mailbox->downloadAttachment($this->dataPartInfo, ['filename' => 'test.txt'], $this->buildPartStructure());

            // No file should appear in any directory we control
            self::assertCount(0, (array)\glob($tempDir . \DIRECTORY_SEPARATOR . '*'));
        } finally {
            \rmdir($tempDir);
        }
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testFileIsSavedWithOriginalNameWhenAttachmentFilenameModeIsTrue(): void
    {
        $tempDir = $this->createTempDir();

        try {
            $mailbox = new Mailbox('', '', '', $tempDir);
            $mailbox->setAttachmentFilenameMode(true);

            $attachment = $mailbox->downloadAttachment(
                $this->dataPartInfo,
                ['filename' => 'hello.txt'],
                $this->buildPartStructure()
            );

            $filePath = $attachment->filePath;

            self::assertFileExists($filePath);
            self::assertSame('hello.txt', \basename($filePath));
            self::assertSame('test', \file_get_contents($filePath));
        } finally {
            $this->removeTempDir($tempDir);
        }
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testFileIsSavedWithRandomHexNameWhenAttachmentFilenameModeIsFalse(): void
    {
        $tempDir = $this->createTempDir();

        try {
            $mailbox = new Mailbox('', '', '', $tempDir);
            $mailbox->setAttachmentFilenameMode(false);

            $attachment = $mailbox->downloadAttachment(
                $this->dataPartInfo,
                ['filename' => 'original.txt'],
                $this->buildPartStructure()
            );

            $filePath = $attachment->filePath;

            self::assertFileExists($filePath);
            // The basename should NOT be the original filename when mode is false
            self::assertNotSame('original.txt', \basename($filePath));
            // The filename should be a hex string followed by a dot and extension
            self::assertMatchesRegularExpression('/^[0-9a-f]{32}\./', \basename($filePath));
        } finally {
            $this->removeTempDir($tempDir);
        }
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testFilePathIsTruncatedToMaxLengthWhenFilenameIsTooLong(): void
    {
        $tempDir = $this->createTempDir();

        try {
            $mailbox = new Mailbox('', '', '', $tempDir);
            $mailbox->setAttachmentFilenameMode(true);

            // Create a filename so long that dir + sep + filename > MAX_LENGTH_FILEPATH
            $longName = \str_repeat('a', 260) . '.txt';

            $attachment = $mailbox->downloadAttachment(
                $this->dataPartInfo,
                ['filename' => $longName],
                $this->buildPartStructure()
            );

            $filePath = $attachment->filePath;

            self::assertLessThanOrEqual(Mailbox::MAX_LENGTH_FILEPATH, \strlen($filePath));
            self::assertStringEndsWith('.txt', $filePath);
        } finally {
            $this->removeTempDir($tempDir);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createTempDir(): string
    {
        $dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'phpimaptest_' . \uniqid();
        \mkdir($dir);

        return $dir;
    }

    private function removeTempDir(string $dir): void
    {
        foreach ((array)\glob($dir . \DIRECTORY_SEPARATOR . '*') as $file) {
            \unlink((string)$file);
        }
        \rmdir($dir);
    }
}
