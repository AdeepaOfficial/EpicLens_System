<?php
/**
 * EpicLens System - Database Schema Setup & First-Time Admin Wizard
 *
 * Runs the database schema DDL once, registers category sequences,
 * and boots the first administrator account.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDB();
$error = '';
$success = '';
$setupComplete = false;

// Check if tables already exist
try {
    $check = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    $adminCount = 0;
    if ($check) {
        $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    }
    if ($check && $adminCount > 0) {
        $setupComplete = true;
    }
} catch (Exception $e) {
    $setupComplete = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$setupComplete) {
    Csrf::requireValid();

    $username = trim($_POST['username'] ?? 'admin');
    $fullName = trim($_POST['full_name'] ?? 'System Administrator');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($username) || strlen($username) < 3) {
        $error = 'Admin username must be at least 3 characters.';
    } elseif (empty($password) || strlen($password) < 8) {
        $error = 'Admin password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Read and execute schema.sql
            $schemaFile = dirname(__DIR__) . '/database/schema.sql';
            if (!file_exists($schemaFile)) {
                throw new RuntimeException('schema.sql file not found in database directory.');
            }
            $sql = file_get_contents($schemaFile);

            // Execute DDL statements
            $pdo->exec($sql);

            // Create initial admin account
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('
                INSERT INTO users (username, password, full_name, role, status, created_at)
                VALUES (?, ?, ?, "admin", "active", NOW())
            ');
            $stmt->execute([$username, $hashed, $fullName]);

            $success = 'Database schema installed and administrator account created successfully!';
            $setupComplete = true;
        } catch (Exception $e) {
            $error = 'Installation error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Installation & Setup | EpicLens System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; box-sizing: border-box; }
        .setup-card { background: #1e293b; border: 1px solid #334155; border-radius: var(--radius-lg); max-width: 520px; width: 100%; padding: 2rem; box-shadow: var(--shadow-lg); }
        .setup-card h1 { font-size: 1.5rem; color: #f8fafc; margin-bottom: 0.5rem; }
        .setup-card p { font-size: 0.875rem; color: #94a3b8; margin-bottom: 1.5rem; line-height: 1.6; }
        .setup-card .form-label { color: #f8fafc; }
        .setup-card .form-control { background: #0f172a; border-color: #334155; color: #f8fafc; }
        .setup-card .form-control:focus { border-color: var(--primary); }
    </style>
</head>
<body>
    <div class="setup-card">
        <h1>EpicLens System Setup</h1>

        <?php if ($setupComplete): ?>
            <div class="alert alert-success">
                <span>The system database and administrator account are already initialized and locked.</span>
            </div>
            <a href="login.php" class="btn btn-primary btn-block btn-lg" style="margin-top: 1rem;">
                Go to Sign In
            </a>
        <?php else: ?>
            <p>Initialize the clean database schema and create the primary administrator account for this instance.</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="setup.php">
                <?= Csrf::field() ?>

                <div class="form-group">
                    <label class="form-label" for="username">Administrator Username <span class="required">*</span></label>
                    <input type="text" id="username" name="username" class="form-control" value="admin" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="full_name">Administrator Display Name</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" value="Lead Event Coordinator">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Administrator Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Minimum 8 characters" minlength="8" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter password" minlength="8" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1.5rem;">
                    Run Schema Setup & Initialize
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
