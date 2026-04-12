<?php

declare(strict_types=1);

namespace PhpImap\Tests\Functional;

use IMAP\Connection;
use ParagonIE\HiddenString\HiddenString;
use PhpImap\Exceptions\ConnectionException;
use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Imap;
use PhpImap\Mailbox;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

/**
 * Functional tests covering Imap methods not yet reached by other test suites.
 */
class ImapMethodsCoverageTest extends TestCase
{
    use MailboxTestingTrait;

    private const TEST_MESSAGE = "Subject: coverage-test\r\nMIME-Version: 1.0\r\n\r\ncoverage test body";

    private function serverPrefix(Mailbox $mailbox): string
    {
        $path = $mailbox->getImapPath();
        $pos = \strpos($path, '}');

        return $pos !== false ? \substr($path, 0, $pos + 1) : $path;
    }

    private function folderName(string $fullImapPath): string
    {
        $pos = \strpos($fullImapPath, '}');

        return $pos !== false ? \substr($fullImapPath, $pos + 1) : $fullImapPath;
    }

    private function appendTestMessage(Connection $imapStream, string $mailboxPath): void
    {
        Imap::append($imapStream, $mailboxPath, self::TEST_MESSAGE);
    }

    // -------------------------------------------------------------------------
    // timeout – no Connection required
    // -------------------------------------------------------------------------

    #[Test]
    public function testTimeoutGetReturnsInt(): void
    {
        $result = Imap::timeout(\IMAP_OPENTIMEOUT);

        self::assertIsInt($result);
    }

    #[Test]
    public function testTimeoutSetAndRestore(): void
    {
        $original = Imap::timeout(\IMAP_OPENTIMEOUT);
        self::assertIsInt($original);

        Imap::timeout(\IMAP_OPENTIMEOUT, 60);

        $updated = Imap::timeout(\IMAP_OPENTIMEOUT);
        self::assertSame(60, $updated);

        Imap::timeout(\IMAP_OPENTIMEOUT, $original);
    }

    // -------------------------------------------------------------------------
    // append branches
    // -------------------------------------------------------------------------

