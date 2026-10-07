<?php

declare(strict_types=1);

namespace Denosys\Http\Middleware;

use JsonException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class JsonBodyParserMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $mediaType = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'), 2)[0]));

        $isJson = $mediaType === 'application/json'
            || (str_starts_with($mediaType, 'application/') && str_ends_with($mediaType, '+json'));

        if (!$isJson || !in_array($request->getParsedBody(), [null, []], true)) {
            return $handler->handle($request);
        }

        $stream = $request->getBody();
        $position = null;

        try {
            if ($stream->isSeekable()) {
                $position = $stream->tell();
                $stream->rewind();
            }

            $body = $stream->getContents();
        } catch (RuntimeException) {
            return new JsonResponse(['error' => 'Unable to read request body.'], 500);
        } finally {
            if ($position !== null) {
                $stream->seek($position);
            }
        }

        if ($body === '') {
            return $handler->handle($request);
        }

        try {
            $parsed = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON request body.'], 400);
        }

        if (!is_array($parsed)) {
            return new JsonResponse(['error' => 'Invalid JSON request body.'], 400);
        }

        return $handler->handle($request->withParsedBody($parsed));
    }
}
