<?php

declare(strict_types=1);

namespace PhpImap\Tests\Functional;

use PhpImap\Entities\PartStructure;
use PhpImap\Mailbox;
use PhpImap\Tests\Fixtures\DataPartInfo as FixtureDataPartInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DownloadAttachmentEncodingTest extends TestCase
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
        $partStructure = new PartStructure(
            subtype: 'PLAIN',
            ifid: false,
            ifsubtype: true,
            ifdescription: false,
        );

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
}
