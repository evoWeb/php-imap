<?php

declare(strict_types=1);

namespace PhpImap;

use IMAP\Connection;
use PhpImap\Exceptions\ConnectionException;
use PhpImap\Exceptions\InvalidParameterException;

/**
 * @see https://github.com/barbushin/php-imap
 *
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 *
 * @phpstan-type PARTSTRUCTURE_PARAM = object{attribute: string, value?: string}
 *
 * @phpstan-type PARTSTRUCTURE = object{
 *      id?: string,
 *      encoding: int|mixed,
 *      partStructure: object[],
 *      parameters: PARTSTRUCTURE_PARAM[],
 *      dparameters: object{attribute:string, value:string}[],
 *      parts: array<int, \stdClass>,
 *      type: int,
 *      ifid?: string,
 *      ifsubtype?: string,
 *      ifdescription?: string,
 *      ifdisposition?: string,
 *      description: string,
 *      subtype: string,
 *      disposition?: string|null,
 *      bytes?: int
 * }
 * @phpstan-type HOSTNAMEANDADDRESS_ENTRY = object{host?: string, personal?: string, mailbox: string}
 * @phpstan-type HOSTNAMEANDADDRESS = array{0: HOSTNAMEANDADDRESS_ENTRY, 1?: HOSTNAMEANDADDRESS_ENTRY}
 * @phpstan-type COMPOSE_ENVELOPE = array{
 *      subject?: string
 * }
 * @phpstan-type COMPOSE_BODY = list<array{
 *      type?: int,
 *      encoding?: int,
 *      charset?: string,
 *      subtype?: string,
 *      description?: string,
 *      disposition?: array{filename:string}
 * }>
 */
class Mailbox
{
    public const EXPECTED_SIZE_OF_MESSAGE_AS_ARRAY = 2;

    public const MAX_LENGTH_FILEPATH = 255;

    public const PART_TYPE_TWO = 2;

    public const IMAP_OPTIONS_SUPPORTED_VALUES =
        \OP_READONLY // 2
        | \OP_ANONYMOUS // 4
        | \OP_HALFOPEN // 64
        | \CL_EXPUNGE // 32768
        | \OP_DEBUG // 1
        | \OP_SHORTCACHE // 8
        | \OP_SILENT // 16
        | \OP_PROTOTYPE // 32
        | \OP_SECURE // 256
    ;

    public string $decodeMimeStrDefaultCharset = 'default';

    protected string $imapPath;

    protected string $imapLogin;

    protected string $imapPassword;

    protected int $imapSearchOption = \SE_UID;

    protected int $connectionRetry = 0;

    protected int $connectionRetryDelay = 100;

    protected int $imapOptions = 0;

    protected int $imapRetriesNum = 0;

    /** @phpstan-var array{DISABLE_AUTHENTICATOR?: string} */
    protected array $imapParams = [];

    protected string $serverEncoding = 'UTF-8';

    protected ?string $attachmentsDir;

    protected bool $expungeOnDisconnect = true;

    protected array $timeouts = [];

    protected bool $attachmentsIgnore = false;

    protected string $pathDelimiter = '.';

    protected string $mailboxFolder;

    protected bool $attachmentFilenameMode = false;

    private ?Connection $imapStream = null;

    /**
     * @throws InvalidParameterException
     */
    public function __construct(
        string $imapPath,
        string $login,
        string $password,
        ?string $attachmentsDir = null,
        string $serverEncoding = 'UTF-8',
        bool $trimImapPath = true,
        bool $attachmentFilenameMode = false
    ) {
        $this->imapPath = $trimImapPath ? \trim($imapPath) : $imapPath;
        $this->imapLogin = \trim($login);
        $this->imapPassword = $password;
        $this->setServerEncoding($serverEncoding);
        if ($attachmentsDir != null) {
            $this->setAttachmentsDir($attachmentsDir);
        }
        $this->setAttachmentFilenameMode($attachmentFilenameMode);

        $this->setMailboxFolder();
    }

    /**
     * Disconnects from the IMAP server / mailbox.
     * @throws ConnectionException
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Sets / Changes the path delimiter character (Supported values: '.', '/').
     *
     * @param string $delimiter Path delimiter
     *
     * @throws InvalidParameterException
     */
    public function setPathDelimiter(string $delimiter): void
    {
        if (!$this->validatePathDelimiter($delimiter)) {
            throw new InvalidParameterException(
                'setPathDelimiter() can only set the delimiter to these characters: ".", "/"'
            );
        }

        $this->pathDelimiter = $delimiter;
    }

    /**
     * Returns the current set path delimiter character.
     *
     * @return string Path delimiter
     */
    public function getPathDelimiter(): string
    {
        return $this->pathDelimiter;
    }

    /**
     * Validates the given path delimiter character.
     *
     * @param string $delimiter Path delimiter
     *
     * @return bool true (supported) or false (unsupported)
     */
    public function validatePathDelimiter(string $delimiter): bool
    {
        $supportedDelimiters = ['.', '/'];

        if (!\in_array($delimiter, $supportedDelimiters)) {
            return false;
        }

        return true;
    }

    /**
     * Returns the current set server encoding.
     *
     * @return string Server encoding (eg. 'UTF-8')
     */
    public function getServerEncoding(): string
    {
        return $this->serverEncoding;
    }

    /**
     * Sets / Changes the server encoding.
     *
     * @param string $serverEncoding Server encoding (eg. 'UTF-8')
     *
     * @throws InvalidParameterException
     */
    public function setServerEncoding(string $serverEncoding): void
    {
        $serverEncoding = \strtoupper(\trim($serverEncoding));

        $supportedEncodings = \array_map('strtoupper', \mb_list_encodings());

        if (!\in_array($serverEncoding, $supportedEncodings) && $serverEncoding != 'US-ASCII') {
            throw new InvalidParameterException(
                '"' . $serverEncoding
                . '" is not supported by setServerEncoding(). Your system only supports these encodings: US-ASCII, '
                . \implode(', ', $supportedEncodings)
            );
        }

        $this->serverEncoding = $serverEncoding;
    }

    /**
     * Returns the current set attachment filename mode.
     *
     * @return bool Attachment filename mode (e.g. true)
     */
    public function getAttachmentFilenameMode(): bool
    {
        return $this->attachmentFilenameMode;
    }

    /**
     * Sets / Changes the attachment filename mode.
     *
     * @param bool $attachmentFilenameMode Attachment filename mode (e.g. false)
     */
    public function setAttachmentFilenameMode(bool $attachmentFilenameMode): void
    {
        $this->attachmentFilenameMode = $attachmentFilenameMode;
    }

    /**
     * Returns the current set IMAP search option.
     *
     * @return int IMAP search option (eg. '\SE_UID')
     */
    public function getImapSearchOption(): int
    {
        return $this->imapSearchOption;
    }

    /**
     * Sets / Changes the IMAP search option.
     *
     * @param int $imapSearchOption IMAP search option (eg. '\SE_UID')
     *
     * @phpstan-param int $imapSearchOption
     *
     * @throws InvalidParameterException
     */
    public function setImapSearchOption(int $imapSearchOption): void
    {
        $supported_options = [\SE_FREE, \SE_UID];

        if (!\in_array($imapSearchOption, $supported_options, true)) {
            throw new InvalidParameterException(
                '"' . $imapSearchOption
                . '" is not supported by setImapSearchOption(). Supported options are \SE_FREE and \SE_UID.'
            );
        }

        $this->imapSearchOption = $imapSearchOption;
    }

    /**
     * Set $this->attachmentsIgnore param. Allow to ignore attachments when they are not required and boost performance.
     */
    public function setAttachmentsIgnore(bool $attachmentsIgnore): void
    {
        $this->attachmentsIgnore = $attachmentsIgnore;
    }

    /**
     * Get $this->attachmentsIgnore param.
     *
     * @return bool $attachmentsIgnore
     */
    public function getAttachmentsIgnore(): bool
    {
        return $this->attachmentsIgnore;
    }

    /**
     * Sets the timeout of all or one specific type.
     *
     * @param int $timeout Timeout in seconds
     * @param array $types One of the following:
     *  - IMAP_OPENTIMEOUT
     *  - IMAP_READTIMEOUT
     *  - IMAP_WRITETIMEOUT
     *  - IMAP_CLOSETIMEOUT
     *
     * @phpstan-param list<int> $types
     *
     * @throws InvalidParameterException
     */
    public function setTimeouts(
        int $timeout,
        array $types = [\IMAP_OPENTIMEOUT, \IMAP_READTIMEOUT, \IMAP_WRITETIMEOUT, \IMAP_CLOSETIMEOUT]
    ): void {
        $supported_types = [\IMAP_OPENTIMEOUT, \IMAP_READTIMEOUT, \IMAP_WRITETIMEOUT, \IMAP_CLOSETIMEOUT];

        $found_types = \array_intersect($types, $supported_types);

        if (\count($types) != \count($found_types)) {
            throw new InvalidParameterException(
                'You have provided at least one unsupported timeout type.'
                . ' Supported types are: IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT'
            );
        }

        $this->timeouts = \array_fill_keys($types, $timeout);
    }

