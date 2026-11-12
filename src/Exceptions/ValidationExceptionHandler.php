<?php

declare(strict_types=1);

namespace Denosys\Http\Exceptions;

use Denosys\Http\ResponseFactory;
use Denosys\Validation\ValidationException;
use Throwable;
use Psr\Http\Message\ResponseInterface;

/**
 * Handle validation exceptions.
 * 
 * Converts validation exceptions into JSON or HTML HTTP responses.
 */
class ValidationExceptionHandler implements ExceptionHandlerInterface
{
    use ExceptionHandlerTrait;

    /**
     * @param bool $debug Include the exception class and location in JSON errors.
     *                    The host passes its configured debug flag.
     */
    public function __construct(
        private readonly ResponseFactory $responseFactory,
        private readonly bool $debug = false,
    ) {}

    public function canHandle(Throwable $exception): bool
    {
        return $exception instanceof ValidationException;
    }

    public function getPriority(): int
    {
        return 110;
    }

    public function handle(Throwable $exception): ResponseInterface
    {
        /** @var ValidationException $exception */
        if ($this->isApiRequest()) {
            return $this->createJsonResponse($exception);
        }

        return $this->createWebResponse($exception);
    }

    /**
     * Create JSON response for API requests.
     */
    private function createJsonResponse(ValidationException $exception): ResponseInterface
    {
        $data = [
            'error' => true,
            'message' => 'The given data was invalid.',
            'errors' => $this->errors($exception)
        ];

        if ($this->debug) {
            $data['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ];
        }

        return $this->responseFactory->json($data, 422);
    }

    /**
     * Create web response with error display.
     */
    private function createWebResponse(ValidationException $exception): ResponseInterface
    {
        $errors = $this->errors($exception);
        $errorHtml = $this->renderErrorsHtml($errors);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation Error</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: #dc3545;
            color: white;
            padding: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 20px;
        }
        .error-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .error-item {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 4px;
            border-left: 4px solid #dc3545;
        }
        .field-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .back-link:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Validation Error</h1>
        </div>
        <div class="content">
            <p>Please correct the following errors:</p>
            {$errorHtml}
            <a href="javascript:history.back()" class="back-link">← Go Back</a>
        </div>
    </div>
</body>
</html>
HTML;

        return $this->responseFactory->html($html, 422);
    }

    /**
     * Render errors as HTML.
      * @param array<string, array<string>> $errors
     */
    private function renderErrorsHtml(array $errors): string
    {
        $html = '<ul class="error-list">';

        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $html .= '<li class="error-item">';
                $html .= '<div class="field-name">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $field))) . '</div>';
                $html .= htmlspecialchars($message);
                $html .= '</li>';
            }
        }

        $html .= '</ul>';
        return $html;
    }

    /** @return array<string, list<string>> */
    private function errors(ValidationException $exception): array
    {
        $errors = $exception->getErrors();
        if ($errors === []) {
            return [];
        }

        // Validation 0.2 exposes a flat message list; 0.3 retains field keys.
        return array_is_list($errors) ? ['general' => $errors] : $errors;
    }
}
