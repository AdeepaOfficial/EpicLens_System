<?php
/**
 * EpicLens System - Edit Equipment Master Record (Admin Only)
 */
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Invalid equipment ID specified.');
    header('Location: equipment.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT * FROM equipment WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if (!$item) {
        setFlash('error', 'Equipment item not found.');
        header('Location: equipment.php');
        exit;
    }
} catch (PDOException $e) {
    setFlash('error', 'Database error retrieving equipment record.');
    header('Location: equipment.php');
    exit;
}

$errors = [];
$model = $item['model'];
$serialNumber = $item['serial_number'] ?? '';
$ownerName = $item['owner_name'];
$ownerPhone = $item['owner_phone'];
$notes = $item['notes'] ?? '';
$status = $item['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();

    $model = trim($_POST['model'] ?? '');
    $serialNumber = trim($_POST['serial_number'] ?? '');
    $ownerName = trim($_POST['owner_name'] ?? '');
    $ownerPhone = trim($_POST['owner_phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status = trim($_POST['status'] ?? $item['status']);

    if (!in_array($status, ['Not Received Yet', 'Received', 'Returned'], true)) {
        $status = $item['status'];
    }

    if (empty($model)) {
        $errors[] = 'Model description is required.';
    }
    if (empty($ownerName)) {
        $errors[] = 'Student owner name is required.';
    }
    if (empty($ownerPhone)) {
        $errors[] = 'Owner contact number is required.';
    }

    if (empty($errors)) {
        try {
            $upd = $pdo->prepare('
                UPDATE equipment 
                SET model = ?, serial_number = ?, owner_name = ?, owner_phone = ?, notes = ?, status = ?
                WHERE id = ?
            ');
            $upd->execute([
                $model,
                $serialNumber ?: null,
                $ownerName,
                $ownerPhone,
                $notes ?: null,
                $status,
                $id
            ]);

            setFlash('success', sprintf('Equipment [%s] updated successfully.', $item['item_code']));
            header('Location: equipment.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Error updating equipment: ' . (APP_DEBUG ? $e->getMessage() : 'Database error.');
        }
    }
}

$pageTitle = 'Edit Equipment';
$currentPage = 'equipment';
include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">Edit Equipment Record [<?= e($item['item_code']) ?>]</h2>
            <span style="font-size: 0.75rem; color: var(--text-muted);">Update specifications, owner contact details, and current status</span>
        </div>
        <div>
            <span class="item-code-badge" style="font-size: 1rem;"><?= e($item['item_code']) ?></span>
        </div>
    </div>

    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="equipment_edit.php?id=<?= (int)$id ?>">
            <?= Csrf::field() ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Category (Permanent)</label>
                    <input type="text" class="form-control" value="<?= e($item['category']) ?>" readonly style="background: var(--bg-alt);">
                </div>

                <div class="form-group">
                    <label class="form-label">Item Code (Permanent)</label>
                    <input type="text" class="form-control" value="<?= e($item['item_code']) ?>" readonly style="background: var(--bg-alt); font-family: monospace; font-weight: 700;">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="model">Model / Name <span class="required">*</span></label>
                <input type="text" id="model" name="model" class="form-control" value="<?= e($model) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="serial_number">Serial Number</label>
                <input type="text" id="serial_number" name="serial_number" class="form-control" value="<?= e($serialNumber) ?>">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="owner_name">Student Owner Name <span class="required">*</span></label>
                    <input type="text" id="owner_name" name="owner_name" class="form-control" value="<?= e($ownerName) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="owner_phone">Owner Phone Number <span class="required">*</span></label>
                    <input type="text" id="owner_phone" name="owner_phone" class="form-control" value="<?= e($ownerPhone) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="notes">Notes & Accessories</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"><?= e($notes) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="status">Event Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="Not Received Yet" <?= $status === 'Not Received Yet' ? 'selected' : '' ?>>Not Received Yet</option>
                    <option value="Received" <?= $status === 'Received' ? 'selected' : '' ?>>Received</option>
                    <option value="Returned" <?= $status === 'Returned' ? 'selected' : '' ?>>Returned</option>
                </select>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary btn-lg">Update Record</button>
                <a href="equipment.php" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
