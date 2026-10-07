<?php

declare(strict_types=1);

namespace Denosys\Http\Tests;

use Denosys\Http\Internal\SecurityHeaders;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class SecurityHeadersTest extends TestCase
{
    public function testAddsDefaultsToHttpResponseWithoutHsts(): void
    {
        $response = $this->dispatch('http://example.test/');

        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        self::assertSame('1; mode=block', $response->getHeaderLine('X-XSS-Protection'));
        self::assertFalse($response->hasHeader('Strict-Transport-Security'));
    }

    public function testAddsHstsToHttpsResponse(): void
    {
        $response = $this->dispatch('https://example.test/');

        self::assertSame(
            'max-age=31536000; includeSubDomains',
            $response->getHeaderLine('Strict-Transport-Security')
        );
    }

    public function testPreservesExplicitResponsePolicy(): void
    {
        $response = $this->dispatch(
            'https://example.test/',
            new HtmlResponse('ok', 200, [
                'X-Frame-Options' => 'SAMEORIGIN',
                'Strict-Transport-Security' => 'max-age=0',
            ])
        );

        self::assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
        self::assertSame('max-age=0', $response->getHeaderLine('Strict-Transport-Security'));
    }

    private function dispatch(string $uri, ?ResponseInterface $response = null): ResponseInterface
    {
        self::assertTrue(class_exists(SecurityHeaders::class));

        $policyClass = SecurityHeaders::class;
        $policy = new $policyClass();

        return $policy->apply(
            new ServerRequest([], [], $uri, 'GET'),
            $response ?? new HtmlResponse('ok')
        );
    }
}
