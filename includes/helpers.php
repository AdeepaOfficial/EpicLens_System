<?php
/**
 * EpicLens System - Core Helpers & UI Presenters
 */

/**
 * Escapes HTML output securely.
 *
 * @param mixed $value
 * @return string
 */
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Stores a flash notification message in session.
 *
 * @param string $type ('success', 'error', 'warning', 'info')
 * @param string $message
 */
function setFlash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Retrieves and clears the current flash message.
 *
 * @return array{type: string, message: string}|null
 */
function getFlash(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Formats a database datetime string cleanly.
 *
 * @param string|null $datetime
 * @param string $format
 * @return string
 */
function formatDateTime(?string $datetime, string $format = 'M d, Y H:i'): string {
    if (empty($datetime)) {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts ? date($format, $ts) : '—';
}

/**
 * Renders an HTML badge for an official event status.
 *
 * @param string $status
 * @return string
 */
function renderStatusBadge(string $status): string {
    $classMap = [
        'Not Received Yet' => 'badge-not-received',
        'Received'         => 'badge-received',
        'Returned'         => 'badge-returned',
    ];
    $cls = $classMap[$status] ?? 'badge-secondary';
    return sprintf(
        '<span class="badge %s"><span class="badge-dot"></span>%s</span>',
        $cls,
        e($status)
    );
}

/**
 * Renders an HTML badge for equipment categories.
 *
 * @param string $category
 * @return string
 */
function renderCategoryBadge(string $category): string {
    return sprintf('<span class="badge badge-category">%s</span>', e($category));
}
