<?php

declare(strict_types=1);

namespace CFXP\Core\Exceptions;

use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * Suggestion engine that analyzes container resolution failures and provides
 * actionable suggestions for resolving common issues.
 */
class SuggestionEngine
{
    /**
     * Generate suggestions for a resolution failure.
     *
     * @param string $abstract The abstract identifier that failed to resolve
     * @param Throwable|null $exception The exception that occurred during resolution
     * @param array<string> $resolutionStack The current resolution stack
     * @param array<string, mixed> $containerState Additional container state information
     * @return array<string> Array of suggestions
     */
    public static function generateSuggestions(
        string $abstract,
        ?Throwable $exception = null,
        array $resolutionStack = [],
        array $containerState = []
    ): array {
        $suggestions = [];

        // Analyze the abstract identifier
        $suggestions = array_merge($suggestions, self::analyzeAbstract($abstract));

        // Analyze the exception
        if ($exception !== null) {
            $suggestions = array_merge($suggestions, self::analyzeException($exception, $abstract));
        }

        // Analyze resolution context
        $suggestions = array_merge($suggestions, self::analyzeResolutionContext($abstract, $resolutionStack));

        // Analyze container state
        $suggestions = array_merge($suggestions, self::analyzeContainerState($abstract, $containerState));

        // Remove duplicates and return
        return array_unique($suggestions);
    }

    /**
     * Analyze the abstract identifier and provide suggestions.
     *
     * @param string $abstract The abstract identifier
     * @return array<string>
     */
    /**
     * @return array<string, mixed>
     */
private static function analyzeAbstract(string $abstract): array
    {
        $suggestions = [];

        // Check if it's a class that exists
        if (class_exists($abstract)) {
            try {
                $reflection = new ReflectionClass($abstract);
                
                if ($reflection->isAbstract()) {
                    $suggestions[] = "'{$abstract}' is an abstract class. Bind it to a concrete implementation.";
                }
                
                if ($reflection->isInterface()) {
                    $suggestions[] = "'{$abstract}' is an interface. Bind it to a concrete implementation.";
                }
                
                if (!$reflection->isInstantiable()) {
                    $suggestions[] = "'{$abstract}' is not instantiable. Check if it's abstract or has a private constructor.";
                }

                // Check constructor dependencies
                $constructor = $reflection->getConstructor();
                if ($constructor !== null) {
                    $parameters = $constructor->getParameters();
                    $untypedParams = array_filter($parameters, fn($param) => $param->getType() === null);
                    
                    if (!empty($untypedParams)) {
                        $paramNames = array_map(fn($param) => '$' . $param->getName(), $untypedParams);
                        $suggestions[] = "'{$abstract}' has untyped constructor parameters: " . implode(', ', $paramNames) . 
                                       ". Add type hints or default values.";
                    }
                }
                
            } catch (ReflectionException) {
                // Ignore reflection errors
            }
        } elseif (interface_exists($abstract)) {
            $suggestions[] = "'{$abstract}' is an interface. Bind it to a concrete implementation using bind() or singleton().";
        } else {
            $suggestions[] = "'{$abstract}' does not exist. Check the class name and ensure it's autoloaded.";
            
            // Suggest similar class names
            $similarClasses = self::findSimilarClasses($abstract);
            if (!empty($similarClasses)) {
                $suggestions[] = "Did you mean one of these? " . implode(', ', $similarClasses);
            }
        }

        return $suggestions;
    }

    /**
     * Analyze the exception and provide suggestions.
     *
     * @param Throwable $exception The exception that occurred
     * @param string $abstract The abstract being resolved
     * @return array<string>
     */
    private static function analyzeException(Throwable $exception, string $abstract): array
    {
        $suggestions = [];
        $message = $exception->getMessage();

        // Analyze common exception patterns
        if (str_contains($message, 'not found') || str_contains($message, 'does not exist')) {
            $suggestions[] = "Register '{$abstract}' in the container using bind(), singleton(), or instance().";
        }

        if (str_contains($message, 'circular dependency')) {
            $suggestions[] = "Break the circular dependency by using lazy loading or setter injection.";
            $suggestions[] = "Consider introducing an interface to break the circular reference.";
        }

        if (str_contains($message, 'not instantiable')) {
            $suggestions[] = "Ensure '{$abstract}' is a concrete class with a public constructor.";
        }

        if (str_contains($message, 'untyped parameter')) {
            $suggestions[] = "Add type hints to constructor parameters or provide default values.";
        }

        if (str_contains($message, 'Cannot resolve parameter')) {
            $suggestions[] = "Ensure all constructor dependencies are registered in the container.";
            $suggestions[] = "Consider making optional parameters have default values.";
        }

        return $suggestions;
    }