    /**
     * Returns the IMAP login (usually an email address).
     *
     * @return string IMAP login
     */
    public function getLogin(): string
    {
        return $this->imapLogin;
    }

    /**
     * Set custom connection arguments of imap_open method. See http://php.net/imap_open.
     *
     * @param string[]|null $params
     *
     * @phpstan-param array{DISABLE_AUTHENTICATOR?:string}|array<empty, empty>|null $params
     *
     * @throws InvalidParameterException
     */
    public function setConnectionArgs(int $options = 0, int $retriesNum = 0, ?array $params = null): void
    {
        if ($options !== 0) {
            if (($options & self::IMAP_OPTIONS_SUPPORTED_VALUES) !== $options) {
                throw new InvalidParameterException(
                    'Please check your option for setConnectionArgs()! Unsupported option "'
                    . $options . '". Available options: https://www.php.net/manual/de/function.imap-open.php'
                );
            }
            $this->imapOptions = $options;
        }

        if ($retriesNum != 0) {
            if ($retriesNum < 0) {
                throw new InvalidParameterException(
                    'Invalid number of retries provided for setConnectionArgs()!'
                    . ' It must be a positive integer. (eg. 1 or 3)'
                );
            }
            $this->imapRetriesNum = $retriesNum;
        }

        if (\is_array($params) && !empty($params)) {
            $supported_params = ['DISABLE_AUTHENTICATOR'];

            foreach (\array_keys($params) as $key) {
                if (!\in_array($key, $supported_params, true)) {
                    throw new InvalidParameterException(
                        'Invalid array key of params provided for setConnectionArgs()!'
                        . ' Only DISABLE_AUTHENTICATOR is currently valid.'
                    );
                }
            }

            $this->imapParams = $params;
        }
    }

    /**
     * Set custom folder for attachments in case you want to have tree of folders for each email
     * i.e. a/1 b/1 c/1 where a,b,c - senders, i.e. john@smith.com.
     *
     * @param string $attachmentsDir Folder where to save attachments
     *
     * @throws InvalidParameterException
     */
    public function setAttachmentsDir(string $attachmentsDir): void
    {
        if (empty(\trim($attachmentsDir))) {
            throw new InvalidParameterException('setAttachmentsDir() expects a string as first parameter!');
        }
        if (!\is_dir($attachmentsDir)) {
            throw new InvalidParameterException('Directory "' . $attachmentsDir . '" not found');
        }
        $this->attachmentsDir = \rtrim(\realpath($attachmentsDir), '\\/');
    }

    /**
     * Get current saving folder for attachments.
     *
     * @return string|null Attachments dir
     */
    public function getAttachmentsDir(): ?string
    {
        return $this->attachmentsDir;
    }

    /**
     * Sets / Changes the attempts / retries to connect.
     */
    public function setConnectionRetry(int $maxAttempts): void
    {
        $this->connectionRetry = $maxAttempts;
    }

    /**
     * Sets / Changes the delay between each attempt / retry to connect.
     */
    public function setConnectionRetryDelay(int $milliseconds): void
    {
        $this->connectionRetryDelay = $milliseconds;
    }

    /**
     * Get IMAP mailbox connection stream.
     *
     * @param bool $forceConnection Initialize connection if it's not initialized
     *
     * @return Connection
     * @throws ConnectionException
     */
    public function getImapStream(bool $forceConnection = true): Connection
    {
        if ($forceConnection) {
            $this->pingOrDisconnect();
            if (!$this->imapStream) {
                $this->imapStream = $this->initImapStreamWithRetry();
            }
        }

        return $this->imapStream;
    }

    public function hasImapStream(): bool
    {
        try {
            return $this->imapStream instanceof Connection && \imap_ping($this->imapStream);
        } catch (\Error $exception) {
            // From PHP 8.1.10 imap_ping() on a closed stream throws a ValueError. See #680.
            $valueError = '\ValueError';
            if (class_exists($valueError) && $exception instanceof $valueError) {
                return false;
            }

            throw $exception;
        }
    }

    /**
     * Returns the provided string in UTF7-IMAP encoded format.
     *
     * @return string $str UTF-7 encoded string
     */
    public function encodeStringToUtf7Imap(string $str): string
    {
        return \mb_convert_encoding($str, 'UTF7-IMAP', 'UTF-8');
    }

    /**
     * Returns the provided string in UTF-8 encoded format.
     *
     * @return string $str UTF-7 encoded string or same as before, when it's no string
     */
    public function decodeStringFromUtf7ImapToUtf8(string $str): string
    {
        $out = mb_convert_encoding($str, 'UTF-8', 'UTF7-IMAP');

        $this->assertValueIsString($out, 'out', __METHOD__);

        return $out;
    }

    /**
     * Sets the folder of the current mailbox.
     */
    public function setMailboxFolder(): void
    {
        $imapPathParts = \explode('}', $this->imapPath);
        $this->mailboxFolder = (!empty($imapPathParts[1])) ? $imapPathParts[1] : 'INBOX';
    }

    /**
     * Switch mailbox without opening a new connection.
     *
     * @throws ConnectionException
     */
    public function switchMailbox(string $imapPath, bool $absolute = true): void
    {
        if (\strpos($imapPath, '}') > 0) {
            $this->imapPath = $imapPath;
        } else {
            $this->imapPath = $this->getCombinedPath($imapPath, $absolute);
        }

        $this->setMailboxFolder();

        Imap::reopen($this->getImapStream(), $this->imapPath);
    }

    /**
     * Disconnects from IMAP server / mailbox.
     *
     * @throws ConnectionException
     */
    public function disconnect(): void
    {
        if ($this->hasImapStream()) {
            Imap::close($this->getImapStream(false), $this->expungeOnDisconnect ? \CL_EXPUNGE : 0);
        }
    }

    /**
     * Sets 'expunge on disconnect' parameter.
     */
    public function setExpungeOnDisconnect(bool $isEnabled): void
    {
        $this->expungeOnDisconnect = $isEnabled;
    }

    /**
     * Get information about the current mailbox.
     *
     * Returns the information in an object with following properties:
     *  Date - current system time formatted according to RFC2822
     *  Driver - protocol used to access this mailbox: POP3, IMAP, NNTP
     *  Mailbox - the mailbox name
     *  Nmsgs - number of mails in the mailbox
     *  Recent - number of recent mails in the mailbox
     *
     * @throws ConnectionException
     * @see imap_check
     */
    public function checkMailbox(): object
    {
        return Imap::check($this->getImapStream());
    }

    /**
     * Creates a new mailbox.
     *
     * @param string $name Name of new mailbox (eg. 'PhpImap')
     *
     * @throws ConnectionException
     * @see imap_createmailbox()
     */
    public function createMailbox(string $name): void
    {
        Imap::createMailbox($this->getImapStream(), $this->getCombinedPath($name));
    }

    /**
     * Deletes a specific mailbox.
     *
     * @param string $name Name of mailbox, which you want to delete (eg. 'PhpImap')
     *
     * @throws ConnectionException
     * @see imap_deletemailbox()
     */
    public function deleteMailbox(string $name, bool $absolute = false): bool
    {
        return Imap::deleteMailbox($this->getImapStream(), $this->getCombinedPath($name, $absolute));
    }

    /**
     * Rename an existing mailbox from $oldName to $newName.
     *
     * @param string $oldName Current name of mailbox, which you want to rename (eg. 'PhpImap')
     * @param string $newName New name of mailbox, to which you want to rename it (eg. 'PhpImapTests')
     * @throws ConnectionException
     */
    public function renameMailbox(string $oldName, string $newName): void
    {
        Imap::renameMailbox(
            $this->getImapStream(),
            $this->getCombinedPath($oldName),
            $this->getCombinedPath($newName)
        );
    }

    /**
     * Gets status information about the given mailbox.
     *
     * This function returns an object containing status information.
     * The object has the following properties: messages, recent, unseen, uidnext, and uidvalidity.
     * @throws ConnectionException
     */
    public function statusMailbox(): \stdClass
    {
        return Imap::status($this->getImapStream(), $this->imapPath, \SA_ALL);
    }

