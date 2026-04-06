<?php

declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Mailbox;
use PhpImap\Tests\Fixtures\Constants;
use PhpImap\Tests\Fixtures\Mailbox as FixtureMailbox;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MailboxConstructorTest extends TestCase
{
    private string $imapPath = Constants::IMAP_PATH_INBOX_NO_VALID_SSL;

    private string $login = Constants::LOGIN;

    private string $password = Constants::PASSWORD;

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
        $imapPath = ' ' . Constants::IMAP_PATH_INBOX_SSL . '     ';
        $login = '    ' . Constants::LOGIN;
        $password = '  ' . Constants::PASSWORD;
        // directory names can contain spaces before AND after on
        // Linux/Unix systems. Windows trims these spaces automatically.
        $attachmentsDir = '.';
        $serverEncoding = 'UTF-8  ';

        $mailbox = new FixtureMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        self::assertSame(Constants::IMAP_PATH_INBOX_SSL, $mailbox->getImapPath());
        self::assertSame(Constants::LOGIN, $mailbox->getLogin());
        self::assertSame('  ' . Constants::PASSWORD, $mailbox->getImapPassword());
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
        self::assertEquals(Constants::LOGIN, $this->getMailbox()->getLogin());
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testConstructorWithTrimImapPathFalse(): void
    {
        $imapPath = '  ' . Constants::IMAP_PATH_INBOX_SSL . '  ';
        $mailbox = new FixtureMailbox($imapPath, $this->login, $this->password, $this->attachmentsDir, 'UTF-8', false);

        self::assertSame($imapPath, $mailbox->getImapPath());
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testConstructorWithTrimImapPathTrue(): void
    {
        $imapPath = '  ' . Constants::IMAP_PATH_INBOX_SSL . '  ';
        $mailbox = new FixtureMailbox($imapPath, $this->login, $this->password, $this->attachmentsDir, 'UTF-8', true);

        self::assertSame(Constants::IMAP_PATH_INBOX_SSL, $mailbox->getImapPath());
    }

    /**
     * @throws InvalidParameterException
     */
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

    /**
     * @throws InvalidParameterException
     */
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

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testConstructorWithNullAttachmentsDir(): void
    {
        $mailbox = new Mailbox($this->imapPath, $this->login, $this->password, null);
        $mailbox->setPathDelimiter('.');
        // attachmentsDir is uninitialized when null is passed, so we just verify construction succeeds
        $this->addToAssertionCount(1);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testSetMailboxFolderFromImapPath(): void
    {
        $mailbox = new Mailbox(Constants::IMAP_PATH . 'Sent', '', '');

        self::assertSame(Constants::IMAP_PATH . 'Sent', $mailbox->getImapPath());
    }

    #[Test]
    public function testSetMailboxFolderDefaultsToInbox(): void
    {
        $mailbox = new Mailbox(Constants::IMAP_PATH, '', '');

        self::assertSame(Constants::IMAP_PATH, $mailbox->getImapPath());
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
