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
