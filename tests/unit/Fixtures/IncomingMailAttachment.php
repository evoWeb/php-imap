<?php

declare(strict_types=1);

namespace PhpImap\Fixtures;

use PhpImap\IncomingMailAttachment as Base;

class IncomingMailAttachment extends Base
{
    /** @var string|null */
    public $override_getFileInfo_mime_type;

    public function getFileInfo(int $fileinfo_const = \FILEINFO_NONE): string
    {
        if (
            $fileinfo_const === \FILEINFO_MIME_TYPE &&
            isset($this->override_getFileInfo_mime_type)
        ) {
            return $this->override_getFileInfo_mime_type;
        }

        return parent::getFileInfo($fileinfo_const);
    }
}