    /**
     * Gets listing the folders.
     *
     * This function returns an object containing listing the folders.
     * The object has the following properties: messages, recent, unseen, uidnext, and uidvalidity.
     *
     * @return string[] listing the folders
     *
     * @phpstan-return list<string>
     * @throws ConnectionException
     */
    public function getListingFolders(string $pattern = '*'): array
    {
        return Imap::listOfMailboxes($this->getImapStream(), $this->imapPath, $pattern);
    }

    /**
     * This function uses imap_search() to perform a search on the mailbox currently opened in the given IMAP stream.
     * For example, to match all unanswered mails sent by Mom, you'd use: "UNANSWERED FROM mom".
     *
     * @param string $criteria See http://php.net/imap_search for a complete list of available criteria
     * @param bool $disableServerEncoding Disables server encoding while searching
     *  for mails (can be useful on Exchange servers)
     *
     * @return int[] mailsIds (or empty array)
     *
     * @phpstan-return list<int>
     * @throws ConnectionException
     */
    public function searchMailbox(string $criteria = 'ALL', bool $disableServerEncoding = false): array
    {
        if ($disableServerEncoding) {
            /** @phpstan-var list<int> */
            return Imap::search($this->getImapStream(), $criteria, $this->imapSearchOption);
        }

        /** @phpstan-var list<int> */
        return Imap::search($this->getImapStream(), $criteria, $this->imapSearchOption, $this->getServerEncoding());
    }

    /**
     * Search the mailbox for emails from multiple, specific senders.
     *
     * @return int[]
     *
     * @phpstan-return list<int>
     * @throws ConnectionException
     * @see Mailbox::searchMailboxFromWithOrWithoutDisablingServerEncoding()
     */
    public function searchMailboxFrom(string $criteria, string $sender, string ...$senders): array
    {
        return $this->searchMailboxFromWithOrWithoutDisablingServerEncoding(
            $criteria,
            false,
            $sender,
            ...$senders
        );
    }

    /**
     * Search the mailbox for emails from multiple, specific senders whilst not using server encoding.
     *
     * @return int[]
     *
     * @phpstan-return list<int>
     * @throws ConnectionException
     * @see Mailbox::searchMailboxFromWithOrWithoutDisablingServerEncoding()
     */
    public function searchMailboxFromDisableServerEncoding(string $criteria, string $sender, string ...$senders): array
    {
        return $this->searchMailboxFromWithOrWithoutDisablingServerEncoding(
            $criteria,
            true,
            $sender,
            ...$senders
        );
    }

    /**
     * Search the mailbox using multiple criteria merging the results.
     *
     * @param string $singleCriteria
     * @param string ...$criteria
     *
     * @return int[]
     *
     * @phpstan-return list<int>
     * @throws ConnectionException
     */
    public function searchMailboxMergeResults(string $singleCriteria, ...$criteria): array
    {
        return $this->searchMailboxMergeResultsWithOrWithoutDisablingServerEncoding(
            false,
            $singleCriteria,
            ...$criteria
        );
    }

    /**
     * Search the mailbox using multiple criteria merging the results.
     *
     * @param string $singleCriteria
     * @param string ...$criteria
     *
     * @return int[]
     *
     * @phpstan-return list<int>
     * @throws ConnectionException
     */
    public function searchMailboxMergeResultsDisableServerEncoding(string $singleCriteria, ...$criteria): array
    {
        return $this->searchMailboxMergeResultsWithOrWithoutDisablingServerEncoding(
            false,
            $singleCriteria,
            ...$criteria
        );
    }

    /**
     * Save a specific body section to a file.
     *
     * @param int $mailId message number
     *
     * @throws ConnectionException
     * @see imap_savebody()
     */
    public function saveMail(int $mailId, string $filename = 'email.eml'): void
    {
        Imap::saveBody(
            $this->getImapStream(),
            $filename,
            $mailId,
            '',
            $this->imapSearchOption === \SE_UID ? \FT_UID : 0
        );
    }

    /**
     * Marks mails listed in mailId for deletion.
     *
     * @param int $mailId message number
     *
     * @throws ConnectionException
     * @see imap_delete()
     */
    public function deleteMail(int $mailId): void
    {
        Imap::delete($this->getImapStream(), $mailId, $this->imapSearchOption === \SE_UID ? \FT_UID : 0);
    }

    /**
     * Moves mails listed in mailId into new mailbox.
     *
     * @param string|int $mailId a range or message number
     * @param string $mailBox Mailbox name
     *
     * @throws ConnectionException
     * @see imap_mail_move()
     */
    public function moveMail(string|int $mailId, string $mailBox): void
    {
        Imap::mailMove($this->getImapStream(), $mailId, $mailBox, \CP_UID);
        $this->expungeDeletedMails();
    }

    /**
     * Copies mails listed in mailId into new mailbox.
     *
     * @param string|int $mailId a range or message number
     * @param string $mailBox Mailbox name
     *
     * @throws ConnectionException
     * @see imap_mail_copy()
     */
    public function copyMail(string|int $mailId, string $mailBox): void
    {
        Imap::mailCopy($this->getImapStream(), $mailId, $mailBox, \CP_UID);
        $this->expungeDeletedMails();
    }

    /**
     * Deletes all the mails marked for deletion by imap_delete(), imap_mail_move(), or imap_setflag_full().
     *
     * @throws ConnectionException
     * @see imap_expunge()
     */
    public function expungeDeletedMails(): void
    {
        Imap::expunge($this->getImapStream());
    }

    /**
     * Add the flag \Seen to a mail.
     * @throws ConnectionException
     */
    public function markMailAsRead(int $mailId): void
    {
        $this->setFlag([$mailId], Constants::SEEN);
    }

    /**
     * Remove the flag \Seen from a mail.
     * @throws ConnectionException
     */
    public function markMailAsUnread(int $mailId): void
    {
        $this->clearFlag([$mailId], Constants::SEEN);
    }

    /**
     * Add the flag \Flagged to a mail.
     * @throws ConnectionException
     */
    public function markMailAsImportant(int $mailId): void
    {
        $this->setFlag([$mailId], '\\Flagged');
    }

    /**
     * Add the flag \Seen to a mails.
     *
     * @param int[] $mailId
     *
     * @phpstan-param list<int> $mailId
     * @throws ConnectionException
     */
    public function markMailsAsRead(array $mailId): void
    {
        $this->setFlag($mailId, Constants::SEEN);
    }

    /**
     * Remove the flag \Seen from some mails.
     *
     * @param int[] $mailId
     *
     * @phpstan-param list<int> $mailId
     * @throws ConnectionException
     */
    public function markMailsAsUnread(array $mailId): void
    {
        $this->clearFlag($mailId, Constants::SEEN);
    }

    /**
     * Add the flag \Flagged to some mails.
     *
     * @param int[] $mailId
     *
     * @phpstan-param list<int> $mailId
     * @throws ConnectionException
     */
    public function markMailsAsImportant(array $mailId): void
    {
        $this->setFlag($mailId, '\\Flagged');
    }

    /**
     * Check, if the specified flag for the mail is set or not.
     *
     * @param int $mailId A single mail ID
     * @param string $flag Which you can get are \Seen, \Answered, \Flagged, \Deleted, and \Draft as defined by RFC2060
     *
     * @return bool True, when the flag is set, false when not
     *
     * @phpstan-param int $mailId
     * @throws ConnectionException
     */
    public function flagIsSet(int $mailId, string $flag): bool
    {
        $flag = str_replace('\\', '', strtolower($flag));

        $overview = Imap::fetchOverview($this->getImapStream(), $mailId, \ST_UID);

        if ($overview[0]->$flag == 1) {
            return true;
        }

        return false;
    }

    /**
     * Causes a store to add the specified flag to the flags set for the mails in the specified sequence.
     *
     * @param int[] $mailsIds Array of mail IDs
     * @param string $flag Which you can set are \Seen, \Answered, \Flagged, \Deleted, and \Draft as defined by RFC2060
     *
     * @phpstan-param list<int> $mailsIds
     * @throws ConnectionException
     */
    public function setFlag(array $mailsIds, string $flag): void
    {
        Imap::setFlagFull($this->getImapStream(), \implode(',', $mailsIds), $flag, \ST_UID);
    }

    /**
     * Causes a store to delete the specified flag to the flags set for the mails in the specified sequence.
     *
     * @param int[] $mailsIds Array of mail IDs
     * @param string $flag Which you can delete are
     *  \Seen
     *  \Answered
     *  \Flagged
     *  \Deleted
     *  \Draft
     * as defined by RFC2060
     * @throws ConnectionException
     */
    public function clearFlag(array $mailsIds, string $flag): void
    {
        Imap::clearFlagFull($this->getImapStream(), \implode(',', $mailsIds), $flag, \ST_UID);
    }

