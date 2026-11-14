<?php
/**
 * EpicLens System - Secure Equipment Deletion Handler (Admin Only)
 *
 * Enforces HTTP POST method and CSRF token verification.
 */
require_once __DIR__ . '/../includes/auth.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed. Deletions must be submitted via POST with a valid CSRF token.');
}

Csrf::requireValid();

$pdo = getDB();
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Invalid equipment ID specified.');
    header('Location: equipment.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, item_code, model, status FROM equipment WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if (!$item) {
        setFlash('error', 'Equipment item not found.');
        header('Location: equipment.php');
        exit;
    }

    // Safety guard: Equipment currently with event team cannot be deleted
    if ($item['status'] === 'Received') {
        setFlash('error', sprintf('Cannot delete [%s] "%s" while it is actively marked as Received. Return it first.', $item['item_code'], $item['model']));
        header('Location: equipment.php');
        exit;
    }

    $delStmt = $pdo->prepare('DELETE FROM equipment WHERE id = ?');
    $delStmt->execute([$id]);

    setFlash('success', sprintf('Equipment [%s] "%s" removed from catalog.', $item['item_code'], $item['model']));
} catch (PDOException $e) {
    setFlash('error', 'Database error during deletion.');
}

header('Location: equipment.php');
exit;
