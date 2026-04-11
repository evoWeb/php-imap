<?php

declare(strict_types=1);

namespace PhpImap;

use PhpImap\Entities\Constants;
use PhpImap\Exceptions\ConnectionException;

/**
 * The PhpImap IncomingMail class.
 *
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 *
 * @see https://github.com/barbushin/php-imap
 *
 * @property string $textPlain lazy plain message body
 * @property string $textHtml  lazy html message body
 */
class IncomingMail extends IncomingMailHeader
{
    /**
     * @var IncomingMailAttachment[]
     */
    protected array $attachments = [];

    protected bool $hasAttachments = false;

    /**
     * @var DataPartInfo[][]
     *
     * @phpstan-var array{0:list<DataPartInfo>, 1:list<DataPartInfo>}
     */
    protected array $dataInfo = [[], []];

    private ?string $textPlain;

    private ?string $textHtml;

    /**
     * __get() is utilized for reading data from inaccessible (protected
     * or private) or non-existing properties.
     *
     * @param string $name Name of the property (eg. textPlain)
     *
     * @return string Value of the property (eg. Plain text message)
     *
     * @throws ConnectionException
     */
    public function __get(string $name): string
    {
        $type = false;
        if ($name == 'textPlain') {
            $type = DataPartInfo::TEXT_PLAIN;
        }
        if ($name == 'textHtml') {
            $type = DataPartInfo::TEXT_HTML;
        }
        if ($name === 'textPlain' && isset($this->textPlain)) {
            return (string)$this->textPlain;
        }
        if ($name === 'textHtml' && isset($this->textHtml)) {
            return (string)$this->textHtml;
        }
        if ($type === false) {
            \trigger_error("Undefined property: IncomingMail::$name");
        }
        if (!isset($this->$name)) {
            $this->$name = '';
        }
        foreach ($this->dataInfo[$type] as $data) {
            $this->$name .= \trim($data->fetch());
        }

        /** @var string */
        return $this->$name;
    }

    /**
     * The method __isset() is triggered by calling isset() or empty()
     * on inaccessible (protected or private) or non-existing properties.
     *
     * @param string $name Name of the property (eg. textPlain)
     *
     * @return bool True, if property is set or empty
     *
     * @throws ConnectionException
     */
    public function __isset(string $name): bool
    {
        self::__get($name);

        return isset($this->$name);
    }

    public function setHeader(IncomingMailHeader $header): void
    {
        /** @phpstan-var array<string, scalar|object|null|string[]> $array */
        $array = \get_object_vars($header);
        foreach ($array as $property => $value) {
            $this->$property = $value;
        }
    }

    /**
     * @phpstan-param DataPartInfo::TEXT_PLAIN|DataPartInfo::TEXT_HTML $type
     */
    public function addDataPartInfo(DataPartInfo $dataInfo, int $type): void
    {
        $this->dataInfo[$type][] = $dataInfo;
    }

    public function addAttachment(IncomingMailAttachment $attachment): void
    {
        if (!\is_string($attachment->id)) {
            throw new \InvalidArgumentException(sprintf(Constants::INVALID_ID, 1, __METHOD__));
        }
        $this->attachments[$attachment->id] = $attachment;

        $this->setHasAttachments(true);
    }

    /**
     * Sets property $hasAttachments.
     *
     * @param bool $hasAttachments True, if IncomingMail[] has one or more attachments
     */
    public function setHasAttachments(bool $hasAttachments): void
    {
        $this->hasAttachments = $hasAttachments;
    }

    /**
     * Returns, if the mail has attachments or not.
     *
     * @return bool true or false
     */
    public function hasAttachments(): bool
    {
        return $this->hasAttachments;
    }

    /**
     * @return IncomingMailAttachment[]
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * @param string $id The attachment id
     */
    public function removeAttachment(string $id): bool
    {
        if (!isset($this->attachments[$id])) {
            return false;
        }

        unset($this->attachments[$id]);

        $this->setHasAttachments($this->attachments !== []);

        return true;
    }