    /**
     * Fetch mail headers for listed mails ids.
     *
     * Returns an array of objects describing one mail header each. The object will only
     * define a property if it exists. The possible properties are:
     *  subject - the mails subject
     *  from - who sent it
     *  sender - who sent it
     *  to - recipient
     *  date - when was it sent
     *  message_id - Mail-ID
     *  references - is a reference to this mail id
     *  in_reply_to - is a reply to this mail id
     *  size - size in bytes
     *  uid - UID the mail has in the mailbox
     *  msgno - mail sequence number in the mailbox
     *  recent - this mail is flagged as recent
     *  flagged - this mail is flagged
     *  answered - this mail is flagged as answered
     *  deleted - this mail is flagged for deletion
     *  seen - this mail is flagged as already read
     *  draft - this mail is flagged as being a draft
     * @see https://www.php.net/manual/en/function.imap-fetch-overview.php
     *
     * @return array $mailsIds Array of mail IDs
     *
     * @phpstan-return list<object>
     *
     * @throws \Exception
     */
    public function getMailsInfo(array $mailsIds): array
    {
        $mails = Imap::fetchOverview(
            $this->getImapStream(),
            \implode(',', $mailsIds),
            ($this->imapSearchOption === \SE_UID) ? \FT_UID : 0
        );
        if (!\count($mails)) {
            return [];
        }

        foreach ($mails as $index => $mail) {
            $this->assertPropertyIsStringIfNotNull($mail, 'subject', $index, __METHOD__);
            $this->assertPropertyIsStringIfNotNull($mail, 'from', $index, __METHOD__);
            $this->assertPropertyIsStringIfNotNull($mail, 'to', $index, __METHOD__);
            $this->assertPropertyIsStringIfNotNull($mail, 'sender', $index, __METHOD__);

            $this->decodePropertyToString($mail, 'subject');
            $this->decodePropertyToString($mail, 'from');
            $this->decodePropertyToString($mail, 'to');
            $this->decodePropertyToString($mail, 'sender');
        }

        /** @var list<object> */
        return $mails;
    }

    private function assertPropertyIsStringIfNotNull(object $object, string $name, int $index, string $method): void
    {
        $message = '%s property at index %d of argument 1 passed to %s() was not a string!';
        $value = $object->{$name} ?? '';
        if (!\is_string($value)) {
            throw new \UnexpectedValueException(sprintf($message, $name, $index, $method));
        }
    }

    private function assertValueIsString(mixed $value, string $name, string $method): void
    {
        $message = '%s was present in %s() but was not a string!';
        if (!\is_string($value)) {
            throw new \UnexpectedValueException(sprintf($message, $name, $method));
        }
    }

    private function assertValueIsIntegerIfNotNull(mixed $value, string $name, string $method): void
    {
        $message = '%s was present in %s() but was not an integer!';
        if (!\is_int($value ?? 0)) {
            throw new \UnexpectedValueException(sprintf($message, $name, $method));
        }
    }

    /**
     * @throws \Exception
     */
    private function decodePropertyToString(object $mail, string $property): void
    {
        if (isset($mail->{$property}) && !empty(\trim($mail->{$property}))) {
            $mail->{$property} = $this->decodeMimeStr($mail->{$property});
        }
    }

    /**
     * Get headers for all messages in the defined mailbox,
     * returns an array of string formatted with header info,
     * one element per mail message.
     *
     * @throws ConnectionException
     * @see imap_headers()
     */
    public function getMailboxHeaders(): array
    {
        return Imap::headers($this->getImapStream());
    }

    /**
     * Get information about the current mailbox.
     *
     * Returns an object with following properties:
     *  Date - last change (current datetime)
     *  Driver - driver
     *  Mailbox - name of the mailbox
     *  Nmsgs - number of messages
     *  Recent - number of recent messages
     *  Unread - number of unread messages
     *  Deleted - number of deleted messages
     *  Size - mailbox size
     *
     * @return \stdClass Object with info
     *
     * @throws ConnectionException
     * @see mailboxmsginfo
     */
    public function getMailboxInfo(): \stdClass
    {
        return Imap::mailboxMsgInfo($this->getImapStream());
    }

    /**
     * Gets mails ids sorted by some criteria.
     *
     * Criteria can be one (and only one) of the following constants:
     *  SORTDATE - mail Date
     *  SORTARRIVAL - arrival date (default)
     *  SORTFROM - mailbox in first From address
     *  SORTSUBJECT - mail subject
     *  SORTTO - mailbox in first To address
     *  SORTCC - mailbox in first cc address
     *  SORTSIZE - size of mail in octets
     *
     * @param int $criteria Sorting criteria (eg. SORTARRIVAL)
     * @param bool $reverse Sort reverse or not
     * @param string|null $searchCriteria See http://php.net/imap_search for a complete list of available criteria
     *
     * @phpstan-param value-of<Imap::SORT_CRITERIA> $criteria
     *
     * @return int[] Mails ids
     *
     * @phpstan-return list<int>
     * @throws ConnectionException
     */
    public function sortMails(
        int $criteria = \SORTARRIVAL,
        bool $reverse = true,
        ?string $searchCriteria = 'ALL',
        ?string $charset = null
    ): array {
        return Imap::sort(
            $this->getImapStream(),
            $criteria,
            $reverse,
            $this->imapSearchOption,
            $searchCriteria,
            $charset
        );
    }

    /**
     * Get mails count in mailbox.
     *
     * @throws ConnectionException
     * @see imap_num_msg()
     */
    public function countMails(): int
    {
        return Imap::numMsg($this->getImapStream());
    }

    /**
     * Return quota limit in KB.
     *
     * @param string $quotaRoot Should normally be in the form of which mailbox (i.e. INBOX)
     * @throws ConnectionException
     */
    public function getQuotaLimit(string $quotaRoot = 'INBOX'): int
    {
        $quota = $this->getQuota($quotaRoot);

        /** @var int */
        return $quota['STORAGE']['limit'] ?? 0;
    }

    /**
     * Return quota usage in KB.
     *
     * @param string $quotaRoot Should normally be in the form of which mailbox (i.e. INBOX)
     * @throws ConnectionException
     */
    public function getQuotaUsage(string $quotaRoot = 'INBOX'): int
    {
        $quota = $this->getQuota($quotaRoot);

        /** @var int */
        return $quota['STORAGE']['usage'] ?? 0;
    }

    /**
     * Get raw mail data.
     *
     * @param int $msgId ID of the message
     * @param bool $markAsSeen Mark the email as seen, when set to true
     *
     * @return string Message of the fetched body
     * @throws ConnectionException
     */
    public function getRawMail(int $msgId, bool $markAsSeen = true): string
    {
        $options = ($this->imapSearchOption == \SE_UID) ? \FT_UID : 0;
        if (!$markAsSeen) {
            $options |= \FT_PEEK;
        }

        return Imap::fetchBody($this->getImapStream(), $msgId, '', $options);
    }

    /**
     * Get mail header field value.
     *
     * @param string $headersRaw RAW headers as single string
     * @param string $headerFieldName Name of the required header field
     *
     * @return string Value of the header field
     */
    public function getMailHeaderFieldValue(string $headersRaw, string $headerFieldName): string
    {
        $headerFieldValue = '';

        if (\preg_match("/$headerFieldName:(.*)/i", $headersRaw, $matches)) {
            if (isset($matches[1])) {
                return \trim($matches[1]);
            }
        }

        return $headerFieldValue;
    }

