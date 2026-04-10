<?php

declare(strict_types=1);

namespace PhpImap\Tests\Functional;

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
    // Assertion failures (invalid partStructure values)
    // -------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    #[Test]
    public function testThrowsWhenBytesIsNotInteger(): void
    {
        $partStructure = $this->buildPartStructure(['bytes' => 'not-an-int']);

        $this->expectException(\UnexpectedValueException::class);
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

        $this->expectException(\UnexpectedValueException::class);
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

        $this->expectException(\UnexpectedValueException::class);
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
