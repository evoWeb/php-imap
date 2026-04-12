<?php

/**
* @author BAPCLTD-Marv
*/

declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use PhpImap\DataPartInfo;
use PhpImap\IncomingMail;
use PhpImap\IncomingMailAttachment;
use PhpImap\IncomingMailHeader;
use PhpImap\Mailbox;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IncomingMailTest extends TestCase
{
    #[Test]
    public function testSetHeader(): void
    {
        $mail = new IncomingMail();
        $header = new IncomingMailHeader();

        $mail->id = 1;
        $header->id = 2;

        $mail->isDraft = true;
        $header->isDraft = false;

        $mail->date = \date(\DATE_RFC3339, 0);
        $header->date = \date(\DATE_RFC3339, 60 * 60 * 24);

        $mail->setHeader($header);

        foreach (
            [
                'id',
                'isDraft',
                'date',
            ] as $property
        ) {
            /** @var int|bool|string|null $headerPropertyValue */
            $headerPropertyValue = $header->$property;
            self::assertSame($headerPropertyValue, $mail->$property);
        }
    }

    #[Test]
    public function testDataPartInfo(): void
    {
        $mail = new IncomingMail();
        $mailbox = new Mailbox('', '', '');

        $data_part = new \PhpImap\Tests\Fixtures\DataPartInfo($mailbox, 1, 0, \ENCOTHER, 0);
        $data_part->setData('foo');

        self::assertSame('foo', $data_part->fetch());

        $mail->addDataPartInfo($data_part, DataPartInfo::TEXT_PLAIN);

        self::assertSame('foo', $mail->textPlain);

        self::assertTrue($mail->__isset('textPlain'));
    }

    #[Test]
    public function testAttachments(): void
    {
        $mail = new IncomingMail();

        self::assertFalse($mail->hasAttachments());
        self::assertSame([], $mail->getAttachments());

        $attachments = [new IncomingMailAttachment()];

        foreach ($attachments as $i => $attachment) {
            $attachment->id = (string)$i;
            $mail->addAttachment($attachment);
        }

        self::assertTrue($mail->hasAttachments());
        self::assertSame($attachments, $mail->getAttachments());

        foreach ($attachments as $attachment) {
            self::assertIsString($attachment->id);
            self::assertTrue($mail->removeAttachment($attachment->id));
        }

        self::assertFalse($mail->hasAttachments());
        self::assertSame([], $mail->getAttachments());

        foreach ($attachments as $attachment) {
            self::assertIsString($attachment->id);
            self::assertFalse($mail->removeAttachment($attachment->id));
        }
    }
}
