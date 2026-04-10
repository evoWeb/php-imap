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
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type MAILBOX_ARGS = array{
 *      0: HiddenString,
 *      1: HiddenString,
 *      2: HiddenString,
 *      3: string,
 *      4?: string,
 *      5?: array,
 * }
 * @phpstan-type COMPOSE_ENVELOPE = array{
 *      subject?: string
 * }
 * @phpstan-type COMPOSE_BODY = list<array{
 *      id?: string,
 *      type?: int,
 *      encoding?: int,
 *      charset?: string,
 *      subtype?: string,
 *      description?: string,
 *      disposition?: array{filename: string, type?: string},
 *      'disposition.type'?: string,
 *      'type.parameters'?: array{name: string},
 *      'contents.data'?: string,
 * }>
 * @phpstan-type OPEN_ARGS = array{
 *      0: HiddenString,
 *      1: HiddenString,
 *      2: HiddenString,
 *      3: int,
 *      4: int,
 *      5: array{DISABLE_AUTHENTICATOR: string}|array<empty, empty>
 *  }
 */
abstract class AbstractMailboxTest extends TestCase
{
    use MailboxTestingTrait;

    /**
     * @phpstan-return \Generator<int, array{
     *      0: COMPOSE_ENVELOPE,
     *      1: COMPOSE_BODY,
     *      2: string
     * }, mixed, void>
     */
    public static function composeProvider(): \Generator
    {
        yield from [];
    }

    /**
     * Get subject search criteria and subject.
     *
     * @phpstan-param array{subject?: string} $envelope
     *
     * @phpstan-return array{0: string, 1: string}
     */
    protected function subjectSearchCriteriaAndSubject(array $envelope): array
    {
        /** @var ?string $subject */
        $subject = $envelope['subject'] ?? null;

        self::assertIsString($subject);

        $searchCriteria = \sprintf('SUBJECT "%s"', $subject);

        /** @phpstan-var array{0: string, 1: string} */
        return [$searchCriteria, $subject];
    }

    protected function maybeSkipAppendTest(array $envelope): bool
    {
        if (!isset($envelope['subject'])) {
            self::markTestSkipped('Cannot search for message by subject, no subject specified!');
        }

        return false;
    }
}
