<?php

/**
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 * @author BAPCLTD-Marv
 */

declare(strict_types=1);

namespace PhpImap;

use IMAP\Connection;
use ParagonIE\HiddenString\HiddenString;
use PhpImap\Exceptions\ConnectionException;

/**
 * @phpstan-type MAILBOX_ARGS = array{
 *      0: HiddenString,
 *      1: HiddenString,
 *      2: HiddenString,
 *      3: string,
 *      4?: string,
 *      5?: array<string, mixed>,
 * }
 * @phpstan-type COMPOSE_BODY = list<array{
 *      id?: string,
 *      type?: int,
 *      encoding?: int,
 *      charset?: string,
 *      subtype?: string,
 *      description?: string,
 *      disposition?: array{filename: string, type?: string},
 *      'disposition\.type'?: string,
 *      'type\.parameters'?: array{name: string},
 *      'contents\.data'?: string,
 * }>
 * @phpstan-type COMPOSE_ENVELOPE = array{
 *      subject?: string
 * }
 * @phpstan-type PARTSTRUCTURE_PARAM = object{attribute: string, value?: string}
 * @phpstan-type PARTSTRUCTURE = object{
 *      id?: string,
 *      encoding: int|mixed,
 *      partStructure: object[],
 *      parameters: PARTSTRUCTURE_PARAM[],
 *      dparameters: object{attribute:string, value:string}[],
 *      parts: array<int, object{disposition?:string}>,
 *      type: int,
 *      subtype: string
 * }
 */
final class Imap
{
    /** @phpstan-var list<int> */
    public const SORT_CRITERIA = [
        \SORTARRIVAL,
        \SORTCC,
        \SORTDATE,
        \SORTFROM,
        \SORTSIZE,
        \SORTSUBJECT,
        \SORTTO,
    ];

    /** @phpstan-var list<int> */
    public const TIMEOUT_TYPES = [
        \IMAP_CLOSETIMEOUT,
        \IMAP_OPENTIMEOUT,
        \IMAP_READTIMEOUT,
        \IMAP_WRITETIMEOUT,
    ];

    /** @phpstan-var list<int> */
    public const CLOSE_FLAGS = [
        0,
        \CL_EXPUNGE,
    ];

    public static function flushImapErrors(): void
    {
        \imap_errors();
    }

    /**
     * @param string[]|null $errors
     */
    private static function assertResultNotFalse(
        mixed $result,
        string $message,
        int $code,
        string $functionName,
        ?array $errors = null,
    ): void {
        if ($result === false) {
            throw new \UnexpectedValueException(
                $message,
                $code,
                self::handleErrors($errors ?? \imap_errors() ?: [], $functionName)
            );
        }
    }

    /**
     * @return true
     *
     * @see imap_append()
     */
    public static function append(
        Connection $imapStream,
        string $mailbox,
        string $message,
        ?string $options = null,
        ?string $internalDate = null
    ): bool {
        self::flushImapErrors();

        if ($options !== null && $internalDate !== null) {
            $result = \imap_append(
                $imapStream,
                $mailbox,
                $message,
                $options,
                $internalDate
            );
        } elseif ($options !== null) {
            $result = \imap_append($imapStream, $mailbox, $message, $options);
        } else {
            $result = \imap_append($imapStream, $mailbox, $message);
        }

        self::assertResultNotFalse($result, 'Could not append message to mailbox!', 1, 'imap_append');

        return true;
    }

    public static function body(Connection $imapStream, int $messageNumber, int $options = 0): string
    {
        self::flushImapErrors();

        $result = \imap_body($imapStream, $messageNumber, $options) ?: '';

        self::assertResultNotFalse($result, 'Could not fetch message body from mailbox!', 0, 'imap_body');

        return $result;
    }

    /**
     * @phpstan-return object{Date: string, Driver: string, Mailbox: string, Nmsgs: int, Recent: int}&\stdClass
     *
     * @param Connection $imapStream
     *
     * @return \stdClass
     */
    public static function check(Connection $imapStream): \stdClass
    {
        self::flushImapErrors();

        /** @var object{Date: string, Driver: string, Mailbox: string, Nmsgs: int, Recent: int}&\stdClass $result */
        $result = \imap_check($imapStream);

        self::assertResultNotFalse($result, 'Could not check imap mailbox!', 0, 'imap_check');

        return $result;
    }

