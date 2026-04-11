<?php

declare(strict_types=1);

namespace PhpImap\Tests\Functional;

use PhpImap\Mailbox;
use PhpImap\Tests\Fixtures\DataPartInfo as FixtureDataPartInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type PARTSTRUCTURE from Mailbox
 */
final class DownloadAttachmentDeterminationTest extends TestCase
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
     *
     * @phpstan-return PARTSTRUCTURE
     */
    private function buildPartStructure(array $props = []): \stdClass
    {
        /** @phpstan-var PARTSTRUCTURE $result */
        $result = (object)\array_merge([
            'subtype' => 'PLAIN',
            'encoding' => \ENCBASE64,
            'ifid' => false,
            'ifsubtype' => true,
            'ifdescription' => false,
        ], $props);

        return $result;
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
}
