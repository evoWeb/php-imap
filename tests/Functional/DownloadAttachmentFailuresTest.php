<?php

declare(strict_types=1);

namespace PhpImap\Tests\Functional;

use PhpImap\Entities\PartStructure;
use PhpImap\Mailbox;
use PhpImap\Tests\Fixtures\Constants;
use PhpImap\Tests\Fixtures\DataPartInfo as FixtureDataPartInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DownloadAttachmentFailuresTest extends TestCase
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
            ifsubtype: (bool)($props['ifsubtype'] ?? true),
            ifdescription: (bool)($props['ifdescription'] ?? false),
            disposition: \is_string($props['disposition'] ?? null) ? $props['disposition'] : null,
            bytes: \is_int($props['bytes'] ?? null) ? $props['bytes'] : null,
            type: \is_int($props['type'] ?? null) ? $props['type'] : null,
            id: \is_string($props['id'] ?? null) ? $props['id'] : null,
        );
    }

    // -------------------------------------------------------------------------
    // Assertion failures (invalid parameter values)
    // -------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    #[Test]
    public function testThrowsWhenCharsetIsNotString(): void
    {
        $partStructure = $this->buildPartStructure();

        $this->expectException(\UnexpectedValueException::class);
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
        $partStructure = $this->buildPartStructure(['ifid' => true, 'id' => Constants::FOOBAR]);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertSame(Constants::FOOBAR, $attachment->contentId);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testContentIdHasAngleBracketsStripped(): void
    {
        $partStructure = $this->buildPartStructure(['ifid' => true, 'id' => '<' . Constants::FOOBAR . '>']);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertSame(Constants::FOOBAR, $attachment->contentId);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testContentIdIsTrimmed(): void
    {
        $partStructure = $this->buildPartStructure(['ifid' => true, 'id' => '  < ' . Constants::FOOBAR . ' >  ']);

        $attachment = $this->mailbox->downloadAttachment($this->dataPartInfo, [], $partStructure);

        self::assertSame(Constants::FOOBAR, $attachment->contentId);
    }
}
