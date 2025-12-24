<?php

declare(strict_types=1);

namespace App\Helpers;

use Throwable;

final class ErrorHelper
{
    /**
     * Get a safe error message for API responses.
     * Only exposes details in debug mode.
     */
    public static function getSafeMessage(Throwable $e, ?string $fallback = null): string
    {
        if (config('app.debug')) {
            return $e->getMessage();
        }

        return $fallback ?? __('messages.errors.generic');
    }

    /**
     * Get error details array for API responses.
     * Only includes error details in debug mode.
     */
    public static function getSafeError(Throwable $e): ?string
    {
        if (config('app.debug')) {
            return $e->getMessage();
        }

        return null;
    }
}
