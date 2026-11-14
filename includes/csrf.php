<?php
/**
 * EpicLens System - Cross-Site Request Forgery (CSRF) Protection
 */

class Csrf {
    private const SESSION_KEY = '_csrf_token';

    /**
     * Initializes and returns the active session CSRF token.
     *
     * @return string
     */
    public static function token(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Generates a hidden HTML input field containing the current CSRF token.
     *
     * @return string
     */
    public static function field(): string {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /**
     * Verifies that the submitted CSRF token matches the session token.
     *
     * @param string|null $submittedToken
     * @return bool
     */
    public static function verify(?string $submittedToken = null): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($submittedToken === null) {
            $submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        if (empty($submittedToken) || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        return hash_equals($_SESSION[self::SESSION_KEY], (string)$submittedToken);
    }

    /**
     * Enforces valid CSRF token on POST/PUT/DELETE requests; terminates with 403 on mismatch.
     */
    public static function requireValid(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array(strtoupper($method), ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            if (!self::verify()) {
                http_response_code(403);
                die('
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <title>403 Forbidden - Invalid CSRF Token</title>
                        <style>
                            body { font-family: -apple-system, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                            .box { background: #1e293b; padding: 2rem; border-radius: 8px; border: 1px solid #ef4444; text-align: center; max-width: 450px; }
                            h1 { color: #f87171; font-size: 1.25rem; margin-top: 0; }
                            p { color: #94a3b8; font-size: 0.9rem; }
                            a { display: inline-block; margin-top: 1rem; color: #38bdf8; text-decoration: none; font-weight: 600; }
                        </style>
                    </head>
                    <body>
                        <div class="box">
                            <h1>Security Validation Failed</h1>
                            <p>Your session security token has expired or is invalid. Please reload the page and try again.</p>
                            <a href="javascript:history.back()">Go Back</a>
                        </div>
                    </body>
                    </html>
                ');
            }
        }
    }
}
