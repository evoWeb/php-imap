<?php

/**
* @author BAPCLTD-Marv
*/
declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use ParagonIE\HiddenString\HiddenString;
use PhpImap\Exceptions\ConnectionException;
use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Imap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as Base;
use Random\RandomException;

/**
 * @phpstan-import-type OPEN_ARGS from AbstractLiveMailboxTest
 */
class ImapTest extends Base
{
    use LiveMailboxTestingTrait;

    /**
     * @phpstan-return \Generator<
     *     'CI ENV with invalid password'|'empty mailbox/username/password',
     *      array{
     *          0: class-string<ConnectionException>,
     *          1: non-empty-string,
     *          2: OPEN_ARGS
     *      },
     *      mixed,
     *      void
     * >
     */
    public static function OpenFailure(): \Generator
    {
        yield 'empty mailbox/username/password' => [
            ConnectionException::class,
            'Can\'t open mailbox : no such mailbox',
            [
                new HiddenString(''),
                new HiddenString(''),
                new HiddenString(''),
                0,
                0,
                [],
            ],
        ];

        $imapPath = \getenv('PHPIMAP_IMAP_PATH');
        $login = \getenv('PHPIMAP_LOGIN');
        $password = \getenv('PHPIMAP_PASSWORD');

        if (\is_string($imapPath) && \is_string($login) && \is_string($password)) {
            yield 'CI ENV with invalid password' => [
                ConnectionException::class,
                '/.*\[AUTHENTICATIONFAILED\].*/',
                [
                    new HiddenString($imapPath, true, true),
                    new HiddenString($login, true, true),
                    new HiddenString(\strrev($password), true, true),
                    0,
                    0,
                    [],
                ],
                true,
            ];
        }
    }

    /**
     * @phpstan-param class-string<\Throwable> $exception
     * @phpstan-param OPEN_ARGS $openArguments
     */
    #[Test]
    #[DataProvider('OpenFailure')]
    public function testOpenFailure(
        string $exception,
        string $message,
        array $openArguments,
        bool $messageAsRegex = false
    ): void {
        $this->expectException($exception);

        if ($messageAsRegex) {
            $this->expectExceptionMessageMatches($message);
        } else {
            $this->expectExceptionMessage($message);
        }

        Imap::open(
            $openArguments[0]->getString(),
            $openArguments[1]->getString(),
            $openArguments[2]->getString(),
            $openArguments[3],
            $openArguments[4],
            $openArguments[5]
        );
    }

    /**
     * @throws \Throwable
     * @throws InvalidParameterException
     * @throws RandomException
     */
    #[Test]
    #[DataProvider('MailBoxProvider')]
    #[Group('live')]
    public function testSortEmpty(HiddenString $path, HiddenString $login, HiddenString $password): void
    {
        [$mailbox, $removeMailbox, $path] = $this->getMailboxFromArgs([
            $path,
            $login,
            $password,
            \sys_get_temp_dir(),
        ]);

        /** @var \Throwable|null $exception */
        $exception = null;

        try {
            self::assertSame(
                [],
                Imap::sort(
                    $mailbox->getImapStream(),
                    \SORTARRIVAL,
                    false,
                    0
                )
            );
        } catch (\Throwable $exception) {
        } finally {
            $mailbox->switchMailbox($path->getString());
            $mailbox->deleteMailbox($removeMailbox);
            $mailbox->disconnect();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }
}
