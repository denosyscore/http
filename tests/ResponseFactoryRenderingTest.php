<?php

declare(strict_types=1);

use Denosys\Contracts\View\TemplateRenderer;
use Denosys\Http\ResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResponseFactoryRenderingTest extends TestCase
{
    /** @return iterable<string, array{string, int}> */
    public static function errorPages(): iterable
    {
        yield 'not found' => ['notFound', 404];
        yield 'forbidden' => ['forbidden', 403];
        yield 'server error' => ['serverError', 500];
        yield 'unauthorized' => ['unauthorized', 401];
    }

    /** Fallback error pages must not render the message as markup. */
    #[DataProvider('errorPages')]
    public function testFallbackErrorPagesEscapeTheMessage(string $method, int $status): void
    {
        $response = (new ResponseFactory())->{$method}('<script>alert(1)</script>');

        self::assertSame($status, $response->getStatusCode());
        self::assertStringNotContainsString('<script>', (string) $response->getBody());
        self::assertStringContainsString('&lt;script&gt;', (string) $response->getBody());
    }

    public function testViewResponsesUseAnyTemplateRenderer(): void
    {
        $renderer = new class implements TemplateRenderer {
            public function render(string $template, array $data = []): string
            {
                return "{$template}:{$data['name']}";
            }
        };

        $response = (new ResponseFactory($renderer))->view('welcome', ['name' => 'Ada'], 201);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('welcome:Ada', (string) $response->getBody());
    }
}
