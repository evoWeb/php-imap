<?php

declare(strict_types=1);

namespace PhpImap\Tests\Unit\Fixtures;

use PhpImap\IncomingMailAttachment as Base;

class IncomingMailAttachment extends Base
{
    public ?string $overrideGetFileInfoMimeType;

    public function getFileInfo(int $fileinfoConst = \FILEINFO_NONE): string
    {
        if (
            $fileinfoConst === \FILEINFO_MIME_TYPE
            && isset($this->overrideGetFileInfoMimeType)
        ) {
            return $this->overrideGetFileInfoMimeType;
        }

        return parent::getFileInfo($fileinfoConst);
    }
}
