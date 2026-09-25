<?php
/**
 * Universal System AJAX Responder
 * Guarantees output buffer cleaning, standard HTTP status codes, and a uniform JSON envelope.
 * 
 * Standard Envelope:
 * {
 *   "success": true|false,
 *   "message": "Human readable status message",
 *   "data": mixed,
 *   "error": "Error details (if success=false)"
 * }
 */

class ApiResponse {
    /**
     * Terminate the request and send a clean JSON response.
     * Cleans any prior output buffers to prevent PHP notices/HTML leakage.
     */
    public static function json($payload, int $statusCode = 200): void {
        // Clean all active output buffers to ensure zero stray whitespace or PHP warnings
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send a standardized success response.
     */
    public static function success($data = null, string $message = 'Success', int $statusCode = 200, array $extra = []): void {
        $response = array_merge([
            'success' => true,
            'message' => $message,
            'data'    => $data
        ], $extra);

        self::json($response, $statusCode);
    }

    /**
     * Send a standardized error response.
     */
    public static function error(string $message = 'An error occurred', int $statusCode = 400, $data = null, array $extra = []): void {
        $response = array_merge([
            'success' => false,
            'error'   => $message,
            'message' => $message,
            'data'    => $data
        ], $extra);

        self::json($response, $statusCode);
    }

    /**
     * 401 Unauthorized
     */
    public static function unauthorized(string $message = 'Session expired or unauthorized. Please log in.'): void {
        self::error($message, 401);
    }

    /**
     * 403 Forbidden
     */
    public static function forbidden(string $message = 'Access denied or invalid CSRF token.'): void {
        self::error($message, 403);
    }

    /**
     * 404 Not Found
     */
    public static function notFound(string $message = 'Requested resource not found.'): void {
        self::error($message, 404);
    }

    /**
     * 405 Method Not Allowed
     */
    public static function methodNotAllowed(string $message = 'HTTP method not allowed.'): void {
        self::error($message, 405);
    }

    /**
     * One-line guard: requires active authenticated session.
     */
    public static function requireAuth(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
            self::unauthorized();
        }
    }

    /**
     * One-line guard: validates CSRF token from input or header.
     */
    public static function requireCsrf(?string $token = null): void {
        if ($token === null) {
            // Check headers first
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            
            // Check POST if not in header
            if (!$token && isset($_POST['csrf_token'])) {
                $token = $_POST['csrf_token'];
            }
            
            // Check JSON body if still not found
            if (!$token) {
                $input = self::getJsonInput();
                $token = $input['csrf_token'] ?? null;
            }
        }

        if (!class_exists('Security') && file_exists(__DIR__ . '/Security.php')) {
            require_once __DIR__ . '/Security.php';
        }

        if (class_exists('Security')) {
            if (!Security::validate($token ?? '')) {
                self::forbidden('Invalid or expired security token (CSRF).');
            }
        } else {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $sessionToken = $_SESSION['csrf_token'] ?? '';
            if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
                self::forbidden('Invalid or expired security token (CSRF).');
            }
        }
    }

    /**
     * Helper to safely decode raw JSON payload (php://input).
     */
    public static function getJsonInput(): array {
        static $decoded = null;
        if ($decoded !== null) {
            return $decoded;
        }

        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            $decoded = [];
            return $decoded;
        }

        $data = json_decode($raw, true);
        $decoded = is_array($data) ? $data : [];
        return $decoded;
    }
}
