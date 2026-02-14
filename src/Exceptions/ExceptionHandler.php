<?php

declare(strict_types=1);

namespace Denosys\Http\Exceptions;

use Throwable;
use Psr\Log\LoggerInterface;
use Whoops\Run as Whoops;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\JsonResponseHandler;
use ErrorException;
use Whoops\Run;

class ExceptionHandler
{
    private ?Whoops $whoops = null;

    private ?PrettyPageHandler $handler = null;

    public function __construct(
        private readonly string $basePath,
        private ?LoggerInterface $logger = null,
        private readonly bool $debug = false,
        private readonly string $environment = 'production'
    ) {}

    public function register(): void
    {
        if ($this->shouldUseWhoops()) {
            $this->setupWhoops();
        }

        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
    }

    /**
     * @throws ErrorException
     */
    public function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if ($this->isDeprecation($level)) {
            $this->safeLog('warning', (string) new ErrorException($message, 0, $level, $file, $line));
            return true;
        } elseif (error_reporting() & $level) {
            throw new ErrorException($message, 0, $level, $file, $line);
        }
        return false;
    }

    /**
     * @return never
     */
    public function handleException(Throwable $exception): void
    {
        static $handling = false;

        if ($handling) {
            error_log('Recursive exception in handler: ' . $exception->getMessage());
            exit(1);
        }

        $handling = true;

        $this->cleanOutputBuffers();

        $this->safeLog('error', $exception->getMessage(), [
            'exception' => $exception,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);

        if ($this->shouldUseWhoops() && $this->whoops) {
            $this->handler->setPageTitle($exception->getMessage());
            $this->whoops->handleException($exception);
        } elseif ($this->debug) {
            $this->renderDebugException($exception);
        } else {
            $this->renderProductionError();
        }

        $handling = false;

        exit(1);
    }

    private function isDeprecation(int $level): bool
    {
        return in_array($level, [E_DEPRECATED, E_USER_DEPRECATED]);
    }

    private function shouldUseWhoops(): bool
    {
        return $this->debug &&
            in_array($this->environment, ['local', 'testing']) &&
            class_exists(Whoops::class) &&
            php_sapi_name() !== 'cli';
    }

    private function setupWhoops(): void
    {
        if ($this->isAjaxRequest() || $this->isApiRequest()) {
            $handler = new JsonResponseHandler();
            $handler->addTraceToOutput(true);
        } else {
            $this->handler = new PrettyPageHandler();
            $this->handler->setEditor(PrettyPageHandler::EDITOR_VSCODE);

            $this->handler->addDataTable('Denosys', [
                'Version' => '1.0.0', // TODO: Replace with actual version
            ]);

            $this->handler->setApplicationPaths([$this->basePath]);
        }

        $this->whoops = new Run();
        $this->whoops->pushHandler($this->handler);
        $this->whoops->register();
    }

    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    private function isApiRequest(): bool
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';

        return str_contains($requestUri, '/api/') ||
            str_contains($acceptHeader, 'application/json');
    }

    private function cleanOutputBuffers(): void
    {
        // Suppress any errors during output buffer cleanup
        while (@ob_get_level()) {
            @ob_end_clean();
        }
    }

    private function renderDebugException(Throwable $exception): void
    {
        http_response_code(500);

        echo '<html lang="en"><head><title>Denosys Application Error</title>';
        echo '<style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { background: #dc3545; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
            .content { padding: 20px; }
            .exception-info { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0; }
            .trace { background: #343a40; color: #fff; padding: 15px; border-radius: 4px; overflow-x: auto; }
            pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; }
        </style></head><body>';

        echo '<div class="container">';
        echo '<div class="header"><h1>Application Error</h1></div>';
        echo '<div class="content">';
        echo '<div class="exception-info">';
        echo '<h3>' . get_class($exception) . '</h3>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars($exception->getMessage()) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($exception->getFile()) . ' (line ' . $exception->getLine() . ')</p>';
        echo '</div>';
        echo '<h4>Stack Trace:</h4>';
        echo '<div class="trace"><pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre></div>';
        echo '</div></div>';
        echo '</body></html>';
    }

    private function renderProductionError(): void
    {
        http_response_code(500);

        echo '<html lang="en"><head><title>Server Error</title>';
        echo '<style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; text-align: center; }
            .container { max-width: 600px; margin: 100px auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #dc3545; margin-bottom: 20px; }
            p { color: #6c757d; line-height: 1.6; }
        </style></head><body>';

        echo '<div class="container">';
        echo '<h1>Server Error</h1>';
        echo '<p>We apologize for the inconvenience. An unexpected error has occurred.</p>';
        echo '<p>Please try again later. If the problem persists, please contact our support team.</p>';
        echo '</div>';
        echo '</body></html>';
    }

    /**
     * Set the logger instance (used for late binding)
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Get the logger instance
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Log a message if logger is available, otherwise use error_log
      * @param array<string, mixed> $context
     */
    private function safeLog(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        } else {
            $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
            error_log("[{$level}] {$message}{$contextStr}");
        }
    }
}
