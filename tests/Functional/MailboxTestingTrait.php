<?php

/**
 * Live Mailbox - PHPUnit tests.
 *
 * Runs tests on a live mailbox
 *
 * @author BAPCLTD-Marv
 */

declare(strict_types=1);

namespace PhpImap\Tests\Functional;

use ParagonIE\HiddenString\HiddenString;
use PhpImap\Exceptions\ConnectionException;
use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Mailbox;
use Random\RandomException;

/**
 * @phpstan-import-type MAILBOX_ARGS from AbstractMailboxTest
 */
trait MailboxTestingTrait
{
    /**
     * Provides constructor arguments for a live mailbox.
     *
     * @return array<string, MAILBOX_ARGS>
     */
    public static function mailBoxProvider(): array
    {
        $sets = [];

        $imapPath = \getenv('PHPIMAP_IMAP_PATH');
        $login = \getenv('PHPIMAP_LOGIN');
        $password = \getenv('PHPIMAP_PASSWORD');

        if (\is_string($imapPath) && \is_string($login) && \is_string($password)) {
            $sets['CI ENV'] = [
                new HiddenString($imapPath),
                new HiddenString($login),
                new HiddenString($password, true, true),
                \sys_get_temp_dir(),
            ];
        }

        return $sets;
    }

    /**
     * Get instance of Mailbox, pre-set to a random mailbox.
     *
     * @return (Mailbox|HiddenString|string)[]
     *
     * @throws InvalidParameterException
     * @throws RandomException
     * @throws ConnectionException
     */
    protected function getMailbox(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): array {
        $mailbox = new Mailbox(
            $imapPath->getString(),
            $login->getString(),
            $password->getString(),
            $attachmentsDir,
            $serverEncoding
        );

        $random = 'test-box-' . \date('c') . \bin2hex(\random_bytes(4));

        $mailbox->createMailbox($random);

        $mailbox->switchMailbox($random, false);

        return [$mailbox, $random, $imapPath];
    }

    /**
     * @phpstan-param MAILBOX_ARGS $mailboxArguments
     *
     * @return (Mailbox|HiddenString|string)[]
     *
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     */
    protected function getMailboxFromArgs(array $mailboxArguments): array
    {
        [$imapPath, $login, $password, $attachmentsDir] = $mailboxArguments;

        return $this->getMailbox(
            $imapPath,
            $login,
            $password,
            $attachmentsDir,
            $mailboxArguments[4] ?? 'UTF-8'
        );
    }
}
