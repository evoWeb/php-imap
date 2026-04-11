<?php

declare(strict_types=1);

namespace PhpImap\Exceptions;

/**
 * @see https://github.com/barbushin/php-imap
 *
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 */
class ConnectionException extends \Exception
{
    /**
     * @param string[] $message
     */
    public function __construct(array $message, int $code = 0, ?\Throwable $previous = null)
    {
        $encodedMessage = json_encode($message) ?: '';
        parent::__construct($encodedMessage, $code, $previous);
    }

    /**
     * @phpstan-return string|string[]
     */
    public function getErrors(string $select = 'first'): string|array
    {
        /** @phpstan-var string[] $message */
        $message = json_decode($this->getMessage());

        return match (strtolower($select)) {
            'all' => $message,
            'last' => $message[\count($message) - 1],
            default => $message[0],
        };
    }
}