    /**
     * Analyze the resolution context and provide suggestions.
     *
     * @param string $abstract The abstract being resolved
     * @param array<string> $resolutionStack The current resolution stack
     * @return array<string>
     */
    private static function analyzeResolutionContext(string $abstract, array $resolutionStack): array
    {
        $suggestions = [];

        if (count($resolutionStack) > 10) {
            $suggestions[] = "Deep dependency chain detected. Consider simplifying your dependency structure.";
        }

        if (in_array($abstract, $resolutionStack, true)) {
            $suggestions[] = "Circular dependency detected in resolution stack. Use lazy loading or break the cycle.";
        }

        // Check for common dependency patterns
        $stackString = implode(' -> ', $resolutionStack);
        if (str_contains($stackString, 'Repository') && str_contains($stackString, 'Service')) {
            $suggestions[] = "Consider using dependency injection patterns like Repository and Service layers properly.";
        }

        return $suggestions;
    }

    /**
     * Analyze container state and provide suggestions.
     *
     * @param string $abstract The abstract being resolved
     * @param array<string, mixed> $containerState Container state information
     * @return array<string>
     */
    private static function analyzeContainerState(string $abstract, array $containerState): array
    {
        $suggestions = [];

        // Check if similar bindings exist
        $bindings = $containerState['bindings'] ?? [];
        $similarBindings = array_filter(
            array_keys($bindings),
            fn($binding) => self::calculateSimilarity($abstract, $binding) > 0.7
        );

        if (!empty($similarBindings)) {
            $suggestions[] = "Similar bindings found: " . implode(', ', $similarBindings) . 
                           ". Check if you meant one of these.";
        }

        // Check aliases
        $aliases = $containerState['aliases'] ?? [];
        if (isset($aliases[$abstract])) {
            $suggestions[] = "'{$abstract}' is an alias for '{$aliases[$abstract]}'. The target may not be bound.";
        }

        // Check instances
        $instances = $containerState['instances'] ?? [];
        if (isset($instances[$abstract])) {
            $suggestions[] = "'{$abstract}' is registered as an instance but may have been corrupted or cleared.";
        }

        return $suggestions;
    }

    /**
     * Find classes with similar names to the given abstract.
     *
     * @param string $abstract The abstract identifier
     * @return array<string>
     */
    private static function findSimilarClasses(string $abstract): array
    {
        $similarClasses = [];
        $declaredClasses = get_declared_classes();
        
        foreach ($declaredClasses as $class) {
            if (self::calculateSimilarity($abstract, $class) > 0.8) {
                $similarClasses[] = $class;
            }
        }

        return array_slice($similarClasses, 0, 3); // Limit to 3 suggestions
    }

    /**
     * Calculate similarity between two strings using Levenshtein distance.
     *
     * @param string $str1 First string
     * @param string $str2 Second string
     * @return float Similarity score between 0 and 1
     */
    private static function calculateSimilarity(string $str1, string $str2): float
    {
        $maxLength = max(strlen($str1), strlen($str2));
        if ($maxLength === 0) {
            return 1.0;
        }

        $distance = levenshtein(strtolower($str1), strtolower($str2));
        return 1.0 - ($distance / $maxLength);
    }

    /**
     * Generate suggestions for specific error types.
     *
     * @param string $errorType The type of error
     * @param array<string, mixed> $context Additional context
     * @return array<string>
     */
    public static function getSuggestionsForErrorType(string $errorType, array $context = []): array
    {
        return match ($errorType) {
            'not_found' => [
                'Register the service using bind(), singleton(), or instance()',
                'Check if the class name is correct and the file is autoloaded',
                'Verify that the namespace is correct'
            ],
            'circular_dependency' => [
                'Use lazy loading for one of the dependencies',
                'Break the circular dependency by introducing an interface',
                'Use setter injection instead of constructor injection',
                'Consider if the circular dependency indicates a design issue'
            ],
            'not_instantiable' => [
                'Ensure the class is concrete (not abstract or interface)',
                'Check that the constructor is public',
                'Verify that all required dependencies are available'
            ],
            'parameter_resolution' => [
                'Add type hints to constructor parameters',
                'Provide default values for optional parameters',
                'Register all dependencies in the container',
                'Consider using primitive values with explicit bindings'
            ],
            'binding_conflict' => [
                'Check for duplicate bindings',
                'Use extend() to modify existing bindings',
                'Ensure binding types are compatible'
            ],
            default => [
                'Check the container configuration',
                'Verify all dependencies are properly registered',
                'Review the error message for specific details'
            ]
        };
    }
}