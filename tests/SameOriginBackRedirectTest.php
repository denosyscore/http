<?php

declare(strict_types=1);

use Denosys\Http\ResponseFactory;
use Denosys\Http\SameOriginUrl;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SameOriginBackRedirectTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function referers(): iterable
    {
        yield 'other host' => ['https://evil.example/phish', '/fallback'];
        yield 'protocol-relative' => ['//evil.example/phish', '/fallback'];
        yield 'backslash host' => ['/\\evil.example', '/fallback'];
        yield 'other scheme' => ['http://app.test/account', '/fallback'];
        yield 'other port' => ['https://app.test:8443/account', '/fallback'];
        yield 'credentials' => ['https://user@app.test/account', '/fallback'];
        yield 'javascript' => ['javascript:alert(1)', '/fallback'];
        yield 'same origin absolute' => ['https://app.test/account?tab=1', 'https://app.test/account?tab=1'];
        yield 'same origin path' => ['/account', '/account'];
        yield 'missing' => ['', '/fallback'];
    }

    #[DataProvider('referers')]
    public function testBackOnlyRedirectsToTheSameOrigin(string $referer, string $expected): void
    {
        $request = new ServerRequest([], [], 'https://app.test/form', 'POST');
        if ($referer !== '') {
            $request = $request->withHeader('Referer', $referer);
        }

        $response = (new ResponseFactory())->back($request, '/fallback');

        self::assertSame($expected, $response->getHeaderLine('Location'));
    }

    public function testSameOriginUrlCanBeUsedOnItsOwn(): void
    {
        $request = new ServerRequest([], [], 'https://app.test/form', 'POST');

        self::assertSame('/home', SameOriginUrl::resolve('https://evil.example', $request, '/home'));
        self::assertSame('/orders', SameOriginUrl::resolve('/orders', $request, '/home'));
    }
}
