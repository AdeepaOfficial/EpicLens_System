<?php
/**
 * EpicLens System - Staff Account Management (Admin Only)
 */
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = getDB();
$errors = [];
$currentUser = current_user();

// 1. Handle Add New Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_staff') {
    Csrf::requireValid();

    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($fullName)) {
        $errors[] = 'Staff full name is required.';
    }
    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters long.';
    }
    if (empty($password) || strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (empty($errors)) {
        try {
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $check->execute([$username]);
            if ($check->fetch()) {
                $errors[] = sprintf('The username "%s" is already taken.', htmlspecialchars($username));
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $pdo->prepare('
                    INSERT INTO users (username, password, full_name, role, phone, status, created_at)
                    VALUES (?, ?, ?, "staff", ?, "active", NOW())
                ');
                $ins->execute([$username, $hash, $fullName, $phone ?: null]);

                setFlash('success', sprintf('Staff account for "%s" created successfully.', $fullName));
                header('Location: staff.php');
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error creating staff account.';
        }
    }
}

// 2. Handle Edit Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_staff') {
    Csrf::requireValid();

    $staffId = (int)($_POST['staff_id'] ?? 0);
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $newPass = $_POST['new_password'] ?? '';
    $confirmNewPass = $_POST['confirm_new_password'] ?? '';

    if ($staffId <= 0 || empty($fullName)) {
        $errors[] = 'Staff account and full name are required.';
    }
    if (!empty($newPass)) {
        if (strlen($newPass) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        } elseif ($newPass !== $confirmNewPass) {
            $errors[] = 'New password and confirmation do not match.';
        }
    }

    if (empty($errors)) {
        try {
            if (!empty($newPass)) {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $upd = $pdo->prepare('
                    UPDATE users 
                    SET full_name = ?, phone = ?, password = ?, updated_at = NOW() 
                    WHERE id = ? AND role = "staff"
                ');
                $upd->execute([$fullName, $phone ?: null, $newHash, $staffId]);
            } else {
                $upd = $pdo->prepare('
                    UPDATE users 
                    SET full_name = ?, phone = ?, updated_at = NOW() 
                    WHERE id = ? AND role = "staff"
                ');
                $upd->execute([$fullName, $phone ?: null, $staffId]);
            }

            setFlash('success', 'Staff account updated successfully.');
            header('Location: staff.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Database error updating staff account.';
        }
    }
}

// 3. Handle Toggle Enable/Disable Status (POST with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    Csrf::requireValid();

    $targetId = (int)($_POST['staff_id'] ?? 0);
    $newStatus = ($_POST['status'] ?? '') === 'active' ? 'active' : 'inactive';

    if ($targetId === (int)$currentUser['id']) {
        setFlash('error', 'You cannot disable your own active account.');
    } else {
        try {
            $check = $pdo->prepare('SELECT id, role, full_name FROM users WHERE id = ?');
            $check->execute([$targetId]);
            $target = $check->fetch();

            if ($target && $target['role'] === 'staff') {
                $stmt = $pdo->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$newStatus, $targetId]);

                setFlash('success', sprintf('Staff account for "%s" has been %s.', $target['full_name'], $newStatus === 'active' ? 'enabled' : 'disabled'));
                header('Location: staff.php');
                exit;
            }
        } catch (PDOException $e) {
            setFlash('error', 'Database error updating staff status.');
        }
    }
}

// Fetch staff accounts
try {
    $staffAccounts = $pdo->query('
        SELECT id, username, full_name, phone, status, created_at, updated_at
        FROM users
        WHERE role = "staff"
        ORDER BY status ASC, full_name ASC
    ')->fetchAll();
} catch (PDOException $e) {
    $staffAccounts = [];
}

$pageTitle = 'Staff Accounts';
$currentPage = 'staff';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Staff Accounts Management (<?= count($staffAccounts) ?>)</h2>
            <span style="font-size: 0.75rem; color: var(--text-muted);">Manage event crew staff logins and permission status</span>
        </div>
        <button type="button" class="btn btn-primary btn-sm" data-modal-target="modal-add-staff">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            <span>Add Staff Member</span>
        </button>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="card-body" style="padding-bottom: 0;">
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Username</th>
                    <th>Phone</th>
                    <th>Account Status</th>
                    <th>Created</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($staffAccounts)): ?>
                    <?php foreach ($staffAccounts as $s): ?>
                        <tr>
                            <td><strong><?= e($s['full_name']) ?></strong></td>
                            <td><code><?= e($s['username']) ?></code></td>
                            <td><?= !empty($s['phone']) ? e($s['phone']) : '<span style="color: var(--text-muted);">—</span>' ?></td>
                            <td>
                                <?php if ($s['status'] === 'active'): ?>
                                    <span class="badge" style="background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0;">Active</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca;">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td><?= formatDateTime($s['created_at'], 'M d, Y') ?></td>
                            <td style="text-align: right; white-space: nowrap;">
                                <button type="button" class="btn btn-secondary btn-sm" data-modal-target="modal-edit-<?= (int)$s['id'] ?>">Edit</button>

                                <form method="POST" action="staff.php" style="display: inline-block;">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="staff_id" value="<?= (int)$s['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $s['status'] === 'active' ? 'inactive' : 'active' ?>">
                                    <button type="submit" class="btn btn-sm <?= $s['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>">
                                        <?= $s['status'] === 'active' ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>

                        <!-- Modal: Edit Staff -->
                        <div id="modal-edit-<?= (int)$s['id'] ?>" class="modal-backdrop">
                            <div class="modal-card">
                                <div class="modal-header">
                                    <h3 class="modal-title">Edit Staff Account: <?= e($s['full_name']) ?></h3>
                                    <button type="button" class="btn-icon" data-dismiss="modal">&times;</button>
                                </div>
                                <form method="POST" action="staff.php">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="action" value="edit_staff">
                                    <input type="hidden" name="staff_id" value="<?= (int)$s['id'] ?>">

                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label class="form-label">Full Name <span class="required">*</span></label>
                                            <input type="text" name="full_name" class="form-control" value="<?= e($s['full_name']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Phone Number</label>
                                            <input type="text" name="phone" class="form-control" value="<?= e($s['phone'] ?? '') ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Reset Password (Leave blank to keep unchanged)</label>
                                            <input type="password" name="new_password" class="form-control" placeholder="New password" minlength="8">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Confirm New Password</label>
                                            <input type="password" name="confirm_new_password" class="form-control" placeholder="Re-enter new password" minlength="8">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="table-empty">No staff accounts registered. Click "Add Staff Member" above.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add New Staff -->
<div id="modal-add-staff" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Register New Staff Member</h3>
            <button type="button" class="btn-icon" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST" action="staff.php">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="add_staff">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="full_name" class="form-control" placeholder="e.g., Jane Perera" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Username <span class="required">*</span></label>
                    <input type="text" name="username" class="form-control" placeholder="e.g., jane" required minlength="3">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="e.g., 0719876543">
                </div>
                <div class="form-group">
                    <label class="form-label">Password <span class="required">*</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Minimum 8 characters" required minlength="8">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password <span class="required">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required minlength="8">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Account</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