    public static function clearFlagFull(
        Connection $imapStream,
        int|string $sequence,
        string $flag,
        int $options = 0
    ): bool {
        return self::callWithSequenceAndString(
            \imap_clearflag_full(...),
            $imapStream,
            $sequence,
            $flag,
            $options,
            __METHOD__,
            'Could not clear flag on messages!'
        );
    }

    /**
     * @deprecated since 5.x
     */
    public static function clearflag_full(
        Connection $imapStream,
        int|string $sequence,
        string $flag,
        int $options = 0
    ): bool {
        return self::clearFlagFull($imapStream, $sequence, $flag, $options);
    }

    /**
     * @phpstan-param value-of<self::CLOSE_FLAGS> $flag
     */
    public static function close(Connection $imapStream, int $flag = 0): bool
    {
        self::flushImapErrors();

        $result = \imap_close($imapStream, $flag);

        $message = 'Could not close imap connection';
        if (!$result) {
            if (\CL_EXPUNGE === ($flag & \CL_EXPUNGE)) {
                $message .= ', messages may not have been expunged';
            }
            $message .= '!';
        }
        self::assertResultNotFalse($result, $message, 0, 'imap_close');

        return $result;
    }

    public static function createMailbox(Connection $imapStream, string $mailbox): bool
    {
        self::flushImapErrors();

        $result = \imap_createmailbox($imapStream, self::encodeStringToUtf7Imap($mailbox));

        self::assertResultNotFalse($result, 'Could not create mailbox!', 0, 'createMailbox');

        return $result;
    }

    public static function delete(Connection $imapStream, string|int $messageNumber, int $options = 0): bool
    {
        $messageNumber = self::encodeStringToUtf7Imap(self::ensureRange(
            $messageNumber,
            __METHOD__,
            1
        ));

        self::flushImapErrors();

        $result = \imap_delete($imapStream, $messageNumber, $options);

        self::assertResultNotFalse($result, 'Could not delete message from mailbox!', 0, 'imap_delete');

        return $result;
    }

    public static function deleteMailbox(Connection $imapStream, string $mailbox): bool
    {
        self::flushImapErrors();

        $result = \imap_deletemailbox($imapStream, self::encodeStringToUtf7Imap($mailbox));

        self::assertResultNotFalse($result, 'Could not delete mailbox!', 0, 'deleteMailbox');

        return $result;
    }

    public static function expunge(Connection $imapStream): bool
    {
        self::flushImapErrors();

        $result = \imap_expunge($imapStream);

        self::assertResultNotFalse($result, 'Could not expunge messages from mailbox!', 0, 'imap_expunge');

        return $result;
    }

    /**
     * @return object[]
     *
     * @phpstan-return list<object{
     *     subject: ?string,
     *     from: ?string,
     *     to: ?string,
     *     date: string,
     *     message_id: string,
     *     references: ?string,
     *     in_reply_to: ?string,
     *     size: int,
     *     uid: int,
     *     msgno: int,
     *     recent: int,
     *     flagged: int,
     *     answered: int,
     *     deleted: int,
     *     seen: int,
     *     draft: int,
     *     udate: int
     * }>
     */
    public static function fetchOverview(Connection $imapStream, int|string $sequence, int $options = 0): array
    {
        self::flushImapErrors();

        $result = \imap_fetch_overview(
            $imapStream,
            self::encodeStringToUtf7Imap(self::ensureRange(
                $sequence,
                __METHOD__,
                1,
                true
            )),
            $options
        );

        self::assertResultNotFalse(
            $result,
            'Could not fetch overview for message from mailbox!',
            0,
            'imap_fetch_overview'
        );

        /** @phpstan-var list<object{subject: ?string, from: ?string, to: ?string, date: string, message_id: string, references: ?string, in_reply_to: ?string, size: int, uid: int, msgno: int, recent: int, flagged: int, answered: int, deleted: int, seen: int, draft: int, udate: int}> $result */
        return $result;
    }

    /**
     * @deprecated since 5.x
     *
     * @return object[]
     */
    public static function fetch_overview(Connection $imapStream, int|string $sequence, int $options = 0): array
    {
        return self::fetchOverview($imapStream, $sequence, $options);
    }

