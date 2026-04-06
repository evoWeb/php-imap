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
