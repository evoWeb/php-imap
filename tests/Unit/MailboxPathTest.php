<?php

declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Tests\Fixtures\Constants;
use PhpImap\Tests\Fixtures\Mailbox as FixtureMailbox;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MailboxPathTest extends TestCase
{
    private string $imapPath = Constants::IMAP_PATH_INBOX_NO_VALID_SSL;

    private string $login = Constants::LOGIN;

    private string $password = Constants::PASSWORD;

    private string $attachmentsDir = '.';

    /**
     * Provides test data for testing path delimiter.
     *
     * @return array<int|string, string[]>
     */
    public static function pathDelimiterProvider(): array
    {
        return [
            '0' => ['0'], '1' => ['1'], '2' => ['2'], '3' => ['3'], '4' => ['4'],
            '5' => ['5'], '6' => ['6'], '7' => ['7'], '8' => ['8'], '9' => ['9'],
            'a' => ['a'], 'b' => ['b'], 'c' => ['c'], 'd' => ['d'], 'e' => ['e'],
            'f' => ['f'], 'g' => ['g'], 'h' => ['h'], 'i' => ['i'], 'j' => ['j'],
            'k' => ['k'], 'l' => ['l'], 'm' => ['m'], 'n' => ['n'], 'o' => ['o'],
            'p' => ['p'], 'q' => ['q'], 'r' => ['r'], 's' => ['s'], 't' => ['t'],
            'u' => ['u'], 'v' => ['v'], 'w' => ['w'], 'x' => ['x'], 'y' => ['y'],
            'z' => ['z'], '!' => ['!'], '\\' => ['\\'], '$' => ['$'], '%' => ['%'],
            '§' => ['§'], '&' => ['&'], '/' => ['/'], '(' => ['('], ')' => [')'],
            '=' => ['='], '#' => ['#'], '~' => ['~'], '*' => ['*'], '+' => ['+'],
            ',' => [','], ';' => [';'], '.' => ['.'], ':' => [':'], '<' => ['<'],
            '>' => ['>'], '|' => ['|'], '_' => ['_'],
        ];
    }

    /**
     * Test, that the path delimiter has a default value.
     *
     * @throws \Exception
     */
    #[Test]
    public function testPathDelimiterHasADefault(): void
    {
        self::assertNotEmpty($this->getMailbox()->getPathDelimiter());
    }

    /**
     * Test, that the path delimiter is checked for supported chars.
     *
     * @throws \Exception
     */
    #[Test]
    #[DataProvider('pathDelimiterProvider')]
    public function testPathDelimiterIsBeingChecked(string $str): void
    {
        $supported_delimiters = ['.', '/'];

        $mailbox = $this->getMailbox();

        if (\in_array($str, $supported_delimiters)) {
            self::assertTrue($mailbox->validatePathDelimiter($str));
        } else {
            $this->expectException(InvalidParameterException::class);
            $mailbox->setPathDelimiter($str);
        }
    }

    /**
     * Test, that the path delimiter can be set.
     *
     * @throws InvalidParameterException
     */
    #[Test]
    public function testSetAndGetPathDelimiter(): void
    {
        $mailbox = $this->getMailbox();

        $mailbox->setPathDelimiter('.');
        self::assertEquals('.', $mailbox->getPathDelimiter());

        $mailbox->setPathDelimiter('/');
        self::assertEquals('/', $mailbox->getPathDelimiter());
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testGetImapPath(): void
    {
        $mailbox = $this->getMailbox();

        self::assertSame($this->imapPath, $mailbox->getImapPath());
    }

    #[Test]
    public function testGetCombinedPathEmptyFolder(): void
    {
        $mailbox = new FixtureMailbox(Constants::IMAP_PATH_INBOX, '', '');

        self::assertSame(Constants::IMAP_PATH_INBOX, $mailbox->exposedGetCombinedPath(''));
    }

    #[Test]
    public function testGetCombinedPathSpacesOnly(): void
    {
        $mailbox = new FixtureMailbox(Constants::IMAP_PATH_INBOX, '', '');

        self::assertSame(Constants::IMAP_PATH_INBOX, $mailbox->exposedGetCombinedPath('   '));
    }

    #[Test]
    public function testGetCombinedPathWithFolder(): void
    {
        $mailbox = new FixtureMailbox(Constants::IMAP_PATH_INBOX, '', '');

        self::assertSame(Constants::IMAP_PATH_INBOX . '.Sent', $mailbox->exposedGetCombinedPath('Sent'));
    }

    #[Test]
    public function testGetCombinedPathEndingWithBrace(): void
    {
        $mailbox = new FixtureMailbox(Constants::IMAP_PATH, '', '');

        self::assertSame(Constants::IMAP_PATH_INBOX, $mailbox->exposedGetCombinedPath('INBOX'));
    }

    #[Test]
    public function testGetCombinedPathAbsolute(): void
    {
        $mailbox = new FixtureMailbox(Constants::IMAP_PATH_INBOX, '', '');

        self::assertSame(Constants::IMAP_PATH . 'Sent', $mailbox->exposedGetCombinedPath('Sent', true));
    }

    #[Test]
    public function testGetCombinedPathAbsoluteWithSlash(): void
    {
        $mailbox = new FixtureMailbox(Constants::IMAP_PATH_INBOX, '', '');

        self::assertSame(Constants::IMAP_PATH, $mailbox->exposedGetCombinedPath('/', true));
    }

    #[Test]
    public function testGetCombinedPathAbsoluteWithoutBrace(): void
    {
        $mailbox = new FixtureMailbox('no-brace-path', '', '');

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('"}" was not present in IMAP path!');

        $mailbox->exposedGetCombinedPath('Folder', true);
    }

    /**
     * @throws InvalidParameterException
     */
    #[Test]
    public function testGetCombinedPathWithSlashDelimiter(): void
    {
        $mailbox = new FixtureMailbox(Constants::IMAP_PATH_INBOX, '', '');
        $mailbox->setPathDelimiter('/');

        self::assertSame(Constants::IMAP_PATH_INBOX . '/Sent', $mailbox->exposedGetCombinedPath('Sent'));
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
