<?php

/**
 * Live Mailbox - PHPUnit tests.
 *
 * Runs tests on a live mailbox
 *
 * @author BAPCLTD-Marv
 */
declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use Generator;
use ParagonIE\HiddenString\HiddenString;
use PhpImap\Exceptions\InvalidParameterException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Random\RandomException;

/**
 * @phpstan-import-type MAILBOX_ARGS from AbstractLiveMailboxTest
 */
class LiveMailboxWithManualSetupTest extends AbstractLiveMailboxTest
{
    /**
     * @phpstan-return Generator<int, array{0: '.issue-499.Éléments envoyés'}, mixed, void>
     */
    public static function RelativeToRootPathProvider(): \Generator
    {
        yield [
            '.issue-499.Éléments envoyés',
        ];
    }

    /**
     * @phpstan-return Generator<int, MAILBOX_ARGS}, mixed, void>
     */
    public static function statusProviderAbsolutePath(): \Generator
    {
        foreach (self::RelativeToRootPathProvider() as $pathArguments) {
            foreach (self::MailBoxProvider() as $args) {
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
     * @throws RandomException
     * @throws InvalidParameterException
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