    /**
     * Get mail header.
     *
     * @param int $mailId ID of the message
     *
     * @throws \Exception
     */
    public function getMailHeader(int $mailId): IncomingMailHeader
    {
        $headersRaw = Imap::fetchHeader(
            $this->getImapStream(),
            $mailId,
            ($this->imapSearchOption === \SE_UID) ? \FT_UID : 0
        );

        /** @var \stdClass&object{
         *      date?: scalar,
         *      Date?: scalar,
         *      subject?: scalar,
         *      from?: HOSTNAMEANDADDRESS,
         *      to?: HOSTNAMEANDADDRESS,
         *      cc?: HOSTNAMEANDADDRESS,
         *      bcc?: HOSTNAMEANDADDRESS,
         *      reply_to?: HOSTNAMEANDADDRESS,
         *      sender?: HOSTNAMEANDADDRESS,
         *      message_id?: scalar,
         * } $head
         */
        $head = \imap_rfc822_parse_headers($headersRaw);

        $this->assertPropertyIsStringIfNotNull($head, 'date', $mailId, __METHOD__);
        $this->assertPropertyIsStringIfNotNull($head, 'Date', $mailId, __METHOD__);
        $this->assertPropertyIsStringIfNotNull($head, 'subject', $mailId, __METHOD__);
        $this->assertPropertyIsStringIfNotNull($head, 'from', $mailId, __METHOD__);
        $this->assertPropertyIsStringIfNotNull($head, 'sender', $mailId, __METHOD__);
        $this->assertPropertyIsStringIfNotNull($head, 'to', $mailId, __METHOD__);
        $this->assertPropertyIsStringIfNotNull($head, 'cc', $mailId, __METHOD__);
        $this->assertPropertyIsStringIfNotNull($head, 'bcc', $mailId, __METHOD__);
        $this->assertPropertyIsStringIfNotNull($head, 'reply_to', $mailId, __METHOD__);

        $header = new IncomingMailHeader();
        $header->headersRaw = $headersRaw;
        $header->headers = $head;
        $header->id = $mailId;
        $header->imapPath = $this->imapPath;
        $header->mailboxFolder = $this->mailboxFolder;
        $header->isSeen = $this->flagIsSet($mailId, Constants::SEEN);
        $header->isAnswered = $this->flagIsSet($mailId, '\Answered');
        $header->isRecent = $this->flagIsSet($mailId, '\Recent');
        $header->isFlagged = $this->flagIsSet($mailId, '\Flagged');
        $header->isDeleted = $this->flagIsSet($mailId, '\Deleted');
        $header->isDraft = $this->flagIsSet($mailId, '\Draft');
        $header->mimeVersion = $this->getMailHeaderFieldValue($headersRaw, 'MIME-Version');
        $header->xVirusScanned = $this->getMailHeaderFieldValue($headersRaw, 'X-Virus-Scanned');
        $header->organization = $this->getMailHeaderFieldValue($headersRaw, 'Organization');
        $header->contentType = $this->getMailHeaderFieldValue($headersRaw, 'Content-Type');
        $header->xMailer = $this->getMailHeaderFieldValue($headersRaw, 'X-Mailer');
        $header->contentLanguage = $this->getMailHeaderFieldValue($headersRaw, 'Content-Language');
        $header->xSenderIp = $this->getMailHeaderFieldValue($headersRaw, 'X-Sender-IP');
        $header->priority = $this->getMailHeaderFieldValue($headersRaw, 'Priority');
        $header->importance = $this->getMailHeaderFieldValue($headersRaw, 'Importance');
        $header->sensitivity = $this->getMailHeaderFieldValue($headersRaw, 'Sensitivity');
        $header->autoSubmitted = $this->getMailHeaderFieldValue($headersRaw, 'Auto-Submitted');
        $header->precedence = $this->getMailHeaderFieldValue($headersRaw, 'Precedence');
        $header->failedRecipients = $this->getMailHeaderFieldValue($headersRaw, 'Failed-Recipients');
        $header->xOriginalTo = $this->getMailHeaderFieldValue($headersRaw, 'X-Original-To');

        $header->date = $this->parseDateFromHead($head);

        $header->subject = isset($head->subject) && !empty(\trim($head->subject))
            ? $this->decodeMimeStr($head->subject)
            : null;

        $this->populateSenderFields($header, $head, $headersRaw);

        if (isset($head->to)) {
            [$header->to, $header->toString] = $this->parseRecipientList($head->to);
        }
        if (isset($head->cc)) {
            [$header->cc, $header->ccString] = $this->parseRecipientList($head->cc);
        }
        if (isset($head->bcc)) {
            [$header->bcc] = $this->parseRecipientList($head->bcc);
        }
        if (isset($head->reply_to)) {
            [$header->replyTo] = $this->parseRecipientList($head->reply_to);
        }

        if (isset($head->message_id)) {
            $this->assertValueIsString($head->message_id, 'Message ID', __METHOD__);
            $header->messageId = $head->message_id;
        }

        return $header;
    }

    /**
     * @throws InvalidParameterException
     */
    private function parseDateFromHead(\stdClass $head): string
    {
        if (isset($head->date) && !empty(\trim($head->date))) {
            return self::parseDateTime($head->date);
        }
        if (isset($head->Date) && !empty(\trim($head->Date))) {
            return self::parseDateTime($head->Date);
        }
        return self::parseDateTime((new \DateTime())->format('Y-m-d H:i:s'));
    }

    private function populateSenderFields(IncomingMailHeader $header, \stdClass $head, string $headersRaw): void
    {
        if (!empty($head->from)) {
            [
                $header->fromHost,
                $header->fromName,
                $header->fromAddress
            ] = $this->possiblyGetHostNameAndAddress($head->from);
        } elseif (
            \preg_match(
                '/smtp.mailfrom=[-0-9a-zA-Z.+_]+@[-0-9a-zA-Z.+_]+.[a-zA-Z]{2,4}/',
                $headersRaw,
                $matches
            )
        ) {
            $header->fromAddress = \substr($matches[0], 14);
        }

        if (!empty($head->sender)) {
            [
                $header->senderHost,
                $header->senderName,
                $header->senderAddress
            ] = $this->possiblyGetHostNameAndAddress($head->sender);
        }
    }

    /**
     * @param object[] $recipients
     *
     * @return array{0: array<string, string|null>, 1: string}
     *
     * @throws \Exception
     */
    protected function parseRecipientList(array $recipients): array
    {
        $emailMap = [];
        $strings = [];
        foreach ($recipients as $recipient) {
            $parsed = $this->possiblyGetEmailAndNameFromRecipient($recipient);
            if ($parsed !== null) {
                [$email, $name] = $parsed;
                $strings[] = $name ? "{$name} <{$email}>" : $email;
                $emailMap[$email] = $name;
            }
        }
        return [$emailMap, \implode(', ', $strings)];
    }

    /**
     * taken from https://www.electrictoolbox.com/php-imap-message-parts/.
     *
     * @param \stdClass[] $messageParts
     * @param \stdClass[] $flattenedParts
     *
     * @phpstan-param array<string, PARTSTRUCTURE> $flattenedParts
     *
     * @return \stdClass[]
     *
     * @phpstan-return array<string, \stdClass>
     */
    public function flattenParts(
        array $messageParts,
        array $flattenedParts = [],
        string $prefix = '',
        int $index = 1,
        bool $fullPrefix = true
    ): array {
        foreach ($messageParts as $part) {
            $flattenedParts[$prefix . $index] = $part;
            if (isset($part->parts)) {
                /** @var \stdClass[] $partParts */
                $partParts = $part->parts;

                if ($part->type == self::PART_TYPE_TWO) {
                    $flattenedParts = $this->flattenParts(
                        $partParts,
                        $flattenedParts,
                        $prefix . $index . '.',
                        1,
                        false
                    );
                } elseif ($fullPrefix) {
                    $flattenedParts = $this->flattenParts(
                        $partParts,
                        $flattenedParts,
                        $prefix . $index . '.'
                    );
                } else {
                    $flattenedParts = $this->flattenParts(
                        $partParts,
                        $flattenedParts,
                        $prefix
                    );
                }
                unset($flattenedParts[$prefix . $index]->parts);
            }
            ++$index;
        }

        /** @var array<string, \stdClass> */
        return $flattenedParts;
    }

    /**
     * Get mail data.
     *
     * @param int $mailId ID of the mail
     * @param bool $markAsSeen Mark the email as seen, when set to true
     * @throws \Exception
     */
    public function getMail(int $mailId, bool $markAsSeen = true): IncomingMail
    {
        $mail = new IncomingMail();
        $mail->setHeader($this->getMailHeader($mailId));

        $mailStructure = Imap::fetchStructure(
            $this->getImapStream(),
            $mailId,
            ($this->imapSearchOption === \SE_UID) ? \FT_UID : 0
        );

        if (empty($mailStructure->parts)) {
            $this->initMailPart($mail, $mailStructure, 0, $markAsSeen);
        } else {
            /** @var array<string, \stdClass> $parts */
            $parts = $mailStructure->parts;
            foreach ($this->flattenParts($parts) as $partNum => $partStructure) {
                $this->initMailPart($mail, $partStructure, $partNum, $markAsSeen);
            }
        }

        return $mail;
    }

