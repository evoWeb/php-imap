<?php

declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Mailbox;
use PhpImap\Tests\Fixtures\Constants;
use PhpImap\Tests\Fixtures\Mailbox as FixtureMailbox;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MailboxAttachmentTest extends TestCase
{
    private string $imapPath = Constants::IMAP_PATH_INBOX_NO_VALID_SSL;

    private string $login = Constants::LOGIN;

    private string $password = Constants::PASSWORD;

    private string $attachmentsDir = '.';

    /**
     * Provides test data for testing attachments ignore.
     *
     * @phpstan-return array<string, array{0: bool}>
     */
    public static function attachmentsIgnoreProvider(): array
    {
        /** @phpstan-var array<string, array{0: bool}> */
        return [
            'true' => [true],
            'false' => [false],
        ];
    }

    /**
     * @return string[][]
     */
    public static function attachmentDirFailureProvider(): array
    {
        return [
            [
                __DIR__,
                '',
                InvalidParameterException::class,
                Constants::ERROR_ATTACHMENTS_DIR,
            ],
            [
                __DIR__,
                ' ',
                InvalidParameterException::class,
                Constants::ERROR_ATTACHMENTS_DIR,
            ],
            [
                __DIR__,
                __FILE__,
                InvalidParameterException::class,
                'Directory "' . __FILE__ . '" not found',
            ],
        ];
    }

    /**
     * Test, that the attachments are not ignored by default.
     *
     * @throws \Exception
     */
    #[Test]
    public function testGetAttachmentsAreNotIgnoredByDefault(): void
    {
        self::assertFalse($this->getMailbox()->getAttachmentsIgnore());
    }

    /**
     * Test, that attachments can be ignored and only valid values are accepted.
     *
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('attachmentsIgnoreProvider')]
    public function testSetAttachmentsIgnore(bool $paramValue): void
    {
        $mailbox = $this->getMailbox();
        $mailbox->setAttachmentsIgnore($paramValue);
        self::assertEquals($paramValue, $mailbox->getAttachmentsIgnore());
    }

    /**
     * Test that setting the attachments directory fails when expected.
     *
     * @phpstan-param class-string<\Exception> $expectedException
     *
     * @throws InvalidParameterException
     */
    #[Test]
    #[DataProvider('attachmentDirFailureProvider')]
    public function testAttachmentDirFailure(
        string $initialDir,
        string $attachmentsDir,
        string $expectedException,
        string $expectedExceptionMessage
    ): void {
        $mailbox = new Mailbox('', '', '', $initialDir);

        self::assertSame(\trim($initialDir), $mailbox->getAttachmentsDir());

        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $mailbox->setAttachmentsDir($attachmentsDir);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testSetAndGetAttachmentFilenameMode(): void
    {
        $mailbox = $this->getMailbox();

        self::assertFalse($mailbox->getAttachmentFilenameMode());

        $mailbox->setAttachmentFilenameMode(true);
        self::assertTrue($mailbox->getAttachmentFilenameMode());

        $mailbox->setAttachmentFilenameMode(false);
        self::assertFalse($mailbox->getAttachmentFilenameMode());
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testSetAttachmentsDirSuccess(): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setAttachmentsDir(\sys_get_temp_dir());

        self::assertSame(\rtrim(\realpath(\sys_get_temp_dir()), '\\/'), $mailbox->getAttachmentsDir());
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testSetAttachmentsDirWithNonExistentDirectory(): void
    {
        $mailbox = $this->getMailbox();

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Directory "/nonexistent/path" not found');

        $mailbox->setAttachmentsDir('/nonexistent/path');
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testSetAttachmentsDirWithEmptyString(): void
    {
        $mailbox = $this->getMailbox();

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage(Constants::ERROR_ATTACHMENTS_DIR);

        $mailbox->setAttachmentsDir('');
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
