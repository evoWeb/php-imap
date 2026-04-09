<?php

declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use PhpImap\IncomingMail;
use PhpImap\Tests\Fixtures\Mailbox as FixtureMailbox;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('offline')]
final class InitMailPartTest extends TestCase
{
    private FixtureMailbox $mailbox;

    protected function setUp(): void
    {
        $this->mailbox = new FixtureMailbox('', '', '');
    }

    /**
     * Builds a minimal stdClass partStructure with sensible defaults.
     *
     * @param array<string, mixed> $props
     */
    private function buildPartStructure(array $props = []): object
    {
        return (object)\array_merge([
            'type' => \TYPETEXT,
            'subtype' => 'PLAIN',
            'encoding' => \ENCQUOTEDPRINTABLE,
            'ifdisposition' => 0,
            'parameters' => [],
            'dparameters' => [],
            'parts' => [],
        ], $props);
    }

    /**
     * Guard at the very top of initMailPart: if IncomingMail has no id set,
     * an InvalidArgumentException must be thrown before any IMAP access.
     *
     * @throws \Exception
     */
    #[Test]
    public function testThrowsInvalidArgumentExceptionWhenMailIdNotSet(): void
    {
        $mail = new IncomingMail();
        // $mail->id is declared as ?int without a default — isset() returns false

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/id property/');

        $this->mailbox->exposeInitMailPart($mail, $this->buildPartStructure(), 0);
    }

    /**
     * When a TYPETEXT leaf has ifdisposition=1 (truthy) but the disposition
     * property is absent on the structure, initMailPart must throw instead of
     * silently mishandling a non-string disposition.
     *
     * Reproduces lines 2015-2019 in Mailbox.php:
     *   } elseif (!\is_string($partStructure->disposition)) {
     *       throw new \InvalidArgumentException(...)
     *
     * The property is intentionally omitted, so stdClass returns null when
     * accessed — which satisfies `!\is_string(null)`.
     *
     * @throws \Exception
     */
    #[Test]
    public function testThrowsInvalidArgumentExceptionWhenDispositionPresentButNotString(): void
    {
        $mail = new IncomingMail();
        $mail->id = 1;

        $partStructure = $this->buildPartStructure([
            'type' => \TYPETEXT,
            'subtype' => 'HTML',
            'ifdisposition' => 1,
            'disposition' => null,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/disposition/');

        $this->mailbox->exposeInitMailPart($mail, $partStructure, 1);
    }

    /**
     * When attachmentsIgnore is true and the part is a non-text attachment,
     * initMailPart must return early (before calling downloadAttachment / fetch),
     * but it must still have called setHasAttachments(true) beforehand.
     *
     * Covers the early-return block at lines 1951-1963 in Mailbox.php.
     * Because the return happens before any DataPartInfo::fetch() call, this
     * test does not require a live IMAP connection.
     *
     * @throws \Exception
     */
    #[Test]
    public function testAttachmentsIgnoreSkipsDownloadButMarksHasAttachments(): void
    {
        $this->mailbox->setAttachmentsIgnore(true);

        $mail = new IncomingMail();
        $mail->id = 1;

        // TYPEAPPLICATION with a Content-ID → $isAttachment = true
        // attachmentsIgnore causes early return before downloadAttachment is reached
        $partStructure = $this->buildPartStructure([
            'type' => \TYPEAPPLICATION,
            'subtype' => 'OCTET-STREAM',
            'encoding' => \ENCBASE64,
            'id' => 'some-content-id',
            'ifid' => 1,
            'ifsubtype' => 1,
            'ifdescription' => 0,
        ]);

        // Must not throw even though mail->id=1 does not refer to a real message,
        // because DataPartInfo::fetch() is never called on this path.
        $this->mailbox->exposeInitMailPart($mail, $partStructure, 1);

        self::assertTrue(
            $mail->hasAttachments(),
            'setHasAttachments(true) must be called before the early return'
        );
        self::assertEmpty(
            $mail->getAttachments(),
            'addAttachment() must not be called after the early return'
        );
    }
}
