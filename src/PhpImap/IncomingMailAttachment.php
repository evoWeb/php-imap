<?php

declare(strict_types=1);

namespace PhpImap;

use PhpImap\Exceptions\ConnectionException;

/**
 * @see https://github.com/barbushin/php-imap
 *
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 *
 * @property string|false|null $filePath lazy attachment data file
 *
 * @phpstan-type fileinfoconst = 0|2|16|1024|1040|8|32|128|256|16777216
 */
class IncomingMailAttachment
{
    public ?string $id;

    public ?string $contentId;

    public ?int $type;

    public ?int $encoding;

    public ?string $subtype;

    public ?string $description;

    public ?string $name;

    public ?int $sizeInBytes;

    public ?string $disposition;

    public ?string $charset;

    public ?bool $emlOrigin;

    public ?string $fileInfoRaw;

    public ?string $fileInfo;

    public ?string $mime;

    public ?string $mimeEncoding;

    public ?string $fileExtension;

    public ?string $mimeType;

    private string $filePath;

    private DataPartInfo $dataInfo;

    /**
     * @return false|string
     */
    public function __get(string $name)
    {
        if ($name !== 'filePath') {
            \trigger_error("Undefined property: IncomingMailAttachment::$name");
        }
        return $this->filePath;
    }

    /**
     * Sets the file path.
     *
     * @param string $filePath File path incl. file name and optional extension
     */
    public function setFilePath(string $filePath): void
    {
        $this->filePath = $filePath;
    }

    /**
     * Sets the data part info.
     *
     * @param DataPartInfo $dataInformation Date info (file content)
     */
    public function addDataPartInfo(DataPartInfo $dataInformation): void
    {
        $this->dataInfo = $dataInformation;
    }

    /**
     * Gets information about a file.
     *
     * @param int $fileInformationConstant Any predefined constant.
     *      See https://www.php.net/manual/en/fileinfo.constants.php
     *
     * @phpstan-param fileinfoconst $fileInformationConstant
     *
     * @throws ConnectionException
     */
    public function getFileInfo(int $fileInformationConstant = \FILEINFO_NONE): string
    {
        $fileInformation = new \finfo($fileInformationConstant);

        return $fileInformation->buffer($this->getContents());
    }

    /**
     * Gets the file content.
     *
     * @throws ConnectionException
     */
    public function getContents(): string
    {
        return $this->dataInfo->fetch();
    }

    /**
     * Saves the attachment object on the disk.
     *
     * @return bool True, if it could save the attachment on the disk
     *
     * @throws ConnectionException
     */
    public function saveToDisk(): bool
    {
        if (\file_put_contents($this->__get('filePath'), $this->dataInfo->fetch()) === false) {
            unset($this->filePath);

            return false;
        }

        return true;
    }
}