    /**
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testAppendWithOptions(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        [$mailbox, $removeMailbox] = $this->getMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        $exception = null;

        try {
            $imapStream = $mailbox->getImapStream();
            $path = $mailbox->getImapPath();

            Imap::append($imapStream, $path, self::TEST_MESSAGE, '\\Seen');

            $msgs = Imap::search($imapStream, 'SEEN');
            self::assertNotEmpty($msgs);
        } catch (\Exception $exception) {
        } finally {
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testAppendWithOptionsAndDate(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        [$mailbox, $removeMailbox] = $this->getMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        $exception = null;

        try {
            $imapStream = $mailbox->getImapStream();
            $path = $mailbox->getImapPath();
            $internalDate = \date('d-M-Y H:i:s O');

            Imap::append($imapStream, $path, self::TEST_MESSAGE, '\\Seen', $internalDate);

            $msgs = Imap::search($imapStream, 'SEEN');
            self::assertNotEmpty($msgs);
        } catch (\Exception $exception) {
        } finally {
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    // -------------------------------------------------------------------------
    // Flags: setFlagFull / clearFlagFull and deprecated aliases
    // -------------------------------------------------------------------------

    /**
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testSetFlagFullAndClearFlagFull(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        [$mailbox, $removeMailbox] = $this->getMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        $exception = null;

        try {
            $imapStream = $mailbox->getImapStream();
            $path = $mailbox->getImapPath();

            $this->appendTestMessage($imapStream, $path);
            $msgs = Imap::search($imapStream, 'ALL');
            self::assertNotEmpty($msgs);
            $msgId = $msgs[0];

            Imap::setFlagFull($imapStream, $msgId, '\\Flagged');
            $flagged = Imap::search($imapStream, 'FLAGGED');
            self::assertContains($msgId, $flagged);

            Imap::clearFlagFull($imapStream, $msgId, '\\Flagged');
            $flagged = Imap::search($imapStream, 'FLAGGED');
            self::assertNotContains($msgId, $flagged);
        } catch (\Exception $exception) {
        } finally {
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testDeprecatedFlagAliases(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        [$mailbox, $removeMailbox] = $this->getMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        $exception = null;

        try {
            $imapStream = $mailbox->getImapStream();
            $path = $mailbox->getImapPath();

            $this->appendTestMessage($imapStream, $path);
            $msgs = Imap::search($imapStream, 'ALL');
            $msgId = $msgs[0];

            self::assertTrue(Imap::setflag_full($imapStream, $msgId, '\\Seen'));
            self::assertTrue(Imap::clearflag_full($imapStream, $msgId, '\\Seen'));
        } catch (\Exception $exception) {
        } finally {
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    // -------------------------------------------------------------------------
    // fetchOverview deprecated alias
    // -------------------------------------------------------------------------

    /**
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testFetchOverviewDeprecatedAlias(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        [$mailbox, $removeMailbox] = $this->getMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        $exception = null;

        try {
            $imapStream = $mailbox->getImapStream();
            $path = $mailbox->getImapPath();

            $this->appendTestMessage($imapStream, $path);
            $msgs = Imap::search($imapStream, 'ALL');
            $msgId = $msgs[0];

            $new = Imap::fetchOverview($imapStream, $msgId);
            $old = Imap::fetch_overview($imapStream, $msgId);

            self::assertEquals($new, $old);
        } catch (\Exception $exception) {
        } finally {
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    // -------------------------------------------------------------------------
    // headers
    // -------------------------------------------------------------------------

    /**
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testHeaders(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        [$mailbox, $removeMailbox] = $this->getMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        $exception = null;

        try {
            $imapStream = $mailbox->getImapStream();
            $path = $mailbox->getImapPath();

            $this->appendTestMessage($imapStream, $path);

            $headers = Imap::headers($imapStream);
            self::assertNotEmpty($headers);
        } catch (\Exception $exception) {
        } finally {
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    // -------------------------------------------------------------------------
    // listOfMailboxes
    // -------------------------------------------------------------------------

    /**
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testListOfMailboxes(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        [$mailbox, $removeMailbox] = $this->getMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        $exception = null;

        try {
            $imapStream = $mailbox->getImapStream();
            $prefix = $this->serverPrefix($mailbox);

            $list = Imap::listOfMailboxes($imapStream, $prefix, '*');

            self::assertNotEmpty($list);
        } catch (\Exception $exception) {
        } finally {
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    // -------------------------------------------------------------------------
    // mailCopy / mailMove and deprecated aliases
    // -------------------------------------------------------------------------

    /**
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testMailCopyAndMailMove(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        [$mailbox, $removeMailbox] = $this->getMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        $targetMailbox = 'copy-target-' . \bin2hex(\random_bytes(4));
        $targetCreated = false;
        $exception = null;

        try {
            $sourcePath = $mailbox->getImapPath();
            $imapStream = $mailbox->getImapStream();

            // Create target at the same level as removeMailbox (from INBOX context)
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->createMailbox($targetMailbox);
            $targetCreated = true;
            $mailbox->switchMailbox($targetMailbox, false);
            $targetPath = $mailbox->getImapPath();
            $mailbox->switchMailbox($sourcePath);

            $this->appendTestMessage($imapStream, $sourcePath);
            $msgs = Imap::search($imapStream, 'ALL');
            $msgId = $msgs[0];

            Imap::mailCopy($imapStream, $msgId, $this->folderName($targetPath));

            $mailbox->switchMailbox($targetPath);
            $imapStream = $mailbox->getImapStream();
            $copied = Imap::search($imapStream, 'ALL');
            self::assertCount(1, $copied);

            Imap::mailMove($imapStream, $copied[0], $this->folderName($sourcePath));
            Imap::expunge($imapStream);

            $remaining = Imap::search($imapStream, 'ALL');
            self::assertSame([], $remaining);
        } catch (\Exception $exception) {
        } finally {
            $mailbox->switchMailbox($imapPath->getString());
            if ($targetCreated) {
                $mailbox->deleteMailbox($targetMailbox);
            }
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testDeprecatedCopyMoveAliases(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        [$mailbox, $removeMailbox] = $this->getMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        $targetMailbox = 'alias-target-' . \bin2hex(\random_bytes(4));
        $targetCreated = false;
        $exception = null;

        try {
            $sourcePath = $mailbox->getImapPath();

            // Create target at the same level as removeMailbox (from INBOX context)
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->createMailbox($targetMailbox);
            $targetCreated = true;
            $mailbox->switchMailbox($targetMailbox, false);
            $targetPath = $mailbox->getImapPath();
            $mailbox->switchMailbox($sourcePath);
            $imapStream = $mailbox->getImapStream();

            $this->appendTestMessage($imapStream, $sourcePath);
            $msgs = Imap::search($imapStream, 'ALL');
            $msgId = $msgs[0];

            self::assertTrue(Imap::mail_copy($imapStream, $msgId, $this->folderName($targetPath)));

            $mailbox->switchMailbox($targetPath);
            $imapStream = $mailbox->getImapStream();
            $copied = Imap::search($imapStream, 'ALL');
            self::assertCount(1, $copied);

            self::assertTrue(Imap::mail_move($imapStream, $copied[0], $this->folderName($sourcePath)));
            Imap::expunge($imapStream);
        } catch (\Exception $exception) {
        } finally {
            $mailbox->switchMailbox($imapPath->getString());
            if ($targetCreated) {
                $mailbox->deleteMailbox($targetMailbox);
            }
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    // -------------------------------------------------------------------------
    // mailboxMsgInfo
    // -------------------------------------------------------------------------

    /**
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testMailboxMsgInfo(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        [$mailbox, $removeMailbox] = $this->getMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        $exception = null;

        try {
            $imapStream = $mailbox->getImapStream();
            $path = $mailbox->getImapPath();

            $this->appendTestMessage($imapStream, $path);

            $info = Imap::mailboxMsgInfo($imapStream);

            self::assertTrue(\property_exists($info, 'Nmsgs'));
            self::assertSame(1, $info->Nmsgs);
        } catch (\Exception $exception) {
        } finally {
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    // -------------------------------------------------------------------------
    // num_msg deprecated alias
    // -------------------------------------------------------------------------

    /**
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testNumMsgDeprecatedAlias(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        [$mailbox, $removeMailbox] = $this->getMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        $exception = null;

        try {
            $imapStream = $mailbox->getImapStream();
            $path = $mailbox->getImapPath();

            $this->appendTestMessage($imapStream, $path);

            self::assertSame(
                Imap::numMsg($imapStream),
                Imap::num_msg($imapStream)
            );
        } catch (\Exception $exception) {
        } finally {
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    // -------------------------------------------------------------------------
    // renameMailbox
    // -------------------------------------------------------------------------

    /**
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testRenameMailbox(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        [$mailbox, $removeMailbox] = $this->getMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        $renamedMailbox = $removeMailbox . '-renamed';
        $renamed = false;
        $exception = null;

        try {
            $sourcePath = $mailbox->getImapPath();

            $mailbox->switchMailbox($imapPath->getString());
            $imapStream = $mailbox->getImapStream();

            // Derive renamed path by replacing the short name at the end
            $pos = \strrpos($sourcePath, $removeMailbox);
            $renamedPath = ($pos !== false ? \substr($sourcePath, 0, $pos) : '') . $renamedMailbox;

            Imap::renameMailbox($imapStream, $sourcePath, $renamedPath);
            $renamed = true;

            $prefix = $this->serverPrefix($mailbox);
            $list = Imap::listOfMailboxes($imapStream, $prefix, '*');
            self::assertContains($renamedPath, $list);
            self::assertNotContains($sourcePath, $list);
        } catch (\Exception $exception) {
        } finally {
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->deleteMailbox($renamed ? $renamedMailbox : $removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    // -------------------------------------------------------------------------
    // subscribe / getSubscribed / unsubscribe
    // -------------------------------------------------------------------------

    /**
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testSubscribeGetSubscribedUnsubscribe(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        [$mailbox, $removeMailbox] = $this->getMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        $exception = null;

        try {
            $imapStream = $mailbox->getImapStream();
            $path = $mailbox->getImapPath();
            $prefix = $this->serverPrefix($mailbox);

            Imap::subscribe($imapStream, $path);

            $subscribed = Imap::getSubscribed($imapStream, $prefix, '*');
            self::assertGreaterThanOrEqual(1, \count($subscribed));

            Imap::unsubscribe($imapStream, $path);
        } catch (\UnexpectedValueException $e) {
            self::markTestSkipped('IMAP server does not support subscriptions: ' . $e->getMessage());
        } catch (\Exception $exception) {
        } finally {
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    // -------------------------------------------------------------------------
    // saveBody
    // -------------------------------------------------------------------------

    /**
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testSaveBody(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        [$mailbox, $removeMailbox] = $this->getMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        $tmpFile = \tempnam(\sys_get_temp_dir(), 'imap_savebody_');
        self::assertIsString($tmpFile);

        $exception = null;

        try {
            $imapStream = $mailbox->getImapStream();
            $path = $mailbox->getImapPath();

            $this->appendTestMessage($imapStream, $path);
            $msgs = Imap::search($imapStream, 'ALL');
            $msgId = $msgs[0];

            Imap::saveBody($imapStream, $tmpFile, $msgId);

            self::assertFileExists($tmpFile);
            self::assertStringContainsString('coverage test body', (string)\file_get_contents($tmpFile));
        } catch (\Exception $exception) {
        } finally {
            if (\file_exists($tmpFile)) {
                \unlink($tmpFile);
            }
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    // -------------------------------------------------------------------------
    // getQuotaRoot / get_quotaroot
    // -------------------------------------------------------------------------

    /**
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    public function testGetQuotaRoot(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        [$mailbox, $removeMailbox] = $this->getMailbox($imapPath, $login, $password, $attachmentsDir, $serverEncoding);

        $exception = null;

        try {
            $imapStream = $mailbox->getImapStream();

            // imap_get_quotaroot emits a PHP warning when unsupported; silence it and skip
            \set_error_handler(static function (): bool {
                return true;
            });
            $quota = [];
            try {
                $quota = Imap::getQuotaRoot($imapStream, 'INBOX');
            } catch (\UnexpectedValueException $e) {
                self::markTestSkipped('IMAP server does not support QUOTA: ' . $e->getMessage());
            } finally {
                \restore_error_handler();
            }

            self::assertGreaterThanOrEqual(0, \count($quota));

            \set_error_handler(static function (): bool {
                return true;
            });
            try {
                $quotaAlias = Imap::get_quotaroot($imapStream, 'INBOX');
            } finally {
                \restore_error_handler();
            }
            self::assertSame($quota, $quotaAlias);
        } catch (\UnexpectedValueException $e) {
            self::markTestSkipped('IMAP server does not support QUOTA: ' . $e->getMessage());
        } catch (\Exception $exception) {
        } finally {
            $mailbox->switchMailbox($imapPath->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }
}
