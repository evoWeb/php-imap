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
    public function __construct(array $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(json_encode($message), $code, $previous);
    }

    public function getErrors(string $select = 'first')
    {
        $message = json_decode($this->getMessage());

        return match (strtolower($select)) {
            'all' => $message,
            'last' => $message[\count($message) - 1],
            default => $message[0],
        };
    }
}