    /**
     * Download attachment.
     *
     * @param array $params Array of params of mail
     * @param object $partStructure Part of mail
     * @param bool $emlOrigin True, if it indicates, that the attachment comes from an EML (mail) file
     *
     * @phpstan-param array<string, string> $params
     * @phpstan-param PARTSTRUCTURE $partStructure
     *
     * @return IncomingMailAttachment $attachment
     * @throws \Exception
     */
    public function downloadAttachment(
        DataPartInfo $dataInfo,
        array $params,
        object $partStructure,
        bool $emlOrigin = false
    ): IncomingMailAttachment {
        $dispositionAttachment = (
            isset($partStructure->disposition)
            && \is_string($partStructure->disposition)
            && \mb_strtolower($partStructure->disposition) === 'attachment'
        );

        if ($partStructure->subtype == 'RFC822' && $dispositionAttachment) {
            $fileName = \strtolower($partStructure->subtype) . '.eml';
        } elseif ($partStructure->subtype == 'ALTERNATIVE') {
            $fileName = \strtolower($partStructure->subtype) . '.eml';
        } elseif (
            (!isset($params['filename']) || empty(\trim($params['filename'])))
            && (!isset($params['name']) || empty(\trim($params['name'])))
        ) {
            $fileName = \strtolower($partStructure->subtype);
        } else {
            $fileName = (isset($params['filename']) && !empty(\trim($params['filename'])))
                ? $params['filename']
                : $params['name'];
            $fileName = $this->decodeMimeStr($fileName);
            $fileName = $this->decodeRFC2231($fileName);
        }
        $fileName = str_replace('/', '_', $fileName);

        /** @var ?int $sizeInBytes */
        $sizeInBytes = $partStructure->bytes ?? null;

        /** @var scalar|array|object|null $encoding */
        $encoding = $partStructure->encoding ?? null;

        $this->assertValueIsIntegerIfNotNull($sizeInBytes, 'sizeInBytes', __METHOD__);
        $this->assertValueIsIntegerIfNotNull($encoding, 'encoding', __METHOD__);

        if (isset($partStructure->type)) {
            $this->assertValueIsIntegerIfNotNull($partStructure->type, 'type', __METHOD__);
        }

        $partStructure_id = ($partStructure->ifid && isset($partStructure->id)) ? \trim($partStructure->id) : null;

        $attachment = new IncomingMailAttachment();
        $attachment->id = \bin2hex(\random_bytes(20));
        $attachment->contentId = isset($partStructure_id) ? \trim($partStructure_id, ' <>') : null;
        if (isset($partStructure->type)) {
            $attachment->type = $partStructure->type;
        }
        $attachment->encoding = $encoding;
        $attachment->subtype = ($partStructure->ifsubtype && isset($partStructure->subtype))
            ? \trim($partStructure->subtype)
            : null;
        $attachment->description = ($partStructure->ifdescription && isset($partStructure->description))
            ? \trim((string)$partStructure->description)
            : null;
        $attachment->name = $fileName;
        $attachment->sizeInBytes = $sizeInBytes;
        $attachment->disposition = (isset($partStructure->disposition) && \is_string($partStructure->disposition))
            ? $partStructure->disposition
            : null;

        /** @var ?string $charset */
        $charset = $params['charset'] ?? null;

        if (isset($charset)) {
            $this->assertValueIsString($charset, 'charset', __METHOD__);
        }

        $attachment->charset = (isset($charset) && !empty(\trim($charset))) ? $charset : null;
        $attachment->emlOrigin = $emlOrigin;

        $attachment->addDataPartInfo($dataInfo);

        $attachment->fileInfoRaw = $attachment->getFileInfo(\FILEINFO_RAW);
        $attachment->fileInfo = $attachment->getFileInfo();
        $attachment->mime = $attachment->getFileInfo(\FILEINFO_MIME);
        $attachment->mimeType = $attachment->getFileInfo(\FILEINFO_MIME_TYPE);
        $attachment->mimeEncoding = $attachment->getFileInfo(\FILEINFO_MIME_ENCODING);
        $attachment->fileExtension = $attachment->getFileInfo(\FILEINFO_EXTENSION);

        $attachmentsDir = $this->getAttachmentsDir();

        if ($attachmentsDir != null) {
            if ($this->getAttachmentFilenameMode()) {
                $fileSysName = $attachment->name;
            } else {
                $fileSysName = \bin2hex(\random_bytes(16)) . '.' . $attachment->fileExtension;
            }

            $filePath = $attachmentsDir . \DIRECTORY_SEPARATOR . $fileSysName;

            if (\strlen($filePath) > self::MAX_LENGTH_FILEPATH) {
                $ext = \pathinfo($filePath, \PATHINFO_EXTENSION);
                $filePath = \substr($filePath, 0, self::MAX_LENGTH_FILEPATH - 1 - \strlen($ext)) . '.' . $ext;
            }

            $attachment->setFilePath($filePath);
            $attachment->saveToDisk();
        }

        return $attachment;
    }

    /**
     * Converts a string to UTF-8.
     *
     * @param string $string MIME string to decode
     * @param string $fromCharset Charset to convert from
     *
     * @return string Converted string if conversion was successful, or the original string if not
     */
    public function convertToUtf8(string $string, string $fromCharset): string
    {
        $fromCharset = mb_strtolower($fromCharset);
        $newString = '';

        if ($fromCharset === 'default') {
            $fromCharset = $this->decodeMimeStrDefaultCharset;
        }

        switch ($fromCharset) {
            case 'default': // Charset default is already ASCII (not encoded)
            case 'utf-8': // Charset UTF-8 is OK
                $newString .= $string;
                break;
            default:
                // If charset exists in mb_list_encodings(), convert using mb_convert function
                if (\in_array($fromCharset, $this->lowercaseMbListEncodings(), true)) {
                    $newString .= \mb_convert_encoding($string, 'UTF-8', $fromCharset);
                } else {
                    // Fallback: Try to convert with iconv()
                    $iconv_converted_string = @\iconv($fromCharset, 'UTF-8', $string);
                    if (!$iconv_converted_string) {
                        // If iconv() could also not convert, return string as it is
                        // (unknown charset)
                        $newString .= $string;
                    } else {
                        $newString .= $iconv_converted_string;
                    }
                }
                break;
        }

        return $newString;
    }

    /**
     * Decodes a mime string.
     *
     * @param string $string MIME string to decode
     *
     * @return string Converted string if conversion was successful, or the original string if not
     */
    public function decodeMimeStr(string $string): string
    {
        $newString = '';
        /** @var list<object{charset?: string, text?: string}>|false $elements */
        $elements = \imap_mime_header_decode($string);

        if ($elements === false) {
            return $string;
        }

        foreach ($elements as $element) {
            $newString .= $this->convertToUtf8($element->text, $element->charset);
        }

        return $newString;
    }

    public function isUrlEncoded(string $string): bool
    {
        $hasInvalidChars = \preg_match('#[^%a-zA-Z0-9\-_.+]#', $string);
        $hasEscapedChars = \preg_match('#%[a-zA-Z0-9]{2}#', $string);

        return !$hasInvalidChars && $hasEscapedChars;
    }

    /**
     * Converts the datetime to an RFC 3339 compliant format.
     *
     * @param string $dateHeader Header datetime
     *
     * @return string RFC 3339 compliant format or original (unchanged) format, if conversation is not possible
     * @throws InvalidParameterException
     */
    public function parseDateTime(string $dateHeader): string
    {
        if (empty(\trim($dateHeader))) {
            throw new InvalidParameterException(
                'parseDateTime() expects parameter 1 to be a parsable string datetime'
            );
        }

        $dateHeaderUnixtimestamp = \strtotime($dateHeader);

        if (!$dateHeaderUnixtimestamp) {
            return $dateHeader;
        }

        return \date(\DATE_RFC3339, $dateHeaderUnixtimestamp);
    }

    /**
     * Gets IMAP path.
     */
    public function getImapPath(): string
    {
        return $this->imapPath;
    }

    /**
     * Get message in MBOX format.
     *
     * @param int $mailId message number
     * @throws ConnectionException
     */
    public function getMailMboxFormat(int $mailId): string
    {
        $option = ($this->imapSearchOption == \SE_UID) ? \FT_UID : 0;

        return Imap::fetchHeader($this->getImapStream(), $mailId, $option | \FT_PREFETCHTEXT)
            . Imap::body($this->getImapStream(), $mailId, $option);
    }

    /**
     * Get folders list.
     *
     * @return (false|mixed|string)[][]
     *
     * @phpstan-return list<array{fullpath: string, attributes: mixed, delimiter: mixed, shortpath: false|string}>
     * @throws ConnectionException
     */
    public function getMailboxes(string $search = '*'): array
    {
        /** @phpstan-var array<int, scalar|array|object{name?: string}|resource|null> $mailboxes */
        $mailboxes = Imap::getMailboxes($this->getImapStream(), $this->imapPath, $search);

        return $this->possiblyGetMailboxes($mailboxes);
    }

