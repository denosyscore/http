<?php

declare(strict_types=1);

namespace Denosys\Http\Middleware;

use Closure;
use Denosys\Http\RedirectResponse;
use Denosys\Http\SameOriginUrl;
use Denosys\Http\Traits\ResolvesReferer;
use Denosys\Session\SessionInterface;
use Denosys\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ValidationExceptionMiddleware implements MiddlewareInterface
{
    use ResolvesReferer;

    /** @var Closure(ServerRequestInterface): int|null */
    private readonly ?Closure $redirectStatusResolver;
    
    private const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'secret',
        'api_key',
        'credit_card',
        'card_number',
        'cvv',
        'cvc',
        'ssn',
    ];

    public function __construct(
        private readonly SessionInterface $session,
        ?callable $redirectStatusResolver = null,
    ) {
        $this->redirectStatusResolver = $redirectStatusResolver === null
            ? null
            : Closure::fromCallable($redirectStatusResolver);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ValidationException $e) {
            return $this->handleValidationException($request, $e);
        }
    }

    /**
     * Handle a validation exception by flashing errors and redirecting back.
     */
    private function handleValidationException(
        ServerRequestInterface $request, 
        ValidationException $e
    ): ResponseInterface {
        // Flash validation errors in proper ErrorBag format ['field' => ['messages']]
        $this->session->flash('errors', $e->validator->errors()->toArray());
        
        // Flash old input (excluding sensitive fields)
        $oldInput = $this->filterSensitiveFields($request->getParsedBody() ?? []);
        $this->session->flash('_old_input', $oldInput);
        
        // Flash the first error message for easy display
        $firstError = $e->getFirstError();
        if ($firstError) {
            $this->session->flash('error', $firstError);
        }
        
        // Get referrer URL for redirect back (using trait method)
        $referer = SameOriginUrl::resolve(
            $this->getRefererUrl($request, $this->session),
            $request,
        );
        
        $status = $this->redirectStatusResolver === null
            ? 302
            : ($this->redirectStatusResolver)($request);

        return $this->createRedirectResponse($referer, $status);
    }

    /**
     * Filter out sensitive fields from old input.
     */
    /**
     * @return array<string, mixed>
      * @param array<string, mixed> $data
     */
private function filterSensitiveFields(array $data): array
    {
        $filtered = [];
        
        foreach ($data as $key => $value) {
            // Skip sensitive fields
            if ($this->isSensitiveField($key)) {
                continue;
            }
            
            // Recursively filter nested arrays
            if (is_array($value)) {
                $filtered[$key] = $this->filterSensitiveFields($value);
            } else {
                $filtered[$key] = $value;
            }
        }
        
        return $filtered;
    }

    /**
     * Check if a field name is sensitive.
     */
    private function isSensitiveField(string $field): bool
    {
        $lowerField = strtolower($field);
        
        foreach (self::SENSITIVE_FIELDS as $sensitiveField) {
            if (str_contains($lowerField, $sensitiveField)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Create a redirect response.
     */
    private function createRedirectResponse(string $url, int $status): ResponseInterface
    {
        return new RedirectResponse($url, $status);
    }
}