    public static function fetchBody(
        Connection $imapStream,
        int $messageNumber,
        int|string $section,
        int $options = 0
    ): string {
        self::flushImapErrors();

        $result = \imap_fetchbody(
            $imapStream,
            $messageNumber,
            self::encodeStringToUtf7Imap((string)$section),
            $options
        ) ?: '';

        self::assertResultNotFalse($result, 'Could not fetch message body from mailbox!', 0, 'fetchBody');

        return $result;
    }

    public static function fetchHeader(Connection $imapStream, int $messageNumber, int $options = 0): string
    {
        self::flushImapErrors();

        $result = \imap_fetchheader($imapStream, $messageNumber, $options) ?: '';

        self::assertResultNotFalse($result, 'Could not fetch message header from mailbox!', 0, 'fetchHeader');

        return $result;
    }

    public static function fetchStructure(Connection $imapStream, int $messageNumber, int $options = 0): \stdClass
    {
        self::flushImapErrors();

        $result = \imap_fetchstructure($imapStream, $messageNumber, $options);

        self::assertResultNotFalse(
            $result,
            'Could not fetch message structure from mailbox!',
            0,
            'fetchStructure'
        );

        /** @phpstan-var \stdClass $result */
        return $result;
    }

    /**
     * @return int[]
     */
    public static function getQuotaRoot(Connection $imapStream, string $quotaRoot): array
    {
        self::flushImapErrors();

        $result = \imap_get_quotaroot($imapStream, self::encodeStringToUtf7Imap($quotaRoot)) ?: [];

        self::assertResultNotFalse($result, 'Could not quota for mailbox!', 0, 'imap_get_quotaroot');

        return $result;
    }

    /**
     * @deprecated since 5.x
     *
     * @return int[]
     */
    public static function get_quotaroot(Connection $imapStream, string $quotaRoot): array
    {
        return self::getQuotaRoot($imapStream, $quotaRoot);
    }

    /**
     * @return object[]
     */
    public static function getMailboxes(Connection $imapStream, string $reference, string $pattern): array
    {
        self::flushImapErrors();

        $result = \imap_getmailboxes($imapStream, $reference, $pattern);

        $errors = null;
        if ($result === false) {
            $errors = \imap_errors();

            if ($errors === false) {
                /*
                * if there were no errors then there were no mailboxes,
                *  rather than a failure to get mailboxes.
                */
                return [];
            }
        }
        self::assertResultNotFalse(
            $result,
            'Call to imap_getmailboxes() with supplied arguments returned false, not array!',
            0,
            'getMailboxes',
            $errors
        );

        /** @phpstan-var list<object> */
        return $result;
    }

    /**
     * @return object[]
     */
    public static function getSubscribed(Connection $imapStream, string $reference, string $pattern): array
    {
        self::flushImapErrors();

        $result = \imap_getsubscribed($imapStream, $reference, $pattern);

        self::assertResultNotFalse(
            $result,
            'Call to imap_getsubscribed() with supplied arguments returned false, not array!',
            0,
            'getSubscribed'
        );

        /** @phpstan-var list<object> */
        return $result;
    }

    /**
     * @return string[]
     */
    public static function headers(Connection $imapStream): array
    {
        self::flushImapErrors();

        $result = \imap_headers($imapStream) ?: [];

        self::assertResultNotFalse($result, 'Could not fetch headers from mailbox!', 0, 'imap_headers');

        return $result;
    }

    /**
     * @return string[]
     *
     * @phpstan-return string[]
     */
    public static function listOfMailboxes(Connection $imapStream, string $reference, string $pattern): array
    {
        self::flushImapErrors();

        $result = \imap_list(
            $imapStream,
            self::encodeStringToUtf7Imap($reference),
            self::encodeStringToUtf7Imap($pattern)
        ) ?: [];

        self::assertResultNotFalse($result, 'Could not list folders mailbox!', 0, 'imap_list');

        return \array_values(\array_map(
            static function (string $folder): string {
                return self::decodeStringFromUtf7ImapToUtf8($folder);
            },
            $result
        ));
    }

    /**
     * @phpstan-param COMPOSE_ENVELOPE $envelope An associative array of headers fields (docblock is not complete)
     * @phpstan-param COMPOSE_BODY $body An indexed array of bodies (docblock is not complete)
     */
    public static function mailCompose(array $envelope, array $body): string
    {
        return \imap_mail_compose($envelope, $body) ?: '';
    }