    /**
     * Get array of internal HTML links placeholders.
     *
     * @return array attachmentId => link placeholder
     *
     * @phpstan-return array<string, string>
     *
     * @throws ConnectionException
     */
    public function getInternalLinksPlaceholders(): array
    {
        $fetchedHtml = $this->__get('textHtml');

        $match = \preg_match_all('/=["\'](ci?d:([\w.%*@-]+))["\']/i', $fetchedHtml, $matches);
        /** @phpstan-var array{list<string>, list<non-falsy-string>, list<non-empty-string>} $matches */

        return $match ? \array_combine($matches[2], $matches[1]) : [];
    }

    /**
     * @throws ConnectionException
     */
    public function replaceInternalLinks(string $baseUri): string
    {
        $baseUri = \rtrim($baseUri, '\\/') . '/';
        $fetchedHtml = $this->textHtml ?? '';
        $search = [];
        $replace = [];
        foreach ($this->getInternalLinksPlaceholders() as $attachmentId => $placeholder) {
            foreach ($this->attachments as $attachment) {
                if ($attachment->contentId == $attachmentId) {
                    if (!\is_string($attachment->id)) {
                        throw new \InvalidArgumentException(sprintf(Constants::INVALID_ID, 1, __METHOD__));
                    }
                    $search[] = $placeholder;
                    $replace[] = $baseUri . \basename($this->attachments[$attachment->id]->filePath);
                }
            }
        }

        /** @phpstan-var string */
        return \str_replace($search, $replace, $fetchedHtml);
    }

    /**
     * Embed inline image attachments as base64 to allow for
     * email HTML to display inline images automatically.
     *
     * @throws ConnectionException
     */
    public function embedImageAttachments(): void
    {
        $fetchedHtml = $this->__get('textHtml');

        \preg_match_all("/\bcid:[^'\"\s]{1,256}/mi", $fetchedHtml, $matches);

        if (!\count($matches[0])) {
            return;
        }

        $matches = $matches[0];
        foreach ($matches as $match) {
            $cid = \str_replace('cid:', '', $match);

            $matched = $this->findMatchingAttachmentForCid($cid);
            if ($matched !== null) {
                $this->replaceCidWithImageInHtml($match, $matched);
            }
        }
    }

    /**
     * Inline images can contain a "Content-Disposition: inline", but only a "Content-ID" is also enough.
     * See https://github.com/barbushin/php-imap/issues/569.
     *
     * Re-fetch attachments each iteration so removed attachments are excluded.
     * Prefer contentId matching; only fall back to disposition-based matching when no contentId
     * match exists, to avoid embedding the wrong attachment when multiple inline images are present.
     */
    private function findMatchingAttachmentForCid(string $cid): ?IncomingMailAttachment
    {
        $attachments = $this->getAttachments();
        $matched = null;
        foreach ($attachments as $attachment) {
            if ($attachment->contentId == $cid) {
                $matched = $attachment;
                break;
            }
        }

        if ($matched === null) {
            foreach ($attachments as $attachment) {
                if (\mb_strtolower((string)$attachment->disposition) == 'inline') {
                    $matched = $attachment;
                    break;
                }
            }
        }

        return $matched;
    }

    /**
     * @throws ConnectionException
     */
    private function replaceCidWithImageInHtml(string $match, IncomingMailAttachment $matched): void
    {
        $contents = $matched->getContents();
        $contentType = $matched->getFileInfo(\FILEINFO_MIME_TYPE);

        if (str_contains($contentType, 'image')) {
            if (!\is_string($matched->id)) {
                throw new \InvalidArgumentException(sprintf(Constants::INVALID_ID, 1, __METHOD__));
            }

            $base64encoded = \base64_encode($contents);
            $replacement = 'data:' . $contentType . ';base64, ' . $base64encoded;

            $this->textHtml = \str_replace($match, $replacement, $this->textHtml ?? '');

            $this->removeAttachment($matched->id);
        }
    }
}
