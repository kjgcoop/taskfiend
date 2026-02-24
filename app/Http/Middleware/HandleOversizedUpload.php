<?php

namespace App\Http\Middleware;

/**
 * Utility class for detecting PHP-level upload size limits.
 * The actual request interception is handled in the exception handler
 * (bootstrap/app.php) for TokenMismatchException, because when PHP drops
 * the POST body (post_max_size exceeded), the CSRF token is lost.
 */
class HandleOversizedUpload
{
    /**
     * Convert a PHP ini size string (e.g. "8M", "2G") to bytes.
     */
    public static function iniToBytes(string $val): int
    {
        $val = trim($val);
        if ($val === '') {
            return 0;
        }
        $last = strtolower($val[strlen($val) - 1]);
        $num = (int) $val;
        return match ($last) {
            'g' => $num * 1073741824,
            'm' => $num * 1048576,
            'k' => $num * 1024,
            default => $num,
        };
    }
}