    /**
     * @deprecated since 5.x
     *
     * @phpstan-param COMPOSE_ENVELOPE $envelope An associative array of headers fields (docblock is not complete)
     * @phpstan-param COMPOSE_BODY $body An indexed array of bodies (docblock is not complete)
     */
    public static function mail_compose(array $envelope, array $body): string
    {
        return self::mailCompose($envelope, $body);
    }

    public static function mailCopy(
        Connection $imapStream,
        int|string $messageList,
        string $mailbox,
        int $options = 0
    ): bool {
        return self::callWithSequenceAndString(
            \imap_mail_copy(...),
            $imapStream,
            $messageList,
            $mailbox,
            $options,
            __METHOD__,
            'Could not copy messages!'
        );
    }

    /**
     * @deprecated since 5.x
     */
    public static function mail_copy(
        Connection $imapStream,
        int|string $messageList,
        string $mailbox,
        int $options = 0
    ): bool {
        return self::mailCopy($imapStream, $messageList, $mailbox, $options);
    }

    public static function mailMove(
        Connection $imapStream,
        int|string $messageList,
        string $mailbox,
        int $options = 0
    ): bool {
        return self::callWithSequenceAndString(
            \imap_mail_move(...),
            $imapStream,
            $messageList,
            $mailbox,
            $options,
            __METHOD__,
            'Could not move messages!'
        );
    }

    /**
     * @deprecated since 5.x
     */
    public static function mail_move(
        Connection $imapStream,
        int|string $messageList,
        string $mailbox,
        int $options = 0
    ): bool {
        return self::mailMove($imapStream, $messageList, $mailbox, $options);
    }

    public static function mailboxMsgInfo(Connection $imapStream): \stdClass
    {
        self::flushImapErrors();

        $result = \imap_mailboxmsginfo($imapStream);

        self::assertResultNotFalse(
            $result,
            'Could not fetch message info from mailbox!',
            0,
            'mailboxMsgInfo'
        );

        return $result;
    }

    public static function numMsg(Connection $imapStream): int
    {
        self::flushImapErrors();

        $result = \imap_num_msg($imapStream) ?: 0;

        self::assertResultNotFalse(
            $result,
            'Could not get the number of messages in the mailbox!',
            0,
            'imap_num_msg'
        );

        return $result;
    }

    /**
     * @deprecated since 5.x
     */
    public static function num_msg(Connection $imapStream): int
    {
        return self::numMsg($imapStream);
    }

    /**
     * @phpstan-param array{DISABLE_AUTHENTICATOR: string}|array<empty, empty> $parameters
     * @throws ConnectionException
     */
    public static function open(
        string $mailbox,
        string $username,
        string $password,
        int $options = 0,
        int $retries = 0,
        array $parameters = []
    ): Connection {
        if (\preg_match("/^\{.*}(.*)$/", $mailbox, $matches)) {
            $mailboxName = $matches[1];

            if (!\mb_detect_encoding($mailboxName, 'ASCII', true)) {
                $mailbox = self::encodeStringToUtf7Imap($mailbox);
            }
        }

        self::flushImapErrors();

        $result = @\imap_open($mailbox, $username, $password, $options, $retries, $parameters);

        if (!$result) {
            /** @var string[] $errors */
            $errors = \imap_errors() ?: [];
            throw new ConnectionException($errors);
        }

        return $result;
    }

    public static function ping(Connection $imapStream): bool
    {
        return \imap_ping($imapStream);
    }

    public static function renameMailbox(Connection $imapStream, string $oldMailbox, string $newMailbox): bool
    {
        $oldMailbox = self::encodeStringToUtf7Imap($oldMailbox);
        $newMailbox = self::encodeStringToUtf7Imap($newMailbox);

        self::flushImapErrors();

        $result = \imap_renamemailbox($imapStream, $oldMailbox, $newMailbox);

        self::assertResultNotFalse($result, 'Could not rename mailbox!', 0, 'renameMailbox');

        return $result;
    }

    public static function reopen(
        Connection $imapStream,
        string $mailbox,
        int $options = 0,
        int $retries = 0
    ): bool {
        $mailbox = self::encodeStringToUtf7Imap($mailbox);

        self::flushImapErrors();

        $result = \imap_reopen($imapStream, $mailbox, $options, $retries);

        self::assertResultNotFalse($result, 'Could not reopen mailbox!', 0, 'imap_reopen');

        return $result;
    }

