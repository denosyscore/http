<?php

declare(strict_types=1);

namespace Denosys\Http\Tests;

use Denosys\Http\Exceptions\ExceptionHandlerInterface;
use Denosys\Http\Exceptions\ValidationExceptionHandler;
use Denosys\Http\ResponseFactory;
use Denosys\Validation\ValidationException;
use Denosys\Validation\Validator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ValidationExceptionHandlerTest extends TestCase
{
    public function testJsonResponseAndLegacyEntryPoint(): void
    {
        $legacy = 'Denosys\\Validation\\Handlers\\ValidationExceptionHandler';
        self::assertTrue(class_exists($legacy));
        $handler = new $legacy(new ResponseFactory());

        self::assertInstanceOf(ValidationExceptionHandler::class, $handler);
        self::assertInstanceOf(ExceptionHandlerInterface::class, $handler);
        self::assertSame(110, $handler->getPriority());
        self::assertFalse($handler->canHandle(new RuntimeException('unrelated')));

        $previous = $_SERVER['HTTP_ACCEPT'] ?? null;
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        try {
            $validator = Validator::make([], []);
            $validator->errors()->add('email', 'Invalid address');
            $exception = new ValidationException($validator);
            self::assertTrue($handler->canHandle($exception));
            $response = $handler->handle($exception);
            self::assertSame(422, $response->getStatusCode());
            self::assertSame(['email' => ['Invalid address']], json_decode((string) $response->getBody(), true)['errors']);
        } finally {
            if ($previous === null) {
                unset($_SERVER['HTTP_ACCEPT']);
            } else {
                $_SERVER['HTTP_ACCEPT'] = $previous;
            }
        }
    }

    public function testHtmlResponseEscapesValidationMessages(): void
    {
        $previous = $_SERVER['HTTP_ACCEPT'] ?? null;
        unset($_SERVER['HTTP_ACCEPT']);
        try {
            $validator = Validator::make([], []);
            $validator->errors()->add('name', '<script>alert(1)</script>');
            $response = (new ValidationExceptionHandler(new ResponseFactory()))->handle(new ValidationException($validator));
            self::assertSame(422, $response->getStatusCode());
            self::assertStringContainsString('&lt;script&gt;', (string) $response->getBody());
            self::assertStringNotContainsString('<script>', (string) $response->getBody());
        } finally {
            if ($previous !== null) {
                $_SERVER['HTTP_ACCEPT'] = $previous;
            }
        }
    }
}
