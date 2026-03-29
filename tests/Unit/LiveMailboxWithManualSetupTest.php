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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * @phpstan-type MAILBOX_ARGS = array{
 *	0:HiddenString,
 *	1:HiddenString,
 *	2:HiddenString,
 *	3:string,
 *	4?:string
 * }
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
     * @phpstan-return Generator<int, array{0: array{0: HiddenString, 1: HiddenString, 2: HiddenString, 3: string, 4?: string}}, mixed, void>
     */
    public static function statusProviderAbsolutePath(): \Generator
    {
        foreach (self::RelativeToRootPathProvider() as $path_args) {
            foreach (self::MailBoxProvider() as $args) {
                $args[0] = new HiddenString($args[0]->getString() . $path_args[0]);

                yield [$args];
            }
        }
    }

    /**
     * Tests the status of an absolute mailbox path set from the Mailbox constructor.
     *
     * @phpstan-param MAILBOX_ARGS $mailbox_args
     */
    #[Test]
    #[DataProvider('statusProviderAbsolutePath')]
    #[Group('live')]
    #[Group('live-manual')]
    public function testAbsolutePathStatusFromConstruction(
        array $mailbox_args
    ): void {
        [$mailbox] = $this->getMailboxFromArgs($mailbox_args);

        self::assertNotFalse($mailbox->statusMailbox());
    }
}