    /**
     * @param string|false|resource $file
     */
    public static function saveBody(
        Connection $imapStream,
        mixed $file,
        int $messageNumber,
        string $partNumber = '',
        int $options = 0
    ): bool {
        $file = \is_string($file) ? $file : self::ensureResource($file, __METHOD__, 2);
        $partNumber = self::encodeStringToUtf7Imap($partNumber);

        self::flushImapErrors();

        $result = \imap_savebody($imapStream, $file, $messageNumber, $partNumber, $options);

        self::assertResultNotFalse($result, 'Could not reopen mailbox!', 0, 'saveBody');

        return true;
    }

    /**
     * @return int[]
     */
    public static function search(
        Connection $imapStream,
        string $criteria,
        int $options = \SE_FREE,
        ?string $charset = null,
        bool $encodeCriteriaAsUtf7Imap = false
    ): array {
        self::flushImapErrors();

        if ($encodeCriteriaAsUtf7Imap) {
            $criteria = self::encodeStringToUtf7Imap($criteria);
        }

        if (\is_string($charset)) {
            $result = \imap_search(
                $imapStream,
                $criteria,
                $options,
                self::encodeStringToUtf7Imap($charset)
            );
        } else {
            $result = \imap_search($imapStream, $criteria, $options);
        }

        $errors = null;
        if (!$result) {
            $errors = \imap_errors();

            if ($errors === false) {
                /*
                * if there were no errors then there were no matches,
                *  rather than a failure to parse criteria.
                */
                return [];
            }
        }
        self::assertResultNotFalse($result, 'Could not search mailbox!', 0, 'imap_search', $errors);

        /** @phpstan-var list<int> */
        return $result;
    }

    public static function setFlagFull(
        Connection $imapStream,
        int|string $sequence,
        string $flag,
        int $options = 0
    ): bool {
        return self::callWithSequenceAndString(
            \imap_setflag_full(...),
            $imapStream,
            $sequence,
            $flag,
            $options,
            __METHOD__,
            'Could not set flag on messages!'
        );
    }

    /**
     * @deprecated since 5.x
     */
    public static function setflag_full(
        Connection $imapStream,
        int|string $sequence,
        string $flag,
        int $options = 0
    ): bool {
        return self::setFlagFull($imapStream, $sequence, $flag, $options);
    }

    /**
     * @phpstan-param value-of<self::SORT_CRITERIA> $criteria
     *
     * @return int[]
     */
    public static function sort(
        Connection $imapStream,
        int $criteria,
        bool $reverse,
        int $options,
        ?string $searchCriteria = null,
        ?string $charset = null
    ): array {
        self::flushImapErrors();

        if ($searchCriteria !== null && $charset !== null) {
            $result = \imap_sort(
                $imapStream,
                $criteria,
                $reverse,
                $options,
                self::encodeStringToUtf7Imap($searchCriteria),
                self::encodeStringToUtf7Imap($charset)
            );
        } elseif ($searchCriteria !== null) {
            $result = \imap_sort(
                $imapStream,
                $criteria,
                $reverse,
                $options,
                self::encodeStringToUtf7Imap($searchCriteria)
            );
        } else {
            $result = \imap_sort(
                $imapStream,
                $criteria,
                $reverse,
                $options
            );
        }

        self::assertResultNotFalse($result, 'Could not sort messages!', 0, 'imap_sort');

        /** @phpstan-var list<int> */
        return $result;
    }

    public static function status(Connection $imapStream, string $mailbox, int $options): \stdClass
    {
        $mailbox = self::encodeStringToUtf7Imap($mailbox);

        self::flushImapErrors();

        $result = \imap_status($imapStream, $mailbox, $options);

        self::assertResultNotFalse($result, 'Could not get status of mailbox!', 0, 'imap_status');

        /** @phpstan-var \stdClass $result */
        return $result;
    }

    public static function subscribe(Connection $imapStream, string $mailbox): void
    {
        $mailbox = self::encodeStringToUtf7Imap($mailbox);

        self::flushImapErrors();

        $result = \imap_subscribe($imapStream, $mailbox);

        self::assertResultNotFalse($result, 'Could not subscribe to mailbox!', 0, 'imap_subscribe');
    }

    public static function timeout(int $timeoutType, int $timeout = -1): bool|int
    {
        self::flushImapErrors();

        $result = \imap_timeout($timeoutType, $timeout);

        self::assertResultNotFalse($result, 'Could not get/set connection timeout!', 0, 'imap_timeout');

        return $result;
    }

