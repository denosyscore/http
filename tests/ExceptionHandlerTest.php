<?php

declare(strict_types=1);

use Denosys\Http\Exceptions\ExceptionHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

final class ExceptionHandlerTest extends TestCase
{
    /** @return iterable<string, array{array<string, string>, class-string}> */
    public static function requestHeaders(): iterable
    {
        yield 'XHR' => [['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'], JsonResponseHandler::class];
        yield 'JSON' => [['HTTP_ACCEPT' => 'application/json'], JsonResponseHandler::class];
        yield 'HTML' => [['HTTP_ACCEPT' => 'text/html'], PrettyPageHandler::class];
    }

    /** @param array<string, string> $headers */
    #[DataProvider('requestHeaders')]
    public function testWhoopsRegistersAHandlerForTheRequest(array $headers, string $expectedHandler): void
    {
        $previous = $_SERVER;
        $_SERVER = array_diff_key($_SERVER, array_flip(['HTTP_X_REQUESTED_WITH', 'HTTP_ACCEPT', 'REQUEST_URI']));
        $_SERVER = array_merge($_SERVER, $headers);

        $exceptionHandler = new ExceptionHandler(__DIR__, debug: true, environment: 'local');
        $whoops = null;

        try {
            (new ReflectionMethod(ExceptionHandler::class, 'setupWhoops'))->invoke($exceptionHandler);
            $whoops = (new ReflectionProperty(ExceptionHandler::class, 'whoops'))->getValue($exceptionHandler);

            self::assertInstanceOf(Run::class, $whoops);
            self::assertCount(1, $whoops->getHandlers());
            self::assertInstanceOf($expectedHandler, $whoops->getHandlers()[0]);
        } finally {
            $whoops?->unregister();
            $_SERVER = $previous;
        }
    }
}
