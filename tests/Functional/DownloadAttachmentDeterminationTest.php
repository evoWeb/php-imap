<?php

declare(strict_types=1);

namespace PhpImap\Tests\Functional;

use PhpImap\Entities\PartStructure;
use PhpImap\Mailbox;
use PhpImap\Tests\Fixtures\DataPartInfo as FixtureDataPartInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
     * Creates a minimal valid PartStructure with sensible defaults.
     *
     * @param array<string, mixed> $props
     */
    private function buildPartStructure(array $props = []): PartStructure
    {
        return new PartStructure(
            subtype: \is_string($props['subtype'] ?? null) ? $props['subtype'] : 'PLAIN',
            encoding: \is_int($props['encoding'] ?? null) ? $props['encoding'] : \ENCBASE64,
            ifid: (bool)($props['ifid'] ?? false),
            id: \is_string($props['id'] ?? null) ? $props['id'] : null,
            ifsubtype: (bool)($props['ifsubtype'] ?? true),
            ifdescription: (bool)($props['ifdescription'] ?? false),
            description: \is_string($props['description'] ?? null) ? $props['description'] : null,
            disposition: \is_string($props['disposition'] ?? null) ? $props['disposition'] : null,
            ifdisposition: (bool)($props['ifdisposition'] ?? false),
            bytes: \is_int($props['bytes'] ?? null) ? $props['bytes'] : null,
            type: \is_int($props['type'] ?? null) ? $props['type'] : null,
        );
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
