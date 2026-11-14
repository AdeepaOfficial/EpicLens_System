<?php
/**
 * EpicLens System - Add New Equipment (Admin Only)
 */
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = getDB();
$errors = [];
$category = 'Camera';
$model = '';
$serialNumber = '';
$ownerName = '';
$ownerPhone = '';
$notes = '';
$status = 'Not Received Yet';

$allNextCodes = getAllNextItemCodes($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();

    $category = trim($_POST['category'] ?? 'Camera');
    $model = trim($_POST['model'] ?? '');
    $serialNumber = trim($_POST['serial_number'] ?? '');
    $ownerName = trim($_POST['owner_name'] ?? '');
    $ownerPhone = trim($_POST['owner_phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status = trim($_POST['status'] ?? 'Not Received Yet');

    if (!in_array($status, ['Not Received Yet', 'Received', 'Returned'], true)) {
        $status = 'Not Received Yet';
    }

    if (empty($model)) {
        $errors[] = 'Equipment model / description is required.';
    }
    if (empty($ownerName)) {
        $errors[] = 'Student owner name is required.';
    }
    if (empty($ownerPhone)) {
        $errors[] = 'Student owner contact number is required.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Atomically reserve next permanent Item Code
            $itemCode = getNextItemCode($pdo, $category, true);

            $stmt = $pdo->prepare('
                INSERT INTO equipment 
                (item_code, category, model, serial_number, owner_name, owner_phone, notes, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $itemCode,
                $category,
                $model,
                $serialNumber ?: null,
                $ownerName,
                $ownerPhone,
                $notes ?: null,
                $status
            ]);

            $pdo->commit();

            setFlash('success', sprintf('Equipment [%s] "%s" registered successfully.', $itemCode, $model));
            header('Location: equipment.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Error saving equipment record: ' . (APP_DEBUG ? $e->getMessage() : 'Database error.');
        }
    }
}

$pageTitle = 'Add Equipment';
$currentPage = 'equipment_add';
include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h2 class="card-title">Register New Equipment Master Record</h2>
            <span style="font-size: 0.75rem; color: var(--text-muted);">Assign permanent unique category item code and record student ownership</span>
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

        <form method="POST" action="equipment_add.php">
            <?= Csrf::field() ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="equipment_category_select">Category <span class="required">*</span></label>
                    <select id="equipment_category_select" name="category" class="form-control" required>
                        <?php foreach ($allNextCodes as $catName => $projectedCode): ?>
                            <option value="<?= e($catName) ?>" <?= $category === $catName ? 'selected' : '' ?>>
                                <?= e($catName) ?> (Next: <?= e($projectedCode) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Auto-Generated Item Code</label>
                    <div style="display: flex; align-items: center; height: 38px;">
                        <span id="projected_item_code" class="item-code-badge" style="font-size: 0.95rem; padding: 0.35rem 0.85rem;">
                            <?= e($allNextCodes[$category] ?? 'CM001') ?>
                        </span>
                        <span style="margin-left: 0.5rem; font-size: 0.75rem; color: var(--text-muted);">(Assigned on save)</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="model">Equipment Model / Name <span class="required">*</span></label>
                <input type="text" id="model" name="model" class="form-control" placeholder="e.g., Sony A7 III, Canon 50mm f/1.8" value="<?= e($model) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="serial_number">Serial Number (Optional)</label>
                <input type="text" id="serial_number" name="serial_number" class="form-control" placeholder="e.g., SN-884102" value="<?= e($serialNumber) ?>">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="owner_name">Student Owner Name <span class="required">*</span></label>
                    <input type="text" id="owner_name" name="owner_name" class="form-control" placeholder="e.g., Kasun Perera" value="<?= e($ownerName) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="owner_phone">Owner Phone Number <span class="required">*</span></label>
                    <input type="text" id="owner_phone" name="owner_phone" class="form-control" placeholder="e.g., 0712345678" value="<?= e($ownerPhone) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="notes">Equipment Notes / Accessories</label>
                <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Included accessories, carrying bag, battery count, existing marks"><?= e($notes) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="status">Initial Event Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="Not Received Yet" <?= $status === 'Not Received Yet' ? 'selected' : '' ?>>Not Received Yet (Default before check-in)</option>
                    <option value="Received" <?= $status === 'Received' ? 'selected' : '' ?>>Received (Already handed in)</option>
                </select>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary btn-lg">Save Equipment Record</button>
                <a href="equipment.php" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
    window.categoryNextCodes = <?= json_encode($allNextCodes) ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
