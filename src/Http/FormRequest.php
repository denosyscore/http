<?php

declare(strict_types=1);

namespace Denosys\Http;

use Denosys\Validation\Validator;
use Denosys\Validation\ValidationException;
use Denosys\Http\Exceptions\AuthorizationException;
use Denosys\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Base class for form requests with automatic validation.
 * 
 * Uses composition with Request rather than inheritance for cleaner architecture.
 * FormRequest's primary purpose is validation, not being a Request.
 */
abstract class FormRequest
{
    protected ContainerInterface $container;
    protected Validator $validator;
    protected Request $request;
    private bool $validated = false;

    /** @var array<string, mixed> */
    private array $validatedData = [];

    /**
     * Create a new form request instance.
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get validation rules.
     *
     * @return array<string, string|array<string|object>>
     */
    abstract public function rules(): array;

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Set the container instance.
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * Get the underlying Request instance.
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Validate the request.
     *
     * @throws ValidationException
     */
    public function validate(): void
    {
        if ($this->validated) {
            return;
        }

        // Check authorization first
        if (!$this->authorize()) {
            throw new AuthorizationException();
        }

        // Get data to validate
        $data = $this->all();

        // Create validator
        $this->validator = new Validator($data, $this->rules(), $this->messages());

        // Run validation
        if ($this->validator->fails()) {
            $this->failedValidation($this->validator);
        }

        $this->validatedData = $this->validator->validated();
        $this->validated = true;
    }

    /**
     * Handle failed validation.
     *
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator);
    }

    /**
     * Get validated data.
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        if (!$this->validated) {
            $this->validate();
        }

        return $this->validatedData;
    }

    /**
     * Get a validated input value.
     */
    public function validatedInput(string $key, mixed $default = null): mixed
    {
        $validated = $this->validated();
        return $validated[$key] ?? $default;
    }

    /**
     * Get input value from request body or query parameters.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request->input($key, $default);
    }

    /**
     * Get all input data (body + query combined).
      * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->request->all();
    }

    /**
     * Get only specified keys from input.
      * @param array<string> $keys
      * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        return $this->request->only($keys);
    }

    /**
     * Get all input except specified keys.
      * @param array<string> $keys
      * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        return $this->request->except($keys);
    }

    /**
     * Check if input has a specific key.
     */
    public function has(string $key): bool
    {
        return $this->request->has($key);
    }

    /**
     * Get route parameter value.
     */
    public function route(string $param, mixed $default = null): mixed
    {
        return $this->request->route($param, $default);
    }

    /**
     * Get the authenticated user.
     */
    public function user(): mixed
    {
        return $this->request->user();
    }

    /**
     * Get the session instance.
     */
    public function session(): \Denosys\Session\SessionInterface
    {
        return $this->request->session();
    }

    /**
     * Get uploaded file.
     */
    public function file(string $key): ?object
    {
        return $this->request->file($key);
    }

    /**
     * Check if request has file upload.
     */
    public function hasFile(string $key): bool
    {
        return $this->request->hasFile($key);
    }

    /**
     * Check if request expects JSON response.
     */
    public function expectsJson(): bool
    {
        return $this->request->expectsJson();
    }

    /**
     * Get request path.
     */
    public function path(): string
    {
        return $this->request->path();
    }

    /**
     * Get request URL.
     */
    public function url(): string
    {
        return $this->request->url();
    }

    /**
     * Get request method.
     */
    public function method(): string
    {
        return $this->request->getMethod();
    }

    /**
     * Get client IP address.
     */
    public function ip(): string
    {
        return $this->request->ip();
    }

    /**
     * Create from PSR-7 request.
     * 
     * @param ServerRequestInterface $psrRequest The PSR-7 request
     * @param ContainerInterface|null $container Optional container for dependency injection
     */
    public static function createFromPsr7(
        ServerRequestInterface $psrRequest,
        ?ContainerInterface $container = null
    ): static {
        $request = Request::createFromPsr7($psrRequest);
        /** @phpstan-ignore-next-line - Abstract factory pattern, child classes must use same constructor signature */
        $formRequest = new static($request);

        if ($container !== null) {
            $formRequest->setContainer($container);
        }

        return $formRequest;
    }

    /**
     * Create from an existing Request instance.
     * 
     * @param Request $request The request instance
     * @param ContainerInterface|null $container Optional container for dependency injection
     */
    public static function createFromRequest(
        Request $request,
        ?ContainerInterface $container = null
    ): static {
        /** @phpstan-ignore-next-line - Abstract factory pattern, child classes must use same constructor signature */
        $formRequest = new static($request);

        if ($container !== null) {
            $formRequest->setContainer($container);
        }

        return $formRequest;
    }

    /**
     * Magic method to get validated data as properties.
     */
    public function __get(string $name): mixed
    {
        return $this->validatedInput($name);
    }
}
