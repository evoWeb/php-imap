<?php

declare(strict_types=1);

namespace PhpImap\Tests\Functional;

use PhpImap\Mailbox;
use PhpImap\Tests\Fixtures\DataPartInfo as FixtureDataPartInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DownloadAttachmentSavingTest extends TestCase
{
    private FixtureDataPartInfo $dataPartInfo;

    protected function setUp(): void
    {
        $helperMailbox = new Mailbox('', '', '');
        // ENCQUOTEDPRINTABLE is used so that repeated fetch() calls are idempotent:
        // quoted_printable_decode('test') === 'test' on every invocation.
        $this->dataPartInfo = new FixtureDataPartInfo($helperMailbox, 0, 0, \ENCQUOTEDPRINTABLE, 0);
        $this->dataPartInfo->setData('test');
    }

    /**
     * Creates a minimal valid partStructure object with sensible defaults.
     */
    private function buildPartStructure(): object
    {
        return (object)\array_merge([
            'subtype' => 'PLAIN',
            'encoding' => \ENCBASE64,
            'ifid' => false,
            'ifsubtype' => true,
            'ifdescription' => false,
        ], []);
    }

    // -------------------------------------------------------------------------
    // Disk saving
    // -------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    #[Test]
    public function testNoFileIsSavedWhenAttachmentsDirIsNull(): void
    {
        // Mailbox with no attachmentsDir (null by default)
        $mailbox = new Mailbox('', '', '');

        $tempDir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'phpimaptest_nodir_' . \uniqid();
        \mkdir($tempDir);

        try {
            $mailbox->downloadAttachment($this->dataPartInfo, ['filename' => 'test.txt'], $this->buildPartStructure());

            // No file should appear in any directory we control
            self::assertCount(0, (array)\glob($tempDir . \DIRECTORY_SEPARATOR . '*'));
        } finally {
            \rmdir($tempDir);
        }
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testFileIsSavedWithOriginalNameWhenAttachmentFilenameModeIsTrue(): void
    {
        $tempDir = $this->createTempDir();

        try {
            $mailbox = new Mailbox('', '', '', $tempDir);
            $mailbox->setAttachmentFilenameMode(true);

            $attachment = $mailbox->downloadAttachment(
                $this->dataPartInfo,
                ['filename' => 'hello.txt'],
                $this->buildPartStructure()
            );

            $filePath = $attachment->filePath;

            self::assertFileExists($filePath);
            self::assertSame('hello.txt', \basename($filePath));
            self::assertSame('test', \file_get_contents($filePath));
        } finally {
            $this->removeTempDir($tempDir);
        }
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testFileIsSavedWithRandomHexNameWhenAttachmentFilenameModeIsFalse(): void
    {
        $tempDir = $this->createTempDir();

        try {
            $mailbox = new Mailbox('', '', '', $tempDir);
            $mailbox->setAttachmentFilenameMode(false);

            $attachment = $mailbox->downloadAttachment(
                $this->dataPartInfo,
                ['filename' => 'original.txt'],
                $this->buildPartStructure()
            );

            $filePath = $attachment->filePath;

            self::assertFileExists($filePath);
            // The basename should NOT be the original filename when mode is false
            self::assertNotSame('original.txt', \basename($filePath));
            // The filename should be a hex string followed by a dot and extension
            self::assertMatchesRegularExpression('/^[0-9a-f]{32}\./', \basename($filePath));
        } finally {
            $this->removeTempDir($tempDir);
        }
    }

    /**
     * @throws \Exception
     */
    #[Test]
    public function testFilePathIsTruncatedToMaxLengthWhenFilenameIsTooLong(): void
    {
        $tempDir = $this->createTempDir();

        try {
            $mailbox = new Mailbox('', '', '', $tempDir);
            $mailbox->setAttachmentFilenameMode(true);

            // Create a filename so long that dir + sep + filename > MAX_LENGTH_FILEPATH
            $longName = \str_repeat('a', 260) . '.txt';

            $attachment = $mailbox->downloadAttachment(
                $this->dataPartInfo,
                ['filename' => $longName],
                $this->buildPartStructure()
            );

            $filePath = $attachment->filePath;

            self::assertLessThanOrEqual(Mailbox::MAX_LENGTH_FILEPATH, \strlen($filePath));
            self::assertStringEndsWith('.txt', $filePath);
        } finally {
            $this->removeTempDir($tempDir);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createTempDir(): string
    {
        $dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'phpimaptest_' . \uniqid();
        \mkdir($dir);

        return $dir;
    }

    private function removeTempDir(string $dir): void
    {
        foreach ((array)\glob($dir . \DIRECTORY_SEPARATOR . '*') as $file) {
            \unlink((string)$file);
        }
        \rmdir($dir);
    }
}
