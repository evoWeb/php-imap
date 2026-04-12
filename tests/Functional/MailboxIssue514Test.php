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
use PhpImap\Entities\ComposeBody;
use PhpImap\Entities\ComposeEnvelope;
use PhpImap\Exceptions\ConnectionException;
use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Imap;
use PhpImap\Tests\Fixtures\Constants;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Random\RandomException;

class MailboxIssue514Test extends AbstractMailboxTest
{
    private const BODY = [
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
            'contents.data' => '<img alt="png" width="5" height="1" src="cid:foo.png">'
                . '<img alt="webp" width="5" height="1" src="cid:foo.webp">',
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
            'contents.data' => '',
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
            'contents.data' => '',
        ],
    ];

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

        $envelope = new ComposeEnvelope('barbushin/php-imap#514--' . \bin2hex(\random_bytes(16)));

        [$searchCriteria] = $this->subjectSearchCriteriaAndSubject($envelope);

        $body = self::BODY;
        $pngFileContent = \file_get_contents(__DIR__ . '/../Fixtures/rgbkw5x1.png');
        self::assertIsString($pngFileContent);
        $body[3]['contents.data'] = \base64_encode($pngFileContent);
        $webpFileContent = \file_get_contents(__DIR__ . '/../Fixtures/rgbkw5x1.webp');
        self::assertIsString($webpFileContent);
        $body[4]['contents.data'] = \base64_encode($webpFileContent);

        $body[0] = ComposeBody::fromArray($body[0]);
        $body[1] = ComposeBody::fromArray($body[1]);
        $body[2] = ComposeBody::fromArray($body[2]);
        $body[3] = ComposeBody::fromArray($body[3]);
        $body[4] = ComposeBody::fromArray($body[4]);

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
                'counts should only contain foo.png and foo.webp, found: ' . \implode(', ', \array_keys($counts))
            );

            foreach ($counts as $cid => $count) {
                self::assertSame(1, $count, $cid . ' had ' . $count . ', expected 1.');
            }

            self::assertSame('foo', $result->textPlain, 'plain text body did not match expected result!');

            $embedded = \implode('', [
                '<img alt="png" width="5" height="1" src="',
                'data:image/png;base64, ',
                $body[3]->contentsData,
                '">',
                '<img alt="webp" width="5" height="1" src="',
                'data:image/webp;base64, ',
                $body[4]->contentsData,
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
                    $replaced = \str_replace('foo.png', '/' . \basename($attachment->filePath), $replaced);
                } elseif ($attachment->contentId === 'foo.webp') {
                    $replaced = \str_replace('foo.webp', '/' . \basename($attachment->filePath), $replaced);
                }
            }

            self::assertSame(
                $replaced,
                $result->replaceInternalLinks(''),
                'replaced html body did not match expected result!'
            );

            self::assertSame(
                $body[2]->contentsData,
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
            // delaying throw to clean up and close connections before that
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
