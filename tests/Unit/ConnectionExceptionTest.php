<?php

declare(strict_types=1);

namespace PhpImap\Tests\Unit;

use PhpImap\Exceptions\ConnectionException;
use PhpImap\Tests\Fixtures\Constants;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConnectionExceptionTest extends TestCase
{
    #[Test]
    public function testConstructorEncodesMessageAsJson(): void
    {
        $errors = ['Error 1', 'Error 2', 'Error 3'];
        $exception = new ConnectionException($errors);

        self::assertSame(\json_encode($errors), $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }

    #[Test]
    public function testConstructorWithCustomCode(): void
    {
        $exception = new ConnectionException(['some error'], 42);

        self::assertSame(42, $exception->getCode());
    }

    #[Test]
    public function testConstructorWithPreviousException(): void
    {
        $previous = new \RuntimeException('previous');
        $exception = new ConnectionException(['error'], 0, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function testConstructorWithEmptyArray(): void
    {
        $exception = new ConnectionException([]);

        self::assertSame('[]', $exception->getMessage());
    }

    #[Test]
    public function testGetErrorsFirstReturnsFirstElement(): void
    {
        $exception = new ConnectionException([Constants::ERROR_FIRST, Constants::ERROR_SECOND, Constants::ERROR_THIRD]);

        self::assertSame(Constants::ERROR_FIRST, $exception->getErrors());
    }

    #[Test]
    public function testGetErrorsDefaultReturnsFirstElement(): void
    {
        $exception = new ConnectionException([Constants::ERROR_FIRST, Constants::ERROR_SECOND]);

        self::assertSame(Constants::ERROR_FIRST, $exception->getErrors());
    }

    #[Test]
    public function testGetErrorsLastReturnsLastElement(): void
    {
        $exception = new ConnectionException([Constants::ERROR_FIRST, Constants::ERROR_SECOND, Constants::ERROR_THIRD]);

        self::assertSame(Constants::ERROR_THIRD, $exception->getErrors('last'));
    }

    #[Test]
    public function testGetErrorsLastWithSingleElement(): void
    {
        $exception = new ConnectionException([Constants::ERROR_ONLY]);

        self::assertSame(Constants::ERROR_ONLY, $exception->getErrors('last'));
    }

    #[Test]
    public function testGetErrorsAllReturnsAllElements(): void
    {
        $errors = [Constants::ERROR_FIRST, Constants::ERROR_SECOND, Constants::ERROR_THIRD];
        $exception = new ConnectionException($errors);

        self::assertSame($errors, $exception->getErrors('all'));
    }

    #[Test]
    public function testGetErrorsAllWithSingleElement(): void
    {
        $exception = new ConnectionException([Constants::ERROR_ONLY]);

        self::assertSame([Constants::ERROR_ONLY], $exception->getErrors('all'));
    }

    #[Test]
    public function testGetErrorsDefaultFallsBackToFirst(): void
    {
        $exception = new ConnectionException(['first', 'second']);

        self::assertSame('first', $exception->getErrors('unknown_selector'));
    }

    #[Test]
    public function testGetErrorsIsCaseInsensitive(): void
    {
        $exception = new ConnectionException(['first', 'second', 'third']);

        self::assertSame('first', $exception->getErrors('FIRST'));
        self::assertSame('third', $exception->getErrors('LAST'));
        self::assertSame(['first', 'second', 'third'], $exception->getErrors('ALL'));
    }
}
