<?php

declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Tests\Fixtures\Mailbox as FixtureMailbox;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MailboxRecipientTest extends TestCase
{
    private string $imapPath = '{imap.example.com:993/imap/ssl/novalidate-cert}INBOX';

    private string $login = 'php-imap@example.com';

    private string $password = 'v3rY!53cEt&P4sSWöRd$';

    private string $attachmentsDir = '.';

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetEmailAndNameFromRecipientWithMailboxAndHost(): void
    {
        $mailbox = $this->getMailbox();

        $recipient = new \stdClass();
        $recipient->mailbox = 'john';
        $recipient->host = 'example.com';
        $recipient->personal = 'John Doe';

        $result = $mailbox->exposedPossiblyGetEmailAndNameFromRecipient($recipient);

        self::assertNotNull($result);
        self::assertSame('john@example.com', $result[0]);
        self::assertSame('John Doe', $result[1]);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetEmailAndNameFromRecipientWithoutPersonal(): void
    {
        $mailbox = $this->getMailbox();

        $recipient = new \stdClass();
        $recipient->mailbox = 'jane';
        $recipient->host = 'example.com';

        $result = $mailbox->exposedPossiblyGetEmailAndNameFromRecipient($recipient);

        self::assertNotNull($result);
        self::assertSame('jane@example.com', $result[0]);
        self::assertNull($result[1]);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetEmailAndNameFromRecipientWithEmptyMailbox(): void
    {
        $mailbox = $this->getMailbox();

        $recipient = new \stdClass();
        $recipient->mailbox = '';
        $recipient->host = 'example.com';

        $result = $mailbox->exposedPossiblyGetEmailAndNameFromRecipient($recipient);

        self::assertNull($result);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetEmailAndNameFromRecipientWithEmptyHost(): void
    {
        $mailbox = $this->getMailbox();

        $recipient = new \stdClass();
        $recipient->mailbox = 'john';
        $recipient->host = '';

        $result = $mailbox->exposedPossiblyGetEmailAndNameFromRecipient($recipient);

        self::assertNull($result);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetEmailAndNameFromRecipientWithoutProperties(): void
    {
        $mailbox = $this->getMailbox();

        $recipient = new \stdClass();

        $result = $mailbox->exposedPossiblyGetEmailAndNameFromRecipient($recipient);

        self::assertNull($result);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetEmailAndNameFromRecipientWithEmptyPersonal(): void
    {
        $mailbox = $this->getMailbox();

        $recipient = new \stdClass();
        $recipient->mailbox = 'john';
        $recipient->host = 'example.com';
        $recipient->personal = '   ';

        $result = $mailbox->exposedPossiblyGetEmailAndNameFromRecipient($recipient);

        self::assertNotNull($result);
        self::assertSame('john@example.com', $result[0]);
        self::assertNull($result[1]);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetHostNameAndAddress(): void
    {
        $mailbox = $this->getMailbox();

        $entry = new \stdClass();
        $entry->mailbox = 'john';
        $entry->host = 'example.com';
        $entry->personal = 'John Doe';

        $result = $mailbox->exposedPossiblyGetHostNameAndAddress([$entry]);

        self::assertSame('example.com', $result[0]);
        self::assertSame('John Doe', $result[1]);
        self::assertSame('john@example.com', $result[2]);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetHostNameAndAddressWithTwoEntries(): void
    {
        $mailbox = $this->getMailbox();

        $entry0 = new \stdClass();
        $entry0->mailbox = 'john';
        $entry0->host = 'example.com';

        $entry1 = new \stdClass();
        $entry1->host = 'fallback.com';
        $entry1->personal = 'Fallback Name';

        $result = $mailbox->exposedPossiblyGetHostNameAndAddress([$entry0, $entry1]);

        self::assertSame('example.com', $result[0]);
        self::assertSame('Fallback Name', $result[1]);
        self::assertSame('john@example.com', $result[2]);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testPossiblyGetHostNameAndAddressFallbackHost(): void
    {
        $mailbox = $this->getMailbox();

        $entry0 = new \stdClass();
        $entry0->mailbox = 'john';

        $entry1 = new \stdClass();
        $entry1->host = 'fallback.com';
        $entry1->personal = 'Name';

        $result = $mailbox->exposedPossiblyGetHostNameAndAddress([$entry0, $entry1]);

        self::assertSame('fallback.com', $result[0]);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testParseRecipientListEmpty(): void
    {
        $result = $this->getMailbox()->exposeParseRecipientList([]);

        self::assertSame([], $result[0]);
        self::assertSame('', $result[1]);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testParseRecipientListSingleWithName(): void
    {
        $recipient = new \stdClass();
        $recipient->mailbox = 'john';
        $recipient->host = 'example.com';
        $recipient->personal = 'John Doe';

        $result = $this->getMailbox()->exposeParseRecipientList([$recipient]);

        self::assertSame(['john@example.com' => 'John Doe'], $result[0]);
        self::assertSame('John Doe <john@example.com>', $result[1]);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testParseRecipientListSingleWithoutName(): void
    {
        $recipient = new \stdClass();
        $recipient->mailbox = 'jane';
        $recipient->host = 'example.com';

        $result = $this->getMailbox()->exposeParseRecipientList([$recipient]);

        self::assertSame(['jane@example.com' => null], $result[0]);
        self::assertSame('jane@example.com', $result[1]);
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testParseRecipientListMultiple(): void
    {
        $r1 = new \stdClass();
        $r1->mailbox = 'alice';
        $r1->host = 'example.com';
        $r1->personal = 'Alice';

        $r2 = new \stdClass();
        $r2->mailbox = 'bob';
        $r2->host = 'example.com';

        $result = $this->getMailbox()->exposeParseRecipientList([$r1, $r2]);

        self::assertSame(['alice@example.com' => 'Alice', 'bob@example.com' => null], $result[0]);
        self::assertSame('Alice <alice@example.com>, bob@example.com', $result[1]);
    }

    /**
     * Invalid recipients (missing mailbox/host) are silently skipped.
     *
     * @throws \Exception
     */
    #[Test]
    public function testParseRecipientListSkipsInvalidRecipients(): void
    {
        $invalid = new \stdClass();

        $valid = new \stdClass();
        $valid->mailbox = 'valid';
        $valid->host = 'example.com';

        $result = $this->getMailbox()->exposeParseRecipientList([$invalid, $valid]);

        self::assertSame(['valid@example.com' => null], $result[0]);
        self::assertSame('valid@example.com', $result[1]);
    }

    /**
     * @throws InvalidParameterException
     */
    private function getMailbox(): FixtureMailbox
    {
        return new FixtureMailbox(
            $this->imapPath,
            $this->login,
            $this->password,
            $this->attachmentsDir,
            'UTF-8'
        );
    }
}
