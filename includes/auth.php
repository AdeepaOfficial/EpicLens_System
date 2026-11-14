<?php
/**
 * EpicLens System - Authentication, Role-Based Access Control & Session Hardening
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';

// Hardened session initialization
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $secureCookie = filter_var(getenv('SESSION_SECURE_COOKIE') ?: false, FILTER_VALIDATE_BOOLEAN);

    session_set_cookie_params([
        'lifetime' => (int)(getenv('SESSION_LIFETIME') ?: 86400),
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

/**
 * Checks whether an authenticated user session is active.
 *
 * @return bool
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Checks whether the current user is an Administrator.
 *
 * @return bool
 */
function is_admin(): bool {
    return is_logged_in() && (($_SESSION['role'] ?? '') === 'admin');
}

/**
 * Checks whether the current user is a Staff member.
 *
 * @return bool
 */
function is_staff(): bool {
    return is_logged_in() && (($_SESSION['role'] ?? '') === 'staff');
}

/**
 * Requires login; redirects to login.php if unauthenticated.
 */
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Strictly requires the Admin role; redirects with error if user is only Staff.
 */
function require_admin(): void {
    require_login();
    if (!is_admin()) {
        setFlash('error', 'Administrator privileges are required to access this resource.');
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Checks whether at least one active administrator account exists.
 *
 * @return bool
 */
function has_admin_user(): bool {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'");
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Returns fresh, authoritative user profile data from the database.
 *
 * @return array{id: int|null, username: string, full_name: string, role: string, phone: string, status: string}
 */
function current_user(): array {
    if (!is_logged_in()) {
        return [
            'id'        => null,
            'username'  => 'Guest',
            'full_name' => 'Guest',
            'role'      => 'guest',
            'phone'     => '',
            'status'    => 'inactive'
        ];
    }

    static $cached = null;
    if ($cached === null) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare('SELECT id, username, full_name, role, phone, status FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if ($user) {
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $cached = $user;
            } else {
                logout_user();
                header('Location: login.php');
                exit;
            }
        } catch (Exception $e) {
            $cached = [
                'id'        => (int)($_SESSION['user_id'] ?? 0),
                'username'  => (string)($_SESSION['username'] ?? 'User'),
                'full_name' => (string)($_SESSION['full_name'] ?? 'User'),
                'role'      => (string)($_SESSION['role'] ?? 'staff'),
                'phone'     => '',
                'status'    => 'active'
            ];
        }
    }

    return $cached;
}

/**
 * Authenticates and seeds session data for a user.
 *
 * @param array $user
 */
function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'] ?? 'staff';
}

/**
 * Destroys session data securely upon logout.
 */
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}
