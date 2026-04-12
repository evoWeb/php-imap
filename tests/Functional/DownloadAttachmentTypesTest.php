<?php

declare(strict_types=1);

namespace PhpImap\Tests\Functional;

use PhpImap\Entities\PartStructure;
use PhpImap\Mailbox;
use PhpImap\Tests\Fixtures\DataPartInfo as FixtureDataPartInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DownloadAttachmentTypesTest extends TestCase
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
}
