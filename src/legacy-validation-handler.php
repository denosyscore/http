<?php

declare(strict_types=1);

// Keep the former validation namespace available when HTTP is installed.
$legacyHandler = 'Denosys\\Validation\\Handlers\\ValidationExceptionHandler';
if (!class_exists($legacyHandler, false)) {
    class_alias(\Denosys\Http\Exceptions\ValidationExceptionHandler::class, $legacyHandler);
}
