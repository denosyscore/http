<?php

declare(strict_types=1);

namespace Denosys\Http\Internal;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** @internal Kernel-owned response policy, not a consumer extension point. */
final class SecurityHeaders
{
    public function apply(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
        ];

        if ($request->getUri()->getScheme() === 'https') {
            $defaults['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($defaults as $name => $value) {
            if (!$response->hasHeader($name)) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}
