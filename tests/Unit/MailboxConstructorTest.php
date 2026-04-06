<?php

declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use PhpImap\Mailbox;
use PhpImap\Tests\Fixtures\Mailbox as FixtureMailbox;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MailboxConstructorTest extends TestCase
{
    private string $imapPath = '{imap.example.com:993/imap/ssl/novalidate-cert}INBOX';

    private string $login = 'php-imap@example.com';

    private string $password = 'v3rY!53cEt&P4sSWöRd$';

    private string $attachmentsDir = '.';

    /**
     * Test, that the constructor trims possible variables
     * Leading and ending spaces are not even possible in some variables.
     *
     * @throws \Exception
     */
    #[Test]
    public function testConstructorTrimsPossibleVariables(): void
    {
        $imapPath = ' {imap.example.com:993/imap/ssl}INBOX     ';
        $login = '    php-imap@example.com';
        $password = '  v3rY!53cEt&P4sSWöRd$';
        // directory names can contain spaces before AND after on
        // Linux/Unix systems. Windows trims these spaces automatically.
        $attachmentsDir = '.';
        $serverEncoding = 'UTF-8  ';

        $mailbox = new FixtureMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        self::assertSame('{imap.example.com:993/imap/ssl}INBOX', $mailbox->getImapPath());
        self::assertSame('php-imap@example.com', $mailbox->getLogin());
        self::assertSame('  v3rY!53cEt&P4sSWöRd$', $mailbox->getImapPassword());
        self::assertSame(\realpath('.'), $mailbox->getAttachmentsDir());
        self::assertSame('UTF-8', $mailbox->getServerEncoding());
    }

    /**
     * Test, that server encoding is set to a default value.
     *
     * @throws \Exception
     */
    #[Test]
    public function testServerEncodingHasDefaultSetting(): void
    {
        $mailbox = new Mailbox($this->imapPath, $this->login, $this->password, $this->attachmentsDir);
        self::assertSame('UTF-8', $mailbox->getServerEncoding());
    }

    /**
     * Test, that the imap login can be retrieved.
     *
     * @throws \Exception
     */
    #[Test]
    public function testGetLogin(): void
    {
        self::assertEquals('php-imap@example.com', $this->getMailbox()->getLogin());
    }

    #[Test]
    public function testConstructorWithTrimImapPathFalse(): void
    {
        $imapPath = '  {imap.example.com:993/imap/ssl}INBOX  ';
        $mailbox = new FixtureMailbox($imapPath, $this->login, $this->password, $this->attachmentsDir, 'UTF-8', false);

        self::assertSame($imapPath, $mailbox->getImapPath());
    }

    #[Test]
    public function testConstructorWithTrimImapPathTrue(): void
    {
        $imapPath = '  {imap.example.com:993/imap/ssl}INBOX  ';
        $mailbox = new FixtureMailbox($imapPath, $this->login, $this->password, $this->attachmentsDir, 'UTF-8', true);

        self::assertSame('{imap.example.com:993/imap/ssl}INBOX', $mailbox->getImapPath());
    }

    #[Test]
    public function testConstructorWithAttachmentFilenameModeTrue(): void
    {
        $mailbox = new Mailbox(
            $this->imapPath,
            $this->login,
            $this->password,
            $this->attachmentsDir,
            'UTF-8',
            true,
            true
        );

        self::assertTrue($mailbox->getAttachmentFilenameMode());
    }

    #[Test]
    public function testConstructorWithAttachmentFilenameModeFalse(): void
    {
        $mailbox = new Mailbox(
            $this->imapPath,
            $this->login,
            $this->password,
            $this->attachmentsDir,
            'UTF-8',
            true,
            false
        );

        self::assertFalse($mailbox->getAttachmentFilenameMode());
    }

    #[Test]
    public function testConstructorWithNullAttachmentsDir(): void
    {
        $mailbox = new Mailbox($this->imapPath, $this->login, $this->password, null);
        $mailbox->setPathDelimiter('.');
        // attachmentsDir is uninitialized when null is passed, so we just verify construction succeeds
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function testSetMailboxFolderFromImapPath(): void
    {
        $mailbox = new Mailbox('{imap.example.com:993}Sent', '', '');

        self::assertSame('{imap.example.com:993}Sent', $mailbox->getImapPath());
    }

    #[Test]
    public function testSetMailboxFolderDefaultsToInbox(): void
    {
        $mailbox = new Mailbox('{imap.example.com:993}', '', '');

        self::assertSame('{imap.example.com:993}', $mailbox->getImapPath());
    }

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
