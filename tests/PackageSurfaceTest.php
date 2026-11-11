<?php

declare(strict_types=1);

use Denosys\Http\Middleware\VerifyCsrfToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PackageSurfaceTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function removedClasses(): iterable
    {
        foreach ([
            'BindingConflictException',
            'CircularDependencyException',
            'ContainerException',
            'ContainerExceptionFactory',
            'ContainerResolutionException',
            'DatabaseException',
            'HandlerException',
            'ItemNotFoundException',
            'NoConfigFileSampleException',
            'NotFoundException',
            'WrongValueException',
        ] as $class) {
            yield $class => ['Denosys\\Http\\Exceptions\\' . $class];
        }

        yield 'ScreamBooting' => ['Denosys\\Http\\Listeners\\ScreamBooting'];
    }

    /** Container errors come from the container package; http keeps no copies or debugging leftovers. */
    #[DataProvider('removedClasses')]
    public function testCopiedAndDebuggingClassesAreGone(string $class): void
    {
        self::assertFalse(class_exists($class));
    }

    public function testCsrfMiddlewareHasNoUnusedRefererResolver(): void
    {
        self::assertFalse(method_exists(VerifyCsrfToken::class, 'getRefererUrl'));
    }

    public function testErrorPageReportsNoPlaceholderVersion(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/src/Exceptions/ExceptionHandler.php');

        self::assertStringNotContainsString("'1.0.0'", $source);
    }
}
