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
use PhpImap\Tests\Fixtures\Constants;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Random\RandomException;

/**
 * @phpstan-import-type MAILBOX_ARGS from AbstractMailboxTest
 */
class MailboxWithManualSetupTest extends AbstractMailboxTest
{
    /**
     * @phpstan-return \Generator<int, array{0: string}, mixed, void>
     */
    public static function relativeToRootPathProvider(): \Generator
    {
        yield [
            '.issue-499.' . Constants::ENVOYES,
        ];
    }

    /**
     * @phpstan-return \Generator<int, MAILBOX_ARGS[], mixed, void>
     */
    public static function statusProviderAbsolutePath(): \Generator
    {
        foreach (self::relativeToRootPathProvider() as $pathArguments) {
            foreach (self::mailBoxProvider() as $args) {
                $args[0] = new HiddenString($args[0]->getString() . $pathArguments[0]);

                yield [$args];
            }
        }
    }

    /**
     * Tests the status of an absolute mailbox path set from the Mailbox constructor.
     *
     * @phpstan-param MAILBOX_ARGS $mailboxArguments
     *
     * @throws ConnectionException
     * @throws InvalidParameterException
     * @throws RandomException
     */
    #[Test]
    #[DataProvider('statusProviderAbsolutePath')]
    #[Group('live')]
    #[Group('live-manual')]
    public function testAbsolutePathStatusFromConstruction(array $mailboxArguments): void
    {
        [$mailbox] = $this->getMailboxFromArgs($mailboxArguments);

        self::assertNotFalse($mailbox->statusMailbox());
    }
}
