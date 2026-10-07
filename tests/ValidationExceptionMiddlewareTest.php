<?php

declare(strict_types=1);

use Denosys\Http\Middleware\ValidationExceptionMiddleware;
use Denosys\Session\SessionInterface;
use Denosys\Validation\ValidationException;
use Denosys\Validation\Validator;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

if (!interface_exists(SessionInterface::class)) {
    require __DIR__ . '/Fixtures/SessionInterface.php';
}

final class ValidationExceptionMiddlewareTest extends TestCase
{
    public function testInertiaMutationRejectsExternalRefererAndFlashesReadableOldInput(): void
    {
        $flashes = [];
        $session = $this->createMock(SessionInterface::class);
        $session->method('flash')->willReturnCallback(
            static function (string $key, mixed $value) use (&$flashes): void {
                $flashes[$key] = $value;
            },
        );
        $session->method('previousUrl')->willReturn(null);

        $request = (new ServerRequest([], [], 'https://example.test/submit', 'POST'))
            ->withHeader('Referer', 'https://other.test/steal')
            ->withHeader('X-Inertia', 'true')
            ->withParsedBody(['email' => 'invalid', 'password' => 'secret']);

        $response = new ValidationExceptionMiddleware($session)->process($request, $this->failingHandler());

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/', $response->getHeaderLine('Location'));
        self::assertSame(['email' => 'invalid'], $flashes['_old_input'] ?? null);
        self::assertArrayNotHasKey('old', $flashes);
        self::assertSame(['email' => ['A valid email is required.']], $flashes['errors'] ?? null);
    }

    public function testLocalRefererRetainsOrdinaryRedirectStatus(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $request = (new ServerRequest([], [], 'https://example.test/submit', 'POST'))
            ->withHeader('Referer', 'https://example.test/form?step=2');

        $response = new ValidationExceptionMiddleware($session)->process($request, $this->failingHandler());

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://example.test/form?step=2', $response->getHeaderLine('Location'));
    }

    public function testExternalSessionFallbackIsRejected(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('previousUrl')->willReturn('//other.test/steal');

        $response = new ValidationExceptionMiddleware($session)->process(
            new ServerRequest([], [], 'https://example.test/submit', 'POST'),
            $this->failingHandler(),
        );

        self::assertSame('/', $response->getHeaderLine('Location'));
    }

    public function testOnlySameOriginOrLocalReturnUrlsAreAccepted(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $middleware = new ValidationExceptionMiddleware($session);

        foreach ([
            '/form?step=2' => '/form?step=2',
            'https://example.test:443/form' => 'https://example.test:443/form',
            '//other.test/form' => '/',
            'https://example.test.other.test/form' => '/',
            'https://person@example.test/form' => '/',
            'http://example.test/form' => '/',
            '/\\other.test/form' => '/',
        ] as $referer => $expected) {
            $request = (new ServerRequest([], [], 'https://example.test/submit', 'POST'))
                ->withHeader('Referer', $referer);

            $response = $middleware->process($request, $this->failingHandler());

            self::assertSame($expected, $response->getHeaderLine('Location'), $referer);
        }
    }

    private function failingHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $validator = Validator::make([], []);
                $validator->validate();
                $validator->errors()->add('email', 'A valid email is required.');

                throw new ValidationException($validator);
            }
        };
    }
}