    public static function unsubscribe(Connection $imapStream, string $mailbox): void
    {
        $mailbox = self::encodeStringToUtf7Imap($mailbox);

        self::flushImapErrors();

        $result = \imap_unsubscribe($imapStream, $mailbox);

        self::assertResultNotFalse($result, 'Could not unsubscribe from mailbox!', 0, 'imap_unsubscribe');
    }

    /**
     * Returns the provided string in UTF7-IMAP encoded format.
     *
     * @return string $str UTF-7 encoded string
     */
    public static function encodeStringToUtf7Imap(string $string): string
    {
        $out = \mb_convert_encoding($string, 'UTF7-IMAP', 'UTF-8');

        self::assertResultNotFalse(
            \is_string($out),
            'mb_convert_encoding($str, \'UTF-8\', {detected}) could not convert $str',
            0,
            'mb_convert_encoding'
        );

        return $out;
    }

    /**
     * Returns the provided string in UTF-8 encoded format.
     *
     * @return string $str, but UTF-8 encoded
     */
    public static function decodeStringFromUtf7ImapToUtf8(string $string): string
    {
        $out = \mb_convert_encoding($string, 'UTF-8', 'UTF7-IMAP');

        self::assertResultNotFalse(
            \is_string($out),
            'mb_convert_encoding($str, \'UTF-8\', \'UTF7-IMAP\') could not convert $str',
            0,
            'mb_convert_encoding'
        );

        return $out;
    }

    /**
     * @param false|resource|Connection $maybe
     *
     * @throws \InvalidArgumentException if $maybe is not a valid resource
     *
     * @return resource
     */
    private static function ensureResource(mixed $maybe, string $method, int $argument)
    {
        self::assertResultNotFalse(
            (!$maybe || !\is_resource($maybe)),
            sprintf(Constants::INVALID_RESOURCE, $argument, $method),
            0,
            'is_resource'
        );
        /** @phpstan-var resource $maybe */
        return $maybe;
    }

    /**
     * @throws ConnectionException if $maybe is not a valid resource
     */
    public static function ensureConnection(mixed $maybe, string $method, int $argument): Connection
    {
        if (!$maybe instanceof Connection) {
            throw new ConnectionException([sprintf(Constants::INVALID_RESOURCE, $argument, $method)], 0);
        }
        return $maybe;
    }

    /**
     * @param string[] $errors
     */
    private static function handleErrors(array $errors, string $method): \UnexpectedValueException
    {
        if ($errors) {
            return new \UnexpectedValueException(
                'IMAP method ' . $method . '() failed with error: ' . \implode('. ', $errors)
            );
        }

        return new \UnexpectedValueException('IMAP method ' . $method . '() failed!');
    }

    private static function encodeSequence(int|string $sequence, string $method): string
    {
        return self::encodeStringToUtf7Imap(self::ensureRange($sequence, $method, 2, true));
    }

    private static function callWithSequenceAndString(
        \Closure $callable,
        Connection $imapStream,
        int|string $sequence,
        string $string,
        int $options,
        string $method,
        string $errorMessage
    ): bool {
        self::flushImapErrors();

        $result = $callable(
            $imapStream,
            self::encodeSequence($sequence, $method),
            self::encodeStringToUtf7Imap($string),
            $options
        );

        self::assertResultNotFalse(
            $result,
            $errorMessage,
            0,
            (new \ReflectionFunction($callable))->getName()
        );

        return $result;
    }

    private static function ensureRange(
        int|string $messageNumber,
        string $method,
        int $argument,
        bool $allowSequence = false
    ): string {
        $regex = '/^\d+:\d+$/';
        $suffix = '() did not appear to be a valid message id range!';

        if ($allowSequence) {
            $regex = '/^\d+(?:(?:,\d+)+|:(\d+|\*))$/';
            $suffix = '() did not appear to be a valid message id range or sequence!';
        }

        if (\is_int($messageNumber) || \preg_match('/^\d+$/', $messageNumber)) {
            return \sprintf('%1$s:%1$s', $messageNumber);
        }

        self::assertResultNotFalse(
            \preg_match($regex, $messageNumber) !== 1,
            'Argument ' . $argument . ' passed to ' . $method . $suffix,
            0,
            'preg_match'
        );

        return $messageNumber;
    }
}
