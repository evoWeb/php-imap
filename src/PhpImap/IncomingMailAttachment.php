<?php

declare(strict_types=1);

namespace PhpImap;

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

    private ?string $file_path;

    private ?DataPartInfo $dataInfo;

    private ?string $filePath;

    /**
     * @return false|string
     */
    public function __get(string $name)
    {
        if ($name !== 'filePath') {
            \trigger_error("Undefined property: IncomingMailAttachment::$name");
        }

        if (!isset($this->file_path)) {
            return false;
        }

        $this->filePath = $this->file_path;

        if (@\file_exists($this->file_path)) {
            return $this->filePath;
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
        $this->file_path = $filePath;
    }

    /**
     * Sets the data part info.
     *
     * @param DataPartInfo $dataInfo Date info (file content)
     */
    public function addDataPartInfo(DataPartInfo $dataInfo): void
    {
        $this->dataInfo = $dataInfo;
    }

    /**
     * Gets information about a file.
     *
     * @param int $fileinfoConst Any predefined constant. See https://www.php.net/manual/en/fileinfo.constants.php
     *
     * @phpstan-param fileinfoconst $fileinfoConst
     */
    public function getFileInfo(int $fileinfoConst = \FILEINFO_NONE): string
    {
        $finfo = new \finfo($fileinfoConst);

        return $finfo->buffer($this->getContents());
    }

    /**
     * Gets the file content.
     */
    public function getContents(): string
    {
        if ($this->dataInfo === null) {
            throw new \UnexpectedValueException(static::class . '::$dataInfo has not been set by calling ' . self::class . '::addDataPartInfo()');
        }

        return $this->dataInfo->fetch();
    }

    /**
     * Saves the attachment object on the disk.
     *
     * @return bool True, if it could save the attachment on the disk
     */
    public function saveToDisk(): bool
    {
        if ($this->dataInfo === null) {
            return false;
        }

        if (\file_put_contents($this->__get('filePath'), $this->dataInfo->fetch()) === false) {
            unset($this->filePath, $this->file_path);

            return false;
        }

        return true;
    }
}
