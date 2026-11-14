<?php
/**
 * EpicLens System - Secure Authentication & First-Time Setup
 */
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$pdo = getDB();
$adminExists = has_admin_user();
$error = '';
$usernameVal = '';
$fullNameVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();

    $action = $_POST['action'] ?? ($adminExists ? 'login' : 'create_admin');

    if ($action === 'create_admin') {
        if (has_admin_user()) {
            $error = 'An administrator account already exists. Please sign in.';
            $adminExists = true;
        } else {
            $username = trim($_POST['username'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $usernameVal = $username;
            $fullNameVal = $fullName;

            if (empty($fullName)) {
                $fullName = 'Administrator';
            }

            if (empty($username) || strlen($username) < 3) {
                $error = 'Username must be at least 3 characters long.';
            } elseif (empty($password) || strlen($password) < 8) {
                $error = 'Password must be at least 8 characters long.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Password and confirmation password do not match.';
            } else {
                try {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('
                        INSERT INTO users (username, password, full_name, role, status, created_at)
                        VALUES (?, ?, ?, "admin", "active", NOW())
                    ');
                    $stmt->execute([$username, $hashed, $fullName]);
                    $newId = (int)$pdo->lastInsertId();

                    login_user([
                        'id'        => $newId,
                        'username'  => $username,
                        'full_name' => $fullName,
                        'role'      => 'admin'
                    ]);

                    setFlash('success', 'Administrator account created successfully. Welcome to EpicLens!');
                    header('Location: dashboard.php');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Failed to create administrator account: ' . (APP_DEBUG ? $e->getMessage() : 'Database error.');
                }
            }
        }
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $usernameVal = $username;

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT id, username, password, full_name, role, status FROM users WHERE username = ? LIMIT 1');
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if (($user['status'] ?? 'active') === 'inactive') {
                        $error = 'Your account has been deactivated. Please contact an administrator.';
                    } else {
                        login_user($user);
                        setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');
                        header('Location: dashboard.php');
                        exit;
                    }
                } else {
                    $error = 'Invalid credentials. Please verify your username and password.';
                }
            } catch (PDOException $e) {
                $error = 'Authentication service error. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $adminExists ? 'Sign In' : 'Initial Admin Setup' ?> | EpicLens System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body.login-body {
            background: radial-gradient(circle at top right, #1e293b, #0f172a);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
        }
        .login-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 440px;
            padding: 2.25rem;
        }
        .brand-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .brand-header .brand-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 0.75rem;
        }
        .brand-header h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.5px;
        }
        .brand-header p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }
    </style>
</head>
<body class="login-body">
    <div class="login-card">
        <div class="brand-header">
            <div class="brand-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <circle cx="12" cy="12" r="4"></circle>
                </svg>
            </div>
            <h1>EpicLens</h1>
            <p><?= $adminExists ? 'Campus Event Equipment Management' : 'Initial System Administrator Setup' ?></p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$adminExists): ?>
            <div class="alert alert-info">
                <span>No administrator account found. Create the initial administrator to secure the system.</span>
            </div>

            <form method="POST" action="login.php" autocomplete="off">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="create_admin">

                <div class="form-group">
                    <label for="username" class="form-label">Admin Username <span class="required">*</span></label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="e.g., admin" value="<?= e($usernameVal) ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="full_name" class="form-label">Full Name / Display Name</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" placeholder="e.g., Event Director" value="<?= e($fullNameVal) ?>">
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="At least 8 characters" minlength="8" required>
                    <div class="form-hint">Must contain at least 8 characters.</div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter password" minlength="8" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1.5rem;">
                    <span>Create Administrator Account</span>
                </button>
            </form>
        <?php else: ?>
            <form method="POST" action="login.php" autocomplete="off">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="login">

                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" value="<?= e($usernameVal) ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1.5rem;">
                    <span>Sign In</span>
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
