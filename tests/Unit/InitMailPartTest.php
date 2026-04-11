<?php

declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use PhpImap\Entities\PartStructure;
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
     * Builds a minimal PartStructure with sensible defaults.
     *
     * @param array<string, mixed> $props
     */
    private function buildPartStructure(array $props = []): PartStructure
    {
        return new PartStructure(
            type: \is_int($props['type'] ?? null) ? $props['type'] : \TYPETEXT,
            subtype: \is_string($props['subtype'] ?? null) ? $props['subtype'] : 'PLAIN',
            encoding: \is_int($props['encoding'] ?? null) ? $props['encoding'] : \ENCQUOTEDPRINTABLE,
            ifdisposition: (bool)($props['ifdisposition'] ?? false),
            disposition: \array_key_exists('disposition', $props) ? (
                \is_string($props['disposition']) ? $props['disposition'] : null
            ) : null,
            ifid: (bool)($props['ifid'] ?? false),
            id: \is_string($props['id'] ?? null) ? $props['id'] : null,
            ifsubtype: (bool)($props['ifsubtype'] ?? true),
            ifdescription: (bool)($props['ifdescription'] ?? false),
        );
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
     * When a TYPETEXT leaf has ifdisposition=true but the disposition
     * property is null on the structure, initMailPart must throw instead of
     * silently mishandling a non-string disposition.
     *
     * Reproduces lines in Mailbox.php:
     *   } elseif (!\is_string($partStructure->disposition)) {
     *       throw new \InvalidArgumentException(...)
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
     * Covers the early-return block in Mailbox.php.
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