    /**
     * Get folders list.
     *
     * @return (false|mixed|string)[][]
     *
     * @phpstan-return list<array{fullpath: string, attributes: mixed, delimiter: mixed, shortpath: false|string}>
     * @throws ConnectionException
     */
    public function getSubscribedMailboxes(string $search = '*'): array
    {
        /** @phpstan-var array<int, scalar|array|object{name?: string}|resource|null> $mailboxes */
        $mailboxes = Imap::getSubscribed($this->getImapStream(), $this->imapPath, $search);

        return $this->possiblyGetMailboxes($mailboxes);
    }

    /**
     * Subscribe to a mailbox.
     *
     * @throws \Exception
     */
    public function subscribeMailbox(string $mailbox): void
    {
        Imap::subscribe(
            $this->getImapStream(),
            $this->getCombinedPath($mailbox)
        );
    }

    /**
     * Unsubscribe from a mailbox.
     *
     * @throws \Exception
     */
    public function unsubscribeMailbox(string $mailbox): void
    {
        Imap::unsubscribe(
            $this->getImapStream(),
            $this->getCombinedPath($mailbox)
        );
    }

    /**
     * Appends $message to $mailbox.
     *
     * @phpstan-param string|array{0: COMPOSE_ENVELOPE, 1: COMPOSE_BODY} $message
     *
     * @throws ConnectionException
     * @see Imap::append()
     */
    public function appendMessageToMailbox(
        string|array $message,
        string $mailbox = '',
        ?string $options = null,
        ?string $internal_date = null
    ): bool {
        if (
            \is_array($message) &&
            \count($message) === self::EXPECTED_SIZE_OF_MESSAGE_AS_ARRAY &&
            isset($message[0], $message[1])
        ) {
            $message = Imap::mailCompose($message[0], $message[1]);
        }

        if (!\is_string($message)) {
            throw new \InvalidArgumentException(
                'Argument 1 passed to ' . __METHOD__ . ' must be a string or envelope/body pair.'
            );
        }

        return Imap::append(
            $this->getImapStream(),
            $this->getCombinedPath($mailbox),
            $message,
            $options,
            $internal_date
        );
    }

    /**
     * Returns the list of available encodings in lower case.
     *
     * @return string[]
     *
     * @phpstan-return list<string>
     */
    protected function lowercaseMbListEncodings(): array
    {
        $lowercaseEncodings = [];
        $encodings = \mb_list_encodings();
        foreach ($encodings as $encoding) {
            $lowercaseEncodings[] = \strtolower($encoding);
        }

        return $lowercaseEncodings;
    }

    /**
     * Returns the list of available encodings in lower case.
     *
     * @return string[]
     *
     * @phpstan-return list<string>
     *
     * @deprecated since 5.x
     */
    protected function lowercase_mb_list_encodings(): array
    {
        return $this->lowercaseMbListEncodings();
    }

    /**
     * @throws ConnectionException
     */
    protected function initImapStreamWithRetry(): Connection
    {
        $retry = $this->connectionRetry;

        do {
            try {
                return $this->initImapStream();
            } catch (ConnectionException $exception) {
                if ($this->connectionRetryDelay) {
                    \usleep($this->connectionRetryDelay * 1000);
                }
            }
        } while (--$retry > 0);

        throw $exception;
    }

    /**
     * Retrieve the quota settings per user.
     *
     * @param string $quotaRoot Should normally be in the form of which mailbox (i.e. INBOX)
     *
     * @throws ConnectionException
     * @see imap_get_quotaroot()
     */
    protected function getQuota(string $quotaRoot = 'INBOX'): array
    {
        return Imap::getQuotaRoot($this->getImapStream(), $quotaRoot);
    }

    /**
     * Open an IMAP stream to a mailbox.
     *
     * @return Connection IMAP stream on success
     *
     * @throws ConnectionException
     */
    protected function initImapStream(): Connection
    {
        foreach ($this->timeouts as $type => $timeout) {
            Imap::timeout($type, $timeout);
        }

        return Imap::open(
            $this->imapPath,
            $this->imapLogin,
            $this->imapPassword,
            $this->imapOptions,
            $this->imapRetriesNum,
            $this->imapParams
        );
    }

    /**
     * @phpstan-param PARTSTRUCTURE $partStructure
     * @throws \Exception
     */
    protected function initMailPart(
        IncomingMail $mail,
        object $partStructure,
        string|int $partNum,
        bool $markAsSeen = true,
        bool $emlParse = false
    ): void {
        if (!isset($mail->id)) {
            throw new \InvalidArgumentException(
                'Argument 1 passeed to ' . __METHOD__ . '() did not have the id property set!'
            );
        }

        $options = ($this->imapSearchOption === \SE_UID) ? \FT_UID : 0;

        if (!$markAsSeen) {
            $options |= \FT_PEEK;
        }
        $dataInfo = new DataPartInfo($this, $mail->id, $partNum, $partStructure->encoding, $options);

        /** @var array<string, string> $params */
        $params = [];
        if (!empty($partStructure->parameters)) {
            foreach ($partStructure->parameters as $param) {
                $params[\strtolower($param->attribute)] = '';
                $value = $param->value ?? null;
                if (isset($value) && \trim($value) !== '') {
                    $params[\strtolower($param->attribute)] = $this->decodeMimeStr($value);
                }
            }
        }
        if (!empty($partStructure->dparameters)) {
            foreach ($partStructure->dparameters as $param) {
                $paramName = \strtolower(\preg_match('~^(.*?)\*~', $param->attribute, $matches)
                    ? $matches[1]
                    : $param->attribute);
                if (isset($params[$paramName])) {
                    $params[$paramName] .= $param->value;
                } else {
                    $params[$paramName] = $param->value;
                }
            }
        }

        $isAttachment = isset($params['filename']) || isset($params['name']) || isset($partStructure->id);

        $dispositionAttachment = (isset($partStructure->disposition) &&
            \is_string($partStructure->disposition) &&
            \mb_strtolower($partStructure->disposition) === 'attachment');

        // ignore contentId on body when mail isn't multipart (https://github.com/barbushin/php-imap/issues/71)
        if (
            !$partNum &&
            $partStructure->type === \TYPETEXT &&
            !$dispositionAttachment
        ) {
            $isAttachment = false;
        }

        if ($isAttachment) {
            $mail->setHasAttachments(true);
        }

        // check if the part is a subpart of another attachment part (RFC822)
        if ($partStructure->subtype === 'RFC822' && $dispositionAttachment) {
            // Although we are downloading each part separately, we are going to download the EML to a single file
            // in case someone wants to process or parse in another process
            $attachment = self::downloadAttachment($dataInfo, $params, $partStructure);
            $mail->addAttachment($attachment);
        }

        // If it comes from an EML file it is an attachment
        if ($emlParse) {
            $isAttachment = true;
        }

        // Do NOT parse attachments, when getAttachmentsIgnore() is true
        if (
            $this->getAttachmentsIgnore()
            && (
                $partStructure->type !== \TYPEMULTIPART
                && (
                    $partStructure->type !== \TYPETEXT
                    || !\in_array(\mb_strtolower($partStructure->subtype), ['plain', 'html'], true)
                )
            )
        ) {
            return;
        }

        if ($isAttachment) {
            $attachment = self::downloadAttachment($dataInfo, $params, $partStructure, $emlParse);
            $mail->addAttachment($attachment);
        } else {
            if (isset($params['charset']) && !empty(\trim($params['charset']))) {
                $dataInfo->charset = $params['charset'];
            }
        }

        if (!empty($partStructure->parts)) {
            foreach ($partStructure->parts as $subPartNum => $subPartStructure) {
                $notAttachment = (!isset($partStructure->disposition) || $partStructure->disposition !== 'attachment');

                if ($partStructure->type === \TYPEMESSAGE && $partStructure->subtype === 'RFC822' && $notAttachment) {
                    $this->initMailPart($mail, $subPartStructure, $partNum, $markAsSeen);
                } elseif (
                    $partStructure->type === \TYPEMULTIPART
                    && $partStructure->subtype === 'ALTERNATIVE'
                    && $notAttachment
                ) {
                    // https://github.com/barbushin/php-imap/issues/198
                    $this->initMailPart($mail, $subPartStructure, $partNum, $markAsSeen);
                } elseif ($partStructure->subtype === 'RFC822' && $dispositionAttachment) {
                    //If it comes from am EML attachment, download each part separately as a file
                    $this->initMailPart(
                        $mail,
                        $subPartStructure,
                        $partNum . '.' . ($subPartNum + 1),
                        $markAsSeen,
                        true
                    );
                } else {
                    $this->initMailPart(
                        $mail,
                        $subPartStructure,
                        $partNum . '.' . ($subPartNum + 1),
                        $markAsSeen
                    );
                }
            }
        } else {
            if ($partStructure->type === \TYPETEXT) {
                if (\mb_strtolower($partStructure->subtype) === 'plain') {
                    if ($dispositionAttachment) {
                        return;
                    }

                    $mail->addDataPartInfo($dataInfo, DataPartInfo::TEXT_PLAIN);
                } elseif (!$partStructure->ifdisposition) {
                    $mail->addDataPartInfo($dataInfo, DataPartInfo::TEXT_HTML);
                } elseif (!\is_string($partStructure->disposition)) {
                    throw new \InvalidArgumentException(
                        'disposition property of object passed as argument 2 to '
                        . __METHOD__ . '() was present but not a string!'
                    );
                } elseif (!$dispositionAttachment) {
                    $mail->addDataPartInfo($dataInfo, DataPartInfo::TEXT_HTML);
                }
            } elseif ($partStructure->type === \TYPEMESSAGE) {
                $mail->addDataPartInfo($dataInfo, DataPartInfo::TEXT_PLAIN);
            }
        }
    }

