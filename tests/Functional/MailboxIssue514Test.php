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
use PhpImap\Imap;
use PhpImap\Tests\Fixtures\Constants;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Random\RandomException;

/**
 * @phpstan-import-type COMPOSE_ENVELOPE from AbstractMailboxTest
 */
class MailboxIssue514Test extends AbstractMailboxTest
{
    /**
     * @throws ConnectionException
     * @throws \Exception
     * @throws InvalidParameterException
     * @throws RandomException
     */
    #[Test]
    #[DataProvider('mailBoxProvider')]
    #[Group('live')]
    #[Group('live-issue-514')]
    public function testEmbed(
        HiddenString $imapPath,
        HiddenString $login,
        HiddenString $password,
        string $attachmentsDir,
        string $serverEncoding = 'UTF-8'
    ): void {
        /** @var ?\Exception $exception */
        $exception = null;

        /** @phpstan-var COMPOSE_ENVELOPE $envelope */
        $envelope = [
            'subject' => 'barbushin/php-imap#514--' . \bin2hex(\random_bytes(16)),
        ];

        [$searchCriteria] = $this->subjectSearchCriteriaAndSubject($envelope);

        $body = [
            [
                'type' => \TYPEMULTIPART,
            ],
            [
                'type' => \TYPETEXT,
                'subtype' => 'plain',
                'contents.data' => 'foo',
            ],
            [
                'type' => \TYPETEXT,
                'subtype' => 'html',
                'contents.data' => \implode('', [
                    '<img alt="png" width="5" height="1" src="cid:foo.png">',
                    '<img alt="webp" width="5" height="1" src="cid:foo.webp">',
                ]),
            ],
            [
                'type' => \TYPEIMAGE,
                'subtype' => 'png',
                'encoding' => \ENCBASE64,
                'id' => 'foo.png',
                'description' => 'foo.png',
                'disposition' => ['filename' => 'foo.png'],
                'disposition.type' => 'inline',
                'type.parameters' => ['name' => 'foo.png'],
                'contents.data' => \base64_encode(
                    \file_get_contents(__DIR__ . '/../Fixtures/rgbkw5x1.png')
                ),
            ],
            [
                'type' => \TYPEIMAGE,
                'subtype' => 'webp',
                'encoding' => \ENCBASE64,
                'id' => 'foo.webp',
                'description' => 'foo.webp',
                'disposition' => ['filename' => 'foo.webp'],
                'disposition.type' => 'inline',
                'type.parameters' => ['name' => 'foo.webp'],
                'contents.data' => \base64_encode(
                    \file_get_contents(__DIR__ . '/../Fixtures/rgbkw5x1.webp')
                ),
            ],
        ];

        $message = Imap::mailCompose($envelope, $body);

        [$mailbox, $removeMailbox, $path] = $this->getMailboxFromArgs([
            $imapPath,
            $login,
            $password,
            $attachmentsDir,
            $serverEncoding,
        ]);

        try {
            $search = $mailbox->searchMailbox($searchCriteria);

            self::assertCount(0, $search, Constants::SUBJECT_INSUFFICIENT_UNIQUE);

            $mailbox->appendMessageToMailbox($message);

            $search = $mailbox->searchMailbox($searchCriteria);

            self::assertCount(1, $search, Constants::SUBJECT_NOT_FOUND);

            $result = $mailbox->getMail($search[0], false);

            /** @var array<string, int> $counts */
            $counts = [];

            foreach ($result->getAttachments() as $attachment) {
                if (!isset($counts[(string)$attachment->contentId])) {
                    $counts[(string)$attachment->contentId] = 0;
                }

                ++$counts[(string)$attachment->contentId];
            }

            self::assertCount(
                2,
                $counts,
                (
                    'counts should only contain foo.png and foo.webp, found: ' .
                    \implode(', ', \array_keys($counts))
                )
            );

            foreach ($counts as $cid => $count) {
                self::assertSame(
                    1,
                    $count,
                    $cid . ' had ' . $count . ', expected 1.'
                );
            }

            self::assertSame(
                'foo',
                $result->textPlain,
                'plain text body did not match expected result!'
            );

            $embedded = \implode('', [
                '<img alt="png" width="5" height="1" src="',
                'data:image/png;base64, ',
                $body[3]['contents.data'],
                '">',
                '<img alt="webp" width="5" height="1" src="',
                'data:image/webp;base64, ',
                $body[4]['contents.data'],
                '">',
            ]);

            self::assertSame(
                [
                    'foo.png' => 'cid:foo.png',
                    'foo.webp' => 'cid:foo.webp',
                ],
                $result->getInternalLinksPlaceholders(),
                'Internal link placeholders did not match expected result!'
            );

            $replaced = \implode('', [
                '<img alt="png" width="5" height="1" src="',
                'foo.png',
                '">',
                '<img alt="webp" width="5" height="1" src="',
                'foo.webp',
                '">',
            ]);

            foreach ($result->getAttachments() as $attachment) {
                if ($attachment->contentId === 'foo.png') {
                    $replaced = \str_replace(
                        'foo.png',
                        '/' . \basename($attachment->filePath),
                        $replaced
                    );
                } elseif ($attachment->contentId === 'foo.webp') {
                    $replaced = \str_replace(
                        'foo.webp',
                        '/' . \basename($attachment->filePath),
                        $replaced
                    );
                }
            }

            self::assertSame(
                $replaced,
                $result->replaceInternalLinks(''),
                'replaced html body did not match expected result!'
            );

            self::assertSame(
                $body[2]['contents.data'],
                $result->textHtml,
                'unembeded html body did not match expected result!'
            );

            $result->embedImageAttachments();

            self::assertSame(
                $embedded,
                $result->textHtml,
                'embeded html body did not match expected result!'
            );

            $mailbox->deleteMail($search[0]);
        } catch (\Exception $exception) {
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
