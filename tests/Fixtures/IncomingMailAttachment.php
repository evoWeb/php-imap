<?php

declare(strict_types=1);

namespace PhpImap\Tests\Fixtures;

use PhpImap\IncomingMailAttachment as Base;

class IncomingMailAttachment extends Base
{
    public ?string $overrideGetFileInfoMimeType;

    public function getFileInfo(int $fileInformationConstant = \FILEINFO_NONE): string
    {
        if (
            $fileInformationConstant === \FILEINFO_MIME_TYPE
            && isset($this->overrideGetFileInfoMimeType)
        ) {
            return $this->overrideGetFileInfoMimeType;
        }

        return parent::getFileInfo($fileInformationConstant);
    }
}