    /**
     * @throws \Exception
     */
    protected function decodeRFC2231(string $string): string
    {
        if (\preg_match("/^(.*?)'.*?'(.*)$/", $string, $matches)) {
            $data = $matches[2];
            if ($this->isUrlEncoded($data)) {
                $string = $this->decodeMimeStr(\urldecode($data));
            }
        }

        return $string;
    }

    /**
     * Combine Subfolder or Folder to the connection.
     * Have the imapPath a folder added to the connection info, then will the $folder added as subfolder.
     * If the parameter $absolute TRUE, then will the connection new builded only with this folder as root element.
     *
     * @param string $folder Folder, the will added to the path
     * @param bool $absolute Add folder as root element to the connection and remove all other from this
     *
     * @return string Return the new path
     */
    protected function getCombinedPath(string $folder, bool $absolute = false): string
    {
        if (empty(\trim($folder))) {
            $result = $this->imapPath;
        } elseif (str_ends_with($this->imapPath, '}')) {
            $result = $this->imapPath . $folder;
        } elseif ($absolute === true) {
            $folder = ($folder === '/') ? '' : $folder;
            $posConnectionDefinitionEnd = \strpos($this->imapPath, '}');

            if ($posConnectionDefinitionEnd === false) {
                throw new \UnexpectedValueException('"}" was not present in IMAP path!');
            }

            $result = \substr($this->imapPath, 0, $posConnectionDefinitionEnd + 1) . $folder;
        } else {
            $result = $this->imapPath . $this->getPathDelimiter() . $folder;
        }

        return $result;
    }

    /**
     * @phpstan-return array{0: string, 1: null|string}|null
     *
     * @return (string|null)[]|null
     * @throws \Exception
     */
    protected function possiblyGetEmailAndNameFromRecipient(object $recipient): ?array
    {
        if (isset($recipient->mailbox, $recipient->host)) {
            $this->assertValueIsString($recipient->mailbox, 'recipientMailbox', __METHOD__);
            $this->assertValueIsString($recipient->host, 'recipientHost', __METHOD__);
            $this->assertPropertyIsStringIfNotNull($recipient, 'recipientPersonal', 1, __METHOD__);

            $recipientMailbox = $recipient->mailbox;
            $recipientHost = $recipient->host;
            $recipientPersonal = $recipient->personal ?? null;

            if (\trim($recipientMailbox) !== '' && \trim($recipientHost) !== '') {
                $recipientEmail = \strtolower($recipientMailbox . '@' . $recipientHost);
                $recipientName = (\is_string($recipientPersonal) && \trim($recipientPersonal) !== '')
                    ? $this->decodeMimeStr($recipientPersonal)
                    : null;

                return [
                    $recipientEmail,
                    $recipientName,
                ];
            }
        }

        return null;
    }

    /**
     * @phpstan-param array<int, scalar|array|object{name?: string}|resource|null> $t
     *
     * @return (false|mixed|string)[][]
     *
     * @phpstan-return list<array{fullpath: string, attributes: mixed, delimiter: mixed, shortpath: false|string}>
     */
    protected function possiblyGetMailboxes(array $t): array
    {
        $arr = [];
        if ($t) {
            foreach ($t as $index => $item) {
                if (!\is_object($item)) {
                    throw new \UnexpectedValueException(
                        'Index ' . $index . ' of argument 1 passed to '
                        . __METHOD__ . '() corresponds to a non-object value, ' . \gettype($item) . ' given!'
                    );
                }
                /** @var ?string $itemName */
                $itemName = $item->name ?? null;

                if (!isset($item->name, $item->attributes, $item->delimiter)) {
                    throw new \UnexpectedValueException(
                        'The object at index ' . $index . ' of argument 1 passed to '
                        . __METHOD__
                        . '() was missing one or more of the required properties "name", "attributes", "delimiter"!'
                    );
                }
                $this->assertValueIsString($itemName, 'itemName', __METHOD__);

                // https://github.com/barbushin/php-imap/issues/339
                $name = $this->decodeStringFromUtf7ImapToUtf8($itemName);
                $namePosition = \strpos($name, '}');
                if ($namePosition === false) {
                    throw new \UnexpectedValueException('Expected token "}" not found in subscription name!');
                }
                $arr[] = [
                    'fullpath' => $name,
                    'attributes' => $item->attributes,
                    'delimiter' => $item->delimiter,
                    'shortpath' => \substr($name, $namePosition + 1),
                ];
            }
        }

        return $arr;
    }

    /**
     * @phpstan-param HOSTNAMEANDADDRESS $t
     *
     * @phpstan-return array{0: string|null, 1: string|null, 2: string}
     * @throws \Exception
     */
    protected function possiblyGetHostNameAndAddress(array $t): array
    {
        $out = [
            $t[0]->host ?? (isset($t[1], $t[1]->host) ? $t[1]->host : null),
            1 => null,
        ];
        foreach ([0, 1] as $index) {
            $maybe = isset($t[$index], $t[$index]->personal) ? $t[$index]->personal : null;
            if (\is_string($maybe) && \trim($maybe) !== '') {
                $out[1] = $this->decodeMimeStr($maybe);

                break;
            }
        }

        $out[] = \strtolower($t[0]->mailbox . '@' . $out[0]);

        /** @var array{0: string|null, 1: string|null, 2: string} */
        return $out;
    }

    /**
     * @throws ConnectionException
     */
    protected function pingOrDisconnect(): void
    {
        if ($this->imapStream && !Imap::ping($this->imapStream)) {
            $this->disconnect();
            $this->imapStream = null;
        }
    }

    /**
     * Search the mailbox for emails from multiple, specific senders.
     *
     * This function wraps Mailbox::searchMailbox() to overcome a shortcoming in ext-imap
     *
     * @return int[]
     *
     * @phpstan-return list<int>
     * @throws ConnectionException
     */
    protected function searchMailboxFromWithOrWithoutDisablingServerEncoding(
        string $criteria,
        bool $disableServerEncoding,
        string $sender,
        string ...$senders,
    ): array {
        \array_unshift($senders, $sender);

        $senders = \array_values(\array_unique(\array_map(
            static function (string $sender) use ($criteria): string {
                return $criteria . ' FROM ' . \mb_strtolower($sender);
            },
            $senders
        )));

        return $this->searchMailboxMergeResultsWithOrWithoutDisablingServerEncoding(
            $disableServerEncoding,
            ...$senders
        );
    }

    /**
     * Search the mailbox using different criteria, then merge the results.
     *
     * @param bool $disableServerEncoding
     * @param string $singleCriteria
     * @param string ...$criteria
     *
     * @return int[]
     *
     * @phpstan-return list<int>
     * @throws ConnectionException
     */
    protected function searchMailboxMergeResultsWithOrWithoutDisablingServerEncoding(
        bool $disableServerEncoding,
        string $singleCriteria,
        ...$criteria
    ): array {
        \array_unshift($criteria, $singleCriteria);

        $criteria = \array_values(\array_unique($criteria));

        $out = [];

        foreach ($criteria as $criterion) {
            $out = \array_merge($out, $this->searchMailbox($criterion, $disableServerEncoding));
        }

        /** @phpstan-var list<int> */
        return \array_values(\array_unique($out, \SORT_NUMERIC));
    }
}
